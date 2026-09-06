<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetAdminUserPasswordRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $users = User::query()
            ->withCount('expenses')
            ->withSum('expenses', 'usd_amount')
            ->withMax('expenses', 'spent_at')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => $this->userShape($user));

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => ['search' => $search],
        ]);
    }

    public function show(User $user): Response
    {
        $user->loadCount('expenses')
            ->loadSum('expenses', 'usd_amount')
            ->loadMax('expenses', 'spent_at');

        $stats = [
            'total_usd' => round((float) $user->expenses_sum_usd_amount, 2),
            'categories_count' => $user->categories()->count(),
            'sources_count' => $user->paymentSources()->count(),
        ];

        $recentExpenses = $user->expenses()
            ->with(['category', 'paymentSource'])
            ->orderByDesc('spent_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn ($expense) => [
                'id' => $expense->id,
                'description' => $expense->description,
                'amount' => $expense->amount,
                'currency' => $expense->currency->value,
                'usd_amount' => $expense->usd_amount,
                'spent_at' => $expense->spent_at->toDateString(),
                'category' => $expense->category->name,
                'source' => $expense->paymentSource->name,
            ]);

        return Inertia::render('admin/users/show', [
            'user' => $this->userShape($user),
            'stats' => $stats,
            'recentExpenses' => $recentExpenses,
        ]);
    }

    public function update(UpdateAdminUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->forceFill(collect($data)->except('is_admin')->all())->save();

        if (array_key_exists('is_admin', $data)) {
            $user->forceFill(['is_admin' => (bool) $data['is_admin']])->save();
        }

        AdminAction::record('user.updated', $user);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', __('messages.user_updated'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            abort(403, 'No puedes eliminar tu propia cuenta.');
        }

        AdminAction::record('user.deleted', $user);
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', __('messages.user_deleted'));
    }

    public function verifyEmail(Request $request, User $user): RedirectResponse
    {
        if ($user->hasVerifiedEmail()) {
            return back()->with('error', __('messages.user_already_verified'));
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        AdminAction::record('user.verified', $user);

        return back()->with('success', __('messages.user_verified'));
    }

    public function resetPassword(ResetAdminUserPasswordRequest $request, User $user): RedirectResponse
    {
        $user->forceFill(['password' => $request->validated('password')])->save();

        AdminAction::record('user.password_reset', $user);

        return back()->with('success', __('messages.user_password_reset'));
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            abort(403, 'No puedes suspender tu propia cuenta.');
        }

        if ($user->isSuspended()) {
            return back()->with('error', __('messages.user_already_suspended'));
        }

        $user->forceFill(['suspended_at' => now()])->save();

        AdminAction::record('user.suspended', $user);

        return back()->with('success', __('messages.user_suspended'));
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        if (! $user->isSuspended()) {
            return back()->with('error', __('messages.user_not_suspended'));
        }

        $user->forceFill(['suspended_at' => null])->save();

        AdminAction::record('user.reactivated', $user);

        return back()->with('success', __('messages.user_reactivated'));
    }

    /**
     * @return array<string, mixed>
     */
    private function userShape(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->isAdmin(),
            'email_verified_at' => $user->email_verified_at?->toDateString(),
            'suspended_at' => $user->suspended_at?->toDateString(),
            'created_at' => $user->created_at->toDateString(),
            'updated_at' => $user->updated_at->toDateString(),
            'expenses_count' => $user->expenses_count,
            'total_usd' => round((float) ($user->expenses_sum_usd_amount ?? 0), 2),
            'last_expense_at' => $user->expenses_max_spent_at ? Carbon::parse($user->expenses_max_spent_at)->toDateString() : null,
        ];
    }
}
