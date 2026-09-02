import { Link, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CurrencyChip } from '@/components/tracker/currency-chip';
import type { CurrencyCode } from '@/lib/format';
import { formatAmount } from '@/lib/format';
import { destroy as incomesDestroy } from '@/routes/incomes';

const OPEN_OFFSET = -84;
const CLOSE_THRESHOLD = -42;

function clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, value));
}

export function IncomeListItem({
    income,
}: {
    income: {
        id: number;
        description: string;
        amount: string | number;
        currency: CurrencyCode;
        received_at: string;
        category: { name: string; icon: string; color: string };
    };
}) {
    const [dragX, setDragX] = useState(0);
    const [open, setOpen] = useState(false);
    const [dragging, setDragging] = useState(false);
    const startX = useRef(0);
    const baseX = useRef(0);
    const { t } = useTranslation();

    function destroy() {
        if (
            confirm(
                t('incomes.delete_confirm', {
                    description: income.description,
                }),
            )
        ) {
            router.delete(incomesDestroy(income.id).url);
        }
    }

    return (
        <div className="relative overflow-hidden rounded-xl">
            <button
                type="button"
                onClick={destroy}
                className="absolute inset-y-0 right-0 flex w-20 items-center justify-center bg-destructive/90 text-destructive-foreground"
                aria-label={t('incomes.delete_aria', {
                    description: income.description,
                })}
            >
                <Trash2 className="size-5" />
            </button>

            <Link
                href={`/incomes/${income.id}`}
                onClick={(event) => {
                    if (open) {
                        event.preventDefault();
                        setOpen(false);
                        setDragX(0);
                    }
                }}
                onTouchStart={(event) => {
                    startX.current = event.touches[0].clientX;
                    baseX.current = open ? OPEN_OFFSET : 0;
                    setDragging(true);
                }}
                onTouchMove={(event) => {
                    if (!dragging) {
                        return;
                    }

                    setDragX(
                        clamp(
                            baseX.current +
                                (event.touches[0].clientX - startX.current),
                            OPEN_OFFSET,
                            0,
                        ),
                    );
                }}
                onTouchEnd={() => {
                    setDragging(false);

                    const shouldOpen = dragX < CLOSE_THRESHOLD;

                    setOpen(shouldOpen);
                    setDragX(shouldOpen ? OPEN_OFFSET : 0);
                }}
                className="flex items-center gap-3 rounded-xl bg-surface-low p-3.5 transition-colors hover:bg-surface-high active:scale-[0.99]"
                style={{
                    transform: `translateX(${dragX}px)`,
                    transition: dragging ? 'none' : 'transform 0.2s ease',
                    touchAction: 'pan-y',
                }}
            >
                <span
                    className="flex size-10 shrink-0 items-center justify-center rounded-full"
                    style={{
                        backgroundColor: `${income.category.color}26`,
                        color: income.category.color,
                    }}
                    aria-hidden="true"
                >
                    {income.category.name.charAt(0)}
                </span>

                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold text-foreground">
                        {income.description}
                    </p>
                    <p className="truncate text-xs text-muted-foreground">
                        {income.category.name}
                    </p>
                </div>

                <div className="flex shrink-0 flex-col items-end gap-1">
                    <span className="font-display text-sm font-bold text-blue-400 tabular-nums">
                        {formatAmount(income.amount)}
                    </span>
                    <CurrencyChip currency={income.currency} />
                </div>
            </Link>
        </div>
    );
}
