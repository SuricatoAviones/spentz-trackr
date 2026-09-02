<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePaymentSourceRequest;
use App\Models\AdminAction;
use App\Models\PaymentSource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentSourceController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $sources = PaymentSource::query()
            ->with('user:id,name,email')
            ->withCount('expenses')
            ->withSum('expenses', 'usd_amount')
            ->when($request->integer('user_id'), function ($query, int $userId) {
                $query->where('user_id', $userId);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PaymentSource $source) => [
                'id' => $source->id,
                'name' => $source->name,
                'icon' => $source->icon,
                'color' => $source->color,
                'is_system' => $source->is_system,
                'expenses_count' => $source->expenses_count,
                'total_usd' => round((float) ($source->expenses_sum_usd_amount ?? 0), 2),
                'can_delete' => $source->expenses_count === 0,
                'user' => [
                    'id' => $source->user->id,
                    'name' => $source->user->name,
                    'email' => $source->user->email,
                ],
            ]);

        return Inertia::render('admin/sources/index', [
            'sources' => $sources,
            'filters' => [
                'search' => $search,
                'user_id' => $request->integer('user_id'),
            ],
            'users' => $this->userOptions(),
        ]);
    }

    public function update(UpdatePaymentSourceRequest $request, PaymentSource $source): RedirectResponse
    {
        $source->update($request->validated());

        AdminAction::record('source.updated', $source);

        return back()->with('success', __('messages.source_updated'));
    }

    public function destroy(Request $request, PaymentSource $source): RedirectResponse
    {
        if ($source->expenses()->exists()) {
            return back()->with('error', __('messages.source_delete_blocked'));
        }

        AdminAction::record('source.deleted', $source);
        $source->delete();

        return back()->with('success', __('messages.source_deleted'));
    }

    /**
     * @return array<int, array{id: int, name: string, email: string}>
     */
    private function userOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->all();
    }
}
