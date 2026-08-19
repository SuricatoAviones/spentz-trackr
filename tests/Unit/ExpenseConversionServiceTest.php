<?php

use App\Enums\Currency;
use App\Services\ExpenseConversionService;

beforeEach(function () {
    $this->converter = new ExpenseConversionService;
});

test('USD amounts pass through unchanged', function () {
    $result = $this->converter->convert(Currency::Usd, 25.5);

    expect($result['usd_amount'])->toBe(25.5)
        ->and($result['usdt_amount'])->toBe(25.5);
});

test('USDT amounts pass through unchanged', function () {
    $result = $this->converter->convert(Currency::Usdt, 50);

    expect($result['usd_amount'])->toBe(50.0)
        ->and($result['usdt_amount'])->toBe(50.0);
});

test('VES amounts are converted with the given rate', function () {
    $result = $this->converter->convert(Currency::Ves, 285, 28.5);

    expect($result['usd_amount'])->toBe(10.0)
        ->and($result['usdt_amount'])->toBe(10.0);
});

test('VES conversion rounds to two decimals', function () {
    $result = $this->converter->convert(Currency::Ves, 100, 30);

    expect($result['usd_amount'])->toBe(3.33);
});

test('VES conversion without a rate throws an exception', function () {
    $this->converter->convert(Currency::Ves, 100);
})->throws(InvalidArgumentException::class, 'Una tasa de cambio mayor a 0 es obligatoria para gastos en Bs.');

test('VES conversion with a zero rate throws an exception', function () {
    $this->converter->convert(Currency::Ves, 100, 0);
})->throws(InvalidArgumentException::class);
