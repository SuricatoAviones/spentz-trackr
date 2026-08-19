<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminRateRequest extends FormRequest
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
            'bcv' => ['required_without:paralelo', 'nullable', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'paralelo' => ['required_without:bcv', 'nullable', 'numeric', 'gt:0', 'max:9999999999.9999'],
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
            'bcv.gt' => 'La tasa BCV debe ser mayor a 0.',
            'paralelo.gt' => 'La tasa paralela debe ser mayor a 0.',
            'bcv.required_without' => 'Indica al menos la tasa BCV o la paralela.',
            'paralelo.required_without' => 'Indica al menos la tasa BCV o la paralela.',
        ];
    }
}
