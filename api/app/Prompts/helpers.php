<?php

use App\Support\StackSpec;

function platformSuffix(string $target): string
{
    return match ($target) {
        'mobile' => 'Platform target: Mobile Android (APK).' . PHP_EOL
            . 'Tech stack: ' . StackSpec::FLUTTER . '.',
        'both' => 'Platform target: Web dan Mobile Android.' . PHP_EOL
            . 'Tech stack Web: ' . StackSpec::web() . '.' . PHP_EOL
            . 'Tech stack Mobile: ' . StackSpec::FLUTTER . '.',
        default => 'Platform target: Web App.' . PHP_EOL
            . 'Tech stack: ' . StackSpec::web() . '.',
    };
}

function techStackShort(string $target): string
{
    return match ($target) {
        'mobile' => StackSpec::FLUTTER,
        'both' => StackSpec::line('both'),
        default => StackSpec::web(),
    };
}
