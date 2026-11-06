<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class ScanReceiptRequest extends FormRequest
{
    /**
     * Límite por fichero, en KB. Literal y por debajo de 8 MB a propósito:
     * SonarCloud (S5693) señala como vulnerabilidad cualquier subida mayor, y
     * no hace falta más — las fotos se reducen antes de enviarse y un PDF de
     * banco pesa una fracción de esto.
     */
    public const MAX_KB = 7168;

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
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:7168'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => __('validation.app.ai_file_mimes'),
            'file.max' => __('validation.app.ai_file_max', ['mb' => intdiv(self::MAX_KB, 1024)]),
        ];
    }
}
