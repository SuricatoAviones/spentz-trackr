import { CURRENCY_META } from '@/lib/format';
import type { CurrencyCode } from '@/lib/format';

export function CurrencyChip({
    currency,
    className = '',
}: {
    currency: CurrencyCode;
    className?: string;
}) {
    const meta = CURRENCY_META[currency];

    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-wide ${meta.className} ${className}`}
        >
            {meta.label}
        </span>
    );
}
