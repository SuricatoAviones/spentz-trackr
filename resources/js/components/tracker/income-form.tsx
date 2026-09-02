import { useForm } from '@inertiajs/react';
import { Camera } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { CategoryIcon } from '@/components/tracker/category-icon';
import { CURRENCY_META, formatRate, todayInputValue } from '@/lib/format';
import type { CurrencyCode } from '@/lib/format';
import {
    store as incomesStore,
    update as incomesUpdate,
} from '@/routes/incomes';
import type { CategoryOption, RateInfo, RateOptions } from '@/types';

type RateChoice = 'bcv' | 'paralelo' | 'custom';

function resolveChoice(provider: string | null | undefined): RateChoice {
    return provider === 'bcv' || provider === 'paralelo' ? provider : 'custom';
}

export function IncomeForm({
    mode,
    categories,
    rate,
    rates,
    initial = null,
}: {
    mode: 'create' | 'edit';
    categories: CategoryOption[];
    rate: RateInfo;
    rates: RateOptions;
    initial?: {
        id: number;
        description: string;
        note: string | null;
        amount: string;
        currency: CurrencyCode;
        exchange_rate: string | null;
        rate_provider: string | null;
        category_id: number;
        received_at: string;
        has_receipt: boolean;
    } | null;
}) {
    const { t } = useTranslation();
    const { data, setData, post, put, processing, errors } = useForm({
        description: initial?.description ?? '',
        note: initial?.note ?? '',
        amount: initial?.amount ?? '',
        currency: (initial?.currency ?? 'usd') as CurrencyCode,
        exchange_rate: initial?.exchange_rate ?? '',
        rate_provider: initial?.rate_provider ?? '',
        category_id: initial?.category_id ?? 0,
        received_at: initial?.received_at ?? todayInputValue(),
        receipt: null as File | null,
        remove_receipt: false,
    });

    const rateNumber = data.exchange_rate
        ? Number(data.exchange_rate)
        : Number(rate.rate);
    const isVes = data.currency === 'ves';
    const rateChoice = resolveChoice(data.rate_provider);

    const usdEquivalent = useMemo(() => {
        if (!data.amount) {
            return 0;
        }

        const value = Number(data.amount) || 0;

        if (isVes && rateNumber > 0) {
            return value / rateNumber;
        }

        return value;
    }, [data.amount, isVes, rateNumber]);

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
            label: t('incomes.form_rate_custom'),
            rate: t('incomes.form_rate_custom_value'),
        },
    ];

    function handleCurrencyChange(currency: CurrencyCode): void {
        setData((values) => {
            if (currency !== 'ves') {
                return {
                    ...values,
                    currency,
                    exchange_rate: '',
                    rate_provider: '',
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
            remove_receipt: mode === 'edit' && values.remove_receipt,
        }));

        if (mode === 'edit' && initial) {
            put(incomesUpdate({ income: initial.id }).url);
        } else {
            post(incomesStore().url);
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
                        {t('incomes.form_amount')}
                    </span>
                    <input
                        id="amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        inputMode="decimal"
                        placeholder={t('incomes.form_amount_placeholder')}
                        value={data.amount}
                        onChange={(event) =>
                            setData('amount', event.target.value)
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
                            {t('incomes.form_rate_question')}
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
                                                ? 'bg-blue-500/15 ring-1 ring-blue-500/50'
                                                : 'bg-surface-low'
                                        }`}
                                    >
                                        <span
                                            className={`block text-xs font-semibold ${
                                                active
                                                    ? 'text-blue-400'
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
                                    {t('incomes.form_rate_custom_label')}
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
                    </div>
                )}
            </section>

            <section className="rounded-xl bg-surface-low p-4">
                <label htmlFor="description" className="block">
                    <span className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                        {t('incomes.form_description')}
                    </span>
                    <input
                        id="description"
                        type="text"
                        maxLength={255}
                        placeholder={t('incomes.form_description_placeholder')}
                        value={data.description}
                        onChange={(event) =>
                            setData('description', event.target.value)
                        }
                        className="mt-1.5 h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-blue-500/50 focus:outline-none"
                    />
                </label>
                {errors.description && (
                    <p className="mt-1 text-xs text-destructive">
                        {errors.description}
                    </p>
                )}

                <label htmlFor="note" className="mt-4 block">
                    <span className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                        {t('incomes.form_note')}
                    </span>
                    <textarea
                        id="note"
                        rows={2}
                        maxLength={2000}
                        placeholder={t('incomes.form_note_placeholder')}
                        value={data.note}
                        onChange={(event) =>
                            setData('note', event.target.value)
                        }
                        className="mt-1.5 w-full resize-none rounded-lg bg-surface-high px-3 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-blue-500/50 focus:outline-none"
                    />
                </label>
            </section>

            <section className="rounded-xl bg-surface-low p-4">
                <span className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                    {t('incomes.form_category')}
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
                                        ? 'bg-surface-high ring-2 ring-blue-500/60'
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
                <div className="grid grid-cols-2 gap-3">
                    <label className="block">
                        <span className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                            {t('incomes.form_date')}
                        </span>
                        <input
                            type="date"
                            value={data.received_at}
                            max={todayInputValue()}
                            onChange={(event) =>
                                setData('received_at', event.target.value)
                            }
                            className="mt-1.5 h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground [color-scheme:dark] focus:ring-2 focus:ring-blue-500/50 focus:outline-none"
                        />
                    </label>
                    {errors.received_at && (
                        <p className="self-end text-xs text-destructive">
                            {errors.received_at}
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
                                    ? t('incomes.form_change_receipt')
                                    : t('incomes.form_receipt_optional')}
                            </span>
                            <span className="block text-[11px] text-muted-foreground">
                                {data.receipt
                                    ? data.receipt.name
                                    : t('incomes.form_receipt_hint')}
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
                            {t('incomes.form_remove_receipt')}
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
                className="w-full rounded-xl bg-gradient-to-br from-blue-400 to-blue-600 py-3.5 font-display text-sm font-bold text-primary-foreground shadow-lg shadow-blue-500/25 transition-all active:scale-[0.99] disabled:opacity-60"
            >
                {processing
                    ? t('incomes.form_saving')
                    : mode === 'create'
                      ? t('incomes.form_save')
                      : t('incomes.form_update')}
            </button>
        </form>
    );
}
