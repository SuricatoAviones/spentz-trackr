import { Link, router, setLayoutProps } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    FileImage,
    Pencil,
    Trash2,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CategoryIcon } from '@/components/tracker/category-icon';
import { CurrencyChip } from '@/components/tracker/currency-chip';
import { TrackerCard } from '@/components/tracker/tracker-card';
import { formatAmount, formatDate, formatRate } from '@/lib/format';
import { destroy as expensesDestroy } from '@/routes/expenses';
import type { Expense } from '@/types';

export default function ShowExpense({ expense }: { expense: Expense }) {
    const { t } = useTranslation();
    const receipts = expense.receipts ?? [];
    const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);

    setLayoutProps({ title: t('expenses.show_title') });

    function destroy() {
        if (confirm(t('expenses.show_delete_confirm'))) {
            router.delete(expensesDestroy(expense.id).url);
        }
    }

    const closeLightbox = useCallback(() => setLightboxIndex(null), []);

    useEffect(() => {
        if (lightboxIndex === null) {
            return;
        }

        const index = lightboxIndex;

        function onKeyDown(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                closeLightbox();
            } else if (event.key === 'ArrowLeft' && index > 0) {
                setLightboxIndex(index - 1);
            } else if (
                event.key === 'ArrowRight' &&
                index < receipts.length - 1
            ) {
                setLightboxIndex(index + 1);
            }
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [lightboxIndex, receipts.length, closeLightbox]);

    const isVes = expense.currency === 'ves';

    const rateProviderLabel =
        expense.rate_provider === 'bcv'
            ? t('rates.provider_bcv')
            : expense.rate_provider === 'paralelo'
              ? t('rates.provider_paralelo')
              : expense.rate_provider === 'user'
                ? t('rates.provider_manual')
                : t('rates.provider_custom');

    return (
        <div className="space-y-4 lg:mx-auto lg:max-w-2xl">
            <div className="flex items-center justify-between">
                <p className="text-xs font-medium text-muted-foreground">
                    {t('expenses.show_detail')}
                </p>
                <div className="flex gap-2">
                    <Link
                        href={`/expenses/${expense.id}/editar`}
                        className="inline-flex size-9 items-center justify-center rounded-lg bg-surface-low text-foreground"
                        aria-label={t('expenses.show_edit_aria')}
                    >
                        <Pencil className="size-4" />
                    </Link>
                    <button
                        type="button"
                        onClick={destroy}
                        className="inline-flex size-9 items-center justify-center rounded-lg bg-surface-low text-destructive"
                        aria-label={t('expenses.show_delete_aria')}
                    >
                        <Trash2 className="size-4" />
                    </button>
                </div>
            </div>

            <section className="flex flex-col items-center rounded-xl bg-gradient-to-br from-surface-high to-surface-low p-6 text-center">
                <CategoryIcon
                    icon={expense.category.icon}
                    color={expense.category.color}
                    size="lg"
                    className="ring-4 ring-white/5"
                />
                <h2 className="mt-3 font-display text-lg font-bold text-foreground">
                    {expense.description}
                </h2>
                <CurrencyChip currency={expense.currency} className="mt-1.5" />
                <p className="mt-3 font-display text-4xl font-extrabold text-foreground tabular-nums">
                    {formatAmount(expense.amount)}
                    <span className="ml-2 text-lg font-bold text-muted-foreground">
                        {expense.currency.toUpperCase()}
                    </span>
                </p>
            </section>

            {isVes && (
                <TrackerCard title={t('expenses.show_equivalences')}>
                    <div className="space-y-3 px-4 pt-3 pb-4">
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-muted-foreground">
                                {t('expenses.show_in_usd')}
                            </span>
                            <span className="font-display text-lg font-bold text-emerald-400 tabular-nums">
                                {formatAmount(expense.usd_amount)} USD
                            </span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-muted-foreground">
                                {t('expenses.show_in_usdt')}
                            </span>
                            <span className="font-display text-lg font-bold text-blue-400 tabular-nums">
                                {formatAmount(expense.usdt_amount)} USDT
                            </span>
                        </div>
                        <p className="border-t border-white/5 pt-2.5 text-[11px] text-muted-foreground">
                            {t('expenses.show_rate', {
                                rate: formatRate(expense.exchange_rate ?? 0),
                            })}{' '}
                            ·{' '}
                            <span className="font-semibold text-foreground">
                                {rateProviderLabel}
                            </span>
                        </p>
                        {expense.commission &&
                            Number(expense.commission) > 0 && (
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-muted-foreground">
                                        {t('expenses.show_commission')}
                                    </span>
                                    <span className="font-display text-base font-bold text-amber-400 tabular-nums">
                                        {formatAmount(expense.commission)} Bs
                                    </span>
                                </div>
                            )}
                        {expense.commission &&
                            Number(expense.commission) > 0 && (
                                <p className="border-t border-white/5 pt-2.5 text-[11px] text-muted-foreground">
                                    {t('expenses.show_total_hint')}
                                </p>
                            )}
                        {expense.payment_method && (
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">
                                    {t('expenses.show_payment_method')}
                                </span>
                                <span className="text-sm font-semibold text-foreground">
                                    {expense.payment_method === 'pago_movil'
                                        ? t('expenses.method_pago_movil')
                                        : t('expenses.method_transferencia')}
                                </span>
                            </div>
                        )}
                    </div>
                </TrackerCard>
            )}

            <TrackerCard title={t('expenses.show_details')}>
                <div className="divide-y divide-white/5 px-4">
                    <div className="flex items-center justify-between py-3">
                        <span className="text-sm text-muted-foreground">
                            {t('expenses.show_category')}
                        </span>
                        <span className="flex items-center gap-2 text-sm font-medium text-foreground">
                            <CategoryIcon
                                icon={expense.category.icon}
                                color={expense.category.color}
                                size="sm"
                            />
                            {expense.category.name}
                        </span>
                    </div>
                    <div className="flex items-center justify-between py-3">
                        <span className="text-sm text-muted-foreground">
                            {t('expenses.show_source')}
                        </span>
                        <span className="flex items-center gap-2 text-sm font-medium text-foreground">
                            <CategoryIcon
                                icon={expense.source.icon}
                                color={expense.source.color}
                                size="sm"
                            />
                            {expense.source.name}
                        </span>
                    </div>
                    <div className="flex items-center justify-between py-3">
                        <span className="text-sm text-muted-foreground">
                            {t('expenses.show_date')}
                        </span>
                        <span className="text-sm font-medium text-foreground tabular-nums">
                            {formatDate(expense.spent_at)}
                        </span>
                    </div>
                    {expense.note && (
                        <div className="py-3">
                            <span className="text-sm text-muted-foreground">
                                {t('expenses.show_note')}
                            </span>
                            <p className="mt-1 text-sm text-foreground">
                                {expense.note}
                            </p>
                        </div>
                    )}
                </div>
            </TrackerCard>

            <TrackerCard title={t('expenses.show_receipt')}>
                <div className="px-4 pt-3 pb-4">
                    {receipts.length === 0 ? (
                        <div className="flex h-28 items-center justify-center rounded-lg border border-dashed border-white/10 bg-white/[0.02]">
                            <span className="flex flex-col items-center gap-1.5 text-muted-foreground">
                                <FileImage className="size-6" />
                                <span className="text-[11px]">
                                    {t('expenses.show_no_receipt')}
                                </span>
                            </span>
                        </div>
                    ) : (
                        <div className="grid grid-cols-3 gap-2">
                            {receipts.map((receipt, index) => (
                                <button
                                    key={receipt.id}
                                    type="button"
                                    onClick={() => setLightboxIndex(index)}
                                    className="relative aspect-square overflow-hidden rounded-lg bg-surface-high"
                                    aria-label={t(
                                        'expenses.show_view_receipt_aria',
                                        {
                                            name: receipt.original_name,
                                        },
                                    )}
                                >
                                    <img
                                        src={receipt.url}
                                        alt={receipt.original_name}
                                        className="size-full object-cover"
                                        loading="lazy"
                                    />
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </TrackerCard>

            {lightboxIndex !== null && receipts[lightboxIndex] && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/95 p-4"
                    role="dialog"
                    aria-modal="true"
                    aria-label={t('expenses.show_lightbox_aria')}
                    onClick={closeLightbox}
                >
                    <button
                        type="button"
                        onClick={closeLightbox}
                        className="absolute top-4 right-4 inline-flex size-10 items-center justify-center rounded-full bg-white/10 text-white"
                        aria-label={t('expenses.show_close_receipt_aria')}
                    >
                        <X className="size-5" />
                    </button>

                    {receipts.length > 1 && (
                        <>
                            <button
                                type="button"
                                onClick={(event) => {
                                    event.stopPropagation();
                                    setLightboxIndex((index) =>
                                        index !== null && index > 0
                                            ? index - 1
                                            : receipts.length - 1,
                                    );
                                }}
                                className="absolute top-1/2 left-3 inline-flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white"
                                aria-label={t(
                                    'expenses.show_prev_receipt_aria',
                                )}
                            >
                                <ChevronLeft className="size-5" />
                            </button>
                            <button
                                type="button"
                                onClick={(event) => {
                                    event.stopPropagation();
                                    setLightboxIndex((index) =>
                                        index !== null &&
                                        index < receipts.length - 1
                                            ? index + 1
                                            : 0,
                                    );
                                }}
                                className="absolute top-1/2 right-3 inline-flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white"
                                aria-label={t(
                                    'expenses.show_next_receipt_aria',
                                )}
                            >
                                <ChevronRight className="size-5" />
                            </button>
                        </>
                    )}

                    <figure
                        className="max-h-full"
                        onClick={(event) => event.stopPropagation()}
                    >
                        <img
                            src={receipts[lightboxIndex].url}
                            alt={receipts[lightboxIndex].original_name}
                            className="max-h-[80vh] max-w-full rounded-lg object-contain shadow-2xl"
                        />
                        <figcaption className="mt-3 text-center text-xs text-white/60">
                            {receipts[lightboxIndex].original_name} ·{' '}
                            {lightboxIndex + 1} de {receipts.length}
                        </figcaption>
                    </figure>
                </div>
            )}

            <Link
                href={`/expenses/${expense.id}/editar`}
                className="block w-full rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 py-3.5 text-center font-display text-sm font-bold text-primary-foreground shadow-lg shadow-emerald-500/25"
            >
                {t('expenses.show_edit_button')}
            </Link>
        </div>
    );
}
