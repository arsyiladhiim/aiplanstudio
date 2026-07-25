<?php

function platformSuffix(string $target): string
{
    return match ($target) {
        'mobile' => 'Platform target: Mobile (APK/iOS). Stack yang cocok: Flutter atau React Native.',
        'both' => 'Platform target: Web dan Mobile. Berikan dua varian.',
        default => 'Platform target: Web App. Stack: Next.js / Laravel / Postgres.',
    };
}
