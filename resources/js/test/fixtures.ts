/**
 * Props de ejemplo con la forma EXACTA que emiten los presenters de PHP.
 *
 * El reparto es intencionado: `tests/Feature/*` garantiza que el servidor
 * produce esta forma, y estos tests garantizan que las páginas saben pintarla.
 * Si cambias un presenter, cambia también el fixture — y si te olvidas, uno de
 * los dos lados se pone rojo, que es justo lo que se busca.
 *
 * Ver app/Support/Presenters/.
 */

export function expenseFixture(overrides: Record<string, unknown> = {}) {
    return {
        id: 1,
        description: 'Mercado semanal',
        note: null,
        amount: '140.00',
        currency: 'ves' as const,
        payment_method: 'pago_movil' as const,
        commission: '14.00',
        exchange_rate: '30.0000',
        rate_provider: 'bcv' as const,
        usd_amount: '5.13',
        usdt_amount: '5.13',
        spent_at: '2026-09-10',
        category: { id: 3, name: 'Comida', icon: 'utensils', color: '#10B981' },
        source: { id: 5, name: 'Banesco', icon: 'landmark', color: '#3B82F6' },
        has_receipt: false,
        items: [],
        receipts: [],
        ...overrides,
    };
}

export function incomeFixture(overrides: Record<string, unknown> = {}) {
    return {
        id: 1,
        description: 'Salario',
        note: null,
        amount: '250.00',
        currency: 'usd' as const,
        exchange_rate: null,
        rate_provider: null,
        usd_amount: '250.00',
        usdt_amount: '250.00',
        received_at: '2026-09-01',
        category: { id: 7, name: 'Sueldo', icon: 'wallet', color: '#3B82F6' },
        has_receipt: false,
        receipts: [],
        ...overrides,
    };
}

export function creditCardFixture(overrides: Record<string, unknown> = {}) {
    return {
        id: 1,
        bank: 'Banesco',
        name: 'Visa Clásica',
        last_four: '4321',
        brand: 'visa' as const,
        currency: 'ves' as const,
        credit_limit: '50000.00',
        cut_day: 15,
        due_day: 5,
        annual_interest_rate: '60.00',
        minimum_payment_rate: '5.00',
        icon: 'credit-card',
        color: '#8B5CF6',
        active: true,
        note: null,
        payment_source_id: 5,
        cycle: {
            last_cut_date: '2026-08-15',
            next_cut_date: '2026-09-15',
            next_due_date: '2026-10-05',
            days_to_cut: 2,
        },
        balance: {
            closing: 10000,
            charges_since_cut: 2014,
            payments_since_cut: 3000,
            projected_used: 9014,
            available: 40986,
            usage_percent: 18,
            is_estimate: true,
            foreign_movements: 0,
        },
        usd: { projected_used: 300.47 },
        ...overrides,
    };
}

export function creditCardDetailFixture(
    overrides: Record<string, unknown> = {},
) {
    return {
        ...creditCardFixture(),
        statements: [
            {
                id: 1,
                cut_date: '2026-08-15',
                due_date: '2026-09-05',
                closing_balance: '10000.00',
                minimum_payment: '500.00',
                currency: 'ves' as const,
                exchange_rate: '30.0000',
                usd_amount: '333.33',
                paid_at: null,
                note: null,
            },
        ],
        payments: [
            {
                id: 1,
                amount: '3000.00',
                currency: 'ves' as const,
                usd_amount: '100.00',
                paid_at: '2026-09-01',
                statement_id: 1,
                note: null,
            },
        ],
        ...overrides,
    };
}

export const rateFixture = {
    rate: '30.0000',
    provider: 'bcv',
    source: 'api',
    rate_date: '2026-09-13',
};

/** Forma de App\Services\AccountStatementService::build(). */
export function statementFixture(overrides: Record<string, unknown> = {}) {
    return {
        period: { from: '2026-10-01', to: '2026-10-31', days: 31 },
        tracking: { expenses: true, incomes: true },
        totals: {
            incomes: { usd: 500, usdt: 500, count: 1 },
            expenses: { usd: 60, usdt: 60, count: 3, commissions_ves: 14 },
            net: { usd: 440, usdt: 440 },
            savings_rate: 88,
            daily_average_expense_usd: 1.94,
        },
        by_currency: {
            expenses: { usd: 50, ves: 374, usdt: 0 },
            incomes: { usd: 500, ves: 0, usdt: 0 },
        },
        expense_categories: [
            {
                id: 3,
                name: 'Comida',
                color: '#10B981',
                icon: 'utensils',
                total_usd: 60,
                count: 3,
                percent: 100,
            },
        ],
        income_categories: [],
        sources: [
            {
                id: 5,
                name: 'Efectivo',
                color: '#3B82F6',
                icon: 'wallet',
                total_usd: 60,
                count: 3,
                percent: 100,
            },
        ],
        largest_expenses: [
            {
                id: 1,
                date: '2026-10-03',
                description: 'Mercado',
                category: 'Comida',
                usd_amount: 30,
            },
        ],
        previous: {
            from: '2026-08-31',
            to: '2026-09-30',
            expenses_usd: 40,
            incomes_usd: 0,
            expenses_variation: 50,
            incomes_variation: null,
        },
        budget: { monthly: 200, spent: 60, percent: 30, remaining: 140 },
        movements: [
            {
                type: 'income' as const,
                id: 1,
                date: '2026-10-03',
                description: 'Sueldo de octubre',
                category: 'Sueldo',
                source: null,
                currency: 'usd' as const,
                amount: 500,
                usd_amount: 500,
                running_usd: 500,
            },
            {
                type: 'expense' as const,
                id: 1,
                date: '2026-10-03',
                description: 'Mercado',
                category: 'Comida',
                source: 'Efectivo',
                currency: 'usd' as const,
                amount: 30,
                usd_amount: 30,
                running_usd: 470,
            },
        ],
        movements_truncated: false,
        ...overrides,
    };
}

/** Forma de App\Support\Presenters\AiSettingPresenter::present(). */
export function aiSettingFixture(overrides: Record<string, unknown> = {}) {
    return {
        provider: 'anthropic' as const,
        provider_label: 'Anthropic (Claude)',
        model: null,
        resolved_model: 'claude-opus-5-5',
        base_url: null,
        has_key: true,
        masked_key: '••••abcd',
        ready: true,
        supports_vision: true,
        supports_pdf: true,
        last_verified_at: null,
        updated_at: '2026-11-06T12:00:00+00:00',
        ...overrides,
    };
}

/** Un proveedor de AiSettingPresenter::providers(). */
export function aiProviderFixture(overrides: Record<string, unknown> = {}) {
    return {
        value: 'anthropic' as const,
        label: 'Anthropic (Claude)',
        default_model: 'claude-opus-5-5',
        models: ['claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5'],
        default_base_url: null,
        endpoint_options: [] as { key: string; value: string }[],
        requires_key: true,
        requires_base_url: false,
        supports_vision: true,
        supports_pdf: true,
        key_url: 'https://console.anthropic.com/settings/keys',
        available: true,
        ...overrides,
    };
}
