export const CURRENCY_META = {
    usd: {
        label: 'USD',
        color: '#10B981',
        className: 'bg-emerald-500/15 text-emerald-400',
    },
    ves: {
        label: 'Bs',
        color: '#F59E0B',
        className: 'bg-amber-500/15 text-amber-400',
    },
    usdt: {
        label: 'USDT',
        color: '#3B82F6',
        className: 'bg-blue-500/15 text-blue-400',
    },
} as const;

export type CurrencyCode = keyof typeof CURRENCY_META;

export function formatAmount(value: string | number): string {
    const number = Number(value ?? 0);

    return new Intl.NumberFormat('es-VE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(number);
}

export function formatRate(value: string | number): string {
    const number = Number(value ?? 0);

    return new Intl.NumberFormat('es-VE', {
        minimumFractionDigits: number % 1 === 0 ? 0 : 4,
        maximumFractionDigits: 4,
    }).format(number);
}

export function formatDate(isoDate: string): string {
    const date = new Date(`${isoDate}T12:00:00`);

    return new Intl.DateTimeFormat('es-VE', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(date);
}

export function formatMonthLabel(isoDate: string): string {
    const date = new Date(`${isoDate}T12:00:00`);

    return new Intl.DateTimeFormat('es-VE', {
        day: '2-digit',
        month: 'short',
    })
        .format(date)
        .toUpperCase();
}

export function isToday(isoDate: string): boolean {
    const today = new Date();
    const date = new Date(`${isoDate}T12:00:00`);

    return today.toDateString() === date.toDateString();
}

export function isYesterday(isoDate: string): boolean {
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);

    return (
        yesterday.toDateString() ===
        new Date(`${isoDate}T12:00:00`).toDateString()
    );
}

export function dateToInputValue(date: Date): string {
    return date.toISOString().split('T')[0];
}

export function todayInputValue(): string {
    return dateToInputValue(new Date());
}
