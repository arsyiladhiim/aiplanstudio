<?php

namespace Tests\Unit;

use App\Services\SseEmitter;
use PHPUnit\Framework\TestCase;

class SseEmitterTest extends TestCase
{
    public function test_emit_writes_to_injected_stream(): void
    {
        $stream = fopen('php://memory', 'w+');
        $emitter = new SseEmitter($stream);
        $emitter->emit('status', ['stage' => 'erd', 'state' => 'done']);
        unset($emitter);

        rewind($stream);
        $out = stream_get_contents($stream);
        fclose($stream);

        $this->assertStringContainsString('event: status', (string) $out);
        $this->assertStringContainsString('"stage":"erd"', (string) $out);
    }

    public function test_injected_stream_survives_emitter_destruction(): void
    {
        // Regression #54: regenerate membuat 2 runner pada stream yang sama —
        // destructor emitter pertama tidak boleh menutup stream injeksi.
        $stream = fopen('php://memory', 'w+');
        $emitter = new SseEmitter($stream);
        unset($emitter);

        $this->assertIsResource($stream);
        fwrite($stream, "event: ping\n\n");
        rewind($stream);
        $out = stream_get_contents($stream);
        fclose($stream);

        $this->assertStringContainsString('event: ping', (string) $out);
    }
}
