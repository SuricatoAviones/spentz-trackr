<?php

namespace App\Enums;

/**
 * La marca es solo para presentación (icono y color de la ficha). El banco va
 * en texto libre porque la lista cambia y no compensa una tabla que mantener.
 */
enum CardBrand: string
{
    case Visa = 'visa';
    case Mastercard = 'mastercard';
    case Amex = 'amex';
    case Other = 'other';
}
