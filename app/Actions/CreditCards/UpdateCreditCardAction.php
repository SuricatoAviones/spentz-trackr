<?php

namespace App\Actions\CreditCards;

use App\Models\CreditCard;
use Illuminate\Support\Facades\DB;

class UpdateCreditCardAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(CreditCard $card, array $data): CreditCard
    {
        return DB::transaction(function () use ($card, $data): CreditCard {
            $card->fill($data)->save();

            // El origen de pago es la cara visible de la tarjeta en el
            // formulario de gastos: si cambia el nombre, el banco o el color,
            // tiene que reflejarlo o el desplegable enseña datos viejos.
            $card->paymentSource?->update([
                'name' => StoreCreditCardAction::sourceName($card->only(['name', 'bank', 'last_four'])),
                'icon' => $card->icon,
                'color' => $card->color,
            ]);

            return $card;
        });
    }
}
