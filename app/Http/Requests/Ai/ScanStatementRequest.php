<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Un PDF o varias capturas de la app del banco (los movimientos de un móvil no
 * caben en una sola pantalla).
 */
class ScanStatementRequest extends FormRequest
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
            'files' => ['required', 'array', 'min:1', 'max:'.(int) config('ai.import.max_files', 5)],
            'files.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:7168'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.max' => __('validation.app.import_files_max', ['max' => (int) config('ai.import.max_files', 5)]),
            'files.*.mimes' => __('validation.app.ai_file_mimes'),
            'files.*.max' => __('validation.app.ai_file_max', ['mb' => intdiv(ScanReceiptRequest::MAX_KB, 1024)]),
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    public function uploads(): array
    {
        $files = $this->file('files');

        return is_array($files) ? array_values($files) : [];
    }
}
