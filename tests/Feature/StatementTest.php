<?php

/*
 * Estado de cuenta. Es la fuente de cifras del asistente de IA, así que tiene
 * que ser exacto: congelado USD (nunca la tasa de hoy), solo el usuario y el
 * periodo pedidos, y un neto acumulado que cuadre.
 */

use App\Enums\Currency;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PaymentSource;
use App\Models\User;
use App\Services\AccountStatementService;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create(['monthly_budget' => 200, 'tracking_type' => 'both']);
    $this->food = Category::factory()->for($this->user)->create(['name' => 'Comida']);
    $this->salary = Category::factory()->income()->for($this->user)->create(['name' => 'Sueldo']);
    $this->cash = PaymentSource::factory()->for($this->user)->create(['name' => 'Efectivo']);
    $this->actingAs($this->user);
});

function statementExpense(User $user, Category $category, PaymentSource $source, string $date, float $usd, array $extra = []): Expense
{
    return Expense::factory()->for($user)->for($category)->for($source, 'paymentSource')->create([
        'currency' => Currency::Usd, 'amount' => $usd, 'usd_amount' => $usd, 'usdt_amount' => $usd, 'spent_at' => $date, ...$extra,
    ]);
}

test('the statement totals, breakdowns and running net are exact', function () {
    statementExpense($this->user, $this->food, $this->cash, '2026-10-03', 30);
    statementExpense($this->user, $this->food, $this->cash, '2026-10-31', 20);
    // Bs con comisión: el equivalente congelado manda, no la tasa de hoy.
    statementExpense($this->user, $this->food, $this->cash, '2026-10-10', 10, [
        'currency' => Currency::Ves, 'amount' => 360, 'commission' => 14, 'exchange_rate' => 37.4,
    ]);
    Income::factory()->for($this->user)->for($this->salary)->create([
        'currency' => Currency::Usd, 'amount' => 500, 'usd_amount' => 500, 'usdt_amount' => 500, 'received_at' => '2026-10-03',
    ]);

    // Fuera del periodo y de otro usuario: no cuentan.
    statementExpense($this->user, $this->food, $this->cash, '2026-11-01', 99);
    Expense::factory()->create(['usd_amount' => 777, 'spent_at' => '2026-10-15']);

    $statement = app(AccountStatementService::class)->build($this->user, Date::parse('2026-10-01'), Date::parse('2026-10-31'));

    expect($statement['totals']['expenses'])->toMatchArray(['usd' => 60.0, 'count' => 3, 'commissions_ves' => 14.0])
        ->and($statement['totals']['incomes']['usd'])->toBe(500.0)
        ->and($statement['totals']['net']['usd'])->toBe(440.0)
        ->and($statement['totals']['savings_rate'])->toBe(88.0)
        ->and($statement['by_currency']['expenses']['ves'])->toBe(374.0)
        ->and($statement['expense_categories'][0])->toMatchArray(['name' => 'Comida', 'total_usd' => 60.0, 'count' => 3, 'percent' => 100.0])
        ->and($statement['sources'][0]['name'])->toBe('Efectivo')
        ->and($statement['largest_expenses'][0]['usd_amount'])->toBe(30.0)
        ->and($statement['budget'])->toMatchArray(['monthly' => 200.0, 'spent' => 60.0, 'percent' => 30.0]);

    $movements = $statement['movements'];

    // Mismo día: el ingreso va antes que el gasto, como en un extracto.
    expect(array_column($movements, 'type'))->toBe(['income', 'expense', 'expense', 'expense'])
        ->and(array_column($movements, 'running_usd'))->toBe([500.0, 470.0, 460.0, 440.0])
        ->and($movements[2]['amount'])->toBe(374.0);
});

test('the previous period of equal length is compared', function () {
    statementExpense($this->user, $this->food, $this->cash, '2026-09-20', 40);
    statementExpense($this->user, $this->food, $this->cash, '2026-10-05', 60);

    $statement = app(AccountStatementService::class)->build($this->user, Date::parse('2026-10-01'), Date::parse('2026-10-30'));

    expect($statement['previous'])->toMatchArray([
        'from' => '2026-09-01',
        'to' => '2026-09-30',
        'expenses_usd' => 40.0,
        'expenses_variation' => 50.0,
        'incomes_variation' => null,
    ]);
});

test('the statement follows the tracking mode', function () {
    $this->user->update(['tracking_type' => 'expenses']);
    Income::factory()->for($this->user)->for($this->salary)->create(['usd_amount' => 100, 'received_at' => now()]);

    $statement = app(AccountStatementService::class)->build($this->user, Date::today()->startOfMonth(), Date::today());

    expect($statement['totals']['incomes']['count'])->toBe(0)
        ->and($statement['tracking']['incomes'])->toBeFalse();
});

test('the statement page renders the current month by default', function () {
    statementExpense($this->user, $this->food, $this->cash, now()->toDateString(), 15);

    $this->get(route('reports.statement'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/statement')
            ->where('statement.period.from', now()->startOfMonth()->toDateString())
            ->where('statement.period.to', now()->toDateString())
            ->where('statement.totals.expenses.usd', 15)
            ->where('aiAvailable', false));
});

test('the statement period is validated', function (array $query) {
    $this->get(route('reports.statement', $query))->assertSessionHasErrors('to');
})->with([
    'reversed' => [['from' => '2026-10-10', 'to' => '2026-10-01']],
    'too long' => [['from' => '2024-01-01', 'to' => '2026-01-01']],
]);

test('the statement exports as csv with the running net', function () {
    statementExpense($this->user, $this->food, $this->cash, '2026-10-02', 25, ['description' => '=HYPERLINK("x")']);

    $response = $this->get(route('reports.statement.export', ['from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();

    expect($csv)->toContain('neto_acumulado_usd')
        ->toContain("'=HYPERLINK")
        ->toContain('-25,00');
});

test('the api exposes the same statement', function () {
    statementExpense($this->user, $this->food, $this->cash, '2026-10-02', 25);
    $token = $this->user->createToken('test', ['*'], now()->addDay())->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.v1.reports.statement', ['from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertOk()
        ->assertJsonPath('data.totals.expenses.usd', 25)
        ->assertJsonPath('data.movements.0.running_usd', -25);
});
