<?php

namespace App\Support;

/**
 * Single source of truth untuk versi stack yang dihasilkan wizard
 * (output prompt — BUKAN versi infra repo ini).
 *
 * Semua prompt WAJIB pakai konstanta/method ini atau string identik agar
 * konsisten. Test regression: ExistingPromptsValidationTest.
 */
final class StackSpec
{
    public const PHP = '8.4';

    public const LARAVEL = 'Laravel 13 (PHP 8.4)';

    public const NEXTJS = 'Next.js 16 (App Router, React 19, TypeScript)';

    public const NODE = 'Node 24 LTS';

    public const POSTGRES = 'PostgreSQL 18';

    public const REDIS = 'Redis 8';

    public const TAILWIND = 'Tailwind CSS v4';

    public const FLUTTER = 'Flutter + Dart + Riverpod + GoRouter + Material Design 3 + drift/sqflite';

    public static function web(): string
    {
        return self::LARAVEL.' + '.self::NEXTJS.' + '.self::TAILWIND.' + '.self::POSTGRES;
    }

    public static function line(string $target): string
    {
        return match ($target) {
            'mobile' => self::FLUTTER,
            'both' => 'Web: '.self::LARAVEL.' + Next.js 16 + React 19 + '.self::TAILWIND.' + '.self::POSTGRES.' | Mobile: '.self::FLUTTER,
            default => self::web(),
        };
    }
}
