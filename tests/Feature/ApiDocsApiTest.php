<?php

test('the OpenAPI UI renders', function () {
    $this->get('/api/v1')
        ->assertStatus(200)
        ->assertSee('Spentz Trackr API');
});

test('the OpenAPI document contains the full API surface', function () {
    $response = $this->getJson('/api/v1.json');

    $response->assertStatus(200)
        ->assertJsonPath('info.title', 'Spentz Trackr API')
        ->assertJsonPath('openapi', '3.1.0')
        ->assertJsonStructure([
            'components' => ['securitySchemes' => ['http']],
        ]);

    $json = $response->json();

    expect(array_keys($json['paths']))
        ->toContain('/api/v1/expenses')
        ->toContain('/api/v1/incomes')
        ->toContain('/api/v1/reports')
        ->toContain('/api/v1/dashboard')
        ->toContain('/api/v1/rates');

    expect($json['paths']['/api/v1/auth/register']['post']['security'])
        ->toBe([])
        ->and($json['paths']['/api/v1/auth/login']['post']['security'])
        ->toBe([]);

    $expenseParams = collect($json['paths']['/api/v1/expenses']['get']['parameters']);

    expect($expenseParams->contains(fn (array $parameter) => $parameter['name'] === 'search' && $parameter['in'] === 'query'))->toBeTrue()
        ->and($expenseParams->contains(fn (array $parameter) => $parameter['name'] === 'from' && ($parameter['schema']['format'] ?? null) === 'date'))->toBeTrue();
});
