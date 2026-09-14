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
