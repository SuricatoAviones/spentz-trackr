<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case PagoMovil = 'pago_movil';
    case Transferencia = 'transferencia';

    /**
     * Get the human-readable label in Spanish.
     */
    public function label(): string
    {
        return match ($this) {
            self::PagoMovil => 'Pago móvil',
            self::Transferencia => 'Transferencia',
        };
    }
}
