<?php

function platformSuffix(string $target): string
{
    return match ($target) {
        'mobile' => 'Platform target: Mobile Android (APK).' . PHP_EOL
            . 'Tech stack: Flutter + Dart + Riverpod + GoRouter + Material Design 3 + drift/sqflite.',
        'both' => 'Platform target: Web dan Mobile Android.' . PHP_EOL
            . 'Tech stack Web: Laravel 11 (PHP 8.4) + Next.js (App Router, React 19, TypeScript) + Tailwind CSS v4 + PostgreSQL 16.' . PHP_EOL
            . 'Tech stack Mobile: Flutter + Dart + Riverpod + GoRouter + Material Design 3 + drift/sqflite.',
        default => 'Platform target: Web App.' . PHP_EOL
            . 'Tech stack: Laravel 11 (PHP 8.4) + Next.js (App Router, React 19, TypeScript) + Tailwind CSS v4 + PostgreSQL 16.',
    };
}

function techStackShort(string $target): string
{
    return match ($target) {
        'mobile' => 'Flutter + Dart + Riverpod + GoRouter + Material Design 3 + drift/sqflite',
        'both' => 'Web: Laravel 11 + Next.js + React 19 + Tailwind CSS v4 + PostgreSQL 16 | Mobile: Flutter + Dart + Riverpod + GoRouter + Material Design 3',
        default => 'Laravel 11 (PHP 8.4) + Next.js (App Router, React 19, TypeScript) + Tailwind CSS v4 + PostgreSQL 16',
    };
}
