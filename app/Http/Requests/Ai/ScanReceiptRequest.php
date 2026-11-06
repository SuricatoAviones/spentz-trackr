<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class ScanReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.(int) config('ai.max_upload_kb', 10240)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => __('validation.app.ai_file_mimes'),
            'file.max' => __('validation.app.ai_file_max', ['mb' => (int) round(config('ai.max_upload_kb', 10240) / 1024)]),
        ];
    }
}
