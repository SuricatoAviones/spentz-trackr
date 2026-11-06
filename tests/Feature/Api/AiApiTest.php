<?php

/*
 * Asistente de IA por la API REST: mismos Actions y Form Requests que la web.
 */

use App\Models\AiSetting;
use App\Models\User;
use App\Support\Features;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test', ['*'], now()->addDay())->plainTextToken;
});

test('the api settings never expose the key', function () {
    AiSetting::factory()->for($this->user)->create(['api_key' => 'sk-api-secret-5678']);

    $response = $this->withToken($this->token)
        ->getJson(route('api.v1.ai.settings'))
        ->assertOk()
        ->assertJsonPath('data.setting.masked_key', '••••5678')
        ->assertJsonPath('data.setting.has_key', true)
        ->assertJsonMissingPath('data.setting.api_key');

    expect($response->getContent())->not->toContain('sk-api-secret');
});

test('the api saves settings with the shared validation', function () {
    $this->withToken($this->token)
        ->putJson(route('api.v1.ai.settings.update'), ['provider' => 'gemini'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('api_key');

    $this->withToken($this->token)
        ->putJson(route('api.v1.ai.settings.update'), ['provider' => 'gemini', 'api_key' => 'AIza-test-key-123'])
        ->assertOk()
        ->assertJsonPath('data.provider', 'gemini')
        ->assertJsonPath('data.resolved_model', 'gemini-2.5-flash');

    $this->withToken($this->token)
        ->deleteJson(route('api.v1.ai.settings.destroy'))
        ->assertOk();

    expect(AiSetting::query()->count())->toBe(0);
});

test('the api scans receipts into a draft', function () {
    AiSetting::factory()->for($this->user)->create();
    Http::fake(['api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => '{"is_receipt": true, "description": "Farmacia", "total": 8.4, "currency": "usd", "confidence": 0.95}']]],
    ])]);

    $this->withToken($this->token)
        ->postJson(route('api.v1.ai.receipts.scan'), ['file' => UploadedFile::fake()->image('ticket.jpg')])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.description', 'Farmacia')
        ->assertJsonPath('data.amount', '8.40');
});

test('the api chats with the assistant', function () {
    AiSetting::factory()->for($this->user)->create();
    Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'Hola']]]])]);

    $this->withToken($this->token)
        ->postJson(route('api.v1.ai.chat'), ['messages' => [['role' => 'user', 'content' => 'Hola']]])
        ->assertOk()
        ->assertJsonPath('data.reply', 'Hola');
});

test('provider failures surface with a reason and status', function () {
    AiSetting::factory()->for($this->user)->create();
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'quota exceeded']], 429)]);

    $this->withToken($this->token)
        ->postJson(route('api.v1.ai.chat'), ['messages' => [['role' => 'user', 'content' => 'Hola']]])
        ->assertStatus(429)
        ->assertJsonPath('success', false)
        ->assertJsonPath('reason', 'rate_limited');
});

test('the api ai endpoints answer 503 while the ai is off', function () {
    Features::setAiEnabled(false);

    $this->withToken($this->token)
        ->getJson(route('api.v1.ai.settings'))
        ->assertStatus(503)
        ->assertJsonPath('reason', 'disabled');
});
