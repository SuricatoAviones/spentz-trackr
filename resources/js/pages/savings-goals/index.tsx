import { router, setLayoutProps, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    CircleDollarSign,
    Pencil,
    PiggyBank,
    Plus,
    Trash2,
    TrendingUp,
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
import {
    CURRENCY_META,
    formatAmount,
    formatDate,
    todayInputValue,
} from '@/lib/format';
import type { CurrencyCode } from '@/lib/format';
import {
    destroy as goalsDestroy,
    store as goalsStore,
    update as goalsUpdate,
} from '@/routes/savings-goals';
import {
    destroy as contributionDestroy,
    store as contributionStore,
} from '@/routes/savings-goals/contributions';

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
    'utensils',
    'car',
    'zap',
    'heart-pulse',
    'gamepad-2',
    'shirt',
    'graduation-cap',
];

type SavingsContribution = {
    id: number;
    amount: string | number;
    currency: CurrencyCode;
    usd_amount: string | number;
    income_id?: number | null;
    contributed_at: string;
    note?: string | null;
};

type SavingsGoal = {
    id: number;
    name: string;
    target_amount: string | number;
    currency: CurrencyCode;
    exchange_rate?: string | number | null;
    target_usd_amount: string | number;
    icon: string;
    color: string;
    deadline?: string | null;
    note?: string | null;
    achieved_at?: string | null;
    saved: number;
    percent: number;
    contributions: SavingsContribution[];
};

type IncomeOption = {
    id: number;
    description: string;
    received_at: string;
    usd_amount: string | number;
};

export default function SavingsGoalsIndex({
    goals,
    incomes,
}: {
    goals: SavingsGoal[];
    incomes: IncomeOption[];
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<SavingsGoal | null>(null);
    const [contributing, setContributing] = useState<SavingsGoal | null>(null);

    setLayoutProps({ title: t('savings_goals.title') });

    const goalForm = useForm({
        name: '',
        target_amount: '',
        currency: 'usd' as CurrencyCode,
        exchange_rate: '',
        icon: 'wallet',
        color: '#10B981',
        deadline: '',
        note: '',
    });

    const contributionForm = useForm({
        amount: '',
        currency: 'usd' as CurrencyCode,
        exchange_rate: '',
        income_id: '',
        contributed_at: todayInputValue(),
        note: '',
    });

    function resetGoalForm(newCurrency?: CurrencyCode) {
        goalForm.reset();

        if (newCurrency) {
            goalForm.setData('currency', newCurrency);
        }
    }

    function openCreate() {
        setEditing(null);
        resetGoalForm();
        setOpen(true);
    }

    function openEdit(goal: SavingsGoal) {
        setEditing(goal);
        goalForm.setData({
            name: goal.name,
            target_amount: String(goal.target_amount),
            currency: goal.currency,
            exchange_rate: goal.exchange_rate ? String(goal.exchange_rate) : '',
            icon: goal.icon,
            color: goal.color,
            deadline: goal.deadline ?? '',
            note: goal.note ?? '',
        });
        setOpen(true);
    }

    function handleGoalCurrencyChange(currency: CurrencyCode) {
        goalForm.setData('currency', currency);

        if (currency !== 'ves') {
            goalForm.setData('exchange_rate', '');
        }
    }

    function submitGoal(event: React.FormEvent) {
        event.preventDefault();

        if (editing) {
            goalForm.put(goalsUpdate({ savings_goal: editing.id }).url, {
                onSuccess: () => {
                    setOpen(false);
                    resetGoalForm();
                },
            });
        } else {
            goalForm.post(goalsStore().url, {
                onSuccess: () => {
                    setOpen(false);
                    resetGoalForm();
                },
            });
        }
    }

    function openContribute(goal: SavingsGoal) {
        setContributing(goal);
        contributionForm.reset();
        contributionForm.setData('currency', goal.currency);
        setContributing(goal);
    }

    function handleContributionCurrencyChange(currency: CurrencyCode) {
        contributionForm.setData('currency', currency);

        if (currency !== 'ves') {
            contributionForm.setData('exchange_rate', '');
        }
    }

    function submitContribution(event: React.FormEvent) {
        event.preventDefault();

        if (!contributing) {
            return;
        }

        contributionForm.post(
            contributionStore({ goal: contributing.id }).url,
            {
                onSuccess: () => {
                    setContributing(null);
                    contributionForm.reset();
                },
            },
        );
    }

    function destroyGoal(goal: SavingsGoal) {
        if (confirm(t('savings_goals.delete_confirm', { name: goal.name }))) {
            router.delete(goalsDestroy({ savings_goal: goal.id }).url);
        }
    }

    function destroyContribution(
        goal: SavingsGoal,
        contribution: SavingsContribution,
    ) {
        if (confirm(t('savings_goals.remove_contribution_confirm'))) {
            router.delete(
                contributionDestroy({
                    goal: goal.id,
                    contribution: contribution.id,
                }).url,
            );
        }
    }

    const currencyButtons = (
        selected: CurrencyCode,
        onChange: (currency: CurrencyCode) => void,
    ) => (
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
                            active
                                ? 'bg-emerald-500/20 text-emerald-400'
                                : 'text-muted-foreground'
                        }`}
                    >
                        {meta.label}
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
                            {t('savings_goals.title')}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {goals.length > 0
                                ? t('savings_goals.new')
                                : t('savings_goals.empty_desc')}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={openCreate}
                        className="hidden items-center gap-2 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 px-4 py-2 text-xs font-bold text-primary-foreground lg:inline-flex"
                    >
                        <Plus className="size-4" />
                        {t('savings_goals.new')}
                    </button>
                </div>
            </TrackerCard>

            {goals.length === 0 ? (
                <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-surface-low px-6 py-14 text-center">
                    <div className="mb-3 flex size-14 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-400">
                        <PiggyBank className="size-7" />
                    </div>
                    <p className="font-display text-lg font-semibold text-foreground">
                        {t('savings_goals.empty_title')}
                    </p>
                    <p className="mt-1 max-w-xs text-sm text-muted-foreground">
                        {t('savings_goals.empty_desc')}
                    </p>
                    <button
                        type="button"
                        onClick={openCreate}
                        className="mt-5 inline-flex items-center gap-2 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 px-4 py-2 text-xs font-bold text-primary-foreground"
                    >
                        <Plus className="size-4" />
                        {t('savings_goals.empty_cta')}
                    </button>
                </div>
            ) : (
                <div className="space-y-2.5 lg:grid lg:grid-cols-2 lg:gap-3 lg:space-y-0 xl:grid-cols-3">
                    {goals.map((goal) => {
                        const achieved = Boolean(goal.achieved_at);

                        return (
                            <div
                                key={goal.id}
                                className="rounded-2xl bg-surface-low p-4"
                            >
                                <div className="flex items-start gap-3">
                                    <CategoryIcon
                                        icon={goal.icon}
                                        color={goal.color}
                                    />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-foreground">
                                            {goal.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {formatAmount(goal.saved)} /{' '}
                                            {formatAmount(
                                                goal.target_usd_amount,
                                            )}{' '}
                                            USD
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-0.5">
                                        <button
                                            type="button"
                                            onClick={() => openContribute(goal)}
                                            className="inline-flex size-8 items-center justify-center rounded-lg text-emerald-400 hover:bg-emerald-500/10"
                                            aria-label={t(
                                                'savings_goals.add_contribution',
                                            )}
                                        >
                                            <Plus className="size-4" />
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => openEdit(goal)}
                                            className="inline-flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:text-foreground"
                                            aria-label={t(
                                                'savings_goals.edit_aria',
                                                { name: goal.name },
                                            )}
                                        >
                                            <Pencil className="size-4" />
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => destroyGoal(goal)}
                                            className="inline-flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:text-destructive"
                                            aria-label={t(
                                                'savings_goals.delete_aria',
                                                { name: goal.name },
                                            )}
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </div>
                                </div>

                                <div className="mt-4">
                                    <div className="h-2.5 w-full overflow-hidden rounded-full bg-surface-high">
                                        <div
                                            className={`h-full rounded-full transition-all ${
                                                achieved
                                                    ? 'bg-gradient-to-r from-emerald-400 to-teal-400'
                                                    : 'bg-gradient-to-r from-emerald-400 to-emerald-600'
                                            }`}
                                            style={{
                                                width: `${Math.min(
                                                    100,
                                                    goal.percent,
                                                )}%`,
                                            }}
                                        />
                                    </div>
                                    <div className="mt-1.5 flex items-center justify-between">
                                        <span className="text-[11px] font-medium text-muted-foreground">
                                            {achieved ? (
                                                <span className="text-emerald-400">
                                                    {t(
                                                        'savings_goals.achieved',
                                                    )}
                                                </span>
                                            ) : (
                                                t('savings_goals.remaining', {
                                                    amount: formatAmount(
                                                        Math.max(
                                                            0,
                                                            Number(
                                                                goal.target_usd_amount,
                                                            ) - goal.saved,
                                                        ),
                                                    ),
                                                })
                                            )}
                                        </span>
                                        <span className="text-[11px] font-semibold text-muted-foreground">
                                            {goal.percent}%
                                        </span>
                                    </div>
                                </div>

                                {goal.deadline && (
                                    <div className="mt-3 flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                        <CalendarDays className="size-3.5" />
                                        {formatDate(goal.deadline)}
                                    </div>
                                )}

                                {goal.contributions.length > 0 && (
                                    <div className="mt-3 space-y-1.5 border-t border-border pt-3">
                                        <p className="flex items-center gap-1.5 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                            <TrendingUp className="size-3.5" />
                                            {t('savings_goals.contributions')}
                                        </p>
                                        {goal.contributions.map(
                                            (contribution) => {
                                                const income = incomes.find(
                                                    (item) =>
                                                        item.id ===
                                                        contribution.income_id,
                                                );

                                                return (
                                                    <div
                                                        key={contribution.id}
                                                        className="flex items-center justify-between text-xs"
                                                    >
                                                        <span className="flex min-w-0 items-center gap-2 text-muted-foreground">
                                                            <CircleDollarSign className="size-3.5 shrink-0 text-emerald-400" />
                                                            <span className="truncate">
                                                                {formatAmount(
                                                                    contribution.usd_amount,
                                                                )}{' '}
                                                                USD
                                                                {income
                                                                    ? ` · ${income.description}`
                                                                    : ''}
                                                            </span>
                                                        </span>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                destroyContribution(
                                                                    goal,
                                                                    contribution,
                                                                )
                                                            }
                                                            className="inline-flex size-6 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:text-destructive"
                                                            aria-label={t(
                                                                'savings_goals.remove_contribution_aria',
                                                            )}
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </button>
                                                    </div>
                                                );
                                            },
                                        )}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}

            <button
                type="button"
                onClick={openCreate}
                className="fixed right-4 bottom-24 z-30 inline-flex size-14 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 text-primary-foreground shadow-xl shadow-emerald-500/30 transition-transform active:scale-95 md:right-[calc(50%-13rem)] lg:hidden"
                aria-label={t('savings_goals.new')}
            >
                <Plus className="size-6" strokeWidth={2.5} />
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="rounded-2xl border-white/10 bg-surface-low">
                    <DialogHeader>
                        <DialogTitle className="font-display">
                            {editing
                                ? t('savings_goals.edit_title')
                                : t('savings_goals.create_title')}
                        </DialogTitle>
                        <DialogDescription className="text-muted-foreground">
                            {editing
                                ? t('savings_goals.edit_description')
                                : t('savings_goals.create_description')}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitGoal} className="space-y-4">
                        <div>
                            <label
                                htmlFor="goal-name"
                                className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                {t('common.name')}
                            </label>
                            <input
                                id="goal-name"
                                type="text"
                                maxLength={60}
                                value={goalForm.data.name}
                                onChange={(event) =>
                                    goalForm.setData('name', event.target.value)
                                }
                                placeholder={t(
                                    'savings_goals.name_placeholder',
                                )}
                                className="h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                            />
                            {goalForm.errors.name && (
                                <p className="mt-1 text-xs text-destructive">
                                    {goalForm.errors.name}
                                </p>
                            )}
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    htmlFor="goal-target"
                                    className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    {t('savings_goals.target_amount')}
                                </label>
                                <input
                                    id="goal-target"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    inputMode="decimal"
                                    value={goalForm.data.target_amount}
                                    onChange={(event) =>
                                        goalForm.setData(
                                            'target_amount',
                                            event.target.value,
                                        )
                                    }
                                    placeholder={t(
                                        'savings_goals.target_placeholder',
                                    )}
                                    className="h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                                />
                                {goalForm.errors.target_amount && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {goalForm.errors.target_amount}
                                    </p>
                                )}
                            </div>
                            <div>
                                <span className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                    {t('savings_goals.target_currency')}
                                </span>
                                {currencyButtons(
                                    goalForm.data.currency,
                                    handleGoalCurrencyChange,
                                )}
                                {goalForm.errors.currency && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {goalForm.errors.currency}
                                    </p>
                                )}
                            </div>
                        </div>

                        {goalForm.data.currency === 'ves' && (
                            <div>
                                <label
                                    htmlFor="goal-exchange-rate"
                                    className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    {t('savings_goals.exchange_rate')}
                                </label>
                                <input
                                    id="goal-exchange-rate"
                                    type="number"
                                    step="0.0001"
                                    min="0"
                                    inputMode="decimal"
                                    value={goalForm.data.exchange_rate}
                                    onChange={(event) =>
                                        goalForm.setData(
                                            'exchange_rate',
                                            event.target.value,
                                        )
                                    }
                                    className="h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                                />
                                <p className="mt-1 text-[11px] text-muted-foreground">
                                    {t('savings_goals.exchange_rate_hint')}
                                </p>
                                {goalForm.errors.exchange_rate && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {goalForm.errors.exchange_rate}
                                    </p>
                                )}
                            </div>
                        )}

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    htmlFor="goal-deadline"
                                    className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    {t('savings_goals.deadline')}
                                </label>
                                <input
                                    id="goal-deadline"
                                    type="date"
                                    value={goalForm.data.deadline}
                                    onChange={(event) =>
                                        goalForm.setData(
                                            'deadline',
                                            event.target.value,
                                        )
                                    }
                                    className="h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                                />
                                {goalForm.errors.deadline && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {goalForm.errors.deadline}
                                    </p>
                                )}
                            </div>
                            <div>
                                <span className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                    {t('savings_goals.icon')}
                                </span>
                                <div className="flex flex-wrap gap-2">
                                    {ICON_OPTIONS.map((icon) => (
                                        <button
                                            key={icon}
                                            type="button"
                                            onClick={() =>
                                                goalForm.setData('icon', icon)
                                            }
                                            className={`flex size-10 items-center justify-center rounded-lg transition-colors ${
                                                goalForm.data.icon === icon
                                                    ? 'bg-emerald-500/20 ring-2 ring-emerald-500'
                                                    : 'bg-surface-high'
                                            }`}
                                            aria-label={t('common.icon_aria', {
                                                icon,
                                            })}
                                        >
                                            <CategoryIcon
                                                icon={icon}
                                                color={goalForm.data.color}
                                                size="sm"
                                            />
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>

                        <div>
                            <span className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('savings_goals.color')}
                            </span>
                            <div className="flex flex-wrap gap-2">
                                {PRESET_COLORS.map((color) => (
                                    <button
                                        key={color}
                                        type="button"
                                        onClick={() =>
                                            goalForm.setData('color', color)
                                        }
                                        className={`size-8 rounded-full transition-transform ${
                                            goalForm.data.color === color
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
                            {goalForm.errors.color && (
                                <p className="mt-1 text-xs text-destructive">
                                    {goalForm.errors.color}
                                </p>
                            )}
                        </div>

                        <div>
                            <label
                                htmlFor="goal-note"
                                className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                {t('savings_goals.note_label')}
                            </label>
                            <textarea
                                id="goal-note"
                                rows={2}
                                maxLength={255}
                                value={goalForm.data.note}
                                onChange={(event) =>
                                    goalForm.setData('note', event.target.value)
                                }
                                placeholder={t(
                                    'savings_goals.note_placeholder',
                                )}
                                className="w-full rounded-lg bg-surface-high px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={goalForm.processing}
                            className="w-full rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 py-3 font-display text-sm font-bold text-primary-foreground disabled:opacity-60"
                        >
                            {goalForm.processing
                                ? t('common.saving')
                                : editing
                                  ? t('common.update')
                                  : t('common.create')}
                        </button>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={Boolean(contributing)}
                onOpenChange={(value) => {
                    if (!value) {
                        setContributing(null);
                    }
                }}
            >
                <DialogContent className="rounded-2xl border-white/10 bg-surface-low">
                    <DialogHeader>
                        <DialogTitle className="font-display">
                            {t('savings_goals.contribution_title', {
                                name: contributing?.name ?? '',
                            })}
                        </DialogTitle>
                        <DialogDescription className="text-muted-foreground">
                            {t('savings_goals.contribution_description')}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitContribution} className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    htmlFor="contribution-amount"
                                    className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    {t('savings_goals.contribution_amount')}
                                </label>
                                <input
                                    id="contribution-amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    inputMode="decimal"
                                    value={contributionForm.data.amount}
                                    onChange={(event) =>
                                        contributionForm.setData(
                                            'amount',
                                            event.target.value,
                                        )
                                    }
                                    className="h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                                />
                                {contributionForm.errors.amount && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {contributionForm.errors.amount}
                                    </p>
                                )}
                            </div>
                            <div>
                                <span className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                    {t('savings_goals.contribution_currency')}
                                </span>
                                {currencyButtons(
                                    contributionForm.data.currency,
                                    handleContributionCurrencyChange,
                                )}
                                {contributionForm.errors.currency && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {contributionForm.errors.currency}
                                    </p>
                                )}
                            </div>
                        </div>

                        {contributionForm.data.currency === 'ves' && (
                            <div>
                                <label
                                    htmlFor="contribution-exchange-rate"
                                    className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    {t('savings_goals.exchange_rate')}
                                </label>
                                <input
                                    id="contribution-exchange-rate"
                                    type="number"
                                    step="0.0001"
                                    min="0"
                                    inputMode="decimal"
                                    value={contributionForm.data.exchange_rate}
                                    onChange={(event) =>
                                        contributionForm.setData(
                                            'exchange_rate',
                                            event.target.value,
                                        )
                                    }
                                    className="h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                                />
                                {contributionForm.errors.exchange_rate && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {contributionForm.errors.exchange_rate}
                                    </p>
                                )}
                            </div>
                        )}

                        <div>
                            <label
                                htmlFor="contribution-date"
                                className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                {t('savings_goals.contribution_date')}
                            </label>
                            <input
                                id="contribution-date"
                                type="date"
                                value={contributionForm.data.contributed_at}
                                onChange={(event) =>
                                    contributionForm.setData(
                                        'contributed_at',
                                        event.target.value,
                                    )
                                }
                                className="h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                            />
                            {contributionForm.errors.contributed_at && (
                                <p className="mt-1 text-xs text-destructive">
                                    {contributionForm.errors.contributed_at}
                                </p>
                            )}
                        </div>

                        <div>
                            <label
                                htmlFor="contribution-income"
                                className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                {t('savings_goals.link_income')}
                            </label>
                            <select
                                id="contribution-income"
                                value={contributionForm.data.income_id}
                                onChange={(event) =>
                                    contributionForm.setData(
                                        'income_id',
                                        event.target.value,
                                    )
                                }
                                className="h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                            >
                                <option value="">
                                    {incomes.length === 0
                                        ? t('savings_goals.empty_income')
                                        : t('savings_goals.link_income_none')}
                                </option>
                                {incomes.map((income) => (
                                    <option key={income.id} value={income.id}>
                                        {income.description} ·{' '}
                                        {formatAmount(income.usd_amount)} USD ·{' '}
                                        {formatDate(income.received_at)}
                                    </option>
                                ))}
                            </select>
                            {contributionForm.errors.income_id && (
                                <p className="mt-1 text-xs text-destructive">
                                    {contributionForm.errors.income_id}
                                </p>
                            )}
                        </div>

                        <div>
                            <label
                                htmlFor="contribution-note"
                                className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                {t('savings_goals.contribution_note')}
                            </label>
                            <textarea
                                id="contribution-note"
                                rows={2}
                                maxLength={255}
                                value={contributionForm.data.note}
                                onChange={(event) =>
                                    contributionForm.setData(
                                        'note',
                                        event.target.value,
                                    )
                                }
                                placeholder={t(
                                    'savings_goals.contribution_note_placeholder',
                                )}
                                className="w-full rounded-lg bg-surface-high px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={contributionForm.processing}
                            className="w-full rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 py-3 font-display text-sm font-bold text-primary-foreground disabled:opacity-60"
                        >
                            {contributionForm.processing
                                ? t('common.saving')
                                : t('savings_goals.add_contribution')}
                        </button>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
