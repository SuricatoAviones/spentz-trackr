<?php

/*
 * Lectura de facturas con IA. El borrador NO crea el gasto: rellena el
 * formulario. Lo que se vigila es que nada de lo que invente el modelo (ids
 * ajenos, monedas inexistentes, fechas futuras) llegue al formulario.
 */

use App\Enums\AiProvider;
use App\Models\AiSetting;
use App\Models\Category;
use App\Models\Expense;
use App\Models\PaymentSource;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->user = User::factory()->create();
    $this->category = Category::factory()->for($this->user)->create(['name' => 'Alimentación']);
    $this->source = PaymentSource::factory()->for($this->user)->create(['name' => 'Banesco']);
    $this->actingAs($this->user);
});

function fakeOpenAiReply(string $content): void
{
    Http::fake(['api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => $content]]],
    ])]);
}

test('a receipt becomes a sanitized expense draft', function () {
    AiSetting::factory()->for($this->user)->create();

    $foreignCategory = Category::factory()->create();

    fakeOpenAiReply("Aquí está:\n```json\n".json_encode([
        'is_receipt' => true,
        'merchant' => 'Excelsior Gama',
        'description' => 'Mercado en Excelsior Gama',
        'total' => '1.234,56',
        'currency' => 'VES',
        'date' => now()->subDay()->toDateString(),
        'exchange_rate' => 36.5,
        'payment_method' => 'pago_movil',
        'category_id' => $this->category->id,
        'payment_source_id' => $this->source->id,
        'items' => [['description' => 'Harina PAN', 'amount' => 45.5], ['description' => '', 'amount' => 1]],
        'tax_id' => 'J-12345678-9',
        'invoice_number' => '000123',
        'confidence' => 0.92,
    ])."\n```");

    $this->post(route('ai.receipts.scan'), ['file' => UploadedFile::fake()->image('factura.jpg')], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.description', 'Mercado en Excelsior Gama')
        ->assertJsonPath('data.amount', '1234.56')
        ->assertJsonPath('data.currency', 'ves')
        ->assertJsonPath('data.payment_method', 'pago_movil')
        ->assertJsonPath('data.exchange_rate', 36.5)
        ->assertJsonPath('data.category_id', $this->category->id)
        ->assertJsonPath('data.payment_source_id', $this->source->id)
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.warnings', []);

    // Leer la factura no registra nada.
    expect(Expense::query()->count())->toBe(0);

    Http::assertSent(function (Request $request) {
        $body = json_decode($request->body(), true);

        return $body['response_format'] === ['type' => 'json_object']
            && str_contains($body['messages'][0]['content'], "- {$this->category->id}: Alimentación")
            && str_contains($body['messages'][0]['content'], "- {$this->source->id}: Banesco")
            && $body['messages'][1]['content'][0]['type'] === 'image_url';
    });

    $note = $this->post(route('ai.receipts.scan'), ['file' => UploadedFile::fake()->image('f.jpg')], ['Accept' => 'application/json'])
        ->json('data.note');

    expect($note)->toContain('Excelsior Gama')
        ->toContain('RIF J-12345678-9')
        ->toContain('Factura 000123')
        ->toContain('- Harina PAN: 45,50');

    expect($foreignCategory->id)->not->toBe($this->category->id);
});

test('ids from other users, invented currencies and future dates are dropped', function () {
    AiSetting::factory()->for($this->user)->create();

    $foreignCategory = Category::factory()->create();
    $foreignSource = PaymentSource::factory()->create();

    fakeOpenAiReply(json_encode([
        'is_receipt' => true,
        'description' => 'Algo',
        'total' => 20,
        'currency' => 'eur',
        'date' => now()->addDays(3)->toDateString(),
        'payment_method' => 'pago_movil',
        'category_id' => $foreignCategory->id,
        'payment_source_id' => $foreignSource->id,
        'confidence' => 0.3,
    ]));

    $this->postJson(route('ai.receipts.scan'), ['file' => UploadedFile::fake()->image('f.png')])
        ->assertOk()
        ->assertJsonPath('data.currency', null)
        ->assertJsonPath('data.category_id', null)
        ->assertJsonPath('data.payment_source_id', null)
        ->assertJsonPath('data.spent_at', null)
        // El método de pago solo tiene sentido en Bs.
        ->assertJsonPath('data.payment_method', null)
        ->assertJsonPath('data.warnings', ['date_future', 'low_confidence']);
});

test('a photo that is not a receipt is flagged', function () {
    AiSetting::factory()->for($this->user)->create();
    fakeOpenAiReply('{"is_receipt": false, "total": null, "confidence": 0.1}');

    $this->postJson(route('ai.receipts.scan'), ['file' => UploadedFile::fake()->image('gato.jpg')])
        ->assertOk()
        ->assertJsonPath('data.amount', null)
        ->assertJsonPath('data.warnings', ['not_receipt', 'no_total', 'low_confidence']);
});

test('pdf invoices are sent as documents to providers that read them', function () {
    AiSetting::factory()->for($this->user)->provider(AiProvider::Anthropic)->create();

    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => '{"is_receipt": true, "total": 12.5, "currency": "usd", "confidence": 0.9}']],
        'stop_reason' => 'end_turn',
    ])]);

    $this->postJson(route('ai.receipts.scan'), ['file' => UploadedFile::fake()->create('factura.pdf', 40, 'application/pdf')])
        ->assertOk()
        ->assertJsonPath('data.amount', '12.50')
        ->assertJsonPath('data.currency', 'usd');

    Http::assertSent(fn (Request $request) => $request['messages'][0]['content'][0]['type'] === 'document');
});

test('an unreadable answer is reported, not guessed', function () {
    AiSetting::factory()->for($this->user)->create();
    fakeOpenAiReply('Lo siento, no puedo leer esa imagen.');

    $this->postJson(route('ai.receipts.scan'), ['file' => UploadedFile::fake()->image('f.jpg')])
        ->assertStatus(502)
        ->assertJsonPath('reason', 'invalid_response');
});

test('only images and pdf files are accepted', function () {
    AiSetting::factory()->for($this->user)->create();

    $this->postJson(route('ai.receipts.scan'), ['file' => UploadedFile::fake()->create('virus.exe', 10)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');

    Http::assertNothingSent();
});

test('without a provider the user is sent to the settings', function () {
    $this->postJson(route('ai.receipts.scan'), ['file' => UploadedFile::fake()->image('f.jpg')])
        ->assertStatus(422)
        ->assertJsonPath('reason', 'not_configured');
});

test('the ai endpoints are rate limited per user', function () {
    config(['ai.rate_limit.per_minute' => 1]);
    AiSetting::factory()->for($this->user)->create();
    fakeOpenAiReply('{"is_receipt": true, "total": 1, "currency": "usd"}');

    $this->postJson(route('ai.receipts.scan'), ['file' => UploadedFile::fake()->image('a.jpg')])->assertOk();

    $this->postJson(route('ai.receipts.scan'), ['file' => UploadedFile::fake()->image('b.jpg')])
        ->assertStatus(429)
        ->assertJsonPath('reason', 'local_rate_limited');
});

test('the expense form knows whether it can scan receipts', function () {
    $this->get(route('expenses.create'))
        ->assertInertia(fn (Assert $page) => $page->where('aiScan.available', false));

    AiSetting::factory()->for($this->user)->provider(AiProvider::Gemini)->create();

    // En una petición real el usuario llega fresco; aquí es el mismo objeto.
    $this->actingAs($this->user->fresh())->get(route('expenses.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('aiScan.available', true)
            ->where('aiScan.pdf', true));
});

test('a provider without vision does not offer receipt scanning', function () {
    AiSetting::factory()->for($this->user)->provider(AiProvider::DeepSeek)->create();

    $this->get(route('expenses.create'))
        ->assertInertia(fn (Assert $page) => $page->where('aiScan.available', false));
});

test('files above 7 MB are refused before reaching the provider', function () {
    $this->postJson(route('ai.receipts.scan'), ['file' => UploadedFile::fake()->create('enorme.pdf', 7169, 'application/pdf')])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');

    $this->postJson(route('ai.import.scan'), ['files' => [UploadedFile::fake()->create('enorme.pdf', 7169, 'application/pdf')]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('files.0');

    Http::assertNothingSent();
});
