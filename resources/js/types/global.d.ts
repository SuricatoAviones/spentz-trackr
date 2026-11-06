import type { Auth } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            locale: string;
            translations: {
                messages: Record<string, string>;
                admin: Record<string, string>;
            };
            [key: string]: unknown;
        };
    }
}

export type PaymentMethodCode = 'pago_movil' | 'transferencia';

export type TrackingType = 'expenses' | 'income' | 'both';

export type CommissionDefaults = {
    min_commission: string | number | null;
    commission_rate: string | number | null;
};

export type Income = {
    id: number;
    description: string;
    note?: string | null;
    amount: string | number;
    currency: 'usd' | 'ves' | 'usdt';
    exchange_rate?: string | number | null;
    rate_provider?: 'bcv' | 'paralelo' | 'user' | 'custom' | null;
    usd_amount: string | number;
    usdt_amount: string | number;
    received_at: string;
    created_at?: string;
    category: {
        id: number;
        name: string;
        icon: string;
        color: string;
    };
    has_receipt: boolean;
    receipts?: {
        id: number;
        url: string;
        original_name: string;
    }[];
};

export type ExpenseItem = {
    id: number;
    currency: 'usd' | 'ves' | 'usdt';
    amount: string | number;
    exchange_rate?: string | number | null;
    usd_amount: string | number;
    usdt_amount: string | number;
};

export type Expense = {
    id: number;
    description: string;
    note?: string | null;
    amount: string | number;
    currency: 'usd' | 'ves' | 'usdt';
    payment_method?: PaymentMethodCode | null;
    commission?: string | number | null;
    exchange_rate?: string | number | null;
    rate_provider?: 'bcv' | 'paralelo' | 'user' | 'custom' | null;
    usd_amount: string | number;
    usdt_amount: string | number;
    spent_at: string;
    created_at?: string;
    category: {
        id: number;
        name: string;
        icon: string;
        color: string;
    };
    source: {
        id: number;
        name: string;
        icon: string;
        color: string;
    };
    has_receipt: boolean;
    items?: ExpenseItem[];
    receipts?: {
        id: number;
        url: string;
        original_name: string;
    }[];
};

export type CategoryOption = {
    id: number;
    name: string;
    icon: string;
    color: string;
};

export type SourceOption = CategoryOption;

export type RateInfo = {
    rate: string | number;
    source: string;
    provider: string;
    rate_date: string;
};

export type RateOptions = {
    bcv: string | number;
    paralelo: string | number;
    manual: string | number;
};

export type CreditCardCycle = {
    last_cut_date: string;
    next_cut_date: string;
    next_due_date: string;
    days_to_cut: number;
};

export type CreditCardBalance = {
    /** Saldo del último corte: la cifra del banco. `null` si aún no hay cortes. */
    closing: number | null;
    charges_since_cut: number;
    payments_since_cut: number;
    /** Corte + consumos − abonos. Estimación cuando `is_estimate` es true. */
    projected_used: number;
    available: number;
    usage_percent: number;
    is_estimate: boolean;
    /** Movimientos en otra moneda que no se suman (ver CreditCardCycleService). */
    foreign_movements: number;
};

export type CreditCardSummary = {
    id: number;
    bank: string;
    name: string;
    last_four: string | null;
    brand: 'visa' | 'mastercard' | 'amex' | 'other';
    currency: 'usd' | 'ves' | 'usdt';
    credit_limit: string | number;
    cut_day: number;
    due_day: number;
    annual_interest_rate: string | number | null;
    minimum_payment_rate: string | number | null;
    icon: string;
    color: string;
    active: boolean;
    note: string | null;
    payment_source_id: number | null;
    cycle: CreditCardCycle;
    balance: CreditCardBalance;
    usd: { projected_used: number };
};

export type CreditCardStatement = {
    id: number;
    cut_date: string;
    due_date: string;
    closing_balance: string | number;
    minimum_payment: string | number | null;
    currency: 'usd' | 'ves' | 'usdt';
    exchange_rate: string | number | null;
    usd_amount: string | number;
    paid_at: string | null;
    note: string | null;
};

export type CreditCardPayment = {
    id: number;
    amount: string | number;
    currency: 'usd' | 'ves' | 'usdt';
    usd_amount: string | number;
    paid_at: string;
    statement_id: number | null;
    note: string | null;
};

export type CreditCardDetail = CreditCardSummary & {
    statements: CreditCardStatement[];
    payments: CreditCardPayment[];
};

/* ------------------------------------------------------------------ */
/* Asistente de IA. Contrato de App\Support\Presenters\AiSettingPresenter,
 * App\Actions\Ai\* y App\Services\AccountStatementService: si cambia uno,
 * cambia el otro. */

export type AiProviderCode =
    | 'openai'
    | 'anthropic'
    | 'gemini'
    | 'mistral'
    | 'groq'
    | 'deepseek'
    | 'openrouter'
    | 'ollama'
    | 'custom';

export type AiProviderOption = {
    value: AiProviderCode;
    label: string;
    default_model: string | null;
    models: string[];
    default_base_url: string | null;
    requires_key: boolean;
    requires_base_url: boolean;
    supports_vision: boolean;
    supports_pdf: boolean;
    key_url: string | null;
    available: boolean;
};

export type AiSettingSummary = {
    provider: AiProviderCode;
    provider_label: string;
    model: string | null;
    resolved_model: string;
    base_url: string | null;
    has_key: boolean;
    masked_key: string | null;
    ready: boolean;
    supports_vision: boolean;
    supports_pdf: boolean;
    last_verified_at: string | null;
    updated_at: string | null;
};

export type AiScanCapabilities = {
    available: boolean;
    pdf: boolean;
};

export type ReceiptDraftWarning =
    'not_receipt' | 'no_total' | 'low_confidence' | 'date_future';

export type ReceiptDraft = {
    description: string | null;
    amount: string | null;
    currency: 'usd' | 'ves' | 'usdt' | null;
    spent_at: string | null;
    category_id: number | null;
    payment_source_id: number | null;
    payment_method: PaymentMethodCode | null;
    exchange_rate: number | null;
    note: string | null;
    merchant: string | null;
    items: { description: string; amount: number | null }[];
    confidence: number | null;
    warnings: ReceiptDraftWarning[];
};

export type ExpenseProposal = {
    description: string;
    amount: number;
    currency: 'usd' | 'ves' | 'usdt';
    spent_at: string;
    category_id: number;
    category_name: string | null;
    payment_source_id: number;
    source_name: string | null;
    payment_method: PaymentMethodCode | null;
    note: string | null;
};

export type AssistantReply = {
    reply: string;
    proposals: ExpenseProposal[];
    usage: { input_tokens: number; output_tokens: number };
};

export type StatementBreakdownRow = {
    id: number | null;
    name: string;
    color: string;
    icon: string;
    total_usd: number;
    count: number;
    percent: number;
};

export type StatementMovement = {
    type: 'income' | 'expense';
    id: number;
    date: string;
    description: string;
    category: string | null;
    source: string | null;
    currency: 'usd' | 'ves' | 'usdt';
    amount: number;
    usd_amount: number;
    running_usd: number;
};

export type AccountStatement = {
    period: { from: string; to: string; days: number };
    tracking: { expenses: boolean; incomes: boolean };
    totals: {
        incomes: { usd: number; usdt: number; count: number };
        expenses: {
            usd: number;
            usdt: number;
            count: number;
            commissions_ves: number;
        };
        net: { usd: number; usdt: number };
        savings_rate: number | null;
        daily_average_expense_usd: number;
    };
    by_currency: {
        expenses: Record<'usd' | 'ves' | 'usdt', number>;
        incomes: Record<'usd' | 'ves' | 'usdt', number>;
    };
    expense_categories: StatementBreakdownRow[];
    income_categories: StatementBreakdownRow[];
    sources: StatementBreakdownRow[];
    largest_expenses: {
        id: number;
        date: string;
        description: string;
        category: string | null;
        usd_amount: number;
    }[];
    previous: {
        from: string;
        to: string;
        expenses_usd: number;
        incomes_usd: number;
        expenses_variation: number | null;
        incomes_variation: number | null;
    };
    budget: {
        monthly: number;
        spent: number;
        percent: number;
        remaining: number;
    } | null;
    movements: StatementMovement[];
    movements_truncated: boolean;
};
