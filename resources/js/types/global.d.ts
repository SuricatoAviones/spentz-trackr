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
