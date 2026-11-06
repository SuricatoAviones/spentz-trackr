<?php

namespace App\Http\Requests;

use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Validator;

/**
 * Periodo de un estado de cuenta. Sin fechas, el mes en curso.
 *
 * El tope de 366 días protege el render de la página y el contexto que se le
 * manda a la IA; para más, están el informe anual y el export de gastos.
 */
class StatementRequest extends FormRequest
{
    public const MAX_DAYS = 366;

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
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->from()->greaterThan($this->to())) {
                $validator->errors()->add('to', __('validation.app.statement_order'));

                return;
            }

            if ($this->from()->diffInDays($this->to()) >= self::MAX_DAYS) {
                $validator->errors()->add('to', __('validation.app.statement_range', ['days' => self::MAX_DAYS]));
            }
        });
    }

    public function from(): CarbonInterface
    {
        $value = $this->input('from');

        return is_string($value) && $value !== ''
            ? Date::createFromFormat('!Y-m-d', $value)
            : Date::today()->startOfMonth();
    }

    public function to(): CarbonInterface
    {
        $value = $this->input('to');

        return is_string($value) && $value !== ''
            ? Date::createFromFormat('!Y-m-d', $value)
            : Date::today();
    }
}
