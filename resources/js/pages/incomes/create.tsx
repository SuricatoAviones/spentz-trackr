import { setLayoutProps } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { IncomeForm } from '@/components/tracker/income-form';
import type { CategoryOption, RateInfo, RateOptions } from '@/types';

export default function CreateIncome({
    categories,
    rate,
    rates,
}: {
    categories: CategoryOption[];
    rate: RateInfo;
    rates: RateOptions;
}) {
    const { t } = useTranslation();

    setLayoutProps({ title: t('incomes.create_title') });

    return (
        <div className="space-y-4 lg:mx-auto lg:max-w-2xl">
            <IncomeForm
                mode="create"
                categories={categories}
                rate={rate}
                rates={rates}
            />
        </div>
    );
}
