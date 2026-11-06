<?php

/*
 * Importar movimientos desde un estado de cuenta. Dos invariantes por encima
 * de todo: cada movimiento en Bs congela la tasa DE SU DÍA (no la de hoy), y lo
 * que ya está registrado sale marcado como duplicado para no contarlo dos
 * veces. La IA solo lee; crear es una confirmación aparte, todo o nada.
 */

use App\Enums\Currency;
use App\Models\AiSetting;
use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PaymentSource;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->user = User::factory()->create(['tracking_type' => 'both']);
    $this->food = Category::factory()->for($this->user)->create(['name' => 'Comida']);
    $this->salary = Category::factory()->income()->for($this->user)->create(['name' => 'Sueldo']);
    $this->banesco = PaymentSource::factory()->for($this->user)->create(['name' => 'Banesco']);
    $this->cash = PaymentSource::factory()->for($this->user)->create(['name' => 'Efectivo']);
    AiSetting::factory()->for($this->user)->create();
    $this->actingAs($this->user);

    $this->day1 = now()->subDays(10)->toDateString();
    $this->day2 = now()->subDays(5)->toDateString();

    ExchangeRate::factory()->create(['provider' => 'bcv', 'rate' => 36.5, 'rate_date' => $this->day1]);
    ExchangeRate::factory()->create(['provider' => 'bcv', 'rate' => 38.25, 'rate_date' => $this->day2]);
    ExchangeRate::factory()->create(['provider' => 'bcv', 'rate' => 41.0, 'rate_date' => now()->toDateString()]);
});

function fakeStatementReply(array $payload): void
{
    Http::fake(['api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => json_encode($payload)]]],
    ])]);
}

function statementTransaction(array $overrides = []): array
{
    return [
        'date' => now()->subDays(10)->toDateString(),
        'description' => 'Compra POS Farmatodo',
        'reference' => '000123',
        'amount' => 365,
        'direction' => 'debit',
        'currency' => 'ves',
        'category_id' => null,
        'payment_method' => null,
        'exchange_rate' => null,
        ...$overrides,
    ];
}

test('the import page lists categories with their type', function () {
    $this->get(route('ai.import.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/import')
            ->where('capabilities.available', true)
            ->where('limits.max_files', 5)
            ->where('categories', fn ($categories) => collect($categories)->pluck('type', 'name')->all() === ['Comida' => 'expense', 'Sueldo' => 'income']));
});

test('a statement becomes draft rows with the rate of each day', function () {
    fakeStatementReply([
        'is_statement' => true,
        'bank' => 'BANESCO BANCO UNIVERSAL',
        'currency' => 'ves',
        'payment_source_id' => null,
        'transactions' => [
            statementTransaction(['category_id' => $this->food->id, 'payment_method' => 'pago_movil', 'amount' => '1.234,50']),
            statementTransaction(['date' => $this->day2, 'description' => 'Nómina', 'direction' => 'credit', 'amount' => 3825, 'category_id' => $this->salary->id]),
            // Una categoría de ingreso en un gasto no vale: se descarta.
            statementTransaction(['date' => $this->day2, 'category_id' => $this->salary->id]),
            // Sin monto o sin dirección, la fila se ignora.
            statementTransaction(['amount' => null]),
            statementTransaction(['direction' => 'saldo']),
        ],
    ]);

    $response = $this->post(route('ai.import.scan'), [
        'files' => [UploadedFile::fake()->image('mov1.png'), UploadedFile::fake()->image('mov2.png')],
    ], ['Accept' => 'application/json'])->assertOk();

    $response->assertJsonPath('data.bank', 'BANESCO BANCO UNIVERSAL')
        // El banco del extracto se empareja con el origen "Banesco".
        ->assertJsonPath('data.payment_source_id', $this->banesco->id)
        ->assertJsonCount(3, 'data.rows')
        ->assertJsonPath('data.rows.0.type', 'expense')
        ->assertJsonPath('data.rows.0.amount', '1234.50')
        ->assertJsonPath('data.rows.0.payment_method', 'pago_movil')
        ->assertJsonPath('data.rows.0.category_id', $this->food->id)
        ->assertJsonPath('data.rows.0.exchange_rate', 36.5)
        ->assertJsonPath('data.rows.0.rate_provider', 'bcv')
        ->assertJsonPath('data.rows.0.selected', true)
        ->assertJsonPath('data.rows.1.type', 'income')
        ->assertJsonPath('data.rows.1.exchange_rate', 38.25)
        ->assertJsonPath('data.rows.1.payment_method', null)
        ->assertJsonPath('data.rows.2.category_id', null);

    expect($response->json('data.rows.0.note'))->toContain('Ref. 000123')->toContain('BANESCO');

    // Las dos capturas viajan en la misma petición, con el timeout largo.
    Http::assertSent(function (Request $request) {
        $parts = $request['messages'][1]['content'];

        return collect($parts)->where('type', 'image_url')->count() === 2
            && str_contains($request['messages'][0]['content'], "- {$this->salary->id}: Sueldo");
    });

    expect(Expense::query()->count())->toBe(0);
});

test('already recorded movements are flagged as duplicates once each', function () {
    Expense::factory()->for($this->user)->for($this->food)->for($this->banesco, 'paymentSource')->create([
        'currency' => Currency::Ves, 'amount' => 351, 'commission' => 14, 'spent_at' => $this->day1,
    ]);

    fakeStatementReply(['transactions' => [
        // El banco debitó base + comisión: igual es el mismo gasto.
        statementTransaction(['amount' => 365]),
        // Mismo monto y día otra vez: solo hay UN registro que lo explique.
        statementTransaction(['amount' => 365]),
    ]]);

    $this->postJson(route('ai.import.scan'), ['files' => [UploadedFile::fake()->create('extracto.pdf', 50, 'application/pdf')]])
        ->assertOk()
        ->assertJsonPath('data.rows.0.duplicate_of', Expense::query()->value('id'))
        ->assertJsonPath('data.rows.0.selected', false)
        ->assertJsonPath('data.rows.1.duplicate_of', null)
        ->assertJsonPath('data.rows.1.selected', true);
});

test('rows the user cannot import start unselected and explain why', function () {
    $this->user->update(['tracking_type' => 'expenses']);

    fakeStatementReply(['currency' => null, 'transactions' => [
        statementTransaction(['direction' => 'credit']),
        statementTransaction(['date' => now()->addDay()->toDateString()]),
        statementTransaction(['currency' => null]),
    ]]);

    $response = $this->actingAs($this->user->fresh())
        ->postJson(route('ai.import.scan'), ['files' => [UploadedFile::fake()->image('a.jpg')]])
        ->assertOk();

    expect($response->json('data.rows.0.warnings'))->toContain('type_not_tracked')
        ->and($response->json('data.rows.0.selected'))->toBeFalse()
        ->and($response->json('data.rows.1.warnings'))->toContain('invalid_date')
        ->and($response->json('data.rows.1.date'))->toBeNull()
        ->and($response->json('data.rows.2.warnings'))->toContain('no_currency')
        ->and($response->json('data.rows.2.selected'))->toBeFalse();
});

test('a document that is not a statement says so', function () {
    fakeStatementReply(['is_statement' => false, 'transactions' => []]);

    $this->postJson(route('ai.import.scan'), ['files' => [UploadedFile::fake()->image('gato.jpg')]])
        ->assertOk()
        ->assertJsonPath('data.warnings', ['not_statement', 'no_rows']);
});

test('the upload is bounded', function () {
    $this->postJson(route('ai.import.scan'), [
        'files' => array_map(fn (int $i) => UploadedFile::fake()->image("{$i}.jpg"), range(1, 6)),
    ])->assertStatus(422)->assertJsonValidationErrors('files');

    Http::assertNothingSent();
});

test('confirming creates expenses and incomes frozen at each day rate', function () {
    $this->post(route('ai.import.store'), [
        'payment_source_id' => $this->banesco->id,
        'rows' => [
            // Sin tasa: se usa la del día del movimiento (36,5), no la de hoy (41).
            ['type' => 'expense', 'date' => $this->day1, 'description' => 'Farmatodo', 'amount' => 365, 'currency' => 'ves', 'category_id' => $this->food->id, 'payment_method' => 'pago_movil', 'note' => 'Ref. 1'],
            ['type' => 'expense', 'date' => $this->day2, 'description' => 'Netflix', 'amount' => 9.99, 'currency' => 'usd', 'category_id' => $this->food->id, 'payment_method' => 'pago_movil'],
            ['type' => 'income', 'date' => $this->day2, 'description' => 'Nómina', 'amount' => 3825, 'currency' => 'ves', 'category_id' => $this->salary->id, 'exchange_rate' => 38.25, 'rate_provider' => 'bcv'],
        ],
    ])->assertRedirect(route('expenses.index'));

    $bs = Expense::query()->where('description', 'Farmatodo')->first();
    $usd = Expense::query()->where('description', 'Netflix')->first();
    $income = Income::query()->first();

    expect((float) $bs->exchange_rate)->toBe(36.5)
        ->and((float) $bs->usd_amount)->toBe(10.0)
        ->and($bs->payment_source_id)->toBe($this->banesco->id)
        ->and($bs->note)->toBe('Ref. 1')
        ->and($bs->spent_at->toDateString())->toBe($this->day1)
        // El método de pago solo aplica a Bs.
        ->and($usd->payment_method)->toBeNull()
        ->and((float) $income->usd_amount)->toBe(100.0)
        ->and($income->received_at->toDateString())->toBe($this->day2);
});

test('an import is all or nothing', function () {
    $noRateDay = now()->subYears(3)->toDateString();

    $this->post(route('ai.import.store'), [
        'payment_source_id' => $this->banesco->id,
        'rows' => [
            ['type' => 'expense', 'date' => $this->day1, 'description' => 'Bien', 'amount' => 10, 'currency' => 'usd', 'category_id' => $this->food->id],
            ['type' => 'expense', 'date' => $noRateDay, 'description' => 'Sin tasa', 'amount' => 100, 'currency' => 'ves', 'category_id' => $this->food->id],
        ],
    ])->assertSessionHasErrors('rows.1.exchange_rate');

    expect(Expense::query()->count())->toBe(0);
});

test('each row must match its category type and the tracking mode', function () {
    $this->post(route('ai.import.store'), [
        'payment_source_id' => $this->banesco->id,
        'rows' => [
            ['type' => 'expense', 'date' => $this->day1, 'description' => 'X', 'amount' => 1, 'currency' => 'usd', 'category_id' => $this->salary->id],
        ],
    ])->assertSessionHasErrors('rows.0.category_id');

    $this->user->update(['tracking_type' => 'expenses']);

    $this->actingAs($this->user->fresh())->post(route('ai.import.store'), [
        'rows' => [
            ['type' => 'income', 'date' => $this->day1, 'description' => 'Y', 'amount' => 1, 'currency' => 'usd', 'category_id' => $this->salary->id],
        ],
    ])->assertSessionHasErrors('rows.0.type');

    expect(Expense::query()->count() + Income::query()->count())->toBe(0);
});

test('expenses need a payment source of the user', function () {
    $foreign = PaymentSource::factory()->create();

    $row = ['type' => 'expense', 'date' => $this->day1, 'description' => 'X', 'amount' => 1, 'currency' => 'usd', 'category_id' => $this->food->id];

    $this->post(route('ai.import.store'), ['rows' => [$row]])->assertSessionHasErrors('payment_source_id');
    $this->post(route('ai.import.store'), ['payment_source_id' => $foreign->id, 'rows' => [$row]])->assertSessionHasErrors('payment_source_id');

    expect(Expense::query()->count())->toBe(0);
});

test('the api reads and imports statements with the same rules', function () {
    $token = $this->user->createToken('test', ['*'], now()->addDay())->plainTextToken;
    fakeStatementReply(['transactions' => [statementTransaction(['category_id' => $this->food->id])]]);

    $draft = $this->withToken($token)
        ->postJson(route('api.v1.ai.statements.scan'), ['files' => [UploadedFile::fake()->image('a.jpg')]])
        ->assertOk()
        ->json('data');

    $row = $draft['rows'][0];

    $this->withToken($token)
        ->postJson(route('api.v1.ai.statements.import'), [
            'payment_source_id' => $this->cash->id,
            'rows' => [[
                'type' => $row['type'], 'date' => $row['date'], 'description' => $row['description'],
                'amount' => $row['amount'], 'currency' => $row['currency'], 'category_id' => $row['category_id'],
                'exchange_rate' => $row['exchange_rate'], 'rate_provider' => $row['rate_provider'],
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.expenses', 1)
        ->assertJsonPath('data.incomes', 0);

    expect((float) Expense::query()->value('exchange_rate'))->toBe(36.5);
});
