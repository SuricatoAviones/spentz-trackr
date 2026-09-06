<?php

namespace App\Enums;

enum Frequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    /**
     * Get the human-readable label in Spanish.
     */
    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Diario',
            self::Weekly => 'Semanal',
            self::Monthly => 'Mensual',
            self::Quarterly => 'Trimestral',
            self::Yearly => 'Anual',
        };
    }

    /**
     * Advance the given date by one period for this frequency.
     */
    public function advance(\DateTimeInterface $date): \DateTimeInterface
    {
        return match ($this) {
            self::Daily => $date->modify('+1 day'),
            self::Weekly => $date->modify('+7 days'),
            self::Monthly => $date->modify('+1 month'),
            self::Quarterly => $date->modify('+3 months'),
            self::Yearly => $date->modify('+1 year'),
        };
    }
}
