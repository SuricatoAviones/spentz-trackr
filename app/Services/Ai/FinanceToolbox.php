<?php

namespace App\Services\Ai;

use App\Enums\CategoryType;
use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PaymentSource;
use App\Models\RecurringPayment;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Services\AccountStatementService;
use App\Services\ExchangeRateService;
use App\Support\Presenters\CreditCardPresenter;
use App\Support\Presenters\RecurringPaymentPresenter;
use App\Support\Presenters\SavingsGoalPresenter;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Las herramientas que el asistente puede invocar.
 *
 * Reglas que no se negocian:
 *  - **Todo filtra por el usuario** que conversa. El modelo elige argumentos,
 *    nunca el `user_id`: un id de categoría ajeno simplemente no aparece.
 *  - **Solo lectura.** La única herramienta que "escribe" es
 *    `propose_expense`, y no escribe: deja una propuesta que el usuario
 *    confirma con un botón. Un texto en la nota de un gasto que diga "borra
 *    todo" no puede hacer nada, porque no hay herramienta que borre.
 *  - **Las cifras salen calculadas.** El modelo cita totales, no suma listas.
 */
class FinanceToolbox
{
    private const MAX_RANGE_DAYS = 400;

    /** @var list<array<string, mixed>> */
    private array $proposals = [];

    public function __construct(
        private readonly AccountStatementService $statements,
        private readonly ExchangeRateService $rates,
    ) {}

    /**
     * @return list<AiTool>
     */
    public function tools(): array
    {
        $dateRange = [
            'from' => ['type' => 'string', 'description' => 'Start date, inclusive, YYYY-MM-DD.'],
            'to' => ['type' => 'string', 'description' => 'End date, inclusive, YYYY-MM-DD.'],
        ];

        return [
            new AiTool(
                'get_financial_summary',
                'Totals for a period: incomes, expenses and net in USD/USDT equivalents, totals by original currency, breakdown by category and payment source, largest expenses, comparison with the previous period of equal length and monthly budget status. Use it for any "how much did I spend/earn" question.',
                $dateRange,
                ['from', 'to'],
            ),
            new AiTool(
                'list_transactions',
                'Individual expenses and/or incomes matching filters, newest first (or by amount). Use it to find specific purchases or answer detailed questions.',
                [
                    ...$dateRange,
                    'type' => ['type' => 'string', 'enum' => ['expense', 'income', 'all'], 'description' => 'Which records to list. Default expense.'],
                    'category_id' => ['type' => 'integer', 'description' => 'Optional category id (see get_categories_and_sources).'],
                    'payment_source_id' => ['type' => 'integer', 'description' => 'Optional payment source id (expenses only).'],
                    'search' => ['type' => 'string', 'description' => 'Optional text to look for in description or note.'],
                    'min_usd' => ['type' => 'number', 'description' => 'Optional minimum USD equivalent.'],
                    'sort' => ['type' => 'string', 'enum' => ['date', 'amount'], 'description' => 'Sort order. Default date.'],
                    'limit' => ['type' => 'integer', 'description' => 'Max rows, 1-50. Default 20.'],
                ],
                ['from', 'to'],
            ),
            new AiTool(
                'get_account_statement',
                'Full account statement for a period: totals plus every movement in chronological order with the running net in USD. Use it when the user asks for an "estado de cuenta" or statement.',
                $dateRange,
                ['from', 'to'],
            ),
            new AiTool(
                'get_categories_and_sources',
                'The user\'s expense and income categories (with monthly budget and this month\'s spending) and payment sources, with their ids.',
            ),
            new AiTool(
                'get_budget_status',
                'Current month: overall monthly budget, spent so far, remaining, and per-category budgets.',
            ),
            new AiTool('get_savings_goals', 'Savings goals with target, saved amount, progress and deadline.'),
            new AiTool('get_recurring_payments', 'Recurring payments (subscriptions, bills) with amount, frequency, next due date and whether they are due.'),
            new AiTool('get_credit_cards', 'Credit cards with limit, cycle dates, last statement balance and projected usage. Projected figures flagged is_estimate are estimates.'),
            new AiTool('get_exchange_rates', 'Today\'s Bs per USD exchange rates known to the app (BCV official, parallel, and the user\'s manual rate).'),
            new AiTool(
                'propose_expense',
                'Prepare an expense for the user to confirm. It does NOT save anything: the app shows a confirmation card. Use valid ids from get_categories_and_sources.',
                [
                    'description' => ['type' => 'string', 'description' => 'Short description, max 255 chars.'],
                    'amount' => ['type' => 'number', 'description' => 'Amount in the given currency, without commission.'],
                    'currency' => ['type' => 'string', 'enum' => ['usd', 'ves', 'usdt']],
                    'spent_at' => ['type' => 'string', 'description' => 'Date YYYY-MM-DD, not in the future.'],
                    'category_id' => ['type' => 'integer', 'description' => 'Expense category id.'],
                    'payment_source_id' => ['type' => 'integer', 'description' => 'Payment source id.'],
                    'payment_method' => ['type' => 'string', 'enum' => ['pago_movil', 'transferencia'], 'description' => 'Only for ves expenses paid by pago móvil or transfer.'],
                    'note' => ['type' => 'string', 'description' => 'Optional note.'],
                ],
                ['description', 'amount', 'currency', 'spent_at', 'category_id', 'payment_source_id'],
            ),
        ];
    }

    /**
     * @return array<mixed>
     */
    public function execute(User $user, AiToolCall $call): array
    {
        try {
            return match ($call->name) {
                'get_financial_summary' => $this->summary($user, $call->arguments),
                'list_transactions' => $this->transactions($user, $call->arguments),
                'get_account_statement' => $this->statement($user, $call->arguments),
                'get_categories_and_sources' => $this->catalog($user),
                'get_budget_status' => $this->budget($user),
                'get_savings_goals' => $this->savingsGoals($user),
                'get_recurring_payments' => $this->recurringPayments($user),
                'get_credit_cards' => $this->creditCards($user),
                'get_exchange_rates' => $this->rates->ratesForUser($user),
                'propose_expense' => $this->proposeExpense($user, $call->arguments),
                default => ['error' => "Unknown tool {$call->name}."],
            };
        } catch (Throwable $exception) {
            report($exception);

            return ['error' => 'The tool failed. Tell the user the data could not be read.'];
        }
    }

    /**
     * Gastos que el modelo propuso en esta conversación, listos para que el
     * usuario los confirme.
     *
     * @return list<array<string, mixed>>
     */
    public function proposals(): array
    {
        return $this->proposals;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function summary(User $user, array $arguments): array
    {
        [$from, $to] = $this->range($arguments);

        $statement = $this->statements->build($user, $from, $to, 0);
        unset($statement['movements'], $statement['movements_truncated']);

        return $statement;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function statement(User $user, array $arguments): array
    {
        [$from, $to] = $this->range($arguments);

        $statement = $this->statements->build($user, $from, $to, 150);

        // Lo que el modelo necesita para leer el extracto; colores e iconos
        // solo engordan el contexto.
        foreach (['expense_categories', 'income_categories', 'sources'] as $key) {
            $statement[$key] = array_map(
                fn (array $row): array => array_diff_key($row, array_flip(['color', 'icon'])),
                $statement[$key],
            );
        }

        return $statement;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function transactions(User $user, array $arguments): array
    {
        [$from, $to] = $this->range($arguments);

        $type = in_array($arguments['type'] ?? null, ['expense', 'income', 'all'], true) ? $arguments['type'] : 'expense';
        $limit = max(1, min(50, (int) ($arguments['limit'] ?? 20)));
        $byAmount = ($arguments['sort'] ?? 'date') === 'amount';
        $search = trim((string) ($arguments['search'] ?? ''));
        $categoryId = isset($arguments['category_id']) ? (int) $arguments['category_id'] : null;
        $sourceId = isset($arguments['payment_source_id']) ? (int) $arguments['payment_source_id'] : null;
        $minUsd = isset($arguments['min_usd']) ? (float) $arguments['min_usd'] : null;

        $rows = [];

        if ($type !== 'income' && $user->tracking_type !== 'income') {
            $expenses = Expense::query()
                ->forUser($user->id)
                ->with(['category:id,name', 'paymentSource:id,name'])
                ->whereDate('spent_at', '>=', $from->toDateString())
                ->whereDate('spent_at', '<=', $to->toDateString())
                ->when($categoryId, fn ($query, int $id) => $query->where('category_id', $id))
                ->when($sourceId, fn ($query, int $id) => $query->where('payment_source_id', $id))
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('description', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")))
                ->when($minUsd !== null, fn ($query) => $query->where('usd_amount', '>=', $minUsd))
                ->orderByDesc($byAmount ? 'usd_amount' : 'spent_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            foreach ($expenses as $expense) {
                $rows[] = [
                    'type' => 'expense',
                    'id' => $expense->id,
                    'date' => $expense->spent_at->toDateString(),
                    'description' => $expense->description,
                    'note' => $expense->note,
                    'category' => $expense->category->name,
                    'source' => $expense->paymentSource->name,
                    'currency' => $expense->currency->value,
                    'amount' => round((float) $expense->amount, 2),
                    'commission' => $expense->commission !== null ? round((float) $expense->commission, 2) : null,
                    'exchange_rate' => $expense->exchange_rate !== null ? (float) $expense->exchange_rate : null,
                    'usd_amount' => round((float) $expense->usd_amount, 2),
                ];
            }
        }

        if ($type !== 'expense' && $user->tracking_type !== 'expenses') {
            $incomes = Income::query()
                ->forUser($user->id)
                ->with(['category:id,name'])
                ->whereDate('received_at', '>=', $from->toDateString())
                ->whereDate('received_at', '<=', $to->toDateString())
                ->when($categoryId, fn ($query, int $id) => $query->where('category_id', $id))
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('description', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")))
                ->when($minUsd !== null, fn ($query) => $query->where('usd_amount', '>=', $minUsd))
                ->orderByDesc($byAmount ? 'usd_amount' : 'received_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            foreach ($incomes as $income) {
                $rows[] = [
                    'type' => 'income',
                    'id' => $income->id,
                    'date' => $income->received_at->toDateString(),
                    'description' => $income->description,
                    'note' => $income->note,
                    'category' => $income->category->name,
                    'currency' => $income->currency->value,
                    'amount' => round((float) $income->amount, 2),
                    'usd_amount' => round((float) $income->usd_amount, 2),
                ];
            }
        }

        usort($rows, fn (array $a, array $b): int => $byAmount
            ? $b['usd_amount'] <=> $a['usd_amount']
            : [$b['date'], $b['id']] <=> [$a['date'], $a['id']]);

        $rows = array_slice($rows, 0, $limit);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'count' => count($rows),
            'limit' => $limit,
            'total_usd_of_listed' => round(array_sum(array_column($rows, 'usd_amount')), 2),
            'transactions' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(User $user): array
    {
        $monthStart = Date::today()->startOfMonth()->toDateString();
        $monthEnd = Date::today()->endOfMonth()->toDateString();

        $categories = Category::query()
            ->where('user_id', $user->id)
            ->withSum(['expenses as month_spent_usd' => fn ($query) => $query
                ->whereDate('spent_at', '>=', $monthStart)
                ->whereDate('spent_at', '<=', $monthEnd)], 'usd_amount')
            ->orderBy('name')
            ->get();

        return [
            'expense_categories' => $categories
                ->where('type', CategoryType::Expense)
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'monthly_budget_usd' => $category->budget !== null ? (float) $category->budget : null,
                    'spent_this_month_usd' => round((float) $category->getAttribute('month_spent_usd'), 2),
                ])
                ->values()
                ->all(),
            'income_categories' => $categories
                ->where('type', CategoryType::Income)
                ->map(fn (Category $category): array => ['id' => $category->id, 'name' => $category->name])
                ->values()
                ->all(),
            'payment_sources' => PaymentSource::query()
                ->where('user_id', $user->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (PaymentSource $source): array => ['id' => $source->id, 'name' => $source->name])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function budget(User $user): array
    {
        $catalog = $this->catalog($user);
        $monthly = $user->monthly_budget !== null ? (float) $user->monthly_budget : null;
        $spent = round(array_sum(array_column($catalog['expense_categories'], 'spent_this_month_usd')), 2);
        $today = Date::today();

        return [
            'month' => $today->format('Y-m'),
            'day_of_month' => $today->day,
            'days_in_month' => $today->daysInMonth,
            'monthly_budget_usd' => $monthly,
            'spent_usd' => $spent,
            'remaining_usd' => $monthly !== null ? round($monthly - $spent, 2) : null,
            'percent_used' => $monthly !== null && $monthly > 0 ? round(($spent / $monthly) * 100, 1) : null,
            'categories_with_budget' => array_values(array_filter(
                $catalog['expense_categories'],
                fn (array $category): bool => $category['monthly_budget_usd'] !== null,
            )),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function savingsGoals(User $user): array
    {
        return [
            'goals' => SavingsGoal::query()
                ->where('user_id', $user->id)
                ->withSum('contributions as saved_usd', 'usd_amount')
                ->orderBy('deadline')
                ->get()
                ->map(function (SavingsGoal $goal): array {
                    $presented = SavingsGoalPresenter::present($goal->setRelation('contributions', $goal->newCollection()));

                    return array_intersect_key($presented, array_flip([
                        'id', 'name', 'target_amount', 'currency', 'target_usd_amount', 'deadline', 'achieved_at', 'saved', 'percent',
                    ]));
                })
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recurringPayments(User $user): array
    {
        return [
            'today' => Date::today()->toDateString(),
            'payments' => RecurringPayment::query()
                ->where('user_id', $user->id)
                ->orderBy('next_due_date')
                ->get()
                ->map(fn (RecurringPayment $payment): array => array_diff_key(
                    RecurringPaymentPresenter::present($payment),
                    array_flip(['icon', 'color', 'category_id']),
                ))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function creditCards(User $user): array
    {
        return [
            'cards' => CreditCard::query()
                ->where('user_id', $user->id)
                ->get()
                ->map(fn (CreditCard $card): array => array_diff_key(
                    CreditCardPresenter::present($card),
                    array_flip(['icon', 'color', 'payment_source_id']),
                ))
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function proposeExpense(User $user, array $arguments): array
    {
        $validator = Validator::make($arguments, [
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'spent_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('user_id', $user->id)->where('type', CategoryType::Expense->value)],
            'payment_source_id' => ['required', 'integer', Rule::exists('payment_sources', 'id')->where('user_id', $user->id)],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return [
                'status' => 'invalid',
                'errors' => $validator->errors()->toArray(),
                'hint' => 'Fix the arguments (call get_categories_and_sources for valid ids) or ask the user for the missing data.',
            ];
        }

        $data = $validator->validated();
        $isVes = $data['currency'] === Currency::Ves->value;

        $proposal = [
            'description' => $data['description'],
            'amount' => round((float) $data['amount'], 2),
            'currency' => $data['currency'],
            'spent_at' => $data['spent_at'],
            'category_id' => (int) $data['category_id'],
            'category_name' => Category::query()->whereKey($data['category_id'])->value('name'),
            'payment_source_id' => (int) $data['payment_source_id'],
            'source_name' => PaymentSource::query()->whereKey($data['payment_source_id'])->value('name'),
            'payment_method' => $isVes ? ($data['payment_method'] ?? null) : null,
            'note' => $data['note'] ?? null,
        ];

        $this->proposals[] = $proposal;

        return [
            'status' => 'proposed',
            'message' => 'A confirmation card is shown to the user. Nothing is saved until they confirm.',
            'proposal' => $proposal,
        ];
    }

    /**
     * Rango de fechas pedido por el modelo, saneado: fechas válidas, en orden y
     * con un tope de longitud. Sin fechas, el mes en curso.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function range(array $arguments): array
    {
        $from = $this->date($arguments['from'] ?? null) ?? Date::today()->startOfMonth();
        $to = $this->date($arguments['to'] ?? null) ?? Date::today();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            $from = $to->subDays(self::MAX_RANGE_DAYS);
        }

        return [$from, $to];
    }

    private function date(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            return Date::createFromFormat('Y-m-d', $value)?->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
