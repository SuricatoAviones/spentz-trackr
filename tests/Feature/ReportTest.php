<?php

use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PaymentSource;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->category = Category::factory()->for($this->user)->create(['name' => 'AlimentaciÃƒÂ³n']);
    $this->source = PaymentSource::factory()->for($this->user)->create(['name' => 'Binance']);
    $this->actingAs($this->user);
});

test('reports index shows the annual totals grouped by month and category', function () {
    Expense::factory()->for($this->user)->for($this->category)->for($this->source, 'paymentSource')
        ->on(now()->format('Y-m').'-05')
        ->create(['amount' => 100, 'usd_amount' => 100, 'usdt_amount' => 100]);
    Expense::factory()->for($this->user)->for($this->category)->for($this->source, 'paymentSource')
        ->on(now()->format('Y-m').'-20')
        ->ves(25)
        ->create(['amount' => 250, 'usd_amount' => 10, 'usdt_amount' => 10]);

    $this->get(route('reports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/index')
            ->where('year', now()->year)
            ->where('annual.usd', 110)
            ->where('annual.usdt', 110)
            ->where('months.'.(now()->month - 1).'.usd', 110)
            ->where('categories.0.name', 'AlimentaciÃƒÂ³n')
            ->where('categories.0.total', 110)
            ->where('sources.0.name', 'Binance')
            ->where('sources.0.total', 110)
        );
});

test('reports index can filter by a previous year', function () {
    $previousYear = now()->year - 1;

    Expense::factory()->for($this->user)->for($this->category)->for($this->source, 'paymentSource')
        ->on("{$previousYear}-06-15")
        ->create(['amount' => 75, 'usd_amount' => 75, 'usdt_amount' => 75]);

    $this->get(route('reports.index', ['year' => $previousYear]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/index')
            ->where('year', $previousYear)
            ->where('annual.usd', 75)
            ->where('months.5.usd', 75)
            ->has('years', 1)
        );
});

test('reports only include expenses from the same user', function () {
    $otherUser = User::factory()->create();
    $otherCategory = Category::factory()->for($otherUser)->create();
    $otherSource = PaymentSource::factory()->for($otherUser)->create();
    Expense::factory()->for($otherUser)->for($otherCategory)->for($otherSource, 'paymentSource')
        ->on(now()->format('Y-m').'-10')
        ->create(['amount' => 999, 'usd_amount' => 999, 'usdt_amount' => 999]);

    $this->get(route('reports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/index')
            ->where('annual.usd', 0)
            ->has('categories', 0)
            ->has('sources', 0)
        );
});

test('the CSV export streams the expenses with a UTF-8 BOM', function () {
    Expense::factory()->for($this->user)->for($this->category)->for($this->source, 'paymentSource')
        ->ves(28.5)
        ->on(now()->format('Y-m').'-05')
        ->create([
            'description' => 'Mercado',
            'amount' => 285,
            'usd_amount' => 10,
            'usdt_amount' => 10,
            'note' => 'Semanal',
        ]);

    $response = $this->get(route('reports.export'));

    $response->assertOk();
    $response->assertDownload('gastos_'.now()->format('Y-m').'.csv');

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->toContain('fecha,descripcion,categoria,origen,moneda,monto,tasa_bs_usd,tasa_fuente,equivalente_usd,equivalente_usdt,nota')
        ->toContain('Mercado')
        ->toContain('ves')
        ->toContain('28,5000')
        ->toContain('Semanal');
});

test('the CSV export respects search and currency filters', function () {
    Expense::factory()->for($this->user)->for($this->category)->for($this->source, 'paymentSource')
        ->create(['description' => 'Mercado']);
    Expense::factory()->for($this->user)->for($this->category)->for($this->source, 'paymentSource')
        ->usdt()
        ->create(['description' => 'Netflix']);

    $response = $this->get(route('reports.export', ['search' => 'netflix']));

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($content)->toContain('Netflix')
        ->not->toContain('Mercado');
});

test('reports expose income totals and net balance when incomes are shown', function () {
    $this->user->update(['tracking_type' => 'both']);
    $incomeCategory = Category::factory()->for($this->user)->income()->create(['name' => 'Salario']);

    Income::factory()->for($this->user)->for($incomeCategory)
        ->on(now()->format('Y-m').'-05')
        ->create(['amount' => 500, 'usd_amount' => 500, 'usdt_amount' => 500]);
    Expense::factory()->for($this->user)->for($this->category)->for($this->source, 'paymentSource')
        ->on(now()->format('Y-m').'-20')
        ->create(['amount' => 100, 'usd_amount' => 100, 'usdt_amount' => 100]);

    $this->get(route('reports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/index')
            ->where('showIncomes', true)
            ->where('annual.usd', 100)
            ->where('incomeAnnual.usd', 500)
            ->where('net.usd', 400)
            ->where('incomeMonths.'.(now()->month - 1).'.usd', 500)
            ->where('incomeCategories.0.name', 'Salario')
            ->where('incomeCategories.0.total', 500)
        );
});

test('reports hide income data when tracking type is expense only', function () {
    $this->get(route('reports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/index')
            ->where('showIncomes', false)
            ->where('incomeAnnual.usd', 0)
            ->where('incomeAnnual.usdt', 0)
            ->where('net.usd', 0)
            ->has('incomeCategories', 0)
        );
});
