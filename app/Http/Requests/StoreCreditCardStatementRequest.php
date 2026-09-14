<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Models\CreditCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCreditCardStatementRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cut_date' => ['required', 'date', 'before_or_equal:today'],
            'due_date' => ['nullable', 'date', 'after_or_equal:cut_date'],
            'closing_balance' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'minimum_payment' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            // La moneda la pone la tarjeta, no el formulario; solo hace falta
            // la tasa cuando esa moneda es Bs.
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $card = $this->route('credit_card');

            if (! $card instanceof CreditCard) {
                return;
            }

            if ($card->currency === Currency::Ves && ! $this->filled('exchange_rate')) {
                $validator->errors()->add('exchange_rate', __('validation.app.rate_required_ves'));
            }

            // Un corte por fecha: dos el mismo día harían ambiguo cuál es el
            // último y la proyección partiría de un ancla equivocada.
            $duplicated = $card->statements()
                ->whereDate('cut_date', $this->date('cut_date'))
                ->exists();

            if ($duplicated) {
                $validator->errors()->add('cut_date', __('validation.app.card_statement_duplicated'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cut_date.before_or_equal' => __('validation.app.date_not_future'),
            'exchange_rate.gt' => __('validation.app.rate_gt'),
        ];
    }
}
