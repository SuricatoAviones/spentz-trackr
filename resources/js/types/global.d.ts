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
