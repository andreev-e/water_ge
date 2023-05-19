<?php

namespace App\Enums;

enum EventTypes: string
{
    case water = 'water';
    case gas = 'gas';
    case energy = 'energy';

    public function getIcon(): string
    {
        return match ($this) {
            self::water => '💧',
            self::gas => '🔥',
            self::energy => '⚡️',
        };
    }
}
