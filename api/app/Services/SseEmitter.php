<?php

namespace App\Services;

class SseEmitter
{
    /** @var resource */
    private $stdout;

    public function __construct($stdout = null)
    {
        $this->stdout = $stdout ?? fopen('php://output', 'w');
    }

    public function __destruct()
    {
        if (is_resource($this->stdout)) {
            fclose($this->stdout);
        }
    }

    public function emit(string $event, array $data): void
    {
        $json = json_encode($data);
        if ($json === false) {
            $clean = [];
            foreach ($data as $k => $v) {
                $clean[$k] = is_string($v) ? mb_convert_encoding($v, 'UTF-8', 'UTF-8') : $v;
            }
            $json = json_encode($clean, JSON_INVALID_UTF8_SUBSTITUTE);
        }
        fwrite($this->stdout, "event: {$event}\ndata: {$json}\n\n");
        fwrite($this->stdout, ": ping\n\n");
        if (ob_get_level() > 0) {
            ob_flush();
        }
        if (function_exists('flush')) {
            flush();
        }
    }
}
