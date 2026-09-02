<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentSourceRequest;
use App\Http\Requests\UpdatePaymentSourceRequest;
use App\Models\PaymentSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentSourceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $sources = PaymentSource::query()
            ->where('user_id', $user->id)
            ->withCount('expenses')
            ->withSum('expenses', 'usd_amount')
            ->orderBy('name')
            ->get()
            ->map(fn (PaymentSource $source) => [
                'id' => $source->id,
                'name' => $source->name,
                'icon' => $source->icon,
                'color' => $source->color,
                'is_system' => $source->is_system,
                'expenses_count' => $source->expenses_count,
                'total_usd' => round((float) $source->expenses_sum_usd_amount, 2),
            ]);

        return Inertia::render('sources/index', [
            'sources' => $sources,
        ]);
    }

    public function store(StorePaymentSourceRequest $request): RedirectResponse
    {
        $request->user()->paymentSources()->create($request->validated());

        return redirect()
            ->route('sources.index')
            ->with('success', __('messages.source_created'));
    }

    public function update(UpdatePaymentSourceRequest $request, PaymentSource $source): RedirectResponse
    {
        $this->authorize('update', $source);

        $source->update($request->validated());

        return redirect()
            ->route('sources.index')
            ->with('success', __('messages.source_updated'));
    }

    public function destroy(Request $request, PaymentSource $source): RedirectResponse
    {
        $this->authorize('delete', $source);

        $source->delete();

        return redirect()
            ->route('sources.index')
            ->with('success', __('messages.source_deleted'));
    }
}
