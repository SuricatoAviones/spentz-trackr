import { Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { CalendarDays, CreditCard, Pencil, Plus, Trash2 } from 'lucide-react';
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
import { CURRENCY_META, formatAmount, formatDate } from '@/lib/format';
import type { CurrencyCode } from '@/lib/format';
import {
    destroy as cardsDestroy,
    show as cardsShow,
    store as cardsStore,
    update as cardsUpdate,
} from '@/routes/credit-cards';
import type { CreditCardSummary, RateInfo } from '@/types';

const PRESET_COLORS = [
    '#8B5CF6',
    '#10B981',
    '#3B82F6',
    '#F59E0B',
    '#EF4444',
    '#EC4899',
    '#06B6D4',
    '#6B7280',
];

const ICON_OPTIONS = ['credit-card', 'landmark', 'wallet', 'banknote', 'coins'];

const BANK_SUGGESTIONS = [
    'Banesco',
    'Mercantil',
    'Provincial',
    'BNC',
    'Banco de Venezuela',
    'Bancaribe',
    'Exterior',
    'Bancamiga',
    'Plaza',
];

const EMPTY = {
    bank: '',
    name: '',
    last_four: '',
    brand: 'visa',
    currency: 'ves' as CurrencyCode,
    credit_limit: '',
    cut_day: '15',
    due_day: '5',
    annual_interest_rate: '',
    minimum_payment_rate: '',
    icon: 'credit-card',
    color: '#8B5CF6',
};

export default function CreditCardsIndex({
    cards,
}: {
    cards: CreditCardSummary[];
    rate: RateInfo;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<CreditCardSummary | null>(null);

    setLayoutProps({ title: t('cards.title') });

    const form = useForm({ ...EMPTY });

    function openCreate() {
        setEditing(null);
        form.setDefaults({ ...EMPTY });
        form.reset();
        form.clearErrors();
        setOpen(true);
    }

    function openEdit(card: CreditCardSummary) {
        setEditing(card);
        form.setData({
            bank: card.bank,
            name: card.name,
            last_four: card.last_four ?? '',
            brand: card.brand,
            currency: card.currency,
            credit_limit: String(card.credit_limit ?? ''),
            cut_day: String(card.cut_day),
            due_day: String(card.due_day),
            annual_interest_rate: card.annual_interest_rate
                ? String(card.annual_interest_rate)
                : '',
            minimum_payment_rate: card.minimum_payment_rate
                ? String(card.minimum_payment_rate)
                : '',
            icon: card.icon,
            color: card.color,
        });
        form.clearErrors();
        setOpen(true);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        const onSuccess = () => {
            setOpen(false);
            setEditing(null);
        };

        if (editing) {
            form.put(cardsUpdate(editing.id).url, { onSuccess });

            return;
        }

        form.post(cardsStore().url, { onSuccess });
    }

    function destroyCard(card: CreditCardSummary) {
        if (confirm(t('cards.delete_confirm'))) {
            router.delete(cardsDestroy(card.id).url);
        }
    }

    return (
        <div className="space-y-4">
            <TrackerCard className="px-4 py-3">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm font-semibold text-foreground">
                            {t('cards.title')}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {t('cards.subtitle')}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={openCreate}
                        className="hidden items-center gap-2 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 px-4 py-2 text-xs font-bold text-primary-foreground lg:inline-flex"
                    >
                        <Plus className="size-4" />
                        {t('cards.new')}
                    </button>
                </div>
            </TrackerCard>

            {cards.length === 0 ? (
                <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-surface-low px-6 py-14 text-center">
                    <div className="mb-3 flex size-14 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                        <CreditCard className="size-7" />
                    </div>
                    <p className="font-display text-lg font-semibold text-foreground">
                        {t('cards.empty_title')}
                    </p>
                    <p className="mt-1 max-w-xs text-sm text-muted-foreground">
                        {t('cards.empty_hint')}
                    </p>
                    <button
                        type="button"
                        onClick={openCreate}
                        className="mt-5 inline-flex items-center gap-2 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 px-4 py-2 text-xs font-bold text-primary-foreground"
                    >
                        <Plus className="size-4" />
                        {t('cards.new')}
                    </button>
                </div>
            ) : (
                <div className="space-y-2.5 lg:grid lg:grid-cols-2 lg:gap-3 lg:space-y-0 xl:grid-cols-3">
                    {cards.map((card) => {
                        const unit = CURRENCY_META[card.currency].label;

                        return (
                            <div
                                key={card.id}
                                className={`rounded-2xl bg-surface-low p-4 ${
                                    !card.active ? 'opacity-60' : ''
                                }`}
                            >
                                <div className="flex items-start gap-3">
                                    <CategoryIcon
                                        icon={card.icon}
                                        color={card.color}
                                    />
                                    <div className="min-w-0 flex-1">
                                        <Link
                                            href={cardsShow(card.id).url}
                                            className="block truncate text-sm font-semibold text-foreground hover:text-emerald-600 dark:hover:text-emerald-400"
                                        >
                                            {card.bank}
                                        </Link>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {card.name}
                                            {card.last_four
                                                ? ` ·${card.last_four}`
                                                : ''}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-0.5">
                                        <button
                                            type="button"
                                            onClick={() => openEdit(card)}
                                            className="inline-flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:text-foreground"
                                            aria-label={t('cards.edit_aria', {
                                                name: card.name,
                                            })}
                                        >
                                            <Pencil className="size-4" />
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => destroyCard(card)}
                                            className="inline-flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:text-destructive"
                                            aria-label={t('cards.delete_aria', {
                                                name: card.name,
                                            })}
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </div>
                                </div>

                                <div className="mt-4">
                                    <div className="h-2.5 w-full overflow-hidden rounded-full bg-surface-high">
                                        <div
                                            className="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-600 transition-all"
                                            style={{
                                                width: `${card.balance.usage_percent}%`,
                                            }}
                                        />
                                    </div>
                                    <div className="mt-1.5 flex items-center justify-between">
                                        <span className="text-[11px] font-medium text-muted-foreground">
                                            {t('cards.available_of', {
                                                available: formatAmount(
                                                    card.balance.available,
                                                ),
                                                limit: formatAmount(
                                                    card.credit_limit,
                                                ),
                                                unit,
                                            })}
                                        </span>
                                        <span className="text-[11px] font-semibold text-muted-foreground">
                                            {card.balance.usage_percent}%
                                        </span>
                                    </div>
                                </div>

                                <div className="mt-3 flex flex-wrap items-center gap-2">
                                    {card.balance.is_estimate && (
                                        <span className="inline-flex items-center rounded-full bg-surface-high px-2.5 py-1 text-[11px] font-semibold text-muted-foreground">
                                            {t('cards.estimate_short')}
                                        </span>
                                    )}
                                    {card.balance.foreign_movements > 0 && (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-2.5 py-1 text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                                            {t('cards.foreign_badge', {
                                                count: card.balance
                                                    .foreign_movements,
                                            })}
                                        </span>
                                    )}
                                    {!card.active && (
                                        <span className="inline-flex items-center rounded-full bg-surface-high px-2.5 py-1 text-[11px] font-semibold text-muted-foreground">
                                            {t('cards.inactive')}
                                        </span>
                                    )}
                                </div>

                                <div className="mt-3 flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                    <CalendarDays className="size-3.5" />
                                    {t('cards.cycle_summary', {
                                        cut: formatDate(
                                            card.cycle.next_cut_date,
                                        ),
                                        due: formatDate(
                                            card.cycle.next_due_date,
                                        ),
                                    })}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            <button
                type="button"
                onClick={openCreate}
                className="fixed right-4 bottom-24 z-30 inline-flex size-14 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 text-primary-foreground shadow-xl shadow-emerald-500/30 transition-transform active:scale-95 md:right-[calc(50%-13rem)] lg:hidden"
                aria-label={t('cards.new')}
            >
                <Plus className="size-6" strokeWidth={2.5} />
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto rounded-2xl border-border bg-surface-low">
                    <DialogHeader>
                        <DialogTitle className="font-display">
                            {editing
                                ? t('cards.edit_title')
                                : t('cards.create_title')}
                        </DialogTitle>
                        <DialogDescription className="text-muted-foreground">
                            {editing
                                ? t('cards.edit_description')
                                : t('cards.create_description')}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field
                                id="card-bank"
                                label={t('cards.bank')}
                                error={form.errors.bank}
                            >
                                <input
                                    id="card-bank"
                                    type="text"
                                    maxLength={100}
                                    list="bank-suggestions"
                                    value={form.data.bank}
                                    onChange={(event) =>
                                        form.setData('bank', event.target.value)
                                    }
                                    placeholder={t('cards.bank_placeholder')}
                                    className={INPUT}
                                />
                                <datalist id="bank-suggestions">
                                    {BANK_SUGGESTIONS.map((bank) => (
                                        <option key={bank} value={bank} />
                                    ))}
                                </datalist>
                            </Field>

                            <Field
                                id="card-name"
                                label={t('common.name')}
                                error={form.errors.name}
                            >
                                <input
                                    id="card-name"
                                    type="text"
                                    maxLength={100}
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    placeholder={t('cards.name_placeholder')}
                                    className={INPUT}
                                />
                            </Field>

                            <Field
                                id="card-last-four"
                                label={t('cards.last_four')}
                                error={form.errors.last_four}
                            >
                                <input
                                    id="card-last-four"
                                    type="text"
                                    maxLength={4}
                                    inputMode="numeric"
                                    value={form.data.last_four}
                                    onChange={(event) =>
                                        form.setData(
                                            'last_four',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="1234"
                                    className={`${INPUT} tabular-nums`}
                                />
                            </Field>

                            <div>
                                <span className={LABEL}>
                                    {t('cards.currency')}
                                </span>
                                <div className="grid grid-cols-3 gap-1 rounded-lg bg-surface-high p-1">
                                    {(
                                        Object.keys(
                                            CURRENCY_META,
                                        ) as CurrencyCode[]
                                    ).map((currency) => (
                                        <button
                                            key={currency}
                                            type="button"
                                            onClick={() =>
                                                form.setData(
                                                    'currency',
                                                    currency,
                                                )
                                            }
                                            className={`rounded-md py-2 text-xs font-semibold transition-colors ${
                                                form.data.currency === currency
                                                    ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400'
                                                    : 'text-muted-foreground'
                                            }`}
                                        >
                                            {CURRENCY_META[currency].label}
                                        </button>
                                    ))}
                                </div>
                                {form.errors.currency && (
                                    <p className={ERROR}>
                                        {form.errors.currency}
                                    </p>
                                )}
                            </div>

                            <Field
                                id="card-limit"
                                label={t('cards.limit')}
                                error={form.errors.credit_limit}
                            >
                                <input
                                    id="card-limit"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    inputMode="decimal"
                                    value={form.data.credit_limit}
                                    onChange={(event) =>
                                        form.setData(
                                            'credit_limit',
                                            event.target.value,
                                        )
                                    }
                                    className={INPUT}
                                />
                            </Field>

                            <Field
                                id="card-rate"
                                label={t('cards.interest_rate')}
                                error={form.errors.annual_interest_rate}
                                hint={t('cards.interest_rate_hint')}
                            >
                                <input
                                    id="card-rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    inputMode="decimal"
                                    value={form.data.annual_interest_rate}
                                    onChange={(event) =>
                                        form.setData(
                                            'annual_interest_rate',
                                            event.target.value,
                                        )
                                    }
                                    className={INPUT}
                                />
                            </Field>

                            <Field
                                id="card-cut-day"
                                label={t('cards.cut_day')}
                                error={form.errors.cut_day}
                            >
                                <input
                                    id="card-cut-day"
                                    type="number"
                                    min="1"
                                    max="31"
                                    inputMode="numeric"
                                    value={form.data.cut_day}
                                    onChange={(event) =>
                                        form.setData(
                                            'cut_day',
                                            event.target.value,
                                        )
                                    }
                                    className={INPUT}
                                />
                            </Field>

                            <Field
                                id="card-due-day"
                                label={t('cards.due_day')}
                                error={form.errors.due_day}
                            >
                                <input
                                    id="card-due-day"
                                    type="number"
                                    min="1"
                                    max="31"
                                    inputMode="numeric"
                                    value={form.data.due_day}
                                    onChange={(event) =>
                                        form.setData(
                                            'due_day',
                                            event.target.value,
                                        )
                                    }
                                    className={INPUT}
                                />
                            </Field>
                        </div>

                        <div>
                            <span className={LABEL}>{t('common.icon')}</span>
                            <div className="flex flex-wrap gap-2">
                                {ICON_OPTIONS.map((icon) => (
                                    <button
                                        key={icon}
                                        type="button"
                                        onClick={() =>
                                            form.setData('icon', icon)
                                        }
                                        className={`flex size-10 items-center justify-center rounded-lg transition-colors ${
                                            form.data.icon === icon
                                                ? 'bg-emerald-500/20 ring-2 ring-emerald-500'
                                                : 'bg-surface-high'
                                        }`}
                                        aria-label={t('common.icon_aria', {
                                            icon,
                                        })}
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
                            <span className={LABEL}>{t('common.color')}</span>
                            <div className="flex flex-wrap gap-2">
                                {PRESET_COLORS.map((color) => (
                                    <button
                                        key={color}
                                        type="button"
                                        onClick={() =>
                                            form.setData('color', color)
                                        }
                                        className={`size-8 rounded-full transition-transform ${
                                            form.data.color === color
                                                ? 'scale-110 ring-2 ring-white/70'
                                                : ''
                                        }`}
                                        style={{ backgroundColor: color }}
                                        aria-label={t('common.color_aria', {
                                            color,
                                        })}
                                    />
                                ))}
                            </div>
                        </div>

                        <p className="text-[11px] text-muted-foreground">
                            {t('cards.source_hint')}
                        </p>

                        <button
                            type="submit"
                            disabled={form.processing}
                            className="w-full rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 py-3 font-display text-sm font-bold text-primary-foreground disabled:opacity-60"
                        >
                            {t('cards.save')}
                        </button>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

const LABEL =
    'mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase';
const INPUT =
    'h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none';
const ERROR = 'mt-1 text-xs text-destructive';

function Field({
    id,
    label,
    error,
    hint,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <label htmlFor={id} className={LABEL}>
                {label}
            </label>
            {children}
            {hint && (
                <p className="mt-1 text-[11px] text-muted-foreground">{hint}</p>
            )}
            {error && <p className={ERROR}>{error}</p>}
        </div>
    );
}
