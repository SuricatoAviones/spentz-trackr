import {
    Link,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from '@inertiajs/react';
import {
    ChevronRight,
    Landmark,
    LogOut,
    RefreshCw,
    Settings2,
    Tag,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { TrackerCard } from '@/components/tracker/tracker-card';
import { formatAmount, formatRate } from '@/lib/format';
import { logout } from '@/routes';
import { update as budgetPreferenceUpdate } from '@/routes/budget-preference';
import { index as categoriesIndex } from '@/routes/categories';
import { update as commissionPreferencesUpdate } from '@/routes/commission-preferences';
import { sync as rateSync, update as rateUpdate } from '@/routes/exchange-rate';
import { index as sourcesIndex } from '@/routes/sources';
import { update as trackingPreferencesUpdate } from '@/routes/tracking-preferences';
import type { CommissionDefaults, RateInfo } from '@/types';

function defaultCommissionValue(value: string | number | null): string {
    return value === null || value === undefined || value === ''
        ? ''
        : String(value);
}

export default function Ajustes({
    rate,
    commissionDefaults,
    trackingType,
    monthlyBudget,
    monthlySpent,
    monthlyExpenseCount,
    monthlyIncomeCount,
}: {
    rate: RateInfo;
    commissionDefaults: CommissionDefaults;
    trackingType: 'expenses' | 'income' | 'both';
    monthlyBudget: string | number | null;
    monthlySpent: number;
    monthlyExpenseCount: number;
    monthlyIncomeCount: number;
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const user = auth.user;

    setLayoutProps({ title: t('ajustes.title') });

    const { data, setData, put, processing, errors } = useForm({
        rate: Number(rate.rate) > 0 ? String(rate.rate) : '',
    });

    const {
        data: commissionData,
        setData: setCommissionData,
        put: putCommissions,
        processing: commissionsProcessing,
        errors: commissionErrors,
    } = useForm({
        min_commission: defaultCommissionValue(
            commissionDefaults.min_commission,
        ),
        commission_rate: defaultCommissionValue(
            commissionDefaults.commission_rate,
        ),
    });

    const {
        data: trackingData,
        setData: setTrackingData,
        put: putTracking,
        processing: trackingProcessing,
    } = useForm({
        tracking_type: trackingType,
    });

    const {
        data: budgetData,
        setData: setBudgetData,
        put: putBudget,
        processing: budgetProcessing,
        errors: budgetErrors,
    } = useForm({
        monthly_budget: defaultCommissionValue(monthlyBudget),
    });

    function saveRate(event: React.FormEvent) {
        event.preventDefault();
        put(rateUpdate().url);
    }

    function saveCommissions(event: React.FormEvent) {
        event.preventDefault();
        putCommissions(commissionPreferencesUpdate().url);
    }

    function saveTracking(event: React.FormEvent) {
        event.preventDefault();
        putTracking(trackingPreferencesUpdate().url);
    }

    function saveBudget(event: React.FormEvent) {
        event.preventDefault();
        putBudget(budgetPreferenceUpdate().url);
    }

    function syncRates() {
        router.post(rateSync().url, {}, { preserveScroll: true });
    }

    function logoutUser() {
        router.post(logout().url);
    }

    const initials = user.name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

    const rateBadge =
        rate.provider === 'user'
            ? {
                  label: t('rates.provider_manual'),
                  className: 'bg-blue-500/15 text-blue-400',
              }
            : rate.provider === 'paralelo'
              ? {
                    label: t('rates.provider_paralelo'),
                    className: 'bg-amber-500/15 text-amber-400',
                }
              : {
                    label:
                        rate.provider === 'none'
                            ? t('rates.provider_none')
                            : t('rates.provider_bcv'),
                    className: 'bg-emerald-500/15 text-emerald-400',
                };

    return (
        <div className="grid gap-4 lg:grid-cols-5 lg:gap-5">
            <div className="space-y-4 lg:col-span-2">
                <TrackerCard>
                    <Link
                        href="/settings/profile"
                        className="flex items-center gap-3 px-4 py-4"
                    >
                        <span className="flex size-12 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 font-display text-base font-bold text-primary-foreground">
                            {initials}
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm font-semibold text-foreground">
                                {user.name}
                            </span>
                            <span className="block truncate text-xs text-muted-foreground">
                                {user.email}
                            </span>
                        </span>
                        <ChevronRight className="size-4 text-muted-foreground" />
                    </Link>
                </TrackerCard>

                <TrackerCard title={t('ajustes.rate_title')}>
                    <form onSubmit={saveRate} className="px-4 pt-3 pb-4">
                        <div className="flex items-center justify-between">
                            <span className="text-xs text-muted-foreground">
                                {t('ajustes.rate_today')}
                            </span>
                            <span
                                className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${rateBadge.className}`}
                            >
                                {rateBadge.label}
                            </span>
                        </div>
                        <input
                            type="number"
                            step="0.0001"
                            min="0.0001"
                            inputMode="decimal"
                            value={data.rate}
                            onChange={(event) =>
                                setData('rate', event.target.value)
                            }
                            className="mt-2 w-full bg-transparent font-display text-3xl font-extrabold text-amber-400 tabular-nums focus:outline-none"
                            aria-label={t('ajustes.rate_aria')}
                        />
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            {rate.provider === 'user'
                                ? t('ajustes.rate_user_hint')
                                : t('ajustes.rate_edit_hint')}
                        </p>
                        {errors.rate && (
                            <p className="mt-1 text-xs text-destructive">
                                {errors.rate}
                            </p>
                        )}
                        <button
                            type="submit"
                            disabled={processing}
                            className="mt-3 w-full rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 py-2.5 text-xs font-bold text-primary-foreground disabled:opacity-60"
                        >
                            {processing
                                ? t('common.saving')
                                : t('ajustes.rate_save_button')}
                        </button>
                        <div className="mt-3 flex items-center justify-between border-t border-white/5 pt-3">
                            <span className="text-[11px] text-muted-foreground">
                                {t('ajustes.last_sync', {
                                    date: rate.rate_date,
                                    rate: formatRate(rate.rate),
                                })}
                            </span>
                            <button
                                type="button"
                                onClick={syncRates}
                                className="inline-flex items-center gap-1.5 text-[11px] font-semibold text-emerald-400"
                            >
                                <RefreshCw className="size-3.5" />
                                {t('ajustes.sync')}
                            </button>
                        </div>
                    </form>
                </TrackerCard>
            </div>

            <div className="space-y-4 lg:col-span-3">
                <TrackerCard title={t('ajustes.commissions_title')}>
                    <form onSubmit={saveCommissions} className="px-4 pt-3 pb-4">
                        <p className="text-[11px] text-muted-foreground">
                            {t('ajustes.commissions_desc')}
                        </p>
                        <div className="mt-3 grid grid-cols-2 gap-3">
                            <label className="block">
                                <span className="text-xs font-medium text-muted-foreground">
                                    {t('ajustes.commission_min')}
                                </span>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    inputMode="decimal"
                                    value={commissionData.min_commission}
                                    onChange={(event) =>
                                        setCommissionData(
                                            'min_commission',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1.5 h-11 w-full rounded-lg bg-surface-high px-3 text-sm font-semibold text-amber-400 tabular-nums focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                                />
                            </label>
                            <label className="block">
                                <span className="text-xs font-medium text-muted-foreground">
                                    {t('ajustes.commission_rate')}
                                </span>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    inputMode="decimal"
                                    value={commissionData.commission_rate}
                                    onChange={(event) =>
                                        setCommissionData(
                                            'commission_rate',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1.5 h-11 w-full rounded-lg bg-surface-high px-3 text-sm font-semibold text-amber-400 tabular-nums focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                                />
                            </label>
                        </div>
                        {commissionErrors.min_commission && (
                            <p className="mt-1 text-xs text-destructive">
                                {commissionErrors.min_commission}
                            </p>
                        )}
                        {commissionErrors.commission_rate && (
                            <p className="mt-1 text-xs text-destructive">
                                {commissionErrors.commission_rate}
                            </p>
                        )}
                        <button
                            type="submit"
                            disabled={commissionsProcessing}
                            className="mt-3 w-full rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 py-2.5 text-xs font-bold text-primary-foreground disabled:opacity-60"
                        >
                            {commissionsProcessing
                                ? t('common.saving')
                                : t('ajustes.commissions_save')}
                        </button>
                    </form>
                </TrackerCard>

                <TrackerCard title={t('ajustes.tracking_title')}>
                    <form onSubmit={saveTracking} className="px-4 pt-3 pb-4">
                        <p className="text-[11px] text-muted-foreground">
                            {t('ajustes.tracking_desc')}
                        </p>
                        <div className="mt-3 grid grid-cols-3 gap-2">
                            {(['expenses', 'income', 'both'] as const).map(
                                (option) => (
                                    <button
                                        key={option}
                                        type="button"
                                        onClick={() =>
                                            setTrackingData(
                                                'tracking_type',
                                                option,
                                            )
                                        }
                                        className={`rounded-lg px-2 py-2.5 text-[11px] font-semibold transition-colors ${
                                            trackingData.tracking_type ===
                                            option
                                                ? 'bg-gradient-to-br from-emerald-400 to-emerald-600 text-primary-foreground'
                                                : 'bg-surface-high text-muted-foreground'
                                        }`}
                                    >
                                        {t(`ajustes.tracking_option_${option}`)}
                                    </button>
                                ),
                            )}
                        </div>
                        <button
                            type="submit"
                            disabled={
                                trackingProcessing ||
                                trackingData.tracking_type === trackingType
                            }
                            className="mt-3 w-full rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 py-2.5 text-xs font-bold text-primary-foreground disabled:opacity-50"
                        >
                            {trackingProcessing
                                ? t('common.saving')
                                : t('ajustes.tracking_save')}
                        </button>
                    </form>
                </TrackerCard>

                <TrackerCard title={t('ajustes.budget_title')}>
                    <form onSubmit={saveBudget} className="px-4 pt-3 pb-4">
                        <p className="text-[11px] text-muted-foreground">
                            {t('ajustes.budget_desc')}
                        </p>
                        <div className="mt-3 flex items-end gap-3">
                            <label className="block flex-1">
                                <span className="text-xs font-medium text-muted-foreground">
                                    {t('ajustes.budget_label')}
                                </span>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    inputMode="decimal"
                                    placeholder={t(
                                        'ajustes.budget_placeholder',
                                    )}
                                    value={budgetData.monthly_budget}
                                    onChange={(event) =>
                                        setBudgetData(
                                            'monthly_budget',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1.5 h-11 w-full rounded-lg bg-surface-high px-3 text-sm font-semibold text-emerald-400 tabular-nums focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                                />
                            </label>
                            <span className="pb-3 text-xs font-semibold text-muted-foreground">
                                USD
                            </span>
                        </div>
                        {budgetErrors.monthly_budget && (
                            <p className="mt-1 text-xs text-destructive">
                                {budgetErrors.monthly_budget}
                            </p>
                        )}
                        <div className="mt-3 flex items-center justify-between text-[11px] text-muted-foreground">
                            <span>
                                {t('ajustes.budget_spent', {
                                    spent: formatAmount(monthlySpent),
                                })}
                            </span>
                            {monthlyBudget !== null && monthlyBudget !== '' && (
                                <span
                                    className={
                                        monthlySpent > Number(monthlyBudget)
                                            ? 'font-semibold text-destructive'
                                            : 'font-semibold text-emerald-400'
                                    }
                                >
                                    {Number(monthlyBudget) > 0
                                        ? `${Math.min(
                                              (monthlySpent /
                                                  Number(monthlyBudget)) *
                                                  100,
                                              100,
                                          ).toFixed(0)}%`
                                        : '0%'}
                                </span>
                            )}
                        </div>
                        <button
                            type="submit"
                            disabled={budgetProcessing}
                            className="mt-3 w-full rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 py-2.5 text-xs font-bold text-primary-foreground disabled:opacity-60"
                        >
                            {budgetProcessing
                                ? t('common.saving')
                                : t('ajustes.budget_save')}
                        </button>
                    </form>
                </TrackerCard>

                <TrackerCard title={t('ajustes.preferences')}>
                    <div className="divide-y divide-white/5 px-4">
                        <Link
                            href={categoriesIndex().url}
                            className="flex items-center justify-between py-3.5"
                        >
                            <span className="flex items-center gap-3 text-sm text-foreground">
                                <Tag className="size-4 text-muted-foreground" />
                                {t('ajustes.categories')}
                            </span>
                            <ChevronRight className="size-4 text-muted-foreground" />
                        </Link>
                        <Link
                            href={sourcesIndex().url}
                            className="flex items-center justify-between py-3.5"
                        >
                            <span className="flex items-center gap-3 text-sm text-foreground">
                                <Landmark className="size-4 text-muted-foreground" />
                                {t('ajustes.sources')}
                            </span>
                            <ChevronRight className="size-4 text-muted-foreground" />
                        </Link>
                        <Link
                            href="/settings/appearance"
                            className="flex items-center justify-between py-3.5"
                        >
                            <span className="flex items-center gap-3 text-sm text-foreground">
                                <Settings2 className="size-4 text-muted-foreground" />
                                {t('ajustes.appearance')}
                            </span>
                            <ChevronRight className="size-4 text-muted-foreground" />
                        </Link>
                        <Link
                            href="/settings/security"
                            className="flex items-center justify-between py-3.5"
                        >
                            <span className="flex items-center gap-3 text-sm text-foreground">
                                <Settings2 className="size-4 text-muted-foreground" />
                                {t('ajustes.security')}
                            </span>
                            <ChevronRight className="size-4 text-muted-foreground" />
                        </Link>
                        <div className="flex items-center justify-between py-3.5">
                            <span className="text-sm text-foreground">
                                {t('ajustes.monthly_expenses')}
                            </span>
                            <span className="text-sm font-semibold text-muted-foreground tabular-nums">
                                {monthlyExpenseCount}
                            </span>
                        </div>
                        <div className="flex items-center justify-between py-3.5">
                            <span className="text-sm text-foreground">
                                {t('ajustes.monthly_incomes')}
                            </span>
                            <span className="text-sm font-semibold text-muted-foreground tabular-nums">
                                {monthlyIncomeCount}
                            </span>
                        </div>
                    </div>
                </TrackerCard>

                <button
                    type="button"
                    onClick={logoutUser}
                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-surface-low py-3.5 text-sm font-semibold text-destructive"
                >
                    <LogOut className="size-4" />
                    {t('ajustes.logout')}
                </button>

                <p className="pb-2 text-center text-[11px] text-muted-foreground/70">
                    Spentz Trackr v1.0.0
                </p>
            </div>
        </div>
    );
}
