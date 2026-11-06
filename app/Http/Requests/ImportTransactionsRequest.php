<?php

namespace App\Http\Requests;

use App\Enums\CategoryType;
use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Movimientos confirmados de un estado de cuenta. Mismas reglas por fila que
 * un gasto o ingreso tecleado a mano (ver StoreExpenseRequest y
 * StoreIncomeRequest), más dos que solo tienen sentido en lote: la categoría
 * debe ser del tipo de la fila y el tipo debe estar entre lo que el usuario
 * registra.
 */
class ImportTransactionsRequest extends FormRequest
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
        $userId = $this->user()?->id;

        return [
            'payment_source_id' => ['nullable', 'integer', Rule::exists('payment_sources', 'id')->where('user_id', $userId)],
            'rows' => ['required', 'array', 'min:1', 'max:'.(int) config('ai.import.max_rows', 200)],
            'rows.*.type' => ['required', Rule::in(['expense', 'income'])],
            'rows.*.date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'rows.*.description' => ['required', 'string', 'max:255'],
            'rows.*.amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'rows.*.currency' => ['required', Rule::enum(Currency::class)],
            'rows.*.category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('user_id', $userId)],
            'rows.*.payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'rows.*.exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'rows.*.rate_provider' => ['nullable', Rule::in(['bcv', 'paralelo', 'user', 'custom'])],
            'rows.*.note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            $rows = (array) $this->input('rows', []);
            $types = Category::query()
                ->where('user_id', $user?->id)
                ->whereKey(array_column($rows, 'category_id'))
                ->pluck('type', 'id');

            $hasExpenses = false;

            foreach ($rows as $index => $row) {
                $isExpense = ($row['type'] ?? null) === 'expense';
                $hasExpenses = $hasExpenses || $isExpense;
                $expected = $isExpense ? CategoryType::Expense : CategoryType::Income;

                if ($types->get((int) $row['category_id']) !== $expected) {
                    $validator->errors()->add("rows.{$index}.category_id", __('validation.app.import_category_type'));
                }

                $tracked = $isExpense ? $user?->tracking_type !== 'income' : $user?->tracking_type !== 'expenses';

                if (! $tracked) {
                    $validator->errors()->add("rows.{$index}.type", __('validation.app.import_type_not_tracked'));
                }
            }

            if ($hasExpenses && $this->input('payment_source_id') === null) {
                $validator->errors()->add('payment_source_id', __('validation.app.import_source_required'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.required' => __('validation.app.import_rows_required'),
            'rows.min' => __('validation.app.import_rows_required'),
            'rows.max' => __('validation.app.import_rows_max', ['max' => (int) config('ai.import.max_rows', 200)]),
            'rows.*.date.before_or_equal' => __('validation.app.date_not_future'),
        ];
    }
}
