<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $stats = [
            'total_users' => User::query()->count(),
            'new_users_month' => User::query()
                ->whereBetween('created_at', [$monthStart, $monthEnd.' 23:59:59'])
                ->count(),
            'verified_users' => User::query()->whereNotNull('email_verified_at')->count(),
            'admin_users' => User::query()->where('is_admin', true)->count(),
            'active_users' => User::query()->whereHas('expenses')->count(),
            'total_expenses' => Expense::query()->count(),
            'total_usd' => round((float) Expense::query()->sum('usd_amount'), 2),
            'monthly_expenses' => Expense::query()
                ->forPeriod($monthStart, $monthEnd)
                ->count(),
        ];

        $totalUsd = (float) $stats['total_usd'];

        $monthlyTrend = collect(range(1, 12))->map(function (int $month): array {
            $start = now()->startOfYear()->addMonths($month - 1)->toDateString();
            $end = now()->startOfYear()->addMonths($month - 1)->endOfMonth()->toDateString();

            return [
                'month' => strftime('%b', strtotime($start)),
                'total' => round((float) Expense::query()->forPeriod($start, $end)->sum('usd_amount'), 2),
            ];
        })->values();

        $topCategories = Expense::query()
            ->selectRaw('category_id, sum(usd_amount) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(function ($row) use ($totalUsd): array {
                $category = $row->category()->first(['id', 'name', 'color']);
                $total = round((float) $row->total, 2);

                return [
                    'name' => $category?->name ?? 'Sin categoría',
                    'color' => $category?->color ?? '#6B7280',
                    'total' => $total,
                    'percent' => $totalUsd > 0 ? round(($total / $totalUsd) * 100, 1) : 0.0,
                ];
            })
            ->values();

        $topUsers = Expense::query()
            ->selectRaw('user_id, sum(usd_amount) as total')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(function ($row) use ($totalUsd): array {
                $user = $row->user()->first(['id', 'name']);
                $total = round((float) $row->total, 2);

                return [
                    'id' => $user?->id,
                    'name' => $user?->name ?? 'Usuario eliminado',
                    'total' => $total,
                    'percent' => $totalUsd > 0 ? round(($total / $totalUsd) * 100, 1) : 0.0,
                ];
            })
            ->values();

        $recentUsers = User::query()
            ->withCount('expenses')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->isAdmin(),
                'email_verified_at' => $user->email_verified_at?->toDateString(),
                'expenses_count' => $user->expenses_count,
                'created_at' => $user->created_at->toDateString(),
            ]);

        return Inertia::render('admin/dashboard', [
            'stats' => $stats,
            'monthlyTrend' => $monthlyTrend,
            'topCategories' => $topCategories,
            'topUsers' => $topUsers,
            'recentUsers' => $recentUsers,
        ]);
    }
}
