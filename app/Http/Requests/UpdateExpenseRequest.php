<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('user_id', $this->user()?->id)],
            'payment_source_id' => ['required', 'integer', Rule::exists('payment_sources', 'id')->where('user_id', $this->user()?->id)],
            'currency' => ['required', Rule::enum(Currency::class)],
            'payment_method' => ['prohibited_unless:currency,ves', 'nullable', Rule::enum(PaymentMethod::class), 'required_with:commission'],
            'commission' => ['prohibited_unless:currency,ves', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'rate_provider' => ['nullable', Rule::in(['bcv', 'paralelo', 'user', 'custom'])],
            'description' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'spent_at' => ['required', 'date', 'before_or_equal:today'],
            'receipt' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_receipt' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array', 'max:6'],
            'items.*.currency' => ['required', Rule::enum(Currency::class)],
            'items.*.amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'items.*.exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'items.*.rate_provider' => ['nullable', Rule::in(['bcv', 'paralelo', 'user', 'custom'])],
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'currency.required' => 'Selecciona la moneda del gasto.',
            'exchange_rate.gt' => 'La tasa debe ser mayor a 0.',
            'spent_at.before_or_equal' => 'La fecha no puede ser futura.',
            'receipt.max' => 'El comprobante no puede superar los 5 MB.',
        ];
    }
}
