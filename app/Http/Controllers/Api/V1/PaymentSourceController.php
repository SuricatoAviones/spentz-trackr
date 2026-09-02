<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StorePaymentSourceRequest;
use App\Http\Requests\UpdatePaymentSourceRequest;
use App\Models\PaymentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentSourceController extends BaseApiController
{
    /**
     * Display the user's payment sources with usage metrics.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $sources = PaymentSource::query()
            ->where('user_id', $user->id)
            ->withCount('expenses')
            ->withSum('expenses', 'usd_amount')
            ->orderBy('name')
            ->get(['id', 'name', 'icon', 'color', 'is_system'])
            ->map(fn (PaymentSource $source) => [
                'id' => $source->id,
                'name' => $source->name,
                'icon' => $source->icon,
                'color' => $source->color,
                'is_system' => $source->is_system,
                'expenses_count' => $source->expenses_count,
                'total_usd' => round((float) $source->expenses_sum_usd_amount, 2),
            ]);

        return $this->apiResponse([
            'sources' => $sources,
        ]);
    }

    /**
     * Store a newly created payment source.
     */
    public function store(StorePaymentSourceRequest $request): JsonResponse
    {
        $source = $request->user()->paymentSources()->create($request->validated());

        return $this->apiCreated($source->id, __('messages.source_created'));
    }

    /**
     * Update the specified payment source.
     */
    public function update(UpdatePaymentSourceRequest $request, PaymentSource $source): JsonResponse
    {
        $this->authorize('update', $source);

        $source->update($request->validated());

        return $this->apiResponse([
            'id' => $source->id,
        ], __('messages.source_updated'));
    }

    /**
     * Remove the specified payment source.
     */
    public function destroy(Request $request, PaymentSource $source): JsonResponse
    {
        $this->authorize('delete', $source);

        $source->delete();

        return response()->json([
            'success' => true,
            'data' => ['id' => $source->id],
            'message' => __('messages.source_deleted'),
        ], 200);
    }
}
