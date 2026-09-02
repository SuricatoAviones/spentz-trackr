import { setLayoutProps } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { IncomeForm } from '@/components/tracker/income-form';
import type { CategoryOption, Income, RateInfo, RateOptions } from '@/types';

export default function EditIncome({
    income,
    categories,
    rate,
    rates,
}: {
    income: Income;
    categories: CategoryOption[];
    rate: RateInfo;
    rates: RateOptions;
}) {
    const { t } = useTranslation();

    setLayoutProps({ title: t('incomes.edit_title') });

    return (
        <div className="space-y-4 lg:mx-auto lg:max-w-2xl">
            <IncomeForm
                mode="edit"
                categories={categories}
                rate={rate}
                rates={rates}
                initial={{
                    id: income.id,
                    description: income.description,
                    note: income.note ?? '',
                    amount: String(Number(income.amount) || 0),
                    currency: income.currency,
                    exchange_rate: income.exchange_rate
                        ? String(income.exchange_rate)
                        : null,
                    rate_provider: income.rate_provider ?? null,
                    category_id: income.category.id,
                    received_at: income.received_at,
                    has_receipt: income.has_receipt,
                }}
            />
        </div>
    );
}
