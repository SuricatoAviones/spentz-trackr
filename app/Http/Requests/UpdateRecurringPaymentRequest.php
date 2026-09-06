<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Enums\Frequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecurringPaymentRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'required_if:currency,ves', 'max:9999999999.9999'],
            'frequency' => ['required', Rule::enum(Frequency::class)],
            'next_due_date' => ['required', 'date'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('user_id', $this->user()?->id)],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
