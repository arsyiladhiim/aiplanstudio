<?php

namespace App\Services;

class AiJsonParser
{
    public function extractJson(string $content): string
    {
        $content = trim(preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? '');
        if ($content === '') {
            return '';
        }

        $content = (string) preg_replace('/```(?:json)?\s*\n?(.*?)```/si', '$1', $content);
        $content = trim($content);

        $openBrace = strpos($content, '{');
        $openBracket = strpos($content, '[');
        if ($openBrace === false && $openBracket === false) {
            return '';
        }
        $open = ($openBrace !== false && ($openBracket === false || $openBrace < $openBracket))
            ? $openBrace
            : $openBracket;

        $closer = $content[$open] === '{' ? '}' : ']';
        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($content);
        $end = -1;

        for ($i = $open; $i < $length; $i++) {
            $ch = $content[$i];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($ch === '\\') {
                    $escaped = true;
                } elseif ($ch === '"') {
                    $inString = false;
                }

                continue;
            }
            if ($ch === '"') {
                $inString = true;

                continue;
            }
            if ($ch === '{' || $ch === '[') {
                $depth++;
            } elseif ($ch === '}' || $ch === ']') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }

        if ($end === -1) {
            $end = strrpos($content, $closer);
            if ($end === false || $end <= $open) {
                return '';
            }
        }

        $sub = substr($content, $open, $end - $open + 1);
        $sub = (string) preg_replace('/,\s*([\]}])/', '$1', $sub);

        return trim($sub);
    }

    public function tryJsonDecode(string $content): ?array
    {
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        $stripped = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $content);
        $decoded = json_decode($stripped, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        $quotedKeys = (string) preg_replace(
            '/([{,]\s*)([A-Za-z_][A-Za-z0-9_]*)(\s*:)/',
            '$1"$2"$3',
            $stripped
        );
        $decoded = json_decode($quotedKeys, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        foreach (['[', '{'] as $open) {
            $close = $open === '[' ? ']' : '}';
            $missing = substr_count($stripped, $open) - substr_count($stripped, $close);
            if ($missing > 0 && $missing < 30) {
                $candidate = $stripped.str_repeat($close, $missing);
                $decoded = json_decode($candidate, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }
        }

        // Strategy 5: Trim trailing annotations progressively — but only accept
        // if bracket balance is still even (avoid returning truncated structure).
        $len = strlen($stripped);
        for ($i = 1; $i <= min(40, $len - 1); $i++) {
            $trimmed = substr($stripped, 0, -$i);
            if ($trimmed === '' || $trimmed === false) {
                continue;
            }
            $openBraces = substr_count($trimmed, '{') - substr_count($trimmed, '}');
            $openBrackets = substr_count($trimmed, '[') - substr_count($trimmed, ']');
            if ($openBraces > 0 || $openBrackets > 0) {
                continue;
            }
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        $singleQuoted = (string) preg_replace("/'([^']+)'/", '"$1"', $stripped);
        $combo = (string) preg_replace(
            '/([{,]\s*)([A-Za-z_][A-Za-z0-9_]*)(\s*:)/',
            '$1"$2"$3',
            $singleQuoted
        );
        $decoded = json_decode($combo, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        $decoded = json_decode($singleQuoted, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return null;
    }
}
