import { setLayoutProps } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ExpenseForm } from '@/components/tracker/expense-form';
import type {
    CategoryOption,
    CommissionDefaults,
    RateInfo,
    RateOptions,
    SourceOption,
} from '@/types';

export default function CreateExpense({
    categories,
    sources,
    rate,
    rates,
    commissionDefaults,
}: {
    categories: CategoryOption[];
    sources: SourceOption[];
    rate: RateInfo;
    rates: RateOptions;
    commissionDefaults: CommissionDefaults;
}) {
    const { t } = useTranslation();

    setLayoutProps({ title: t('expenses.create_title') });

    return (
        <div className="space-y-4 lg:mx-auto lg:max-w-2xl">
            <ExpenseForm
                mode="create"
                categories={categories}
                sources={sources}
                rate={rate}
                rates={rates}
                commissionDefaults={commissionDefaults}
            />
        </div>
    );
}
