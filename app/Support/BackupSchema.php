<?php

namespace App\Support;

/**
 * Qué tablas entran en una copia y en qué orden.
 *
 * Fuente única para exportar y restaurar: cuando eran dos listas separadas se
 * desincronizaban en silencio —el backup llegó a omitir ingresos, metas y
 * tarjetas— y un fichero que se llama "backup" pero pierde datos es peor que no
 * tener ninguno.
 *
 * El orden es el de las claves foráneas: se inserta de arriba abajo y se vacía
 * de abajo arriba.
 */
final class BackupSchema
{
    /** Versión del formato. Súbela si cambia la forma del fichero. */
    public const VERSION = 1;

    /**
     * Clave en el JSON => tabla.
     *
     * @return array<string, string>
     */
    public static function tables(): array
    {
        return [
            'users' => 'users',

            'categories' => 'categories',
            'payment_sources' => 'payment_sources',
            'exchange_rates' => 'exchange_rates',

            'expenses' => 'expenses',
            'expense_items' => 'expense_items',
            'receipts' => 'expense_receipts',

            'incomes' => 'incomes',
            'income_receipts' => 'income_receipts',

            'savings_goals' => 'savings_goals',
            'savings_contributions' => 'savings_contributions',

            'recurring_payments' => 'recurring_payments',

            'credit_cards' => 'credit_cards',
            'credit_card_statements' => 'credit_card_statements',
            'credit_card_payments' => 'credit_card_payments',
        ];
    }

    /**
     * Columnas que NUNCA salen en una copia.
     *
     * Un fichero de backup se descarga, se copia y se manda por correo. Los
     * hashes de contraseña y los secretos 2FA no deben viajar en él. La
     * contrapartida está documentada: al restaurar, los usuarios tienen que
     * recuperar su contraseña.
     *
     * @return list<string>
     */
    public static function secretColumns(): array
    {
        return [
            'password',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'remember_token',
        ];
    }
}
