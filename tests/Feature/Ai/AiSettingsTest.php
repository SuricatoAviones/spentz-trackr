<?php

/*
 * Conexión del usuario con su proveedor de IA. Lo crítico aquí es la clave:
 * se guarda cifrada, nunca vuelve al navegador ni a un backup, y cambiar de
 * proveedor no arrastra una clave que ya no sirve.
 */

use App\Enums\AiProvider;
use App\Models\AiSetting;
use App\Models\User;
use App\Support\Features;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('guests cannot reach the ai settings', function () {
    auth()->logout();

    $this->get(route('ai.settings.edit'))->assertRedirect(route('login'));
});

test('the settings page lists providers and never ships the key', function () {
    AiSetting::factory()->for($this->user)->create(['api_key' => 'sk-secret-value-1234']);

    $response = $this->get(route('ai.settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/settings')
            ->where('setting.provider', 'openai')
            ->where('setting.has_key', true)
            ->where('setting.masked_key', '••••1234')
            ->missing('setting.api_key')
            ->has('providers', count(AiProvider::cases()))
            ->where('customEndpointsAllowed', false));

    expect($response->getContent())->not->toContain('sk-secret-value-1234');
});

test('saving a provider stores the key encrypted', function () {
    $this->put(route('ai.settings.update'), [
        'provider' => 'anthropic',
        'model' => 'claude-sonnet-5-5',
        'api_key' => 'sk-ant-plain-key-123456',
    ])->assertRedirect();

    $setting = $this->user->fresh()->aiSetting;

    expect($setting->provider)->toBe(AiProvider::Anthropic)
        ->and($setting->model)->toBe('claude-sonnet-5-5')
        ->and($setting->api_key)->toBe('sk-ant-plain-key-123456');

    $raw = DB::table('ai_settings')->where('user_id', $this->user->id)->value('api_key');

    expect($raw)->not->toContain('sk-ant-plain-key');
});

test('an empty key keeps the stored one for the same provider', function () {
    AiSetting::factory()->for($this->user)->create(['api_key' => 'sk-original-key-999', 'model' => 'gpt-4.1-mini']);

    $this->put(route('ai.settings.update'), ['provider' => 'openai', 'model' => 'gpt-5-mini', 'api_key' => ''])
        ->assertSessionHasNoErrors();

    $setting = $this->user->fresh()->aiSetting;

    expect($setting->api_key)->toBe('sk-original-key-999')
        ->and($setting->model)->toBe('gpt-5-mini');
});

test('switching provider requires a new key', function () {
    AiSetting::factory()->for($this->user)->create();

    $this->put(route('ai.settings.update'), ['provider' => 'gemini', 'api_key' => ''])
        ->assertSessionHasErrors('api_key');

    expect($this->user->fresh()->aiSetting->provider)->toBe(AiProvider::OpenAi);
});

test('custom endpoints are refused unless the operator allows them', function () {
    $this->put(route('ai.settings.update'), [
        'provider' => 'ollama',
        'base_url' => 'http://localhost:11434/v1',
        'model' => 'llama3.2-vision',
    ])->assertSessionHasErrors('provider');

    expect($this->user->fresh()->aiSetting)->toBeNull();
});

test('custom endpoints work when the operator allows them', function () {
    config(['ai.allow_custom_endpoints' => true]);

    $this->put(route('ai.settings.update'), [
        'provider' => 'ollama',
        'base_url' => 'http://192.168.1.20:11434/v1/',
        'model' => 'qwen2.5vl:7b',
    ])->assertSessionHasNoErrors();

    $setting = $this->user->fresh()->aiSetting;

    expect($setting->base_url)->toBe('http://192.168.1.20:11434/v1')
        ->and($setting->isReady())->toBeTrue();
});

test('model names and keys are validated', function (array $payload, string $field) {
    $this->put(route('ai.settings.update'), ['provider' => 'openai', 'api_key' => 'sk-valid-key-123', ...$payload])
        ->assertSessionHasErrors($field);
})->with([
    'model with spaces' => [['model' => 'gpt 4'], 'model'],
    'key with spaces' => [['api_key' => 'sk key with spaces'], 'api_key'],
    'unknown provider' => [['provider' => 'skynet'], 'provider'],
]);

test('disconnecting deletes the key', function () {
    AiSetting::factory()->for($this->user)->create();

    $this->delete(route('ai.settings.destroy'))->assertRedirect();

    expect(AiSetting::query()->count())->toBe(0);
});

test('testing the connection records the verification', function () {
    Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'OK']]]])]);
    AiSetting::factory()->for($this->user)->create();

    $this->postJson(route('ai.settings.test'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.has_key', true)
        ->assertJsonMissingPath('data.api_key');

    expect($this->user->fresh()->aiSetting->last_verified_at)->not->toBeNull();
});

test('a rejected key is explained to the user', function () {
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'bad key']], 401)]);
    AiSetting::factory()->for($this->user)->create();

    $this->postJson(route('ai.settings.test'))
        ->assertStatus(422)
        ->assertJsonPath('reason', 'invalid_key');

    expect($this->user->fresh()->aiSetting->last_verified_at)->toBeNull();
});

test('changing the key clears the previous verification', function () {
    AiSetting::factory()->for($this->user)->create(['last_verified_at' => now()]);

    $this->put(route('ai.settings.update'), ['provider' => 'openai', 'api_key' => 'sk-brand-new-key-1']);

    expect($this->user->fresh()->aiSetting->last_verified_at)->toBeNull();
});

test('turning the ai off hides its pages and answers 503 to json clients', function () {
    Features::setAiEnabled(false);
    AiSetting::factory()->for($this->user)->create();

    $this->get(route('ai.settings.edit'))->assertNotFound();
    $this->get(route('ai.assistant'))->assertNotFound();
    $this->postJson(route('ai.assistant.ask'), ['messages' => [['role' => 'user', 'content' => 'hola']]])
        ->assertStatus(503)
        ->assertJsonPath('reason', 'disabled');

    // Apagar no borra nada: la clave sigue ahí para cuando se encienda.
    expect(AiSetting::query()->count())->toBe(1);
});

test('the ai switch is shared with the frontend', function () {
    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('features.ai', true));
});

test('the backup carries ai settings but never the api key', function () {
    $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    AiSetting::factory()->for($this->user)->create(['api_key' => 'sk-should-not-leak-77']);

    $content = $this->actingAs($admin)->post(route('admin.system.backup'))->streamedContent();
    $payload = json_decode($content, true);

    expect($payload)->toHaveKey('ai_settings')
        ->and($payload['ai_settings'][0])->not->toHaveKey('api_key')
        ->and($payload['ai_settings'][0]['provider'])->toBe('openai')
        ->and($content)->not->toContain('sk-should-not-leak');
});

test('deleting the account removes the ai settings', function () {
    AiSetting::factory()->for($this->user)->create();

    $this->user->delete();

    expect(AiSetting::query()->count())->toBe(0);
});

test('opencode is saved with one of its fixed plans', function () {
    $this->put(route('ai.settings.update'), [
        'provider' => 'opencode',
        'api_key' => 'oc-console-key-12345',
        'model' => 'kimi-k2.6',
        'base_url' => 'https://opencode.ai/inference/go/openai/v1',
    ])->assertSessionHasNoErrors();

    $setting = $this->user->fresh()->aiSetting;

    expect($setting->provider)->toBe(AiProvider::OpenCode)
        ->and($setting->resolvedBaseUrl())->toBe('https://opencode.ai/inference/go/openai/v1')
        ->and($setting->isReady())->toBeTrue();
});

test('opencode refuses any url outside its plans', function () {
    $this->put(route('ai.settings.update'), [
        'provider' => 'opencode',
        'api_key' => 'oc-console-key-12345',
        'base_url' => 'http://10.0.0.5/v1',
    ])->assertSessionHasErrors('base_url');

    expect($this->user->fresh()->aiSetting)->toBeNull();
});

test('opencode is available without enabling custom endpoints', function () {
    $this->get(route('ai.settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('providers', fn ($providers) => collect($providers)->firstWhere('value', 'opencode')['available'] === true
                && count(collect($providers)->firstWhere('value', 'opencode')['endpoint_options']) === 2));
});
