<?php

namespace App\Actions\CreditCards;

use App\Models\CreditCard;
use Illuminate\Support\Facades\DB;

class DeleteCreditCardAction
{
    public function handle(CreditCard $card): void
    {
        DB::transaction(function () use ($card): void {
            $source = $card->paymentSource;

            // Cortes y abonos caen por cascada.
            $card->delete();

            /*
             * El origen solo se borra si nunca se usó. Si tiene gastos, se
             * queda: son movimientos reales del historial y borrarlos —o
             * dejarlos sin origen— falsearía informes ya cerrados. La tarjeta
             * desaparece; el rastro de lo que se compró con ella, no.
             */
            if ($source !== null && $source->expenses()->doesntExist()) {
                $source->delete();
            }
        });
    }
}
