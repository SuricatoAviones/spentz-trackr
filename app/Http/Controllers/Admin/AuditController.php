<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\ExpenseReceipt;
use App\Models\PaymentSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $actions = AdminAction::query()
            ->with([
                'admin:id,name,email',
                'target.morphTo' => fn (MorphTo $morph) => $morph->constrain([
                    User::class => fn ($query) => $query->select('id', 'name', 'email'),
                    Category::class => fn ($query) => $query->select('id', 'name'),
                    PaymentSource::class => fn ($query) => $query->select('id', 'name'),
                    Expense::class => fn ($query) => $query->select('id', 'description'),
                    ExchangeRate::class => fn ($query) => $query->select('id', 'rate_date', 'source', 'provider'),
                    ExpenseReceipt::class => fn ($query) => $query->select('id', 'original_name'),
                ]),
            ])
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AdminAction $action) => [
                'id' => $action->id,
                'action' => $action->action,
                'created_at' => $action->created_at->toDateTimeString(),
                'admin' => $action->admin !== null ? [
                    'id' => $action->admin->id,
                    'name' => $action->admin->name,
                    'email' => $action->admin->email,
                ] : null,
                'target' => $this->targetLabel($action),
            ]);

        return Inertia::render('admin/audit/index', [
            'actions' => $actions,
        ]);
    }

    private function targetLabel(AdminAction $action): ?string
    {
        $target = $action->target;

        return match (true) {
            $target instanceof User => $target->name,
            $target instanceof Category, $target instanceof PaymentSource => $target->name,
            $target instanceof Expense => $target->description,
            $target instanceof ExchangeRate => $target->rate_date->toDateString().' ('.$target->source.')',
            $target instanceof ExpenseReceipt => $target->original_name,
            default => null,
        };
    }
}
