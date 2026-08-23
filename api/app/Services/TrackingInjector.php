<?php

namespace App\Services;

use App\Models\ProjectApiToken;
use App\Models\Version;

/**
 * CP-45.A: Service yang menyuntikkan BLOK LIVE tracking secara deterministik
 * ke master prompt. AI tidak lagi dipercaya menyalin kredensial/URL/version_id;
 * server-side ini yang menjamin blok siap pakai (bash/python/node).
 * Idempotent: marker `<!-- cp45:tracking-live:start -->` ... `-->` melindungi
 * dari penumpukan ketika dipanggil berulang kali.
 */
class TrackingInjector
{
    public const MARKER_START = '<!-- cp45:tracking-live:start -->';

    public const MARKER_END = '<!-- cp45:tracking-live:end -->';

    /**
     * Hasilkan blok live tracking untuk version tertentu.
     * Mengembalikan array: ['block' => string, 'has_token' => bool, 'phase_keys' => array].
     */
    public function build(Version $v): array
    {
        $project = $v->project;
        $baseUrl = rtrim((string) (config('app.tracking_base_url') ?: config('app.url')), '/');
        $webhookUrl = $baseUrl.'/api/webhooks/phase-complete';
        $versionId = $v->id;

        $token = $this->resolveAutoTrackingToken($v);
        $hasToken = $token !== null;
        $tokenPlain = $hasToken ? (string) $token->revealStoredToken() : '';
        $secretPlain = $hasToken ? (string) $token->revealStoredSecret() : '';

        $phaseKeys = array_values(array_filter(
            array_merge(
                is_array($v->phases) ? array_column($v->phases, 'key') : [],
                is_array($v->mobile_phases) ? array_column($v->mobile_phases, 'key') : [],
            ),
            fn ($k) => is_string($k) && $k !== ''
        ));

        $block = $this->renderBlock([
            'webhookUrl' => $webhookUrl,
            'versionId' => $versionId,
            'hasToken' => $hasToken,
            'tokenPlain' => $tokenPlain,
            'secretPlain' => $secretPlain,
            'phaseKeys' => $phaseKeys,
        ]);

        return [
            'block' => $block,
            'has_token' => $hasToken,
            'phase_keys' => $phaseKeys,
            'webhook_url' => $webhookUrl,
        ];
    }

    /**
     * Inject blok ke dalam markdown master prompt.
     * Idempotent: blok lama dengan marker akan diganti.
     * Section heading "6. Tracking Webhook" / "## 6. Tracking Webhook" akan diganti isinya;
     * bila heading tidak ditemukan, blok disisipkan di akhir (dengan marker section sendiri).
     */
    public function inject(Version $v, string $markdown): string
    {
        $built = $this->build($v);
        $wrapped = $built['block'];

        // Hapus blok live lama (idempotent).
        $pattern = '/'.preg_quote(self::MARKER_START, '/').'.*?'.preg_quote(self::MARKER_END, '/').'/su';
        $markdown = preg_replace($pattern, '', $markdown) ?? $markdown;

        // Cari heading §6 tracking (jenis heading bisa bervariasi: ## atau ###).
        if (preg_match('/^(\#{1,4}\s*6[^#\n]*?(?:Tracking|Webhook)[^#\n]*?)\n.*?(?=\n\#{1,4}\s*\d|\Z)/usm', $markdown, $m, PREG_OFFSET_CAPTURE)) {
            $heading = $m[1][0];
            $insertAt = $m[1][1] + strlen($heading);
            $markdown = substr($markdown, 0, $insertAt)."\n\n".$wrapped."\n".substr($markdown, $insertAt);
        } else {
            // Tidak ada §6 — tambahkan section tersendiri di akhir.
            $markdown = rtrim($markdown)."\n\n## 6. Tracking Webhook (Live — server-injected)\n\n".$wrapped."\n";
        }

        return $markdown;
    }

    private function resolveAutoTrackingToken(Version $v): ?ProjectApiToken
    {
        $name = 'auto-tracking-'.substr(md5((string) $v->id), 0, 8);

        return ProjectApiToken::where('project_id', $v->project_id)
            ->where('name', $name)
            ->latest('id')
            ->first();
    }

    /**
     * Render blok live dalam markdown + 3 snippet siap eksekusi.
     */
    private function renderBlock(array $d): string
    {
        $url = $d['webhookUrl'];
        $vid = $d['versionId'];
        $phaseList = implode(', ', $d['phaseKeys']);
        $hasToken = $d['hasToken'];
        $token = $d['tokenPlain'];
        $secret = $d['secretPlain'];

        $credsSection = $hasToken
            ? "**Token (Bearer):** `{$token}`\n**Secret (HMAC key):** `{$secret}`\n\n> ⚠️ RAHASIA. Jangan commit ke repo; simpan di environment variable lokal."
            : '**Token & Secret belum dibuat.** Buka panel **Setup Tracking** di wizard/halaman project → klik tombol — TOKEN+SECRET akan di-generate sekali dan ditampilkan. Setelah itu, regenerate stage master_prompt agar blok live ini ter-update otomatis.';

        $versionHint = "**Version ID (WAJIB):** `{$vid}`\n**Phase key tersedia:** `{$phaseList}`";

        $curlSnippet = $hasToken ? <<<BASH
```bash
# Kirim checkpoint fase (bash + curl + openssl).
send_checkpoint() {
  local status="\$1" phase="\$2" task="\$3" note="\$4"
  local ts body sig
  ts=\$(date +%s)
  if [ -n "\$task" ]; then
    body="{\"version_id\":{$vid},\"event_id\":\"\$ts:\$phase:\$task:\$status\",\"phase_key\":\"\$phase\",\"task_key\":\"\$task\",\"task_type\":\"fitur\",\"status\":\"\$status\",\"output\":\"\$note\"}"
  else
    body="{\"version_id\":{$vid},\"event_id\":\"\$ts:\$phase:\$status\",\"phase_key\":\"\$phase\",\"status\":\"\$status\",\"output\":\"\$note\"}"
  fi
  sig=\$(printf '%s.%s' "\$ts" "\$body" | openssl dgst -sha256 -hmac '{$secret}' -hex | awk '{print \$2}')
  curl -m 10 -sS -X POST '{$url}' \\
    -H "Authorization: Bearer {$token}" \\
    -H "X-Token-Secret: {$secret}" \\
    -H "X-Timestamp: \$ts" \\
    -H "X-Signature: \$sig" \\
    -H 'Content-Type: application/json' \\
    -d "\$body"
}
# Contoh: send_checkpoint done fase1_setup "" "Setup selesai"
```
BASH : "```bash\n# Token belum dibuat — buat lewat Setup Tracking dulu.\n```";

        $pythonSnippet = $hasToken ? <<<PY
```python
# Pakai:  python -c "import tracker; tracker.send('done', 'fase1_setup', note='Setup selesai')"
import hashlib, hmac, json, os, time, urllib.request

WEBHOOK_URL = "{$url}"
TOKEN = os.environ.get("AIPS_TOKEN", "{$token}")
SECRET = os.environ.get("AIPS_SECRET", "{$secret}")
VERSION_ID = {$vid}

def send(status, phase_key, task_key=None, note=""):
    ts = str(int(time.time()))
    payload = {"version_id": VERSION_ID, "phase_key": phase_key, "status": status, "output": note}
    if task_key:
        payload["task_key"] = task_key; payload["task_type"] = "fitur"
    payload["event_id"] = f"{ts}:{phase_key}:{task_key or ''}:{status}"
    body = json.dumps(payload, separators=(",", ":"), ensure_ascii=False)
    sig = hmac.new(SECRET.encode(), f"{ts}.{body}".encode(), hashlib.sha256).hexdigest()
    req = urllib.request.Request(WEBHOOK_URL, data=body.encode(), method="POST", headers={
        "Authorization": f"Bearer {TOKEN}",
        "X-Token-Secret": SECRET,
        "X-Timestamp": ts,
        "X-Signature": sig,
        "Content-Type": "application/json",
    })
    with urllib.request.urlopen(req, timeout=10) as r:
        return r.status, r.read().decode()
```
PY : "```python\n# Token belum dibuat — buat lewat Setup Tracking dulu.\n```";

        $nodeSnippet = $hasToken ? <<<JS
```js
// Node 18+: pakai native crypto + fetch (no deps).
const crypto = require('crypto');

const WEBHOOK_URL = '{$url}';
const TOKEN = process.env.AIPS_TOKEN ?? '{$token}';
const SECRET = process.env.AIPS_SECRET ?? '{$secret}';
const VERSION_ID = {$vid};

async function sendCheckpoint(status, phaseKey, opts = {}) {
  const taskKey = opts.taskKey ?? null;
  const note = opts.note ?? '';
  const ts = String(Math.floor(Date.now() / 1000));
  const eventId = \`\${ts}:\${phaseKey}:\${taskKey ?? ''}:\${status}\`;
  const payload = { version_id: VERSION_ID, phase_key: phaseKey, status, output: note };
  if (taskKey) { payload.task_key = taskKey; payload.task_type = 'fitur'; }
  payload.event_id = eventId;
  const body = JSON.stringify(payload);
  const sig = crypto.createHmac('sha256', SECRET).update(\`\${ts}.\${body}\`).digest('hex');
  const res = await fetch(WEBHOOK_URL, {
    method: 'POST',
    headers: {
      Authorization: \`Bearer \${TOKEN}\`,
      'X-Token-Secret': SECRET,
      'X-Timestamp': ts,
      'X-Signature': sig,
      'Content-Type': 'application/json',
    },
    body,
  });
  return { status: res.status, body: await res.text() };
}

module.exports = { sendCheckpoint };
```
JS : "```js\n// Token belum dibuat — buat lewat Setup Tracking dulu.\n```";

        $tldr = $hasToken
            ? '> **TLDR**: panggil `sendCheckpoint(done, "fase1_setup", {note: "Setup selesai"})` setelah setiap fase/sub-item selesai. Retry 3x backoff; 409 = lanjut; 422 = perbaiki key; total gagal = catat, LANJUT (jangan berhenti).'
            : '> **TLDR**: token belum ada — buka Setup Tracking di UI, lalu regenerate master_prompt agar snippet di bawah muncul dengan kredensial nyata.';

        return self::MARKER_START."\n"
            .$tldr."\n\n"
            .$versionHint."\n\n"
            ."**Endpoint:** `POST {$url}`\n\n"
            .$credsSection."\n\n"
            ."#### Snippet siap pakai (pilih salah satu)\n\n"
            .$curlSnippet."\n\n"
            .$pythonSnippet."\n\n"
            .$nodeSnippet."\n\n"
            .self::MARKER_END;
    }
}
