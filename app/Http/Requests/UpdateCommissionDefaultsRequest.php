<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommissionDefaultsRequest extends FormRequest
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
            'min_commission' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
