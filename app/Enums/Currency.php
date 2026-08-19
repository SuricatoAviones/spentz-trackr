<?php

namespace App\Enums;

enum Currency: string
{
    case Usd = 'usd';
    case Ves = 'ves';
    case Usdt = 'usdt';

    /**
     * Get the human-readable label in Spanish.
     */
    public function label(): string
    {
        return match ($this) {
            self::Usd => 'USD',
            self::Ves => 'Bs',
            self::Usdt => 'USDT',
        };
    }
}
