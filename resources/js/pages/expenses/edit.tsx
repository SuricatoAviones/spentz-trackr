import { ExpenseForm } from '@/components/tracker/expense-form';
import type {
    CategoryOption,
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
}: {
    expense: Expense;
    categories: CategoryOption[];
    sources: SourceOption[];
    rate: RateInfo;
    rates: RateOptions;
}) {
    return (
        <div className="space-y-4 lg:mx-auto lg:max-w-2xl">
            <ExpenseForm
                mode="edit"
                categories={categories}
                sources={sources}
                rate={rate}
                rates={rates}
                initial={{
                    id: expense.id,
                    description: expense.description,
                    note: expense.note ?? '',
                    amount: String(expense.amount),
                    currency: expense.currency,
                    exchange_rate: expense.exchange_rate
                        ? String(expense.exchange_rate)
                        : null,
                    rate_provider: expense.rate_provider ?? null,
                    category_id: expense.category.id,
                    payment_source_id: expense.source.id,
                    spent_at: expense.spent_at,
                    has_receipt: expense.has_receipt,
                }}
            />
        </div>
    );
}
