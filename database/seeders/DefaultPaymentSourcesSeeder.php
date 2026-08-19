<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DefaultPaymentSourcesSeeder extends Seeder
{
    /**
     * Default payment sources created for each new user.
     *
     * @var array<int, array{name: string, icon: string, color: string}>
     */
    public const DEFAULT_SOURCES = [
        ['name' => 'Efectivo', 'icon' => 'banknote', 'color' => '#F59E0B'],
        ['name' => 'Zelle', 'icon' => 'zap', 'color' => '#8B5CF6'],
        ['name' => 'PayPal', 'icon' => 'wallet', 'color' => '#3B82F6'],
        ['name' => 'Binance', 'icon' => 'bitcoin', 'color' => '#EC4899'],
        ['name' => 'Pago Móvil', 'icon' => 'smartphone', 'color' => '#14B8A6'],
        ['name' => 'Banco de Venezuela', 'icon' => 'landmark', 'color' => '#10B981'],
        ['name' => 'Banesco', 'icon' => 'landmark', 'color' => '#EF4444'],
        ['name' => 'Mercantil', 'icon' => 'landmark', 'color' => '#06B6D4'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::query()
            ->whereDoesntHave('paymentSources')
            ->get();

        foreach ($users as $user) {
            foreach (self::DEFAULT_SOURCES as $source) {
                $user->paymentSources()->create([...$source, 'is_system' => true]);
            }
        }
    }
}
