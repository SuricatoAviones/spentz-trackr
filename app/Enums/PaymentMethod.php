<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case PagoMovil = 'pago_movil';
    case Transferencia = 'transferencia';
}
