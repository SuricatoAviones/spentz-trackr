import { useForm } from '@inertiajs/react';
import { Camera, ChevronRight } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CategoryIcon } from '@/components/tracker/category-icon';
import { CURRENCY_META, formatRate, todayInputValue } from '@/lib/format';
import type { CurrencyCode } from '@/lib/format';
import {
    store as expensesStore,
    update as expensesUpdate,
} from '@/routes/expenses';
import type {
    CategoryOption,
    CommissionDefaults,
    PaymentMethodCode,
    RateInfo,
    RateOptions,
    SourceOption,
} from '@/types';

type RateChoice = 'bcv' | 'paralelo' | 'custom';

function resolveChoice(provider: string | null | undefined): RateChoice {
    return provider === 'bcv' || provider === 'paralelo' ? provider : 'custom';
}

export function ExpenseForm({
    mode,
    categories,
    sources,
    rate,
    rates,
    commissionDefaults,
    initial = null,
}: {
    mode: 'create' | 'edit';
    categories: CategoryOption[];
    sources: SourceOption[];
    rate: RateInfo;
    rates: RateOptions;
    commissionDefaults: CommissionDefaults;
    initial?: {
        id: number;
        description: string;
        note: string | null;
        amount: string;
        currency: CurrencyCode;
        payment_method: 'pago_movil' | 'transferencia' | null;
        commission: string | null;
        exchange_rate: string | null;
        rate_provider: string | null;
        category_id: number;
        payment_source_id: number;
        spent_at: string;
        has_receipt: boolean;
    } | null;
}) {
    const { t } = useTranslation();
    const [commissionTouched, setCommissionTouched] = useState(false);
    const { data, setData, post, put, processing, errors } = useForm({
        description: initial?.description ?? '',
        note: initial?.note ?? '',
        amount: initial?.amount ?? '',
        currency: (initial?.currency ?? 'usd') as CurrencyCode,
        payment_method: (initial?.payment_method ?? '') as
            '' | 'pago_movil' | 'transferencia',
        commission: initial?.commission ?? '',
        exchange_rate: initial?.exchange_rate ?? '',
        rate_provider: initial?.rate_provider ?? '',
        category_id: initial?.category_id ?? 0,
        payment_source_id: initial?.payment_source_id ?? 0,
        spent_at: initial?.spent_at ?? todayInputValue(),
        receipt: null as File | null,
        remove_receipt: false,
    });

    const rateNumber = data.exchange_rate
        ? Number(data.exchange_rate)
        : Number(rate.rate);
    const isVes = data.currency === 'ves';
    const rateChoice = resolveChoice(data.rate_provider);
    const commissionNumber = data.payment_method
        ? Number(data.commission) || 0
        : 0;
    const totalAmount = (Number(data.amount) || 0) + commissionNumber;
    const hasNoCommission =
        data.payment_method !== '' &&
        data.commission !== '' &&
        Number(data.commission) === 0;

    const usdEquivalent = useMemo(() => {
        if (!data.amount) {
            return 0;
        }

        const total = (Number(data.amount) || 0) + commissionNumber;

        if (isVes && rateNumber > 0) {
            return total / rateNumber;
        }

        return total;
    }, [data.amount, isVes, rateNumber, commissionNumber]);

    const rateOptions: { value: RateChoice; label: string; rate: string }[] = [
        {
            value: 'bcv',
            label: t('rates.provider_bcv'),
            rate: formatRate(rates.bcv),
        },
        {
            value: 'paralelo',
            label: t('rates.provider_paralelo'),
            rate: formatRate(rates.paralelo),
        },
        {
            value: 'custom',
            label: t('expenses.form_rate_custom'),
            rate: t('expenses.form_rate_custom_value'),
        },
    ];

    function handleCurrencyChange(currency: CurrencyCode): void {
        setData((values) => {
            if (currency !== 'ves') {
                return {
                    ...values,
                    currency,
                    payment_method: '',
                    commission: '',
                };
            }

            const choice = resolveChoice(values.rate_provider);
            const rateValue =
                choice === 'bcv'
                    ? String(rates.bcv)
                    : choice === 'paralelo'
                      ? String(rates.paralelo)
                      : Number(rate.rate) > 0
                        ? String(rate.rate)
                        : values.exchange_rate;

            return {
                ...values,
                currency,
                exchange_rate: values.exchange_rate || rateValue,
                rate_provider: choice,
            };
        });
    }

    function toNumberValue(value: string | number | null | undefined): number {
        if (value === null || value === undefined || value === '') {
            return 0;
        }

        const parsed = Number(value);

        return Number.isFinite(parsed) ? parsed : 0;
    }

    function computedCommissionFor(amountValue: string): number {
        const amount = Number(amountValue) || 0;
        const min = toNumberValue(commissionDefaults.min_commission);
        const rate = toNumberValue(commissionDefaults.commission_rate);

        if (rate <= 0) {
            return min;
        }

        const percentage = (amount * rate) / 100;

        return Math.max(min, Math.round(percentage * 100) / 100);
    }

    function selectPaymentMethod(method: '' | PaymentMethodCode): void {
        setData((values) => {
            if (!method) {
                return { ...values, payment_method: '', commission: '' };
            }

            return {
                ...values,
                payment_method: method,
                commission: String(computedCommissionFor(values.amount)),
            };
        });
        setCommissionTouched(false);
    }

    function handleAmountChange(amount: string): void {
        setData((values) => {
            if (!values.payment_method || commissionTouched) {
                return { ...values, amount };
            }

            return {
                ...values,
                amount,
                commission: String(computedCommissionFor(amount)),
            };
        });
    }

    function toggleNoCommission(noCommission: boolean): void {
        setData((values) => {
            if (!values.payment_method) {
                return values;
            }

            return {
                ...values,
                commission: noCommission
                    ? '0'
                    : String(computedCommissionFor(values.amount)),
            };
        });
        setCommissionTouched(noCommission);
    }

    function selectRateChoice(choice: RateChoice): void {
        setData((values) => {
            if (choice === 'bcv' || choice === 'paralelo') {
                const rateValue =
                    choice === 'bcv'
                        ? String(rates.bcv)
                        : String(rates.paralelo);

                return {
                    ...values,
                    rate_provider: choice,
                    exchange_rate: rateValue,
                };
            }

            const fallback =
                Number(rates.manual) > 0
                    ? String(rates.manual)
                    : values.exchange_rate;

            return {
                ...values,
                rate_provider: 'custom',
                exchange_rate: fallback,
            };
        });
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        setData((values) => ({
            ...values,
            exchange_rate: isVes ? values.exchange_rate : '',
            rate_provider: isVes ? values.rate_provider : '',
            payment_method: isVes ? values.payment_method : '',
            commission: isVes ? values.commission : '',
            remove_receipt: mode === 'edit' && values.remove_receipt,
        }));

        if (mode === 'edit' && initial) {
            put(expensesUpdate({ expense: initial.id }).url);
        } else {
            post(expensesStore().url);
        }
    }

    return (
        <form onSubmit={submit} className="space-y-4">
            {errors.receipt && (
                <p className="text-xs text-destructive">{errors.receipt}</p>
            )}

            <section className="rounded-xl bg-surface-low p-4">
                <label htmlFor="amount" className="block">
                    <span className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                        {t('expenses.form_amount')}
                    </span>
                    <input
                        id="amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        inputMode="decimal"
                        placeholder={t('expenses.form_amount_placeholder')}
                        value={data.amount}
                        onChange={(event) =>
                            handleAmountChange(event.target.value)
                        }
                        className="mt-1 w-full bg-transparent font-display text-4xl font-extrabold text-foreground tabular-nums placeholder:text-muted-foreground/40 focus:outline-none"
                        autoFocus
                    />
                </label>
                {errors.amount && (
                    <p className="mt-1 text-xs text-destructive">
                        {errors.amount}
                    </p>
                )}

                <div className="mt-3 flex gap-2">
                    {(Object.keys(CURRENCY_META) as CurrencyCode[]).map(
                        (currency) => {
                            const meta = CURRENCY_META[currency];
                            const active = data.currency === currency;

                            return (
                                <button
                                    key={currency}
                                    type="button"
                                    onClick={() =>
                                        handleCurrencyChange(currency)
                                    }
                                    className={`flex-1 rounded-lg py-2.5 text-sm font-semibold transition-all ${
                                        active
                                            ? 'text-primary-foreground shadow-lg'
                                            : 'bg-surface-high text-muted-foreground'
                                    }`}
                                    style={
                                        active
                                            ? { backgroundColor: meta.color }
                                            : undefined
                                    }
                                >
                                    {meta.label}
                                </button>
                            );
                        },
                    )}
                </div>
                {errors.currency && (
                    <p className="mt-1 text-xs text-destructive">
                        {errors.currency}
                    </p>
                )}

                {isVes && (
                    <div className="mt-3 rounded-lg bg-surface-high p-3">
                        <span className="text-xs font-medium text-muted-foreground">
                            {t('expenses.form_rate_question')}
                        </span>
                        <div className="mt-2 grid grid-cols-3 gap-2">
                            {rateOptions.map((option) => {
                                const active = rateChoice === option.value;
                                const available =
                                    option.value === 'custom' ||
                                    Number(rates[option.value]) > 0;

                                return (
                                    <button
                                        key={option.value}
                                        type="button"
                                        disabled={!available}
                                        onClick={() =>
                                            selectRateChoice(option.value)
                                        }
                                        className={`rounded-lg px-2 py-2 text-center transition-colors disabled:opacity-40 ${
                                            active
                                                ? 'bg-emerald-500/15 ring-1 ring-emerald-500/50'
                                                : 'bg-surface-low'
                                        }`}
                                    >
                                        <span
                                            className={`block text-xs font-semibold ${
                                                active
                                                    ? 'text-emerald-400'
                                                    : 'text-foreground'
                                            }`}
                                        >
                                            {option.label}
                                        </span>
                                        <span className="block text-[10px] text-muted-foreground tabular-nums">
                                            {option.rate}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                        {rateChoice === 'custom' && (
                            <div className="mt-3">
                                <label
                                    htmlFor="exchange_rate"
                                    className="text-xs font-medium text-muted-foreground"
                                >
                                    {t('expenses.form_rate_custom_label')}
                                </label>
                                <input
                                    id="exchange_rate"
                                    type="number"
                                    step="0.0001"
                                    min="0.0001"
                                    inputMode="decimal"
                                    value={data.exchange_rate}
                                    onChange={(event) =>
                                        setData({
                                            exchange_rate: event.target.value,
                                            rate_provider: 'custom',
                                        })
                                    }
                                    className="mt-1.5 w-full bg-transparent font-display text-xl font-bold text-amber-400 tabular-nums focus:outline-none"
                                />
                            </div>
                        )}
                        {errors.exchange_rate && (
                            <p className="mt-1 text-xs text-destructive">
                                {errors.exchange_rate}
                            </p>
                        )}
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            = {formatRate(usdEquivalent)} USD
                        </p>

                        <div className="mt-3 space-y-3">
                            <div>
                                <span className="text-xs font-medium text-muted-foreground">
                                    {t('expenses.form_payment_method')}
                                </span>
                                <div className="mt-1.5 grid grid-cols-2 gap-2">
                                    {(
                                        [
                                            [
                                                'pago_movil',
                                                t('expenses.method_pago_movil'),
                                            ],
                                            [
                                                'transferencia',
                                                t(
                                                    'expenses.method_transferencia',
                                                ),
                                            ],
                                        ] as const
                                    ).map(([method, methodLabel]) => {
                                        const active =
                                            data.payment_method === method;

                                        return (
                                            <button
                                                key={method}
                                                type="button"
                                                onClick={() =>
                                                    selectPaymentMethod(
                                                        active ? '' : method,
                                                    )
                                                }
                                                className={`rounded-lg border px-3 py-2 text-xs font-semibold transition-colors ${
                                                    active
                                                        ? 'border-emerald-500/60 bg-emerald-500/15 text-emerald-400'
                                                        : 'border-white/10 bg-surface-low text-foreground'
                                                }`}
                                            >
                                                {methodLabel}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            {data.payment_method && (
                                <div className="flex items-end justify-between gap-3">
                                    <div className="w-32">
                                        <label
                                            htmlFor="commission"
                                            className="text-xs font-medium text-muted-foreground"
                                        >
                                            {t('expenses.form_commission')}
                                        </label>
                                        <input
                                            id="commission"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            inputMode="decimal"
                                            disabled={hasNoCommission}
                                            value={data.commission}
                                            onChange={(event) => {
                                                setData(
                                                    'commission',
                                                    event.target.value,
                                                );
                                                setCommissionTouched(true);
                                            }}
                                            className="mt-1.5 w-full bg-transparent font-display text-xl font-bold text-amber-400 tabular-nums focus:outline-none disabled:opacity-40"
                                        />
                                    </div>
                                    <label className="flex cursor-pointer items-center gap-2 pb-1.5">
                                        <input
                                            type="checkbox"
                                            checked={hasNoCommission}
                                            onChange={(event) =>
                                                toggleNoCommission(
                                                    event.target.checked,
                                                )
                                            }
                                            className="size-4 accent-emerald-500"
                                        />
                                        <span className="text-xs text-muted-foreground">
                                            {t('expenses.form_commission_no')}
                                        </span>
                                    </label>
                                </div>
                            )}

                            {errors.payment_method && (
                                <p className="text-xs text-destructive">
                                    {errors.payment_method}
                                </p>
                            )}
                            {errors.commission && (
                                <p className="text-xs text-destructive">
                                    {errors.commission}
                                </p>
                            )}

                            {data.payment_method && (
                                <p className="text-[11px] text-muted-foreground">
                                    {t('expenses.form_total_hint', {
                                        amount: formatRate(
                                            Number(data.amount) || 0,
                                        ),
                                        commission:
                                            formatRate(commissionNumber),
                                        total: formatRate(totalAmount),
                                        usd: formatRate(usdEquivalent),
                                    })}
                                </p>
                            )}
                        </div>
                    </div>
                )}
            </section>

            <section className="rounded-xl bg-surface-low p-4">
                <label htmlFor="description" className="block">
                    <span className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                        {t('expenses.form_description')}
                    </span>
                    <input
                        id="description"
                        type="text"
                        maxLength={255}
                        placeholder={t('expenses.form_description_placeholder')}
                        value={data.description}
                        onChange={(event) =>
                            setData('description', event.target.value)
                        }
                        className="mt-1.5 h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                    />
                </label>
                {errors.description && (
                    <p className="mt-1 text-xs text-destructive">
                        {errors.description}
                    </p>
                )}

                <label htmlFor="note" className="mt-4 block">
                    <span className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                        {t('expenses.form_note')}
                    </span>
                    <textarea
                        id="note"
                        rows={2}
                        maxLength={2000}
                        placeholder={t('expenses.form_note_placeholder')}
                        value={data.note}
                        onChange={(event) =>
                            setData('note', event.target.value)
                        }
                        className="mt-1.5 w-full resize-none rounded-lg bg-surface-high px-3 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                    />
                </label>
            </section>

            <section className="rounded-xl bg-surface-low p-4">
                <span className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                    {t('expenses.form_category')}
                </span>
                <div className="mt-2.5 flex gap-2 overflow-x-auto pb-1">
                    {categories.map((category) => {
                        const active = data.category_id === category.id;

                        return (
                            <button
                                key={category.id}
                                type="button"
                                onClick={() =>
                                    setData('category_id', category.id)
                                }
                                className={`flex shrink-0 flex-col items-center gap-1.5 rounded-xl px-3 py-2.5 transition-colors ${
                                    active
                                        ? 'bg-surface-high ring-2 ring-emerald-500/60'
                                        : 'bg-white/[0.03]'
                                }`}
                            >
                                <CategoryIcon
                                    icon={category.icon}
                                    color={category.color}
                                    size="sm"
                                />
                                <span className="text-[10px] font-medium text-foreground">
                                    {category.name}
                                </span>
                            </button>
                        );
                    })}
                </div>
                {errors.category_id && (
                    <p className="mt-1 text-xs text-destructive">
                        {errors.category_id}
                    </p>
                )}
            </section>

            <section className="rounded-xl bg-surface-low p-4">
                <span className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                    {t('expenses.form_source')}
                </span>
                <div className="mt-2.5 space-y-2">
                    {sources.map((source) => {
                        const active = data.payment_source_id === source.id;

                        return (
                            <button
                                key={source.id}
                                type="button"
                                onClick={() =>
                                    setData('payment_source_id', source.id)
                                }
                                className={`flex w-full items-center gap-3 rounded-lg px-3 py-2.5 transition-colors ${
                                    active
                                        ? 'bg-surface-high ring-2 ring-emerald-500/60'
                                        : 'bg-white/[0.03]'
                                }`}
                            >
                                <CategoryIcon
                                    icon={source.icon}
                                    color={source.color}
                                    size="sm"
                                />
                                <span className="flex-1 text-left text-sm font-medium text-foreground">
                                    {source.name}
                                </span>
                                {active && (
                                    <ChevronRight className="size-4 text-emerald-400" />
                                )}
                            </button>
                        );
                    })}
                </div>
                {errors.payment_source_id && (
                    <p className="mt-1 text-xs text-destructive">
                        {errors.payment_source_id}
                    </p>
                )}
            </section>

            <section className="rounded-xl bg-surface-low p-4">
                <div className="grid grid-cols-2 gap-3">
                    <label className="block">
                        <span className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                            {t('expenses.form_date')}
                        </span>
                        <input
                            type="date"
                            value={data.spent_at}
                            max={todayInputValue()}
                            onChange={(event) =>
                                setData('spent_at', event.target.value)
                            }
                            className="mt-1.5 h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground [color-scheme:dark] focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                        />
                    </label>
                    {errors.spent_at && (
                        <p className="self-end text-xs text-destructive">
                            {errors.spent_at}
                        </p>
                    )}
                </div>

                <label className="mt-4 flex cursor-pointer items-center justify-between rounded-lg bg-surface-high px-3 py-3">
                    <span className="flex items-center gap-3">
                        <span className="flex size-10 items-center justify-center rounded-full bg-blue-500/15 text-blue-400">
                            <Camera className="size-5" />
                        </span>
                        <span>
                            <span className="block text-sm font-medium text-foreground">
                                {initial?.has_receipt
                                    ? t('expenses.form_change_receipt')
                                    : t('expenses.form_receipt_optional')}
                            </span>
                            <span className="block text-[11px] text-muted-foreground">
                                {data.receipt
                                    ? data.receipt.name
                                    : t('expenses.form_receipt_hint')}
                            </span>
                        </span>
                    </span>
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        onChange={(event) =>
                            setData('receipt', event.target.files?.[0] ?? null)
                        }
                        className="hidden"
                    />
                </label>

                {initial?.has_receipt && (
                    <label className="mt-2 flex cursor-pointer items-center justify-between rounded-lg bg-surface-high px-3 py-3">
                        <span className="text-sm font-medium text-destructive">
                            {t('expenses.form_remove_receipt')}
                        </span>
                        <input
                            type="checkbox"
                            checked={data.remove_receipt}
                            onChange={(event) =>
                                setData('remove_receipt', event.target.checked)
                            }
                            className="size-4 accent-red-500"
                        />
                    </label>
                )}
            </section>

            <button
                type="submit"
                disabled={processing}
                className="w-full rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 py-3.5 font-display text-sm font-bold text-primary-foreground shadow-lg shadow-emerald-500/25 transition-all active:scale-[0.99] disabled:opacity-60"
            >
                {processing
                    ? t('expenses.form_saving')
                    : mode === 'create'
                      ? t('expenses.form_save')
                      : t('expenses.form_update')}
            </button>
        </form>
    );
}
