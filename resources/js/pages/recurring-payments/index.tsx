import { router, setLayoutProps, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    Pencil,
    Plus,
    Repeat,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CategoryIcon } from '@/components/tracker/category-icon';
import { TrackerCard } from '@/components/tracker/tracker-card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { CURRENCY_META, formatAmount, formatDate, todayInputValue } from '@/lib/format';
import type { CurrencyCode } from '@/lib/format';
import {
    destroy as paymentsDestroy,
    pay as paymentsPay,
    store as paymentsStore,
    update as paymentsUpdate,
} from '@/routes/recurring-payments';

const PRESET_COLORS = [
    '#10B981',
    '#3B82F6',
    '#F59E0B',
    '#EF4444',
    '#8B5CF6',
    '#EC4899',
    '#06B6D4',
    '#6B7280',
];

const ICON_OPTIONS = [
    'wallet',
    'banknote',
    'shopping-cart',
    'zap',
    'music',
    'gamepad-2',
    'shirt',
    'graduation-cap',
    'car',
    'heart-pulse',
];

const FREQUENCY_OPTIONS = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'] as const;
type FrequencyCode = (typeof FREQUENCY_OPTIONS)[number];

type RecurringPayment = {
    id: number;
    name: string;
    amount: string | number;
    currency: CurrencyCode;
    usd_amount: string | number;
    frequency: FrequencyCode;
    next_due_date?: string | null;
    last_paid_at?: string | null;
    category_id?: number | null;
    icon: string;
    color: string;
    active: boolean;
    note?: string | null;
    due: boolean;
};

type CategoryOption = {
    id: number;
    name: string;
    icon: string;
    color: string;
};

export default function RecurringPaymentsIndex({
    payments,
    categories,
}: {
    payments: RecurringPayment[];
    categories: CategoryOption[];
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<RecurringPayment | null>(null);

    setLayoutProps({ title: t('recurring_payments.title') });

    const form = useForm({
        name: '',
        amount: '',
        currency: 'usd' as CurrencyCode,
        exchange_rate: '',
        frequency: 'monthly',
        next_due_date: todayInputValue(),
        category_id: '',
        icon: 'wallet',
        color: '#10B981',
        note: '',
    });

    function resetForm(newCurrency?: CurrencyCode) {
        form.reset();
        form.setData('next_due_date', todayInputValue());

        if (newCurrency) {
            form.setData('currency', newCurrency);
        }
    }

    function openCreate() {
        setEditing(null);
        resetForm();
        setOpen(true);
    }

    function openEdit(payment: RecurringPayment) {
        setEditing(payment);
        form.setData({
            name: payment.name,
            amount: String(payment.amount),
            currency: payment.currency,
            exchange_rate: '',
            frequency: payment.frequency,
            next_due_date: payment.next_due_date ?? todayInputValue(),
            category_id: payment.category_id ? String(payment.category_id) : '',
            icon: payment.icon,
            color: payment.color,
            note: payment.note ?? '',
        });
        setOpen(true);
    }

    function handleCurrencyChange(currency: CurrencyCode) {
        form.setData('currency', currency);

        if (currency !== 'ves') {
            form.setData('exchange_rate', '');
        }
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (editing) {
            form.put(paymentsUpdate({ recurring_payment: editing.id }).url, {
                onSuccess: () => {
                    setOpen(false);
                    resetForm();
                },
            });
        } else {
            form.post(paymentsStore().url, {
                onSuccess: () => {
                    setOpen(false);
                    resetForm();
                },
            });
        }
    }

    function destroyPayment(payment: RecurringPayment) {
        if (confirm(t('recurring_payments.delete_confirm', { name: payment.name }))) {
            router.delete(paymentsDestroy({ recurring_payment: payment.id }).url);
        }
    }

    const currencyButtons = (selected: CurrencyCode, onChange: (currency: CurrencyCode) => void) => (
        <div className="grid grid-cols-3 gap-1 rounded-lg bg-surface-high p-1">
            {(Object.keys(CURRENCY_META) as CurrencyCode[]).map((currency) => {
                const meta = CURRENCY_META[currency];
                const active = selected === currency;

                return (
                    <button
                        key={currency}
                        type="button"
                        onClick={() => onChange(currency)}
                        className={`rounded-md py-2 text-xs font-semibold transition-colors ${
                            active ? 'bg-emerald-500/20 text-emerald-400' : 'text-muted-foreground'
                        }`}
                    >
                        {meta.label}
                    </button>
                );
            })}
        </div>
    );

    const frequencyButtons = (
        <div className="flex flex-wrap gap-1.5">
            {FREQUENCY_OPTIONS.map((frequency) => {
                const active = form.data.frequency === frequency;

                return (
                    <button
                        key={frequency}
                        type="button"
                        onClick={() => form.setData('frequency', frequency)}
                        className={`rounded-lg px-3 py-2 text-xs font-semibold transition-colors ${
                            active
                                ? 'bg-emerald-500/20 text-emerald-400'
                                : 'bg-surface-high text-muted-foreground'
                        }`}
                    >
                        {t(`recurring_payments.frequency_options.${frequency}`)}
                    </button>
                );
            })}
        </div>
    );

    return (
        <div className="space-y-4">
            <TrackerCard className="px-4 py-3">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm font-semibold text-foreground">
                            {t('recurring_payments.title')}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {payments.length > 0
                                ? t('recurring_payments.new')
                                : t('recurring_payments.empty_desc')}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={openCreate}
                        className="hidden items-center gap-2 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 px-4 py-2 text-xs font-bold text-primary-foreground lg:inline-flex"
                    >
                        <Plus className="size-4" />
                        {t('recurring_payments.new')}
                    </button>
                </div>
            </TrackerCard>

            {payments.length === 0 ? (
                <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-surface-low px-6 py-14 text-center">
                    <div className="mb-3 flex size-14 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-400">
                        <Repeat className="size-7" />
                    </div>
                    <p className="font-display text-lg font-semibold text-foreground">
                        {t('recurring_payments.empty_title')}
                    </p>
                    <p className="mt-1 max-w-xs text-sm text-muted-foreground">
                        {t('recurring_payments.empty_desc')}
                    </p>
                    <button
                        type="button"
                        onClick={openCreate}
                        className="mt-5 inline-flex items-center gap-2 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 px-4 py-2 text-xs font-bold text-primary-foreground"
                    >
                        <Plus className="size-4" />
                        {t('recurring_payments.empty_cta')}
                    </button>
                </div>
            ) : (
                <div className="space-y-2.5 lg:grid lg:grid-cols-2 lg:gap-3 lg:space-y-0 xl:grid-cols-3">
                    {payments.map((payment) => (
                        <div
                            key={payment.id}
                            className={`rounded-2xl bg-surface-low p-4 ${
                                !payment.active ? 'opacity-60' : ''
                            }`}
                        >
                            <div className="flex items-start gap-3">
                                <CategoryIcon icon={payment.icon} color={payment.color} />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold text-foreground">
                                        {payment.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {formatAmount(payment.usd_amount)} USD ·{' '}
                                        {t(`recurring_payments.frequency_options.${payment.frequency}`)}
                                    </p>
                                </div>
                                <div className="flex items-center gap-0.5">
                                    {payment.active && (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                if (
                                                    confirm(
                                                        t(
                                                            'recurring_payments.mark_paid_aria',
                                                            { name: payment.name },
                                                        ) +
                                                            '?',
                                                    )
                                                ) {
                                                    router.post(
                                                        paymentsPay({
                                                            recurring_payment:
                                                                payment.id,
                                                        }).url,
                                                    );
                                                }
                                            }}
                                            className="inline-flex size-8 items-center justify-center rounded-lg text-emerald-400 hover:bg-emerald-500/10"
                                            aria-label={t(
                                                'recurring_payments.mark_paid_aria',
                                                { name: payment.name },
                                            )}
                                        >
                                            <Check className="size-4" />
                                        </button>
                                    )}
                                    <button
                                        type="button"
                                        onClick={() => openEdit(payment)}
                                        className="inline-flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:text-foreground"
                                        aria-label={t(
                                            'recurring_payments.edit_aria',
                                            { name: payment.name },
                                        )}
                                    >
                                        <Pencil className="size-4" />
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => destroyPayment(payment)}
                                        className="inline-flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:text-destructive"
                                        aria-label={t(
                                            'recurring_payments.delete_aria',
                                            { name: payment.name },
                                        )}
                                    >
                                        <Trash2 className="size-4" />
                                    </button>
                                </div>
                            </div>

                            <div className="mt-4 flex flex-wrap items-center gap-2">
                                {payment.due ? (
                                    <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-2.5 py-1 text-[11px] font-semibold text-amber-400">
                                        {t('recurring_payments.due_badge')}
                                    </span>
                                ) : null}
                                {!payment.active && (
                                    <span className="inline-flex items-center rounded-full bg-surface-high px-2.5 py-1 text-[11px] font-semibold text-muted-foreground">
                                        {t('recurring_payments.inactive')}
                                    </span>
                                )}
                            </div>

                            {payment.next_due_date && (
                                <div className="mt-3 flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                    <CalendarDays className="size-3.5" />
                                    {t('recurring_payments.next_due', {
                                        date: formatDate(payment.next_due_date),
                                    })}
                                </div>
                            )}

                            {payment.last_paid_at && (
                                <div className="mt-1 flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                    <Check className="size-3.5" />
                                    {t('recurring_payments.last_paid', {
                                        date: formatDate(payment.last_paid_at),
                                    })}
                                </div>
                            )}

                            {payment.note && (
                                <p className="mt-3 border-t border-border pt-3 text-xs text-muted-foreground">
                                    {payment.note}
                                </p>
                            )}
                        </div>
                    ))}
                </div>
            )}

            <button
                type="button"
                onClick={openCreate}
                className="fixed right-4 bottom-24 z-30 inline-flex size-14 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 text-primary-foreground shadow-xl shadow-emerald-500/30 transition-transform active:scale-95 md:right-[calc(50%-13rem)] lg:hidden"
                aria-label={t('recurring_payments.new')}
            >
                <Plus className="size-6" strokeWidth={2.5} />
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="rounded-2xl border-white/10 bg-surface-low">
                    <DialogHeader>
                        <DialogTitle className="font-display">
                            {editing
                                ? t('recurring_payments.edit_title')
                                : t('recurring_payments.create_title')}
                        </DialogTitle>
                        <DialogDescription className="text-muted-foreground">
                            {editing
                                ? t('recurring_payments.edit_description')
                                : t('recurring_payments.create_description')}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label
                                htmlFor="payment-name"
                                className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                {t('common.name')}
                            </label>
                            <input
                                id="payment-name"
                                type="text"
                                maxLength={60}
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                placeholder={t('recurring_payments.name_placeholder')}
                                className="h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                            />
                            {form.errors.name && (
                                <p className="mt-1 text-xs text-destructive">
                                    {form.errors.name}
                                </p>
                            )}
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    htmlFor="payment-amount"
                                    className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    {t('recurring_payments.amount')}
                                </label>
                                <input
                                    id="payment-amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    inputMode="decimal"
                                    value={form.data.amount}
                                    onChange={(event) =>
                                        form.setData('amount', event.target.value)
                                    }
                                    placeholder={t(
                                        'recurring_payments.amount_placeholder',
                                    )}
                                    className="h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                                />
                                {form.errors.amount && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {form.errors.amount}
                                    </p>
                                )}
                            </div>
                            <div>
                                <span className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                    {t('recurring_payments.currency')}
                                </span>
                                {currencyButtons(form.data.currency, handleCurrencyChange)}
                                {form.errors.currency && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {form.errors.currency}
                                    </p>
                                )}
                            </div>
                        </div>

                        {form.data.currency === 'ves' && (
                            <div>
                                <label
                                    htmlFor="payment-exchange-rate"
                                    className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    {t('recurring_payments.exchange_rate')}
                                </label>
                                <input
                                    id="payment-exchange-rate"
                                    type="number"
                                    step="0.0001"
                                    min="0"
                                    inputMode="decimal"
                                    value={form.data.exchange_rate}
                                    onChange={(event) =>
                                        form.setData('exchange_rate', event.target.value)
                                    }
                                    className="h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                                />
                                <p className="mt-1 text-[11px] text-muted-foreground">
                                    {t('recurring_payments.exchange_rate_hint')}
                                </p>
                                {form.errors.exchange_rate && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {form.errors.exchange_rate}
                                    </p>
                                )}
                            </div>
                        )}

                        <div>
                            <span className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('recurring_payments.frequency')}
                            </span>
                            {frequencyButtons}
                            {form.errors.frequency && (
                                <p className="mt-1 text-xs text-destructive">
                                    {form.errors.frequency}
                                </p>
                            )}
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    htmlFor="payment-due"
                                    className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    {t('recurring_payments.next_due_date')}
                                </label>
                                <input
                                    id="payment-due"
                                    type="date"
                                    value={form.data.next_due_date}
                                    onChange={(event) =>
                                        form.setData('next_due_date', event.target.value)
                                    }
                                    className="h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                                />
                                {form.errors.next_due_date && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {form.errors.next_due_date}
                                    </p>
                                )}
                            </div>
                            <div>
                                <label
                                    htmlFor="payment-category"
                                    className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    {t('recurring_payments.category')}
                                </label>
                                <select
                                    id="payment-category"
                                    value={form.data.category_id}
                                    onChange={(event) =>
                                        form.setData('category_id', event.target.value)
                                    }
                                    className="h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                                >
                                    <option value="">
                                        {t('recurring_payments.category_none')}
                                    </option>
                                    {categories.map((category) => (
                                        <option key={category.id} value={category.id}>
                                            {category.name}
                                        </option>
                                    ))}
                                </select>
                                {form.errors.category_id && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {form.errors.category_id}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <span className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                    {t('recurring_payments.icon')}
                                </span>
                                <div className="flex flex-wrap gap-2">
                                    {ICON_OPTIONS.map((icon) => (
                                        <button
                                            key={icon}
                                            type="button"
                                            onClick={() => form.setData('icon', icon)}
                                            className={`flex size-10 items-center justify-center rounded-lg transition-colors ${
                                                form.data.icon === icon
                                                    ? 'bg-emerald-500/20 ring-2 ring-emerald-500'
                                                    : 'bg-surface-high'
                                            }`}
                                            aria-label={t('common.icon_aria', { icon })}
                                        >
                                            <CategoryIcon
                                                icon={icon}
                                                color={form.data.color}
                                                size="sm"
                                            />
                                        </button>
                                    ))}
                                </div>
                            </div>
                            <div>
                                <span className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                    {t('recurring_payments.color')}
                                </span>
                                <div className="flex flex-wrap gap-2">
                                    {PRESET_COLORS.map((color) => (
                                        <button
                                            key={color}
                                            type="button"
                                            onClick={() => form.setData('color', color)}
                                            className={`size-8 rounded-full transition-transform ${
                                                form.data.color === color
                                                    ? 'scale-110 ring-2 ring-white/70'
                                                    : ''
                                            }`}
                                            style={{ backgroundColor: color }}
                                            aria-label={t('common.color_aria', { color })}
                                        />
                                    ))}
                                </div>
                                {form.errors.color && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {form.errors.color}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div>
                            <label
                                htmlFor="payment-note"
                                className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                {t('recurring_payments.note_label')}
                            </label>
                            <textarea
                                id="payment-note"
                                rows={2}
                                maxLength={255}
                                value={form.data.note}
                                onChange={(event) =>
                                    form.setData('note', event.target.value)
                                }
                                placeholder={t('recurring_payments.note_placeholder')}
                                className="w-full rounded-lg bg-surface-high px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={form.processing}
                            className="w-full rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 py-3 font-display text-sm font-bold text-primary-foreground disabled:opacity-60"
                        >
                            {form.processing
                                ? t('common.saving')
                                : editing
                                  ? t('common.update')
                                  : t('common.create')}
                        </button>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
