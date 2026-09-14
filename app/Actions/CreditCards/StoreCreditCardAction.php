<?php

namespace App\Actions\CreditCards;

use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Crea la tarjeta y, con ella, el origen de pago que la representa en los
 * gastos. Así el formulario de gasto no cambia y los consumos de la tarjeta son
 * gastos normales: sin doble registro ni una tabla de movimientos paralela.
 */
class StoreCreditCardAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): CreditCard
    {
        return DB::transaction(function () use ($user, $data): CreditCard {
            // Vía la relación: `user_id` no está en el $fillable de
            // PaymentSource (a propósito) y un create() suelto lo descartaría.
            $source = $user->paymentSources()->create([
                'name' => self::sourceName($data),
                'icon' => $data['icon'] ?? 'credit-card',
                'color' => $data['color'] ?? '#8B5CF6',
                'is_system' => false,
            ]);

            $card = new CreditCard($data);
            $card->user_id = $user->id;
            $card->payment_source_id = $source->id;
            $card->save();

            return $card;
        });
    }

    /**
     * El origen lleva banco y últimos cuatro dígitos para que se distinga en el
     * desplegable de gastos cuando hay varias tarjetas del mismo banco.
     *
     * @param  array<string, mixed>  $data
     */
    public static function sourceName(array $data): string
    {
        $name = trim((string) ($data['name'] ?? ''));
        $bank = trim((string) ($data['bank'] ?? ''));
        $lastFour = trim((string) ($data['last_four'] ?? ''));

        $label = $name !== '' && $bank !== '' && ! str_contains(mb_strtolower($name), mb_strtolower($bank))
            ? "{$bank} {$name}"
            : ($name !== '' ? $name : $bank);

        return $lastFour !== '' ? "{$label} ·{$lastFour}" : $label;
    }
}
