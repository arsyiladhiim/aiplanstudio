<?php

function platformSuffix(string $target): string
{
    return match ($target) {
        'mobile' => 'Platform target: Mobile (APK/iOS). Pilih stack yang sesuai untuk mobile development.',
        'both' => 'Platform target: Web dan Mobile. Berikan solusi untuk kedua platform.',
        default => 'Platform target: Web App.',
    };
}