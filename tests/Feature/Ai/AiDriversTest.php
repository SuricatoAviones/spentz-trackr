<?php

/*
 * Los tres protocolos de transporte de la IA. Ninguno de estos tests llama a
 * un proveedor real: se comprueba la forma exacta de la petición (que es lo
 * que el proveedor rechaza con un 400 si cambia) y cómo se interpreta la
 * respuesta, incluidos los errores.
 */

use App\Enums\AiProvider;
use App\Models\AiSetting;
use App\Models\User;
use App\Services\Ai\AiAttachment;
use App\Services\Ai\AiException;
use App\Services\Ai\AiManager;
use App\Services\Ai\AiMessage;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiTool;
use App\Services\Ai\AiToolCall;
use App\Services\Ai\Drivers\AiDriver;
use App\Services\Ai\Drivers\OpenAiDriver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->user = User::factory()->create();
});

function driverFor(User $user, AiProvider $provider, ?string $model = null): AiDriver
{
    $setting = AiSetting::factory()->for($user)->provider($provider, $model)->create();

    return app(AiManager::class)->driverFor($setting);
}

function sampleTools(): array
{
    return [
        new AiTool('get_budget_status', 'Budget for the month.'),
        new AiTool('list_transactions', 'List rows.', ['from' => ['type' => 'string']], ['from']),
    ];
}

test('the openai driver sends chat completions with tools and images', function () {
    Http::fake(['api.openai.com/*' => Http::response([
        'choices' => [['message' => ['role' => 'assistant', 'content' => 'Hola'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 3],
    ])]);

    $driver = driverFor($this->user, AiProvider::OpenAi, 'gpt-4.1-mini');

    $response = $driver->send(new AiRequest(
        system: 'sys',
        messages: [AiMessage::user('lee esto', [new AiAttachment('image/png', base64_encode('img'))])],
        tools: sampleTools(),
        maxTokens: 500,
    ));

    expect($response->text)->toBe('Hola')
        ->and($response->inputTokens)->toBe(12)
        ->and($response->outputTokens)->toBe(3);

    Http::assertSent(function (Request $request) {
        $body = json_decode($request->body(), false);

        return $request->url() === 'https://api.openai.com/v1/chat/completions'
            && str_starts_with($request->header('Authorization')[0], 'Bearer key-')
            && $body->model === 'gpt-4.1-mini'
            && $body->max_completion_tokens === 500
            && ! isset($body->max_tokens)
            && ! isset($body->temperature)
            && $body->messages[0]->role === 'system'
            && $body->messages[1]->content[0]->type === 'image_url'
            && str_starts_with($body->messages[1]->content[0]->image_url->url, 'data:image/png;base64,')
            && $body->messages[1]->content[1]->text === 'lee esto'
            // Un objeto sin propiedades tiene que viajar como {} y no como [].
            && $body->tools[0]->function->parameters->properties instanceof stdClass
            && $body->tools[1]->function->parameters->required === ['from'];
    });
});

test('openai compatible providers use max_tokens and their own base url', function () {
    Http::fake(['api.groq.com/*' => Http::response([
        'choices' => [['message' => ['content' => 'ok']]],
    ])]);

    driverFor($this->user, AiProvider::Groq)->send(new AiRequest('sys', [AiMessage::user('hola')], maxTokens: 300));

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
        && $request['max_tokens'] === 300
        && ! isset($request['max_completion_tokens']));
});

test('the openai driver parses tool calls and replays them in the next round', function () {
    Http::fake(['api.openai.com/*' => Http::sequence()
        ->push(['choices' => [['message' => [
            'content' => null,
            'tool_calls' => [['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'get_budget_status', 'arguments' => '{}']]],
        ], 'finish_reason' => 'tool_calls']]])
        ->push(['choices' => [['message' => ['content' => 'Listo']]]]),
    ]);

    $driver = driverFor($this->user, AiProvider::OpenAi);
    $request = new AiRequest('sys', [AiMessage::user('¿presupuesto?')], sampleTools());

    $first = $driver->send($request);

    expect($first->toolCalls)->toHaveCount(1)
        ->and($first->toolCalls[0]->name)->toBe('get_budget_status')
        ->and($first->toolCalls[0]->arguments)->toBe([]);

    $second = $driver->send($request->withMessages([
        ...$request->messages,
        AiMessage::fromResponse($first),
        AiMessage::toolResult($first->toolCalls[0], ['spent' => 10]),
    ]));

    expect($second->text)->toBe('Listo');

    $replayed = json_decode(Http::recorded()[1][0]->body(), false);

    expect($replayed->messages[2]->tool_calls[0]->function->arguments)->toBe('{}')
        ->and($replayed->messages[3]->role)->toBe('tool')
        ->and($replayed->messages[3]->tool_call_id)->toBe('call_1')
        ->and($replayed->messages[3]->content)->toBe('{"spent":10}');
});

test('the anthropic driver sends messages without sampling params and with server fallbacks', function () {
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'], ['type' => 'text', 'text' => 'Hola']],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 20, 'output_tokens' => 5],
    ])]);

    $driver = driverFor($this->user, AiProvider::Anthropic, 'claude-opus-5-5');

    $response = $driver->send(new AiRequest(
        system: 'sys',
        messages: [AiMessage::user('lee', [new AiAttachment('application/pdf', base64_encode('%PDF'))])],
        tools: sampleTools(),
    ));

    expect($response->text)->toBe('Hola');

    Http::assertSent(function (Request $request) {
        $body = json_decode($request->body(), false);

        return $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key')
            && $request->header('anthropic-version')[0] === '2023-06-01'
            && $request->header('anthropic-beta')[0] === 'server-side-fallback-2026-07-01'
            && $body->fallbacks === 'default'
            && ! isset($body->temperature)
            && ! isset($body->thinking)
            && $body->system === 'sys'
            // El documento va antes del texto.
            && $body->messages[0]->content[0]->type === 'document'
            && $body->messages[0]->content[0]->source->media_type === 'application/pdf'
            && $body->messages[0]->content[1]->type === 'text'
            && $body->tools[0]->input_schema->properties instanceof stdClass;
    });
});

test('older claude models do not get the fallback beta', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'ok']], 'stop_reason' => 'end_turn'])]);

    driverFor($this->user, AiProvider::Anthropic, 'claude-haiku-4-5')->send(new AiRequest('sys', [AiMessage::user('hola')]));

    Http::assertSent(fn (Request $request) => ! $request->hasHeader('anthropic-beta') && ! isset($request['fallbacks']));
});

test('the anthropic driver groups tool results and replays its own content verbatim', function () {
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(['content' => [
            ['type' => 'thinking', 'thinking' => '', 'signature' => 'firma'],
            ['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'get_budget_status', 'input' => new stdClass],
            ['type' => 'tool_use', 'id' => 'tu_2', 'name' => 'list_transactions', 'input' => ['from' => '2026-10-01']],
        ], 'stop_reason' => 'tool_use'])
        ->push(['content' => [['type' => 'text', 'text' => 'Fin']], 'stop_reason' => 'end_turn']),
    ]);

    $driver = driverFor($this->user, AiProvider::Anthropic);
    $request = new AiRequest('sys', [AiMessage::user('hola')], sampleTools());
    $first = $driver->send($request);

    expect($first->toolCalls)->toHaveCount(2);

    $driver->send($request->withMessages([
        ...$request->messages,
        AiMessage::fromResponse($first),
        AiMessage::toolResult($first->toolCalls[0], ['ok' => true]),
        AiMessage::toolResult($first->toolCalls[1], ['rows' => []]),
    ]));

    $replayed = json_decode(Http::recorded()[1][0]->body(), false);

    expect($replayed->messages)->toHaveCount(3)
        // El bloque de razonamiento vuelve intacto, con su firma.
        ->and($replayed->messages[1]->content[0]->signature)->toBe('firma')
        // `{}` sigue siendo un objeto al reenviarlo.
        ->and($replayed->messages[1]->content[1]->input)->toBeInstanceOf(stdClass::class)
        // Los dos resultados viajan en UN solo mensaje de usuario.
        ->and($replayed->messages[2]->role)->toBe('user')
        ->and($replayed->messages[2]->content)->toHaveCount(2)
        ->and($replayed->messages[2]->content[1]->tool_use_id)->toBe('tu_2')
        ->and(property_exists($replayed->messages[2], 'tool_results'))->toBeFalse();
});

test('an anthropic refusal is reported as such', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [], 'stop_reason' => 'refusal'])]);

    driverFor($this->user, AiProvider::Anthropic)->send(new AiRequest('sys', [AiMessage::user('x')]));
})->throws(AiException::class, 'se negó');

test('the gemini driver sends generateContent and replays thought signatures', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
        ->push(['candidates' => [['content' => ['role' => 'model', 'parts' => [
            ['functionCall' => ['name' => 'get_budget_status', 'args' => new stdClass], 'thoughtSignature' => 'abc'],
        ]], 'finishReason' => 'STOP']]])
        ->push(['candidates' => [['content' => ['parts' => [['text' => 'pensando', 'thought' => true], ['text' => 'Listo']]]]],
            'usageMetadata' => ['promptTokenCount' => 30, 'candidatesTokenCount' => 4]]),
    ]);

    $driver = driverFor($this->user, AiProvider::Gemini, 'gemini-2.5-flash');
    $request = new AiRequest('sys', [AiMessage::user('hola', [new AiAttachment('image/jpeg', 'QUJD')])], sampleTools());

    $first = $driver->send($request);

    expect($first->toolCalls[0]->name)->toBe('get_budget_status');

    $second = $driver->send($request->withMessages([
        ...$request->messages,
        AiMessage::fromResponse($first),
        AiMessage::toolResult($first->toolCalls[0], ['spent' => 5]),
    ]));

    expect($second->text)->toBe('Listo')
        ->and($second->inputTokens)->toBe(30);

    $initial = Http::recorded()[0][0];
    $body = json_decode($initial->body(), false);

    expect($initial->url())->toBe('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent')
        ->and($initial->hasHeader('x-goog-api-key'))->toBeTrue()
        ->and($body->systemInstruction->parts[0]->text)->toBe('sys')
        ->and($body->contents[0]->parts[0]->inlineData->mimeType)->toBe('image/jpeg')
        // Sin propiedades, Gemini no acepta `parameters`: se omite.
        ->and(property_exists($body->tools[0]->functionDeclarations[0], 'parameters'))->toBeFalse()
        ->and($body->tools[0]->functionDeclarations[1]->parameters->required)->toBe(['from']);

    $replayed = json_decode(Http::recorded()[1][0]->body(), false);

    expect($replayed->contents[1]->role)->toBe('model')
        ->and($replayed->contents[1]->parts[0]->thoughtSignature)->toBe('abc')
        ->and($replayed->contents[2]->parts[0]->functionResponse->name)->toBe('get_budget_status')
        ->and($replayed->contents[2]->parts[0]->functionResponse->response->result->spent)->toBe(5);
});

test('provider errors are translated', function (AiProvider $provider, string $host, int $status, array $body, string $reason) {
    Http::fake([$host => Http::response($body, $status)]);

    try {
        driverFor($this->user, $provider)->send(new AiRequest('sys', [AiMessage::user('x')]));
        $this->fail('Expected an AiException');
    } catch (AiException $exception) {
        expect($exception->reason)->toBe($reason);
    }
})->with([
    'openai invalid key' => [AiProvider::OpenAi, 'api.openai.com/*', 401, ['error' => ['message' => 'Incorrect API key']], AiException::INVALID_KEY],
    'openai quota' => [AiProvider::OpenAi, 'api.openai.com/*', 429, ['error' => ['message' => 'quota']], AiException::RATE_LIMITED],
    'openai bad model' => [AiProvider::OpenAi, 'api.openai.com/*', 404, ['error' => ['message' => 'no model']], AiException::MODEL_NOT_FOUND],
    'anthropic bad request' => [AiProvider::Anthropic, 'api.anthropic.com/*', 400, ['error' => ['message' => 'credit balance too low']], AiException::BAD_REQUEST],
    'gemini invalid key' => [AiProvider::Gemini, 'generativelanguage.googleapis.com/*', 400, ['error' => ['message' => 'API key not valid. Please pass a valid API key.']], AiException::INVALID_KEY],
]);

test('a provider without vision refuses attachments before any request', function () {
    driverFor($this->user, AiProvider::DeepSeek)->send(new AiRequest('sys', [AiMessage::user('x', [new AiAttachment('image/png', 'AA')])]));

    Http::assertNothingSent();
})->throws(AiException::class);

test('a provider without native pdf support asks for an image instead', function () {
    driverFor($this->user, AiProvider::Groq)->send(new AiRequest('sys', [AiMessage::user('x', [new AiAttachment('application/pdf', 'AA')])]));
})->throws(AiException::class, 'PDF');

test('the manager refuses a user without a ready provider', function () {
    app(AiManager::class)->forUser($this->user);
})->throws(AiException::class);

test('tool results keep their order and ids', function () {
    $call = new AiToolCall('id-9', 'get_savings_goals', []);
    $message = AiMessage::toolResult($call, ['goals' => [['saved' => 1.0]]]);

    expect($message->toolCallId)->toBe('id-9')
        ->and($message->toolName)->toBe('get_savings_goals')
        ->and($message->text)->toBe('{"goals":[{"saved":1.0}]}');
});

test('opencode goes through the openai chat endpoint with a stable session per user', function () {
    Http::fake(['opencode.ai/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

    $driver = driverFor($this->user, AiProvider::OpenCode, 'claude-sonnet-5-5');
    $driver->send(new AiRequest('sys', [AiMessage::user('hola')], maxTokens: 400));
    $driver->send(new AiRequest('sys', [AiMessage::user('otra vez')]));

    $other = driverFor(User::factory()->create(), AiProvider::OpenCode);
    $other->send(new AiRequest('sys', [AiMessage::user('hola')]));

    [$first, $second, $third] = array_map(fn (array $pair) => $pair[0], Http::recorded()->all());

    expect($first->url())->toBe('https://opencode.ai/inference/openai/v1/chat/completions')
        ->and($first->header('Authorization')[0])->toStartWith('Bearer key-')
        ->and($first['model'])->toBe('claude-sonnet-5-5')
        ->and($first['max_tokens'])->toBe(400)
        ->and(isset($first['max_completion_tokens']))->toBeFalse()
        ->and($first->header('x-opencode-session')[0])->toMatch('/^ses_[0-9a-f]{26}$/')
        // Estable para el mismo usuario, distinta entre usuarios.
        ->and($second->header('x-opencode-session')[0])->toBe($first->header('x-opencode-session')[0])
        ->and($third->header('x-opencode-session')[0])->not->toBe($first->header('x-opencode-session')[0]);
});

test('the opencode go plan uses its own base url', function () {
    Http::fake(['opencode.ai/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

    $setting = AiSetting::factory()->for($this->user)->provider(AiProvider::OpenCode)->create([
        'base_url' => 'https://opencode.ai/inference/go/openai/v1',
    ]);

    app(AiManager::class)->driverFor($setting)->send(new AiRequest('sys', [AiMessage::user('hola')]));

    Http::assertSent(fn (Request $request) => $request->url() === 'https://opencode.ai/inference/go/openai/v1/chat/completions');
});

test('a tampered opencode base url falls back to the official endpoint', function () {
    $setting = AiSetting::factory()->for($this->user)->provider(AiProvider::OpenCode)->create([
        'base_url' => 'http://169.254.169.254/latest',
    ]);

    expect($setting->resolvedBaseUrl())->toBe('https://opencode.ai/inference/openai/v1');
});

test('opencode does not take pdf files directly', function () {
    driverFor($this->user, AiProvider::OpenCode)->send(new AiRequest('sys', [AiMessage::user('x', [new AiAttachment('application/pdf', 'AA')])]));
})->throws(AiException::class, 'PDF');

test('a request can ask for a longer timeout than the default', function () {
    config(['ai.timeout' => 60]);

    $driver = new class(AiSetting::factory()->for($this->user)->create()) extends OpenAiDriver
    {
        public function client(?AiRequest $request): PendingRequest
        {
            return $this->http($request);
        }
    };

    expect($driver->client(new AiRequest('sys', [], timeout: 120))->getOptions()['timeout'])->toBe(120)
        ->and($driver->client(new AiRequest('sys', [], timeout: 10))->getOptions()['timeout'])->toBe(60)
        ->and($driver->client(null)->getOptions()['timeout'])->toBe(60);
});
