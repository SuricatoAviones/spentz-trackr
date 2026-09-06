import { setLayoutProps } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ExpenseForm } from '@/components/tracker/expense-form';
import type {
    CategoryOption,
    CommissionDefaults,
    Expense,
    RateInfo,
    RateOptions,
    SourceOption,
} from '@/types';

export default function EditExpense({
    expense,
    categories,
    sources,
    rate,
    rates,
    commissionDefaults,
}: {
    expense: Expense;
    categories: CategoryOption[];
    sources: SourceOption[];
    rate: RateInfo;
    rates: RateOptions;
    commissionDefaults: CommissionDefaults;
}) {
    const { t } = useTranslation();

    setLayoutProps({ title: t('expenses.edit_title') });

    return (
        <div className="space-y-4 lg:mx-auto lg:max-w-2xl">
            <ExpenseForm
                mode="edit"
                categories={categories}
                sources={sources}
                rate={rate}
                rates={rates}
                commissionDefaults={commissionDefaults}
                initial={{
                    id: expense.id,
                    description: expense.description,
                    note: expense.note ?? '',
                    amount: String(Number(expense.amount) || 0),
                    currency: expense.currency,
                    payment_method: expense.payment_method ?? null,
                    commission: expense.commission
                        ? String(expense.commission)
                        : null,
                    exchange_rate: expense.exchange_rate
                        ? String(expense.exchange_rate)
                        : null,
                    rate_provider: expense.rate_provider ?? null,
                    category_id: expense.category.id,
                    payment_source_id: expense.source.id,
                    spent_at: expense.spent_at,
                    has_receipt: expense.has_receipt,
                    items: (expense.items ?? []).map((item) => ({
                        currency: item.currency,
                        amount: String(item.amount ?? ''),
                        exchange_rate: item.exchange_rate
                            ? String(item.exchange_rate)
                            : null,
                        rate_provider: null,
                        usd_amount: item.usd_amount
                            ? String(item.usd_amount)
                            : null,
                    })),
                }}
            />
        </div>
    );
}
