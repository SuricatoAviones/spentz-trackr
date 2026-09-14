<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Models\CreditCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCreditCardPaymentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'credit_card_statement_id' => ['nullable', 'integer'],
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

            // El corte debe ser de ESTA tarjeta: sin comprobarlo se podría
            // imputar un abono al corte de la tarjeta de otro usuario.
            if ($this->filled('credit_card_statement_id')) {
                $belongs = $card->statements()
                    ->whereKey($this->integer('credit_card_statement_id'))
                    ->exists();

                if (! $belongs) {
                    $validator->errors()->add('credit_card_statement_id', __('validation.app.card_statement_foreign'));
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'paid_at.before_or_equal' => __('validation.app.date_not_future'),
            'exchange_rate.gt' => __('validation.app.rate_gt'),
        ];
    }
}
