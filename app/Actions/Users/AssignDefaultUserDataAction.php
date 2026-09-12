<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Give a user the starter set of categories and payment sources.
 *
 * Every user-creation path goes through here — Fortify registration and
 * `admin:create` — so no account lands on an empty expense form. It is
 * idempotent: each set is only created when the user has none, so re-running
 * `admin:create` (which upserts on every run) never duplicates them, and the
 * retroactive seeders can reuse it for users created before this existed.
 */
class AssignDefaultUserDataAction
{
    /**
     * @var array<int, array{name: string, icon: string, color: string}>
     */
    public const DEFAULT_CATEGORIES = [
        ['name' => 'Alimentación', 'icon' => 'shopping-cart', 'color' => '#10B981'],
        ['name' => 'Transporte', 'icon' => 'car', 'color' => '#3B82F6'],
        ['name' => 'Servicios', 'icon' => 'zap', 'color' => '#F59E0B'],
        ['name' => 'Salud', 'icon' => 'heart-pulse', 'color' => '#EF4444'],
        ['name' => 'Ocio', 'icon' => 'gamepad-2', 'color' => '#8B5CF6'],
        ['name' => 'Ropa', 'icon' => 'shirt', 'color' => '#EC4899'],
        ['name' => 'Educación', 'icon' => 'graduation-cap', 'color' => '#06B6D4'],
        ['name' => 'Otros', 'icon' => 'tag', 'color' => '#6B7280'],
    ];

    /**
     * @var array<int, array{name: string, icon: string, color: string}>
     */
    public const DEFAULT_PAYMENT_SOURCES = [
        ['name' => 'Efectivo', 'icon' => 'banknote', 'color' => '#F59E0B'],
        ['name' => 'Zelle', 'icon' => 'zap', 'color' => '#8B5CF6'],
        ['name' => 'PayPal', 'icon' => 'wallet', 'color' => '#3B82F6'],
        ['name' => 'Binance', 'icon' => 'bitcoin', 'color' => '#EC4899'],
        ['name' => 'Pago Móvil', 'icon' => 'smartphone', 'color' => '#14B8A6'],
        ['name' => 'Banco de Venezuela', 'icon' => 'landmark', 'color' => '#10B981'],
        ['name' => 'Banesco', 'icon' => 'landmark', 'color' => '#EF4444'],
        ['name' => 'Mercantil', 'icon' => 'landmark', 'color' => '#06B6D4'],
    ];

    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            if (! $user->categories()->exists()) {
                foreach (self::DEFAULT_CATEGORIES as $category) {
                    $user->categories()->create([...$category, 'is_system' => true]);
                }
            }

            if (! $user->paymentSources()->exists()) {
                foreach (self::DEFAULT_PAYMENT_SOURCES as $source) {
                    $user->paymentSources()->create([...$source, 'is_system' => true]);
                }
            }
        });
    }
}
