<?php

namespace App\Http\Requests;

use App\Enums\CardBrand;
use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCreditCardRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bank' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
            // Solo los últimos cuatro: guardar el número completo de una
            // tarjeta sería almacenar un dato de pago sin ninguna necesidad.
            'last_four' => ['nullable', 'string', 'digits:4'],
            'brand' => ['nullable', Rule::enum(CardBrand::class)],
            'currency' => ['required', Rule::enum(Currency::class)],
            'credit_limit' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'cut_day' => ['required', 'integer', 'between:1,31'],
            'due_day' => ['required', 'integer', 'between:1,31'],
            'annual_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'minimum_payment_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
            'active' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'last_four.digits' => __('validation.app.card_last_four'),
            'cut_day.between' => __('validation.app.card_day_range'),
            'due_day.between' => __('validation.app.card_day_range'),
        ];
    }
}
