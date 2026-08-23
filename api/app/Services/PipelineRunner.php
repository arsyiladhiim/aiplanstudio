<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\PhaseProgress;
use App\Models\Version;
use App\Services\Validators\TestingStrategyValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PipelineRunner
{
    private Version $version;

    private AiClient $client;

    private SseEmitter $sse;

    private AiJsonParser $jsonParser;

    private bool $liteMode = false;

    private float $crossRefPenalty = 0.0;

    private AiOutputParser $outputParser;

    private StageArtifactValidator $validator;

    private TrackingInjector $trackingInjector;

    private StageGateRegistry $gateRegistry;

    /** Plain tracking token — kept in-memory only, never persisted plaintext */
    private ?string $plainTrackingToken = null;

    private ?string $pendingTrackingToken = null;

    private const MOBILE_STAGES = ['design_system_mobile', 'pertanyaan_mobile', 'standards_mobile', 'phases_mobile', 'master_mobile', 'app_spec_mobile'];

    private const WEB_DONE_STAGE = 'master_web';

    private const MIN_MCQ_QUESTIONS = 5;

    private const MAX_MCQ_QUESTIONS = 10;

    private const MAX_MCQ_RETRIES = 10;

    private const MAX_VALIDATE_RETRIES = 3;

    /** P2 — budget token output per stage (MCQ kecil; dokumen panjang tetap tinggi agar tak tambah truncation). */
    private const STAGE_MAX_TOKENS = [
        'pertanyaan' => 1500,
        'pertanyaan_mobile' => 1500,
        'analisa' => 4096,
        'erd' => 4096,
        'api_contract' => 4096,
        'app_spec_web' => 4096,
        'app_spec_mobile' => 4096,
        'prd' => 8192,
        'architecture' => 8192,
        'design_system' => 8192,
        'design_system_mobile' => 8192,
        'phases_web' => 8192,
        'phases_mobile' => 8192,
        'standards_web' => 8192,
        'standards_mobile' => 8192,
        'master_web' => 8192,
        'master_mobile' => 8192,
        'env_config' => 8192,
        'security' => 8192,
        'deployment' => 8192,
        'observability' => 8192,
        'agents' => 8192,
    ];

    /** P5 — Lite plan: hanya tahap inti yang dihasilkan, sisanya di-skip. */
    private const LITE_STAGES = ['pertanyaan', 'analisa', 'prd', 'architecture', 'erd', 'master_web'];

    private const STAGE_REQUIRED_KEYWORDS = [
        // Tiap grup adalah OR — minimal 1 keyword dalam grup wajib muncul (case-insensitive).
        'design_system' => [['signature', 'elemen khas'], ['anti-pattern', 'anti-pola'], ['token']],
        'design_system_mobile' => [['Material'], ['ThemeData'], ['signature']],
        'prd' => [['user story', 'user stories', 'user story'], ['acceptance', 'penerimaan'], ['functional requirement', 'kebutuhan fungsional']],
        'architecture' => [['stack', 'teknologi'], ['module', 'boundary', 'batas'], ['trade-off', 'tradeoff', 'keputusan']],
        'standards_web' => [['TypeScript', 'typescript', 'ts'], ['lint'], ['convention', 'konvensi']],
        'standards_mobile' => [['Dart', 'dart'], ['lint'], ['convention', 'konvensi']],
        'security' => [['autentikasi', 'authentication'], ['otorisasi', 'authorization', 'role'], ['owasp', 'checklist', 'keamanan']],
        'observability' => [['logging', 'log'], ['monitoring', 'pemantauan'], ['health check', 'healthcheck', 'kesehatan']],
        'env_config' => [['environment', 'lingkungan'], ['variable', 'variabel'], ['configuration', 'konfigurasi']],
        'deployment' => [['Docker', 'docker'], ['rollback']],
        'agents' => [['AGENTS.md', 'agents'], ['agent'], ['instruction', 'instruksi']],
    ];

    /**
     * Stage dependents: when a stage is regenerated, its dependents (stages that reference its output)
     * should be reset to 'pending' to force re-generation with fresh context.
     * CP-46.C: testing_strategy + verify.* + smoke_test added to dependency chains.
     */
    private const STAGE_DEPENDENTS = [
        'analisa' => ['prd', 'architecture', 'erd', 'api_contract', 'design_system', 'phases_web', 'standards_web', 'testing_strategy', 'master_web', 'app_spec_web', 'design_system_mobile', 'pertanyaan_mobile', 'phases_mobile', 'standards_mobile', 'master_mobile', 'app_spec_mobile', 'env_config', 'security', 'deployment', 'observability', 'agents', 'verify.review', 'smoke_test', 'verify.production_readiness'],
        'prd' => ['architecture', 'erd', 'api_contract', 'design_system', 'phases_web', 'standards_web', 'testing_strategy', 'master_web', 'app_spec_web', 'design_system_mobile', 'pertanyaan_mobile', 'phases_mobile', 'standards_mobile', 'master_mobile', 'app_spec_mobile', 'env_config', 'security', 'deployment', 'observability', 'agents'],
        'architecture' => ['erd', 'api_contract', 'design_system', 'phases_web', 'standards_web', 'testing_strategy', 'master_web', 'app_spec_web', 'design_system_mobile', 'phases_mobile', 'standards_mobile', 'master_mobile', 'app_spec_mobile', 'env_config', 'security', 'deployment', 'observability', 'agents'],
        'erd' => ['api_contract', 'phases_web', 'master_web', 'app_spec_web', 'phases_mobile', 'master_mobile', 'app_spec_mobile', 'testing_strategy'],
        'api_contract' => ['phases_web', 'master_web', 'app_spec_web', 'phases_mobile', 'master_mobile', 'app_spec_mobile', 'testing_strategy'],
        'design_system' => ['standards_web', 'master_web', 'app_spec_web', 'design_system_mobile'],
        'phases_web' => ['standards_web', 'testing_strategy', 'master_web', 'app_spec_web'],
        'standards_web' => ['testing_strategy', 'master_web', 'standards_mobile', 'master_mobile'],
        'testing_strategy' => [],
        'master_web' => ['app_spec_web', 'pertanyaan_mobile', 'phases_mobile', 'standards_mobile', 'master_mobile', 'app_spec_mobile', 'env_config', 'security', 'deployment', 'observability', 'agents'],
        'app_spec_web' => [],
        'design_system_mobile' => ['standards_mobile', 'master_mobile', 'app_spec_mobile'],
        'pertanyaan_mobile' => ['phases_mobile', 'standards_mobile', 'master_mobile', 'app_spec_mobile'],
        'phases_mobile' => ['standards_mobile', 'master_mobile', 'app_spec_mobile'],
        'standards_mobile' => ['master_mobile'],
        'master_mobile' => ['app_spec_mobile'],
        'app_spec_mobile' => [],
        'env_config' => ['security', 'deployment', 'observability', 'agents'],
        'security' => ['deployment', 'observability', 'agents'],
        'deployment' => ['observability', 'agents'],
        'observability' => ['agents'],
        'agents' => [],
    ];

    /**
     * Generic AI-template patterns that indicate low-effort / low-originality output.
     * When matched, the artifact is rejected to force regeneration with real differentiation.
     */
    private const GENERIC_PATTERNS = [
        '/lorem ipsum/i',
        '/in today\'s digital age/i',
        '/leverage cutting[- ]edge/i',
        '/revolutionary (platform|solution|app)/i',
        '/game[- ]changer approach/i',
        '/blue[- ]purple gradient/i',
        '/Inter as (the )?default font/i',
        '/modern (clean|minimal) (UI|interface)/i',
        '/robust (and|&)? scalable/i',
        '/seamless(ly)? (integrated|experience)/i',
    ];

    private const COLUMN_MAP = [
        'analisa' => 'analysis',
        'prd' => 'prd',
        'architecture' => 'architecture',
        'erd' => 'erd',
        'api_contract' => 'api_contract',
        'design_system' => 'design_system',
        'design_system_mobile' => 'design_system_mobile',
        'phases_web' => 'phases',
        'standards_web' => 'standards',
        'master_web' => 'master_prompt',
        'app_spec_web' => 'app_spec_web',
        'pertanyaan_mobile' => 'pertanyaan_mobile',
        'phases_mobile' => 'mobile_phases',
        'standards_mobile' => 'mobile_standards',
        'master_mobile' => 'mobile_master_prompt',
        'app_spec_mobile' => 'app_spec_mobile',
        'env_config' => 'env_config',
        'security' => 'security',
        'deployment' => 'deployment',
        'observability' => 'observability',
        'agents' => 'agents',
    ];

    public function __construct(Version $version, AiClient $client, $stdout = null)
    {
        $this->version = $version;
        $this->client = $client;
        $this->sse = new SseEmitter($stdout);
        $this->jsonParser = new AiJsonParser;
        $this->outputParser = new AiOutputParser($this->jsonParser);
        $this->validator = new StageArtifactValidator($this->outputParser);
        $this->trackingInjector = new TrackingInjector;
        $this->gateRegistry = new StageGateRegistry;
    }

    public function run(?string $stage, bool $auto, bool $lite = false): void
    {
        $this->version->load('project');
        $this->liteMode = $lite;

        // R4: orphan `running` dari proses crash yang mati di tengah — reset ke pending.
        $statuses = $this->version->stage_status ?? [];
        if (in_array('running', $statuses, true)) {
            foreach ($statuses as $k => $v) {
                if ($v === 'running') {
                    $statuses[$k] = 'pending';
                }
            }
            $this->version->update(['stage_status' => $statuses]);
        }

        if (! $this->client->isConfigured()) {
            $this->sse->emit('fail', ['stage' => $stage ?? 'start', 'message' => 'AI Provider belum dikonfigurasi.']);

            return;
        }

        $startIdx = $stage !== null
            ? array_search($stage, Version::ALL_STAGES, true)
            : 0;

        if ($startIdx === false) {
            $startIdx = 0;
        }

        foreach (array_slice(Version::ALL_STAGES, $startIdx) as $key) {
            $target = $this->version->project->target ?? 'web';

            // Lite + Mobile skip filter dulu — gate check jalan hanya untuk stage yang benar-benar akan dieksekusi.
            if ($this->liteMode && ! in_array($key, self::LITE_STAGES, true)) {
                $this->updateStageStatus($key, 'skipped');
                $this->recordSkipReason($key, 'Lite plan — hanya tahap inti dihasilkan');

                continue;
            }

            if (in_array($key, self::MOBILE_STAGES, true)) {
                if ($target !== 'both') {
                    $this->updateStageStatus($key, 'skipped');

                    continue;
                }
                if (($this->version->stage_status[self::WEB_DONE_STAGE] ?? 'pending') !== 'done') {
                    $this->sse->emit('status', ['stage' => 'web_gate', 'state' => 'waiting']);
                    $this->sse->emit('fail', ['stage' => $key, 'message' => 'Web track belum selesai. Selesaikan master_web sebelum melanjutkan ke mobile.']);

                    return;
                }
            }

            // CP-46.A: Quality Gate check — bila gate blocked, set status blocked + continue.
            $gateResult = $this->gateRegistry->check($this->version, $key);
            if (! $gateResult['passes']) {
                $this->gateRegistry->assert($this->version, $key);
                $this->sse->emit('status', [
                    'stage' => $key,
                    'state' => 'blocked',
                    'gate' => $gateResult['gate'],
                    'reason' => $gateResult['reason'],
                ]);
                $this->updateStageStatus($key, Version::STAGE_BLOCKED);

                continue;
            }

            // CP-46.C: composite gates (verify.* + smoke_test) — agent posts evidence, no AI text gen.
            if (in_array($key, ['verify.review', 'smoke_test', 'verify.production_readiness'], true)) {
                $this->processCompositeGate($key);

                continue;
            }

            $this->sse->emit('status', ['stage' => $key, 'state' => 'running']);
            $this->updateStageStatus($key, 'running');

            try {
                $content = $this->runStage($key);
                if ($key === 'pertanyaan' || $key === 'pertanyaan_mobile') {
                    $content = $this->retryPertanyaanForMinimum($content, $key);
                } else {
                    $content = $this->retryAndValidate($key, $content);
                }
                $this->saveArtifact($key, $content);
                $this->recordStageTokens($key, strlen($content));
                $this->clearStageError($key);

                $this->sse->emit('done', ['stage' => $key]);
                $this->sse->emit('status', ['stage' => $key, 'state' => 'done']);
                $this->updateStageStatus($key, 'done');
            } catch (Throwable $e) {
                $this->recordStageError($key, $e->getMessage());
                $this->sse->emit('fail', ['stage' => $key, 'message' => $e->getMessage()]);
                $this->updateStageStatus($key, 'error');
                $this->persistPendingTrackingToken();
                if (! $auto || in_array($key, ['erd', 'api_contract', 'phases_web', 'master_web', 'phases_mobile', 'master_mobile'])) {
                    return;
                }
            }

            if (! $auto) {
                return;
            }
        }
    }

    /**
     * CP-46.C: composite gate handler — no AI text generation; result driven by agent evidence.
     */
    private function processCompositeGate(string $key): void
    {
        $this->sse->emit('status', ['stage' => $key, 'state' => 'running']);
        $this->updateStageStatus($key, 'running');

        $gateResult = $this->gateRegistry->check($this->version, $key);
        $this->gateRegistry->assert($this->version, $key);

        if ($gateResult['passes']) {
            $this->sse->emit('done', ['stage' => $key]);
            $this->sse->emit('status', [
                'stage' => $key,
                'state' => 'done',
                'gate' => $gateResult['gate'],
                'reason' => $gateResult['reason'],
            ]);
            $this->updateStageStatus($key, 'done');

            // CP-46.E precursor: production readiness sets the timestamp.
            if ($key === 'verify.production_readiness') {
                $this->version->update(['production_ready_at' => now()]);
            }
        } else {
            $this->sse->emit('status', [
                'stage' => $key,
                'state' => Version::STAGE_BLOCKED,
                'gate' => $gateResult['gate'],
                'reason' => $gateResult['reason'],
            ]);
            $this->updateStageStatus($key, Version::STAGE_BLOCKED);
        }
    }

    private function updateStageStatus(string $stage, string $state): void
    {
        DB::transaction(function () use ($stage, $state) {
            $locked = Version::where('id', $this->version->id)->lockForUpdate()->first();
            if (! $locked) {
                return;
            }

            $status = array_merge($locked->stage_status ?? [], [$stage => $state]);
            $locked->update(['stage_status' => $status]);
            $this->version = $locked->fresh();
        });

        $this->syncPhaseProgress($stage, $state);
    }

    private function syncPhaseProgress(string $stage, string $state): void
    {
        try {
            $progress = PhaseProgress::firstOrNew([
                'version_id' => $this->version->id,
                'phase_key' => $stage,
            ]);

            $now = now();
            if ($state === 'running' && ! $progress->started_at) {
                $progress->started_at = $now;
            }
            if ($state === 'done') {
                $progress->done = true;
                $progress->status = 'done';
                $progress->finished_at = $now;
                if (! $progress->started_at) {
                    $progress->started_at = $now;
                }
            } elseif ($state === 'error') {
                $progress->done = false;
                $progress->status = 'error';
                $progress->finished_at = $now;
            } else {
                $progress->done = false;
                $progress->status = $state;
            }
            $progress->save();
        } catch (Throwable $e) {
            // Jangan gagalkan pipeline karena sinkronisasi tabel opsional.
            report($e);
        }
    }

    private function runStage(string $key, ?string $overrideTarget = null, string $extraInstruction = ''): string
    {
        $messages = $this->buildMessages($key, $overrideTarget);
        $system = $messages[0]['content'] ?? '';

        $messages = $this->injectRetryHint($messages, $key);

        if ($extraInstruction !== '' && is_string($system)) {
            // Strip role markers that could simulate conversation turns in prompt injection
            $sanitized = preg_replace('/\b(system|assistant|user)\s*:/i', '[rol]:', $extraInstruction);
            $sanitized = preg_replace('/\b(system|assistant|user)\b/i', '[rol]', $sanitized);
            $messages[0]['content'] = $system."\n\n[PERINGATAN TAMBAHAN]\n{$sanitized}";
        }

        $buffer = '';
        $maxChunks = 2;
        $maxBufferBytes = 10 * 1024 * 1024;
        $shouldRedactStream = in_array($key, ['master_web', 'master_mobile'], true);

        for ($i = 0; $i < $maxChunks; $i++) {
            if (strlen($buffer) >= $maxBufferBytes) {
                throw new \RuntimeException("Stage {$key}: Output melebihi batas 10MB. Stage ditandai error.");
            }
            $prevLen = strlen($buffer);
            // CP-44 CP-02: kredensial tracking sengaja ikut di-stream & disimpan di master
            // prompt agar agent eksternal dapat mengirim checkpoint. Salinan aman tanpa
            // kredensial dilakukan client-side (MasterPromptViewer "Salin tanpa kredensial").
            $this->client->stream($messages, function (string $delta) use (&$buffer, $key, $maxBufferBytes) {
                if (strlen($buffer) + strlen($delta) > $maxBufferBytes) {
                    $delta = mb_substr($delta, 0, $maxBufferBytes - strlen($buffer));
                    $buffer .= $delta;
                    $this->sse->emit('token', ['stage' => $key, 'delta' => $delta, 'bytes_so_far' => strlen($buffer)]);

                    return;
                }
                $buffer .= $delta;
                $this->sse->emit('token', ['stage' => $key, 'delta' => $delta, 'bytes_so_far' => strlen($buffer)]);
            }, self::STAGE_MAX_TOKENS[$key] ?? 8192);

            $added = strlen($buffer) - $prevLen;

            // P3: selesai saat finish_reason=stop — jangan tebak heuristik endsNatural (hindari re-request ganda).
            if ($this->client->lastFinishReason === 'stop') {
                break;
            }
            if ($this->client->lastFinishReason === 'length') {
                // Terpotong oleh token limit → lanjut continuation
            } elseif ($added < 20 || $this->client->lastFinishReason === '') {
                break;
            }

            $last200 = mb_substr($buffer, -200);
            $messages = [
                ['role' => 'system', 'content' => 'Lanjutkan output dari bagian sebelumnya. JANGAN ulangi konten yang sudah ada. Langsung lanjutkan dari bagian terakhir yang terpotong.'],
                ['role' => 'user', 'content' => "Lanjutkan dari bagian terakhir:\n{$last200}\n\n---\nLanjutkan dari sini. Jangan ulangi konten sebelumnya."],
            ];
        }

        return $buffer;
    }

    private function buildMessages(string $stage, ?string $overrideTarget = null): array
    {
        $v = $this->version;
        $target = $overrideTarget ?? $v->project->target ?? 'web';
        if (in_array($stage, self::MOBILE_STAGES, true)) {
            $target = 'mobile';
            $overrideTarget = 'mobile';
        } elseif (in_array($stage, ['phases_web', 'standards_web', 'master_web'], true)) {
            $target = 'web';
            $overrideTarget = 'web';
        }
        $system = $this->systemPrompt($stage, $target);
        $context = $this->contextPrompt($stage, $v, $overrideTarget);

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $context],
        ];
    }

    private function systemPrompt(string $stage, string $target): string
    {
        $helpers = __DIR__.'/../Prompts/helpers.php';
        if (file_exists($helpers)) {
            require_once $helpers;
        }

        $promptStage = match ($stage) {
            'standards_web' => 'standards',
            'standards_mobile' => 'standards',
            'phases_web' => 'phases',
            'master_web' => 'phased_master',
            'phases_mobile' => 'phases_mobile',
            'master_mobile' => 'phased_master_mobile',
            'pertanyaan_mobile' => 'pertanyaan_mobile',
            'design_system' => 'design_system',
            'design_system_mobile' => 'design_system_mobile',
            'app_spec_web' => 'app_spec_web',
            'app_spec_mobile' => 'app_spec_mobile',
            default => $stage,
        };
        $path = __DIR__."/../Prompts/{$promptStage}.php";
        if (! file_exists($path)) {
            return '';
        }

        $loader = require $path;

        return $loader($target);
    }

    /** API Contract rich (dari stage api_contract) — untuk master prompts + agents. */

    /** CP-44 CP-02: kredensial tracking kini disengaja tersimpan di master prompt.
     *  Redaksi dilakukan client-side saat user memilih salinan aman (MasterPromptViewer). */
    /** CP-44 CP-06: delegasi ke StageArtifactValidator. */
    private function validateMarkdownArtifact(string $stage, string $content, array $mustHaveHeadings): void
    {
        $this->validator->validateMarkdownArtifact($stage, $content, $mustHaveHeadings);
    }

    /** CP-44 CP-06: delegasi ke StageArtifactValidator. */
    private function assertRequiredKeywords(string $stage, string $content): void
    {
        $this->validator->assertRequiredKeywords($stage, $content);
    }

    /** CP-44 CP-06: delegasi ke StageArtifactValidator. */
    private function detectGenericOutput(string $stage, string $content): void
    {
        $this->validator->detectGenericOutput($stage, $content);
    }

    /** CP-44 CP-06: delegasi ke StageArtifactValidator. */
    private function assertApiContractSchema(array $contract): void
    {
        $this->validator->assertApiContractSchema($contract);
    }

    /** CP-44 CP-06: delegasi ke StageArtifactValidator. */
    private function assertSectionOrdering(string $stage, array $mustHaveHeadings, array $foundHeadings): void
    {
        $this->validator->assertSectionOrdering($stage, $mustHaveHeadings, $foundHeadings);
    }

    /** CP-44 CP-06: delegasi ke StageArtifactValidator. */
    private function validateDesignSystemSectionRules(string $stage, string $content): void
    {
        $this->validator->validateDesignSystemSectionRules($stage, $content);
    }

    /** CP-44 CP-06: delegasi ke StageArtifactValidator. */
    private function validateArchitectureSectionRules(string $content): void
    {
        $this->validator->validateArchitectureSectionRules($content);
    }

    /** CP-44 CP-06: delegasi ke StageArtifactValidator. */
    private function validateSecuritySectionRules(string $content): void
    {
        $this->validator->validateSecuritySectionRules($content);
    }

    /** CP-44 CP-06: delegasi ke StageArtifactValidator. */
    private function assertSignatureElement(string $stage, string $content): void
    {
        $this->validator->assertSignatureElement($stage, $content);
    }

    /** CP-44 CP-06: delegasi ke StageArtifactValidator. */
    private function validatePrdSectionRules(string $content): void
    {
        $this->validator->validatePrdSectionRules($content);
    }

    /** CP-44 CP-06: delegasi ke StageArtifactValidator. */
    private function assertPrdDifferentiation(string $content): void
    {
        $this->validator->assertPrdDifferentiation($content);
    }

    /** CP-44 CP-06: delegasi ke StageArtifactValidator. */
    private function validateEnvConfigSectionRules(string $content): void
    {
        $this->validator->validateEnvConfigSectionRules($content);
    }

    /** CP-44 CP-06: delegasi ke StageArtifactValidator. */
    private function validateStandardsSectionRules(string $stage, string $content): void
    {
        $this->validator->validateStandardsSectionRules($stage, $content);
    }

    /** CP-44 CP-06: delegasi ke StageArtifactValidator. */
    private function validateAgentsSectionRules(string $content): void
    {
        $this->validator->validateAgentsSectionRules($content);
    }

    /** CP-44 CP-06: delegasi ke StageArtifactValidator. */
    private function normalizeApiContract(array $endpoints): array
    {
        return $this->validator->normalizeApiContract($endpoints);
    }

    /** CP-44 CP-06: proxy statis ke StageContextBuilder (BC untuk test). */
    public static function truncateForContext(string $text, int $maxChars): string
    {
        return StageContextBuilder::truncateForContext($text, $maxChars);
    }

    /** CP-44 CP-06: delegasi ke StageContextBuilder (kompatibilitas ReflectionMethod test). */
    private function contextPrompt(string $stage, Version $v, ?string $overrideTarget = null): string
    {
        return (new StageContextBuilder)->contextPrompt($stage, $v, $overrideTarget);
    }

    private function persistPendingTrackingToken(): void
    {
        // CP-6: tracking token creation moved to UI (Setup Tracking flow).
        // versions.tracking_token column is no longer populated by PipelineRunner.
    }

    /**
     * CP-4 (C-1c): catat estimasi token per-stage ke kolom JSONB stage_tokens.
     * Heuristic: bytes / 4 ≈ tokens (English). Akurasi cukup untuk UI throughput.
     */
    private function recordStageTokens(string $stage, int $bytes): void
    {
        try {
            $existing = $this->version->stage_tokens ?? [];
            $existing[$stage] = max(1, intdiv($bytes, 4));
            $this->version->update(['stage_tokens' => $existing]);
            $this->sse->emit('stage_tokens', [
                'stage' => $stage,
                'bytes' => $bytes,
                'tokens' => $existing[$stage],
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function saveArtifact(string $key, string $content): void
    {
        $this->detectGenericOutput($key, $content);

        $map = [
            'pertanyaan' => 'pertanyaan',
            'pertanyaan_mobile' => 'pertanyaan_mobile',
            'analisa' => 'analysis',
            'prd' => 'prd',
            'architecture' => 'architecture',
            'erd' => 'erd',
            'api_contract' => 'api_contract',
            'design_system' => 'design_system',
            'design_system_mobile' => 'design_system_mobile',
            'phases_web' => 'phases',
            'standards_web' => 'standards',
            'testing_strategy' => 'testing_strategy',
            'master_web' => 'master_prompt',
            'app_spec_web' => 'app_spec_web',
            'phases_mobile' => 'mobile_phases',
            'standards_mobile' => 'mobile_standards',
            'master_mobile' => 'mobile_master_prompt',
            'app_spec_mobile' => 'app_spec_mobile',
            'env_config' => 'env_config',
            'security' => 'security',
            'deployment' => 'deployment',
            'observability' => 'observability',
            'agents' => 'agents',
        ];

        $col = $map[$key] ?? null;
        if (! $col) {
            return;
        }

        $value = $content;
        if ($key === 'architecture') {
            $this->validateMarkdownArtifact($key, $content, ['## 1. Stack', '## 2. Module Boundaries', '## 3. Data Flow', '## 4. Folder Structure', '## 5. Deployment Topology', '## 6. Trade-offs']);
            $this->validateArchitectureSectionRules($content);
            $this->sse->emit('artifact', ['stage' => $key, 'content' => $content]);
        } elseif ($key === 'pertanyaan' || $key === 'pertanyaan_mobile') {
            $cleaned = $this->jsonParser->extractJson($content);
            $decoded = $this->jsonParser->tryJsonDecode($cleaned);
            if ($decoded !== null) {
                // Defence-in-depth: buang pertanyaan yang strukturnya rusak (id/question/options).
                $decoded = $this->sanitizeMcqData($decoded);
                if ($this->outputParser->mcqValidCount(json_encode($decoded)) < self::MIN_MCQ_QUESTIONS) {
                    throw new \RuntimeException("{$key}: pertanyaan valid < ".self::MIN_MCQ_QUESTIONS.' setelah sanitasi. Stage ditandai error.');
                }
                $value = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $this->sse->emit('artifact', ['stage' => $key, 'content' => $value]);
            } else {
                $this->sse->emit('artifact', ['stage' => $key, 'content' => $content]);
            }
        } elseif ($key === 'erd') {
            $parsed = $this->outputParser->parseErdText($content);
            if ($parsed !== null) {
                $value = $parsed;
                if (isset($parsed['api_contract'])) {
                    $this->version->update(['api_contract' => $parsed['api_contract']]);
                }
                $this->sse->emit('artifact', ['stage' => $key, 'content' => json_encode($parsed)]);
            } else {
                throw new \RuntimeException('ERD: Gagal parse output AI. Stage ditandai error.');
            }
        } elseif ($key === 'phases_web' || $key === 'phases_mobile') {
            $phases = $this->outputParser->parsePhasesText($content);
            if ($phases === null) {
                throw new \RuntimeException('Phases: Gagal parse output AI. Stage ditandai error.');
            }
            $value = $phases;
            $this->sse->emit('artifact', ['stage' => $key, 'content' => json_encode($phases, JSON_PRETTY_PRINT)]);
        } elseif ($key === 'api_contract') {
            $cleaned = $this->jsonParser->extractJson($content);
            $decoded = $this->jsonParser->tryJsonDecode($cleaned);
            if ($decoded !== null) {
                if (isset($decoded['endpoints']) && is_array($decoded['endpoints']) && $this->outputParser->isListKey($decoded, 'endpoints')) {
                    $value = $decoded['endpoints'];
                } elseif ($this->outputParser->isEndpointList($decoded)) {
                    $value = $decoded;
                } else {
                    throw new \RuntimeException('API Contract: struktur tidak dikenali. Stage ditandai error.');
                }
                $value = $this->normalizeApiContract($value);
                $this->assertApiContractSchema($value);
                $this->sse->emit('artifact', ['stage' => $key, 'content' => json_encode($value, JSON_PRETTY_PRINT)]);
            } else {
                \Log::error('[api_contract] JSON gagal di-parse', [
                    'version_id' => $this->version->id,
                    'cleaned' => $cleaned,
                ]);
                // R2: fallback ke ERD-embedded api_contract bila ada (tetap lewat schema yang sama).
                $erdContract = $this->version->erd['api_contract'] ?? [];
                if (is_array($erdContract) && $erdContract !== []) {
                    $value = $this->normalizeApiContract($erdContract);
                    $this->assertApiContractSchema($value);
                    \Log::warning('[api_contract] fallback ke ERD api_contract', ['version_id' => $this->version->id, 'count' => count($value)]);
                    $this->sse->emit('artifact', ['stage' => $key, 'content' => json_encode($value, JSON_PRETTY_PRINT)]);
                } else {
                    // R2b: fallback deterministik — bangun CRUD dari node ERD (schema SAMA, anti-stuck).
                    $derived = $this->buildCrudContractFromErd($this->version->erd ?? []);
                    if ($derived !== null) {
                        $value = $this->normalizeApiContract($derived);
                        $this->assertApiContractSchema($value);
                        \Log::warning('[api_contract] fallback CRUD dari ERD nodes', ['version_id' => $this->version->id, 'count' => count($value)]);
                        $this->sse->emit('artifact', ['stage' => $key, 'content' => json_encode($value, JSON_PRETTY_PRINT)]);
                    } else {
                        throw new \RuntimeException("JSON tidak valid untuk stage {$key}. Stage ditandai error.");
                    }
                }
            }
        } elseif ($key === 'design_system' || $key === 'design_system_mobile') {
            $headings = ['## 0. Pin the Subject', '## 1. Design Philosophy', '## 2. Token System', '## 3. Signature Element', '## 4. Component Patterns', '## 5. State Vocabulary', '## 6. Anti-Pattern Checklist', '## 7. Layout Rhythm', '## 8. Motion Choreography', '## 9. Microcopy Voice'];
            $this->validateMarkdownArtifact($key, $content, $headings);
            $this->validateDesignSystemSectionRules($key, $content);
            $this->sse->emit('artifact', ['stage' => $key, 'content' => $content]);
        } elseif ($key === 'app_spec_web' || $key === 'app_spec_mobile') {
            $platform = $key === 'app_spec_mobile' ? 'mobile' : 'web';
            $parsed = $this->outputParser->parseAppSpecJson($content, $platform);
            if ($parsed['data'] === null) {
                throw new \RuntimeException($key.': '.implode(' | ', $parsed['errors']).' Stage ditandai error.');
            }
            $value = $parsed['data'];
            // R7: bila komponen/widgets kosong padahal halaman/screens menyebut components_used —
            // turunkan dari referensi halaman (deterministik, schema tetap, anti-stuck).
            $value = $this->deriveSpecComponents($value, $platform);
            $this->validateAppSpecMasterCrossRef($key, $value);
            $this->sse->emit('artifact', ['stage' => $key, 'content' => json_encode($value, JSON_PRETTY_PRINT)]);
        } elseif ($key === 'master_web' || $key === 'master_mobile') {
            $value = $content;
            $this->validateMasterPrompt($key, $value);
            $this->validateMasterStandardsCrossRef($key, $value);
            // CP-45.A: injeksi blok tracking live deterministik (server-side, idempotent).
            $value = $this->trackingInjector->inject($this->version, $value);
            $this->sse->emit('artifact', ['stage' => $key, 'content' => $value]);
        } elseif ($key === 'analisa') {
            $this->validateMarkdownArtifact($key, $content, ['## 1. Intent Summary', '## 2. User Personas', '## 3. Core Problem', '## 4. Success Metrics', '## 5. Anti-Goals', '## 6. Daftar Halaman']);
            $this->sse->emit('artifact', ['stage' => $key, 'content' => $content]);
        } elseif ($key === 'prd') {
            $this->validateMarkdownArtifact($key, $content, ['## 1. Overview', '## 2. User Stories', '## 3. Functional Requirements', '## 4. Non-Functional Requirements', '## 5. Out of Scope', '## 6. Assumptions & Constraints', '## 7. Differentiation', '## 8. Open Questions']);
            $this->validatePrdSectionRules($content);
            $this->sse->emit('artifact', ['stage' => $key, 'content' => $content]);
        } elseif ($key === 'env_config') {
            $this->validateMarkdownArtifact($key, $content, ['## 1. Pendahuluan', '## 2. Environment Variables (Backend', '## 3. Environment Variables (Frontend', '## 5. File .env & .env.example', '## 7. Checklist Verifikasi']);
            $this->validateEnvConfigSectionRules($content);
            $this->sse->emit('artifact', ['stage' => $key, 'content' => $content]);
        } elseif ($key === 'security') {
            $this->validateMarkdownArtifact($key, $content, ['## 1. Autentikasi', '## 2. Otorisasi', '## 3. Input Validation', '## 4. XSS', '## 5. Data Protection', '## 6. Dependencies', '## 7. Transport', '## 8. Rate Limiting', '## 9. Checklist']);
            $this->validateSecuritySectionRules($content);
            $this->sse->emit('artifact', ['stage' => $key, 'content' => $content]);
        } elseif ($key === 'deployment') {
            $this->validateMarkdownArtifact($key, $content, ['## 1. Prerequisites', '## 2. Topology', '## 3. Environment', '## 4. Build & Start', '## 5. Cloudflare Tunnel', '## 6. Backup', '## 7. Rollback', '## 8. Zero-Downtime', '## 9. Post-Deploy', '## 10. Monitoring']);
            $this->sse->emit('artifact', ['stage' => $key, 'content' => $content]);
        } elseif ($key === 'standards_web' || $key === 'standards_mobile') {
            $this->validateStandardsSectionRules($key, $content);
            $this->sse->emit('artifact', ['stage' => $key, 'content' => $content]);
        } elseif ($key === 'observability') {
            $this->validateMarkdownArtifact($key, $content, ['## 1. Health Checks', '## 2. Structured Logging', '## 3. Error Monitoring', '## 4. Uptime', '## 5. Slow Query', '## 6. Dashboard', '## 7. Runbook', '## 8. Alerting', '## 9. Post-Incident']);
            $this->sse->emit('artifact', ['stage' => $key, 'content' => $content]);
        } elseif ($key === 'testing_strategy') {
            // CP-46.C: dedicated validator (10 headings, ≥5 critical paths).
            (new TestingStrategyValidator($this->validator))->validate($key, $content);
            $this->sse->emit('artifact', ['stage' => $key, 'content' => $content]);
        } elseif ($key === 'agents') {
            $this->validateAgentsSectionRules($content);
            $this->sse->emit('artifact', ['stage' => $key, 'content' => $content]);
        } else {
            $this->sse->emit('artifact', ['stage' => $key, 'content' => $content]);
        }

        $updateData = [$col => $value];
        $this->version->update($updateData);

        $quality = $this->computeStageQuality($key, $content);
        if ($quality !== null) {
            $existingQuality = $this->version->stage_quality ?? [];
            $existingQuality[$key] = $quality;
            $this->version->update(['stage_quality' => $existingQuality]);
        }

        $this->snapshotArtifact($key, $col, $value);
    }

    /**
     * Compute a 0-1 quality score for a freshly saved artifact.
     * Combination of structure presence, keyword coverage, length adequacy, and originality.
     */
    private function computeStageQuality(string $stage, string $content): ?float
    {
        if ($stage === 'pertanyaan' || $stage === 'pertanyaan_mobile') {
            return null;
        }

        $content = is_string($content) ? $content : json_encode($content);

        $score = 0.0;
        $checks = 0;

        // 1. Structural ordering check (40% weight contribution)
        $headings = $this->outputParser->extractMarkdownHeadings($content);
        $checks++;
        if (count($headings) >= 4) {
            $score += 0.4;
        }

        // 2. Required keywords (20%) — OR per group
        $groups = self::STAGE_REQUIRED_KEYWORDS[$stage] ?? [];
        if ($groups !== []) {
            $checks++;
            $hit = 0;
            foreach ($groups as $group) {
                foreach ($group as $kw) {
                    if (mb_stripos($content, $kw) !== false) {
                        $hit++;

                        break;
                    }
                }
            }
            $score += 0.2 * ($hit / count($groups));
        }

        // 3. Length adequacy (20%)
        $checks++;
        $len = mb_strlen($content);
        if ($len >= 2500) {
            $score += 0.2;
        } elseif ($len >= 1000) {
            $score += 0.1;
        }

        // 4. Originality — no generic patterns (20%)
        $checks++;
        $genericHit = false;
        foreach (self::GENERIC_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                $genericHit = true;

                break;
            }
        }
        if (! $genericHit) {
            $score += 0.2;
        }

        // 5. Cross-ref penalty (soft) — app_spec pages missing from master
        if ($this->crossRefPenalty > 0) {
            $score = max(0.0, $score - $this->crossRefPenalty);
            $this->crossRefPenalty = 0.0;
        }

        return $checks > 0 ? round($score, 2) : null;
    }

    /** Buang pertanyaan rusak dari MCQ data (id/question/options invalid); re-index. */
    private function sanitizeMcqData(array $decoded): array
    {
        if (! isset($decoded['questions']) || ! is_array($decoded['questions'])) {
            return $decoded;
        }

        $valid = [];
        foreach ($decoded['questions'] as $q) {
            if (! is_array($q)) {
                continue;
            }
            $id = $q['id'] ?? null;
            $question = $q['question'] ?? null;
            $options = $q['options'] ?? null;
            if (! is_string($id) || trim($id) === '' || ! is_string($question) || trim($question) === '' || ! is_array($options) || count($options) < 4) {
                continue;
            }
            $optsOk = true;
            foreach ($options as $opt) {
                if (! is_array($opt) || ! is_string($opt['key'] ?? null) || trim($opt['key'] ?? '') === '' || ! is_string($opt['text'] ?? null) || trim($opt['text'] ?? '') === '') {
                    $optsOk = false;
                    break;
                }
            }
            if ($optsOk) {
                $valid[] = $q;
            }
        }

        $decoded['questions'] = array_values($valid);

        return $decoded;
    }

    private function validateMasterPrompt(string $stage, string $content): void
    {
        if (strlen(trim($content)) < 500) {
            $length = strlen(trim($content));
            throw new \RuntimeException("Master prompt {$stage} terlalu pendek ({$length} chars) — kemungkinan output terpotong. Stage ditandai error.");
        }

        $hasSelesaiMarker = preg_match('/##\s*SELESAI(?:_ALL)?/i', $content) === 1;
        if (! $hasSelesaiMarker) {
            throw new \RuntimeException("Master prompt {$stage} kehilangan marker akhir '## SELESAI' — output terpotong. Stage ditandai error.");
        }

        $placeholderCount = preg_match_all('/<[A-Z][A-Z0-9_]*>/', $content);
        if ($placeholderCount > 3) {
            throw new \RuntimeException("Master prompt {$stage} memiliki {$placeholderCount} placeholder unfilled — output tidak lengkap. Stage ditandai error.");
        }
    }

    /**
     * P2 — App Spec ↔ Master cross-reference.
     * Every page/screen in app_spec must be mentioned in the (already-generated) master prompt.
     * Master is always generated before app_spec, so a missing mention is a real inconsistency.
     */
    private function validateAppSpecMasterCrossRef(string $stage, array $spec): void
    {
        $isMobile = $stage === 'app_spec_mobile';
        $masterKey = $isMobile ? 'mobile_master_prompt' : 'master_prompt';
        $master = (string) $this->version->{$masterKey};
        $items = $isMobile ? ($spec['screens'] ?? []) : ($spec['halaman'] ?? []);

        if ($master === '' || $items === []) {
            return;
        }

        $missing = [];
        foreach ($items as $item) {
            $name = is_array($item) ? ($item['key'] ?? ($item['nama'] ?? null)) : $item;
            if ($name !== null && $name !== '' && mb_stripos($master, (string) $name) === false) {
                $missing[] = $name;
            }
        }

        if ($missing !== []) {
            \Log::warning("Cross-ref app_spec↔master ({$stage})", [
                'stage' => $stage,
                'missing' => array_slice($missing, 0, 5),
            ]);
            $this->crossRefPenalty = max($this->crossRefPenalty ?? 0, 0.1);

            return;
        }
        $this->crossRefPenalty = 0;
    }

    /**
     * P2 — Master ↔ Standards soft cross-reference.
     * Master prompt wajib memuat minimal 1 heading utama dari standards-nya.
     * Soft check: hanya log warning + turunkan skor kualitas, tidak hard-fail.
     */
    private function validateMasterStandardsCrossRef(string $stage, string $content): void
    {
        $isMobile = $stage === 'master_mobile';
        $standards = (string) ($isMobile ? $this->version->mobile_standards : $this->version->standards);
        if ($standards === '') {
            return;
        }

        $standardsHeadings = $this->outputParser->extractMarkdownHeadings($standards);
        $used = [];
        foreach ($standardsHeadings as $heading) {
            if ($heading !== '' && mb_stripos($content, $heading) !== false) {
                $used[] = $heading;
            }
        }

        if ($used === []) {
            \Log::warning("Cross-ref master↔standards gagal untuk {$stage}", [
                'stage' => $stage,
                'standards_headings' => array_slice($standardsHeadings, 0, 10),
            ]);
        }
    }

    private function snapshotArtifact(string $stage, string $column, mixed $value): void
    {
        try {
            $project = $this->version->project;
            if (! $project) {
                return;
            }
            $serialized = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
            $activity = Activity::create([
                'project_id' => $project->id,
                'user_id' => $project->user_id,
                'version_id' => $this->version->id,
                'action' => Activity::ACTION_ARTIFACT_SNAPSHOT,
                'description' => "Snapshot stage {$stage} (v{$this->version->version_no})",
                'metadata' => [
                    'stage' => $stage,
                    'column' => $column,
                    'length' => strlen((string) $serialized),
                    'sha_prefix' => substr(sha1((string) $serialized), 0, 12),
                ],
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * P3 — Generic retry with remediation hint for validation failures.
     * Attempts validation via saveArtifact; on RuntimeException, records the error
     * (injecting it as hint in the next runStage call) and retries up to
     * MAX_VALIDATE_RETRIES. Throws the last error when exhausted.
     */
    /**
     * P3 — Inject last validation error as remediation hint into the system prompt
     * so a retry avoids repeating the same mistake.
     */
    private function injectRetryHint(array $messages, string $stage): array
    {
        $system = $messages[0]['content'] ?? '';
        $lastError = ($this->version->stage_errors ?? [])[$stage] ?? null;
        if ($lastError !== null && is_string($system)) {
            $sanitized = preg_replace('/\b(system|assistant|user)\s*:/i', '[rol]:', (string) $lastError);
            $sanitized = preg_replace('/\b(system|assistant|user)\b/i', '[rol]', $sanitized);
            $system = $system."\n\n[DORONGAN PERBAIKAN — OUTPUT SEBELUMNYA DITOLAK VALIDASI]\n{$sanitized}\nPerbaiki masalah tersebut secara spesifik. Jangan ulangi kesalahan yang sama.";

            // W6: reminder eksplisit keyword grup untuk stage yang gagal keyword
            $groups = self::STAGE_REQUIRED_KEYWORDS[$stage] ?? [];
            if ($groups !== []) {
                $lines = [];
                foreach ($groups as $group) {
                    $lines[] = 'Pastikan dokumen memuat minimal SATU dari: '.implode(' / ', $group).'.';
                }
                $system .= "\n\n[CEK KEYWORD STAGE]\n".implode("\n", $lines);
            }

            $messages[0]['content'] = $system;
        }

        return $messages;
    }

    private function retryAndValidate(string $key, string $content): string
    {
        $attempt = 0;

        while (true) {
            try {
                $this->saveArtifact($key, $content);

                return $content;
            } catch (\RuntimeException $e) {
                $this->recordStageError($key, $e->getMessage());
                $attempt++;
                if ($attempt >= self::MAX_VALIDATE_RETRIES) {
                    throw $e;
                }
                $content = $this->runStage($key);
            }
        }
    }

    private function recordSkipReason(string $stage, string $reason): void
    {
        $reasons = $this->version->skip_reasons ?? [];
        $reasons[$stage] = $reason;
        $this->version->update(['skip_reasons' => $reasons]);
    }

    private function recordStageError(string $stage, string $message): void
    {
        $errors = $this->version->stage_errors ?? [];
        $errors[$stage] = mb_substr($message, 0, 1000);
        $this->version->update(['stage_errors' => $errors]);
    }

    private function clearStageError(string $stage): void
    {
        $errors = $this->version->stage_errors ?? [];
        if (isset($errors[$stage])) {
            unset($errors[$stage]);
            $this->version->update(['stage_errors' => $errors]);
        }
    }

    private function retryPertanyaanForMinimum(string $content, string $stage = 'pertanyaan'): string
    {
        if ($this->outputParser->mcqCount($content) >= self::MIN_MCQ_QUESTIONS) {
            return $content;
        }

        $baseInstruction = 'Output HANYA satu blok JSON valid dimulai langsung dengan "{". Tanpa prosa, tanpa markdown, tanpa ``` fence, tanpa komentar. WAJIB minimal '.self::MIN_MCQ_QUESTIONS.' pertanyaan (target '.self::MIN_MCQ_QUESTIONS.'-'.self::MAX_MCQ_QUESTIONS.').';

        $best = $content;
        $bestCount = $this->outputParser->mcqCount($content);

        for ($attempt = 1; $attempt <= self::MAX_MCQ_RETRIES; $attempt++) {
            // Feedback loop: beri tahu AI output sebelumnya kurang lengkap + JANGAN ulangi
            // pertanyaan yang sudah ada — dorong panjang total ke target 5-10.
            $instruction = $baseInstruction;
            if ($bestCount > 0) {
                $instruction .= "\n\nKesalahan pada percobaan sebelumnya: output hanya berisi {$bestCount} pertanyaan (kurang dari ".self::MIN_MCQ_QUESTIONS.'). JANGAN ulangi pertanyaan yang sudah ada. Lengkapi total menjadi '.self::MIN_MCQ_QUESTIONS.'-'.self::MAX_MCQ_QUESTIONS.' pertanyaan UNIK dalam SATU blok JSON saja.'."\n\nOutput sebelumnya (jangan diulang, hanya sebagai referensi):\n".StageContextBuilder::truncateForContext($best, 800);
            }

            $this->sse->emit('status', ['stage' => $stage, 'state' => 'retrying', 'attempt' => $attempt, 'max' => self::MAX_MCQ_RETRIES, 'message' => 'Pertanyaan kurang dari '.self::MIN_MCQ_QUESTIONS.', generate ulang percobaan ke-'.$attempt.'...']);
            try {
                $content = $this->runStage($stage, null, $instruction);
            } catch (Throwable $e) {
                report($e);
                $delayUs = min(500_000 * (2 ** ($attempt - 1)), 8_000_000);
                usleep($delayUs);

                continue;
            }
            $count = $this->outputParser->mcqCount($content);

            if ($count >= self::MIN_MCQ_QUESTIONS) {
                $this->sse->emit('status', ['stage' => $stage, 'state' => 'running']);
                Log::info('PipelineRunner pertanyaan retry resolved', [
                    'version_id' => $this->version->id,
                    'stage' => $stage,
                    'attempt' => $attempt,
                    'mcq_count' => $count,
                ]);

                return $content;
            }

            if ($count > $bestCount) {
                $best = $content;
                $bestCount = $count;
            }
        }

        // R5: fallback — bila output terbaik berupa teks pertanyaan (bukan JSON), bangun items valid.
        $textQuestions = $this->buildQuestionsFromText($best);
        if ($textQuestions !== null) {
            $json = json_encode(['questions' => $textQuestions], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $this->sse->emit('status', ['stage' => $stage, 'state' => 'running']);
            Log::info('PipelineRunner pertanyaan text fallback', [
                'version_id' => $this->version->id,
                'stage' => $stage,
                'mcq_count' => count($textQuestions),
            ]);

            return $json;
        }

        // Exhausted tetapi masih < MIN → jangan diam-diam sukses; stage ditandai error.
        $label = $stage === 'pertanyaan' ? 'Pertanyaan klarifikasi (web)' : 'Pertanyaan klarifikasi (mobile)';
        throw new \RuntimeException(
            "{$label} hanya berisi {$bestCount} pertanyaan setelah ".self::MAX_MCQ_RETRIES.' percobaan (minimal '.self::MIN_MCQ_QUESTIONS.'). Stage ditandai error — coba lagi.'
        );
    }

    /**
     * R5 — Bangun pertanyaan MCQ minimal dari teks berformat list (1. / - / ###).
     * Return null bila < MIN. Opsi default Ya/Tidak agar tetap lolos mcqValidCount.
     */
    private function buildQuestionsFromText(string $text): ?array
    {
        $items = [];
        $used = [];
        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim($line);
            if (! preg_match('/^(?:\d+[.)]|[-*]|###\s+)\s*(.+)$/', $line, $m)) {
                continue;
            }
            $q = trim($m[1]);
            if ($q === '' || mb_strlen($q) < 8 || isset($used[$q])) {
                continue;
            }
            $used[$q] = true;
            $items[] = ['id' => 'mq'.(count($items) + 1), 'question' => $q, 'options' => ['Ya', 'Tidak']];
            if (count($items) >= self::MAX_MCQ_QUESTIONS) {
                break;
            }
        }

        return count($items) >= self::MIN_MCQ_QUESTIONS ? $items : null;
    }

    /**
     * Reset dependents of a regenerated stage to 'pending' and clear their artifact data.
     * Call before regenerating a stage to ensure downstream stages use fresh context.
     */
    public function invalidateDependents(string $stage): array
    {
        $dependents = self::STAGE_DEPENDENTS[$stage] ?? [];
        $target = $this->version->project->target ?? 'web';
        $reset = [];
        $statuses = $this->version->stage_status ?? [];

        foreach ($dependents as $dep) {
            if ($target === 'web' && in_array($dep, self::MOBILE_STAGES, true)) {
                continue;
            }
            if (($statuses[$dep] ?? 'pending') === 'done') {
                $this->clearArtifact($dep);
                $statuses[$dep] = 'pending';
                $reset[] = $dep;
            }
        }

        $this->version->stage_status = $statuses;
        $this->version->save();

        return $reset;
    }

    private function clearArtifact(string $stage): void
    {
        $col = self::COLUMN_MAP[$stage] ?? null;
        if (! $col) {
            return;
        }
        if (in_array($col, ['erd', 'api_contract', 'phases', 'mobile_phases', 'app_spec_web', 'app_spec_mobile'])) {
            $this->version->{$col} = null;
        } else {
            $this->version->{$col} = null;
        }
    }

    /**
     * R2b — Derive a full CRUD api_contract deterministically from ERD nodes (anti-stuck).
     * Tetap melewati assertApiContractSchema: setiap item lengkap resource/method/path/auth/description.
     */
    /**
     * R7 — Derive components/widgets dari components_used/widgets_used di halaman/screens
     * bila array komponen kosong. Deterministik + schema-compliant (anti-stuck pada provider lemah).
     */
    private function deriveSpecComponents(array $spec, string $platform): array
    {
        $itemsKey = $platform === 'mobile' ? 'widgets' : 'components';
        $pagesKey = $platform === 'mobile' ? 'screens' : 'halaman';
        $usedKey = $platform === 'mobile' ? 'widgets_used' : 'components_used';

        $existing = $spec[$itemsKey] ?? [];
        if (is_array($existing) && $existing !== []) {
            return $spec;
        }

        $names = [];
        foreach (($spec[$pagesKey] ?? []) as $page) {
            $used = is_array($page) ? ($page[$usedKey] ?? []) : [];
            if (is_array($used)) {
                foreach ($used as $u) {
                    if (is_string($u) && $u !== '') {
                        $names[$u] = true;
                    }
                }
            }
        }
        if ($names === []) {
            return $spec;
        }

        ksort($names);
        $spec[$itemsKey] = array_map(fn ($n) => [
            'key' => $n,
            'title' => ucfirst(str_replace('_', ' ', $n)),
            'type' => $platform === 'mobile' ? 'widget' : 'component',
            'used_in' => [],
        ], array_keys($names));

        return $spec;
    }

    private function buildCrudContractFromErd(array $erd): ?array
    {
        $nodes = $erd['nodes'] ?? [];
        if (! is_array($nodes) || $nodes === []) {
            return null;
        }

        $contract = [
            ['resource' => 'auth', 'method' => 'POST', 'path' => '/auth/login', 'description' => 'Login user, set session cookie', 'auth' => 'none'],
            ['resource' => 'auth', 'method' => 'POST', 'path' => '/auth/logout', 'description' => 'Logout, hapus session', 'auth' => 'required'],
        ];
        $seen = [];
        foreach ($nodes as $node) {
            $id = is_array($node) ? ($node['id'] ?? ($node['label'] ?? null)) : $node;
            if (! is_string($id) || $id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $id));
            if ($slug === '') {
                $slug = 'resource';
            }
            $contract[] = ['resource' => $slug, 'method' => 'GET', 'path' => '/'.$slug, 'description' => 'List '.$id, 'auth' => 'required'];
            $contract[] = ['resource' => $slug, 'method' => 'GET', 'path' => '/'.$slug.'/{id}', 'description' => 'Detail '.$id, 'auth' => 'required'];
            $contract[] = ['resource' => $slug, 'method' => 'POST', 'path' => '/'.$slug, 'description' => 'Buat '.$id, 'auth' => 'required'];
            $contract[] = ['resource' => $slug, 'method' => 'PUT', 'path' => '/'.$slug.'/{id}', 'description' => 'Update '.$id, 'auth' => 'required'];
            $contract[] = ['resource' => $slug, 'method' => 'DELETE', 'path' => '/'.$slug.'/{id}', 'description' => 'Hapus '.$id, 'auth' => 'required'];
        }

        return $contract === [] ? null : $contract;
    }
}
