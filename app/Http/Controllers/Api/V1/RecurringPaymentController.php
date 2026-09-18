<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecurringPayments\MarkRecurringPaymentPaidAction;
use App\Actions\RecurringPayments\StoreRecurringPaymentAction;
use App\Actions\RecurringPayments\UpdateRecurringPaymentAction;
use App\Http\Requests\StoreRecurringPaymentRequest;
use App\Http\Requests\UpdateRecurringPaymentRequest;
use App\Models\RecurringPayment;
use App\Support\Presenters\RecurringPaymentPresenter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecurringPaymentController extends BaseApiController
{
    /**
     * Listar los pagos recurrentes del usuario. Los vencidos van primero.
     */
    #[QueryParameter('active', description: 'Filtrar por estado: 1 solo los activos, 0 solo los pausados.', type: 'boolean')]
    #[QueryParameter('due', description: 'Con 1, devolver solo los que ya vencieron y siguen activos.', type: 'boolean')]
    public function index(Request $request): JsonResponse
    {
        $payments = RecurringPayment::query()
            ->forUser($request->user()->id)
            ->when($request->has('active'), function ($query) use ($request) {
                $query->where('active', $request->boolean('active'));
            })
            ->get()
            ->map(fn (RecurringPayment $payment) => RecurringPaymentPresenter::present($payment))
            ->when($request->boolean('due'), fn ($payments) => $payments->where('due', true))
            ->sortBy([
                fn (array $a, array $b) => (int) $b['due'] <=> (int) $a['due'],
                fn (array $a, array $b) => strcmp($a['next_due_date'] ?? '', $b['next_due_date'] ?? ''),
            ])
            ->values()
            ->all();

        return $this->apiResponse([
            'payments' => $payments,
        ]);
    }

    /**
     * Crear un pago recurrente. El importe se congela en USD al guardarlo.
     */
    public function store(StoreRecurringPaymentRequest $request, StoreRecurringPaymentAction $action): JsonResponse
    {
        $payment = $action->handle($request->user(), $request->validated());

        return $this->apiCreated($payment->id, __('messages.recurring_payment_created'))
            ->header('Location', route('api.v1.recurring-payments.show', $payment));
    }

    /**
     * Ver un pago recurrente.
     */
    public function show(RecurringPayment $recurringPayment): JsonResponse
    {
        $this->authorize('view', $recurringPayment);

        return $this->apiResponse([
            'payment' => RecurringPaymentPresenter::present($recurringPayment),
        ]);
    }

    /**
     * Actualizar un pago recurrente. Enviar `active` lo pausa o lo reanuda.
     */
    public function update(UpdateRecurringPaymentRequest $request, RecurringPayment $recurringPayment, UpdateRecurringPaymentAction $action): JsonResponse
    {
        $this->authorize('update', $recurringPayment);

        $action->handle($recurringPayment, $request->validated());

        return $this->apiResponse([
            'payment' => RecurringPaymentPresenter::present($recurringPayment->fresh()),
        ], __('messages.recurring_payment_updated'));
    }

    /**
     * Eliminar un pago recurrente.
     */
    public function destroy(RecurringPayment $recurringPayment): JsonResponse
    {
        $this->authorize('delete', $recurringPayment);

        $recurringPayment->delete();

        return $this->apiResponse(['id' => $recurringPayment->id], __('messages.recurring_payment_deleted'));
    }

    /**
     * Marcar el recurrente como pagado hoy y adelantar el vencimiento.
     *
     * No crea ningún gasto: el recurrente es el recordatorio, no el movimiento.
     */
    public function markPaid(RecurringPayment $recurringPayment, MarkRecurringPaymentPaidAction $action): JsonResponse
    {
        $this->authorize('update', $recurringPayment);

        $action->handle($recurringPayment);

        return $this->apiResponse([
            'payment' => RecurringPaymentPresenter::present($recurringPayment->fresh()),
        ], __('messages.recurring_payment_paid'));
    }
}
