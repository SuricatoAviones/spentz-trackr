<?php

/*
 * Aritmética de ciclo de tarjeta. Aquí es donde viven los bugs de este módulo:
 * días que no existen en el mes, cortes que caen a caballo entre dos meses y
 * vencimientos que se adelantan al cierre.
 */

use App\Models\CreditCard;
use App\Services\CreditCardCycleService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->cycle = new CreditCardCycleService;
});

// Sin factory ni base de datos: estas reglas son aritmética pura de fechas.
function card(int $cutDay, int $dueDay): CreditCard
{
    return new CreditCard(['cut_day' => $cutDay, 'due_day' => $dueDay]);
}

test('a cut day that does not exist falls on the last day of the month', function () {
    // Una tarjeta que corta el 31 no tiene 31 en febrero.
    expect($this->cycle->onDay(Carbon::parse('2026-02-10'), 31)->toDateString())->toBe('2026-02-28')
        ->and($this->cycle->onDay(Carbon::parse('2024-02-10'), 31)->toDateString())->toBe('2024-02-29')
        ->and($this->cycle->onDay(Carbon::parse('2026-04-10'), 31)->toDateString())->toBe('2026-04-30')
        ->and($this->cycle->onDay(Carbon::parse('2026-01-10'), 31)->toDateString())->toBe('2026-01-31');
});

test('the next cut is strictly after today, never today itself', function () {
    $card = card(cutDay: 15, dueDay: 5);

    // Justo el día del corte, el siguiente es el del mes que viene.
    expect($this->cycle->nextCutDate($card, Carbon::parse('2026-03-15'))->toDateString())->toBe('2026-04-15')
        ->and($this->cycle->nextCutDate($card, Carbon::parse('2026-03-14'))->toDateString())->toBe('2026-03-15')
        ->and($this->cycle->nextCutDate($card, Carbon::parse('2026-03-16'))->toDateString())->toBe('2026-04-15');
});

test('the last cut is on or before today', function () {
    $card = card(cutDay: 15, dueDay: 5);

    expect($this->cycle->lastCutDate($card, Carbon::parse('2026-03-15'))->toDateString())->toBe('2026-03-15')
        ->and($this->cycle->lastCutDate($card, Carbon::parse('2026-03-14'))->toDateString())->toBe('2026-02-15')
        ->and($this->cycle->lastCutDate($card, Carbon::parse('2026-03-16'))->toDateString())->toBe('2026-03-15');
});

test('the due date lands in the next month when the payment day precedes the cut', function () {
    // Corta el 15, se paga el 5: el pago es del mes siguiente.
    $card = card(cutDay: 15, dueDay: 5);

    expect($this->cycle->dueDateFor($card, Carbon::parse('2026-03-15'))->toDateString())->toBe('2026-04-05');

    // Corta el 5, se paga el 25: mismo mes.
    $sameMonth = card(cutDay: 5, dueDay: 25);

    expect($this->cycle->dueDateFor($sameMonth, Carbon::parse('2026-03-05'))->toDateString())->toBe('2026-03-25');
});

test('paying on the same day the statement closes rolls to the next month', function () {
    $card = card(cutDay: 10, dueDay: 10);

    expect($this->cycle->dueDateFor($card, Carbon::parse('2026-03-10'))->toDateString())->toBe('2026-04-10');
});

test('a cut on the 31st keeps working across a short month', function () {
    $card = card(cutDay: 31, dueDay: 20);

    // De enero a febrero: el corte se recorta al 28.
    expect($this->cycle->nextCutDate($card, Carbon::parse('2026-02-01'))->toDateString())->toBe('2026-02-28')
        ->and($this->cycle->lastCutDate($card, Carbon::parse('2026-03-01'))->toDateString())->toBe('2026-02-28')
        // Y el vencimiento, en marzo.
        ->and($this->cycle->dueDateFor($card, Carbon::parse('2026-02-28'))->toDateString())->toBe('2026-03-20');
});
