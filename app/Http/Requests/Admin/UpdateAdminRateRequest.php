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
            'bcv.gt' => __('validation.app.bcv_rate_gt'),
            'paralelo.gt' => __('validation.app.paralelo_rate_gt'),
            'bcv.required_without' => __('validation.app.rate_required_without'),
            'paralelo.required_without' => __('validation.app.rate_required_without'),
        ];
    }
}
