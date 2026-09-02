<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\AdminAction;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $categories = Category::query()
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
            ->through(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'color' => $category->color,
                'budget' => $category->budget,
                'is_system' => $category->is_system,
                'expenses_count' => $category->expenses_count,
                'total_usd' => round((float) ($category->expenses_sum_usd_amount ?? 0), 2),
                'can_delete' => $category->expenses_count === 0,
                'user' => [
                    'id' => $category->user->id,
                    'name' => $category->user->name,
                    'email' => $category->user->email,
                ],
            ]);

        return Inertia::render('admin/categories/index', [
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'user_id' => $request->integer('user_id'),
            ],
            'users' => $this->userOptions(),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        AdminAction::record('category.updated', $category);

        return back()->with('success', __('messages.category_updated'));
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        if ($category->expenses()->exists()) {
            return back()->with('error', __('messages.category_delete_blocked'));
        }

        AdminAction::record('category.deleted', $category);
        $category->delete();

        return back()->with('success', __('messages.category_deleted'));
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
