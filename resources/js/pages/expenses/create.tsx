import { ExpenseForm } from '@/components/tracker/expense-form';
import type {
    CategoryOption,
    RateInfo,
    RateOptions,
    SourceOption,
} from '@/types';

export default function CreateExpense({
    categories,
    sources,
    rate,
    rates,
}: {
    categories: CategoryOption[];
    sources: SourceOption[];
    rate: RateInfo;
    rates: RateOptions;
}) {
    return (
        <div className="space-y-4 lg:mx-auto lg:max-w-2xl">
            <ExpenseForm
                mode="create"
                categories={categories}
                sources={sources}
                rate={rate}
                rates={rates}
            />
        </div>
    );
}
