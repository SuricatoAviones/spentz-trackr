import { router, setLayoutProps, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarDays,
    Check,
    Info,
    Receipt,
    Trash2,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CategoryIcon } from '@/components/tracker/category-icon';
import { TrackerCard } from '@/components/tracker/tracker-card';
import { CURRENCY_META, formatAmount, formatDate } from '@/lib/format';
import { index as cardsIndex } from '@/routes/credit-cards';
import {
    destroy as paymentsDestroy,
    store as paymentsStore,
} from '@/routes/credit-cards/payments';
import {
    destroy as statementsDestroy,
    store as statementsStore,
} from '@/routes/credit-cards/statements';
import type { CreditCardDetail, RateInfo } from '@/types';

const LABEL =
    'mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase';
const INPUT =
    'h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none';

type Charge = {
    id: number;
    description: string;
    amount: string | number;
    currency: 'usd' | 'ves' | 'usdt';
    usd_amount: string | number;
    spent_at: string;
    category: string;
};

export default function CreditCardShow({
    card,
    rate,
    recentCharges,
}: {
    card: CreditCardDetail;
    rate: RateInfo;
    recentCharges: Charge[];
}) {
    const { t } = useTranslation();
    const unit = CURRENCY_META[card.currency].label;
    const needsRate = card.currency === 'ves';

    setLayoutProps({ title: `${card.bank} ${card.name}` });

    const statementForm = useForm({
        cut_date: new Date().toISOString().slice(0, 10),
        closing_balance: '',
        minimum_payment: '',
        exchange_rate: needsRate ? String(rate?.rate ?? '') : '',
    });

    const paymentForm = useForm({
        amount: '',
        paid_at: new Date().toISOString().slice(0, 10),
        credit_card_statement_id: '',
        exchange_rate: needsRate ? String(rate?.rate ?? '') : '',
    });

    return (
        <div className="space-y-4">
            <TrackerCard className="px-4 py-3">
                <div className="flex items-start gap-3">
                    <CategoryIcon icon={card.icon} color={card.color} />
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold text-foreground">
                            {card.bank}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                            {card.name}
                            {card.last_four ? ` ·${card.last_four}` : ''}
                        </p>
                    </div>
                </div>

                <div className="mt-4 flex items-baseline gap-2">
                    <span className="font-display text-3xl font-extrabold text-foreground tabular-nums">
                        {formatAmount(card.balance.available)}
                    </span>
                    <span className="text-sm font-semibold text-muted-foreground">
                        {unit} {t('cards.available')}
                    </span>
                </div>

                <div className="mt-3">
                    <div className="h-2.5 w-full overflow-hidden rounded-full bg-surface-high">
                        <div
                            className="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-600 transition-all"
                            style={{ width: `${card.balance.usage_percent}%` }}
                        />
                    </div>
                    <div className="mt-1.5 flex items-center justify-between">
                        <span className="text-[11px] font-medium text-muted-foreground">
                            {t('cards.used')}{' '}
                            {formatAmount(card.balance.projected_used)} {unit}
                        </span>
                        <span className="text-[11px] font-semibold text-muted-foreground">
                            {card.balance.usage_percent}%
                        </span>
                    </div>
                </div>

                <div className="mt-3 flex items-center gap-1.5 text-[11px] text-muted-foreground">
                    <CalendarDays className="size-3.5" />
                    {t('cards.cycle_summary', {
                        cut: formatDate(card.cycle.next_cut_date),
                        due: formatDate(card.cycle.next_due_date),
                    })}
                </div>

                {/* La app nunca presenta una proyección como el saldo del banco. */}
                {card.balance.is_estimate && (
                    <p className="mt-3 flex items-start gap-2 border-t border-border pt-3 text-[11px] text-muted-foreground">
                        <Info className="mt-px size-3.5 shrink-0" />
                        <span>
                            {t('cards.estimate_notice', {
                                closing: formatAmount(
                                    card.balance.closing ?? 0,
                                ),
                                charges: formatAmount(
                                    card.balance.charges_since_cut,
                                ),
                                payments: formatAmount(
                                    card.balance.payments_since_cut,
                                ),
                            })}
                        </span>
                    </p>
                )}

                {card.balance.foreign_movements > 0 && (
                    <p className="mt-2 flex items-start gap-2 rounded-lg bg-amber-500/10 p-3 text-[11px] text-amber-600 dark:text-amber-400">
                        <AlertTriangle className="mt-px size-3.5 shrink-0" />
                        <span>
                            {t('cards.foreign_notice', {
                                count: card.balance.foreign_movements,
                            })}
                        </span>
                    </p>
                )}
            </TrackerCard>

            <div className="space-y-4 lg:grid lg:grid-cols-2 lg:gap-4 lg:space-y-0">
                <TrackerCard title={t('cards.new_statement')}>
                    <form
                        className="space-y-4 p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            statementForm.post(statementsStore(card.id).url, {
                                onSuccess: () => statementForm.reset(),
                            });
                        }}
                    >
                        <p className="text-[11px] text-muted-foreground">
                            {t('cards.statement_hint')}
                        </p>

                        <div>
                            <label htmlFor="cut-date" className={LABEL}>
                                {t('cards.cut_date')}
                            </label>
                            <input
                                id="cut-date"
                                type="date"
                                value={statementForm.data.cut_date}
                                onChange={(event) =>
                                    statementForm.setData(
                                        'cut_date',
                                        event.target.value,
                                    )
                                }
                                className={INPUT}
                            />
                            {statementForm.errors.cut_date && (
                                <p className="mt-1 text-xs text-destructive">
                                    {statementForm.errors.cut_date}
                                </p>
                            )}
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label htmlFor="closing" className={LABEL}>
                                    {t('cards.closing_balance')} ({unit})
                                </label>
                                <input
                                    id="closing"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    inputMode="decimal"
                                    value={statementForm.data.closing_balance}
                                    onChange={(event) =>
                                        statementForm.setData(
                                            'closing_balance',
                                            event.target.value,
                                        )
                                    }
                                    className={INPUT}
                                />
                                {statementForm.errors.closing_balance && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {statementForm.errors.closing_balance}
                                    </p>
                                )}
                            </div>
                            <div>
                                <label htmlFor="minimum" className={LABEL}>
                                    {t('cards.minimum_payment')}
                                </label>
                                <input
                                    id="minimum"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    inputMode="decimal"
                                    value={statementForm.data.minimum_payment}
                                    onChange={(event) =>
                                        statementForm.setData(
                                            'minimum_payment',
                                            event.target.value,
                                        )
                                    }
                                    className={INPUT}
                                />
                            </div>
                        </div>

                        {needsRate && (
                            <div>
                                <label
                                    htmlFor="statement-rate"
                                    className={LABEL}
                                >
                                    {t('cards.exchange_rate')}
                                </label>
                                <input
                                    id="statement-rate"
                                    type="number"
                                    step="0.0001"
                                    min="0"
                                    inputMode="decimal"
                                    value={statementForm.data.exchange_rate}
                                    onChange={(event) =>
                                        statementForm.setData(
                                            'exchange_rate',
                                            event.target.value,
                                        )
                                    }
                                    className={INPUT}
                                />
                                {statementForm.errors.exchange_rate && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {statementForm.errors.exchange_rate}
                                    </p>
                                )}
                            </div>
                        )}

                        <button
                            type="submit"
                            disabled={statementForm.processing}
                            className="w-full rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 py-3 font-display text-sm font-bold text-primary-foreground disabled:opacity-60"
                        >
                            {t('cards.save_statement')}
                        </button>
                    </form>
                </TrackerCard>

                <TrackerCard title={t('cards.new_payment')}>
                    <form
                        className="space-y-4 p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            paymentForm.post(paymentsStore(card.id).url, {
                                onSuccess: () => paymentForm.reset(),
                            });
                        }}
                    >
                        <p className="text-[11px] text-muted-foreground">
                            {t('cards.payment_hint')}
                        </p>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label htmlFor="pay-amount" className={LABEL}>
                                    {t('cards.amount')} ({unit})
                                </label>
                                <input
                                    id="pay-amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    inputMode="decimal"
                                    value={paymentForm.data.amount}
                                    onChange={(event) =>
                                        paymentForm.setData(
                                            'amount',
                                            event.target.value,
                                        )
                                    }
                                    className={INPUT}
                                />
                                {paymentForm.errors.amount && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {paymentForm.errors.amount}
                                    </p>
                                )}
                            </div>
                            <div>
                                <label htmlFor="paid-at" className={LABEL}>
                                    {t('cards.paid_at')}
                                </label>
                                <input
                                    id="paid-at"
                                    type="date"
                                    value={paymentForm.data.paid_at}
                                    onChange={(event) =>
                                        paymentForm.setData(
                                            'paid_at',
                                            event.target.value,
                                        )
                                    }
                                    className={INPUT}
                                />
                                {paymentForm.errors.paid_at && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {paymentForm.errors.paid_at}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div>
                            <label htmlFor="against" className={LABEL}>
                                {t('cards.against_statement')}
                            </label>
                            <select
                                id="against"
                                value={
                                    paymentForm.data.credit_card_statement_id
                                }
                                onChange={(event) =>
                                    paymentForm.setData(
                                        'credit_card_statement_id',
                                        event.target.value,
                                    )
                                }
                                className={INPUT}
                            >
                                <option value="">
                                    {t('cards.no_statement')}
                                </option>
                                {card.statements.map((statement) => (
                                    <option
                                        key={statement.id}
                                        value={statement.id}
                                    >
                                        {formatDate(statement.cut_date)} ·{' '}
                                        {formatAmount(
                                            statement.closing_balance,
                                        )}{' '}
                                        {unit}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {needsRate && (
                            <div>
                                <label htmlFor="payment-rate" className={LABEL}>
                                    {t('cards.exchange_rate')}
                                </label>
                                <input
                                    id="payment-rate"
                                    type="number"
                                    step="0.0001"
                                    min="0"
                                    inputMode="decimal"
                                    value={paymentForm.data.exchange_rate}
                                    onChange={(event) =>
                                        paymentForm.setData(
                                            'exchange_rate',
                                            event.target.value,
                                        )
                                    }
                                    className={INPUT}
                                />
                                {paymentForm.errors.exchange_rate && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {paymentForm.errors.exchange_rate}
                                    </p>
                                )}
                            </div>
                        )}

                        <button
                            type="submit"
                            disabled={paymentForm.processing}
                            className="w-full rounded-lg bg-gradient-to-br from-blue-400 to-blue-600 py-3 font-display text-sm font-bold text-primary-foreground disabled:opacity-60"
                        >
                            {t('cards.save_payment')}
                        </button>
                    </form>
                </TrackerCard>
            </div>

            <TrackerCard title={t('cards.statements')}>
                <div className="divide-y divide-border px-4">
                    {card.statements.length === 0 && (
                        <p className="py-6 text-center text-xs text-muted-foreground">
                            {t('cards.no_statements')}
                        </p>
                    )}
                    {card.statements.map((statement) => (
                        <div
                            key={statement.id}
                            className="flex items-center justify-between gap-3 py-3"
                        >
                            <div className="min-w-0">
                                <p className="text-sm font-semibold text-foreground tabular-nums">
                                    {formatAmount(statement.closing_balance)}{' '}
                                    <span className="text-xs font-normal text-muted-foreground">
                                        {unit}
                                    </span>
                                </p>
                                <p className="text-[11px] text-muted-foreground">
                                    {t('cards.cut_date')}{' '}
                                    {formatDate(statement.cut_date)} ·{' '}
                                    {t('cards.due_date')}{' '}
                                    {formatDate(statement.due_date)}
                                </p>
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                {statement.paid_at ? (
                                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2.5 py-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                        <Check className="size-3" />
                                        {t('cards.paid')}
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center rounded-full bg-amber-500/15 px-2.5 py-1 text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                                        {t('cards.pending')}
                                    </span>
                                )}
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.delete(
                                            statementsDestroy([
                                                card.id,
                                                statement.id,
                                            ]).url,
                                        )
                                    }
                                    className="inline-flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:text-destructive"
                                    aria-label={t('cards.delete_statement')}
                                >
                                    <Trash2 className="size-4" />
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            </TrackerCard>

            <TrackerCard title={t('cards.payments')}>
                <div className="divide-y divide-border px-4">
                    {card.payments.length === 0 && (
                        <p className="py-6 text-center text-xs text-muted-foreground">
                            {t('cards.no_payments')}
                        </p>
                    )}
                    {card.payments.map((payment) => (
                        <div
                            key={payment.id}
                            className="flex items-center justify-between gap-3 py-3"
                        >
                            <div>
                                <p className="text-sm font-semibold text-blue-600 tabular-nums dark:text-blue-400">
                                    {formatAmount(payment.amount)}{' '}
                                    <span className="text-xs font-normal text-muted-foreground">
                                        {unit}
                                    </span>
                                </p>
                                <p className="text-[11px] text-muted-foreground">
                                    {formatDate(payment.paid_at)}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() =>
                                    router.delete(
                                        paymentsDestroy([card.id, payment.id])
                                            .url,
                                    )
                                }
                                className="inline-flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:text-destructive"
                                aria-label={t('cards.delete_payment')}
                            >
                                <Trash2 className="size-4" />
                            </button>
                        </div>
                    ))}
                </div>
            </TrackerCard>

            <TrackerCard title={t('cards.charges')}>
                <div className="divide-y divide-border px-4">
                    {recentCharges.length === 0 && (
                        <p className="py-6 text-center text-xs text-muted-foreground">
                            {t('cards.no_charges')}
                        </p>
                    )}
                    {recentCharges.map((charge) => (
                        <div
                            key={charge.id}
                            className="flex items-center justify-between gap-3 py-3"
                        >
                            <div className="flex min-w-0 items-center gap-2">
                                <Receipt className="size-3.5 shrink-0 text-muted-foreground" />
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium text-foreground">
                                        {charge.description}
                                    </p>
                                    <p className="text-[11px] text-muted-foreground">
                                        {charge.category} ·{' '}
                                        {formatDate(charge.spent_at)}
                                    </p>
                                </div>
                            </div>
                            <span className="shrink-0 font-display text-sm font-bold text-foreground tabular-nums">
                                {formatAmount(charge.amount)}{' '}
                                <span className="text-[10px] font-normal text-muted-foreground">
                                    {CURRENCY_META[charge.currency].label}
                                </span>
                            </span>
                        </div>
                    ))}
                </div>
            </TrackerCard>

            <button
                type="button"
                onClick={() => router.get(cardsIndex().url)}
                className="w-full rounded-lg bg-surface-low py-3 text-sm font-semibold text-muted-foreground transition-colors hover:text-foreground"
            >
                {t('cards.back_to_list')}
            </button>
        </div>
    );
}
