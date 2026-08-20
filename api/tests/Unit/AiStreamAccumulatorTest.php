<?php

namespace Tests\Unit;

use App\Services\AiClient;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AiStreamAccumulatorTest extends TestCase
{
    private function event(string $content, ?string $finish = null): string
    {
        $payload = ['id' => 'x', 'choices' => [['delta' => ['content' => $content], 'finish_reason' => $finish]]];

        return 'data: '.json_encode($payload)."\n";
    }

    public function test_split_event_across_reads_is_reassembled(): void
    {
        $pending = '';
        // Event terbelah di tengah 'data:'
        $chunk1 = 'data: {"id":"a","choices":[{"delta":{"content":"he';
        $events1 = AiClient::extractSseEvents($pending, $chunk1);
        $this->assertSame([], $events1); // belum baris lengkap
        $this->assertNotSame('', $pending);

        $chunk2 = "llo\"} }]}}\n";
        $events2 = AiClient::extractSseEvents($pending, $chunk2);
        $this->assertCount(1, $events2);
        $this->assertStringContainsString('hello', $events2[0]['data']);
        $this->assertFalse($events2[0]['done']);
    }

    public function test_multiple_events_in_single_chunk(): void
    {
        $pending = '';
        $events = AiClient::extractSseEvents($pending, $this->event('a').$this->event('b')."data: [DONE]\n");
        $this->assertCount(3, $events);
        $this->assertFalse($events[0]['done']);
        $this->assertTrue($events[2]['done']);
        $this->assertSame('', $pending);
    }

    public function test_partial_tail_across_three_reads(): void
    {
        $pending = '';
        $all = '';
        $a = AiClient::extractSseEvents($pending, 'data: {"x":1');
        $b = AiClient::extractSseEvents($pending, "}\n");
        $c = AiClient::extractSseEvents($pending, 'data: [DONE]'."\n");
        $this->assertSame([], $a);
        $this->assertCount(1, $b);
        $this->assertCount(1, $c);
        $this->assertTrue($c[0]['done']);
    }
}

class TokenBudgetTest extends TestCase
{
    public function test_budget_map_shapes(): void
    {
        $ref = new ReflectionClass(\App\Services\PipelineRunner::class);
        $map = $ref->getConstant('STAGE_MAX_TOKENS');

        $all = \App\Models\Version::ALL_STAGES;
        foreach ($all as $stage) {
            $this->assertArrayHasKey($stage, $map, "stage {$stage} harus punya budget");
        }
        // MCQ kecil, dokumen panjang tetap tinggi (hindari truncation baru)
        $this->assertSame(1500, $map['pertanyaan']);
        $this->assertSame(1500, $map['pertanyaan_mobile']);
        $this->assertSame(4096, $map['analisa']);
        $this->assertSame(8192, $map['master_web']);
        $this->assertSame(8192, $map['design_system']);
        $this->assertLessThan($map['master_web'], $map['pertanyaan']);
    }
}