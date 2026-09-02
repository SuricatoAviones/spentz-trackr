import { router, setLayoutProps, useForm } from '@inertiajs/react';
import { ChevronRight, Pencil, Plus, Trash2 } from 'lucide-react';
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
import { formatAmount } from '@/lib/format';
import {
    destroy as categoriesDestroy,
    store as categoriesStore,
    update as categoriesUpdate,
} from '@/routes/categories';

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
    'shopping-cart',
    'utensils',
    'car',
    'zap',
    'heart-pulse',
    'gamepad-2',
    'shirt',
    'graduation-cap',
    'tag',
];

type Category = {
    id: number;
    name: string;
    icon: string;
    color: string;
    type: 'expense' | 'income';
    budget: string | null;
    is_system: boolean;
    expenses_count: number;
    incomes_count: number;
    total_usd: number;
    income_total_usd: number;
    monthly_spent: number;
};

type CategoryType = 'expense' | 'income';

export default function CategoriesIndex({
    categories,
    monthlyIncomeCount,
}: {
    categories: Category[];
    monthlyIncomeCount: number;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Category | null>(null);
    const [activeType, setActiveType] = useState<CategoryType>('expense');

    setLayoutProps({ title: t('categories.title') });

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        icon: 'tag',
        color: '#10B981',
        type: 'expense' as CategoryType,
        budget: '',
    });

    const visibleCategories = categories.filter(
        (category) => category.type === activeType,
    );

    function openCreate() {
        setEditing(null);
        reset();
        setData('type', activeType);
        setOpen(true);
    }

    function openEdit(category: Category) {
        setEditing(category);
        setData({
            name: category.name,
            icon: category.icon,
            color: category.color,
            type: category.type,
            budget: category.budget ? String(category.budget) : '',
        });
        setOpen(true);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (editing) {
            put(categoriesUpdate({ category: editing.id }).url, {
                onSuccess: () => {
                    setOpen(false);
                    reset();
                },
            });
        } else {
            post(categoriesStore().url, {
                onSuccess: () => {
                    setOpen(false);
                    reset();
                },
            });
        }
    }

    function destroy(category: Category) {
        if (confirm(t('categories.delete_confirm', { name: category.name }))) {
            router.delete(categoriesDestroy(category.id).url);
        }
    }

    const countFor = (category: Category): number =>
        category.type === 'expense'
            ? category.expenses_count
            : category.incomes_count;

    const isDeleteBlocked = (category: Category): boolean =>
        countFor(category) > 0;

    return (
        <div className="space-y-4">
            <TrackerCard className="px-4 py-3">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm font-semibold text-foreground">
                            {t('categories.month_summary')}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {t('categories.summary_income', {
                                count: categories.filter(
                                    (category) => category.type === 'income',
                                ).length,
                                monthly: monthlyIncomeCount,
                            })}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={openCreate}
                        className="hidden items-center gap-2 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 px-4 py-2 text-xs font-bold text-primary-foreground lg:inline-flex"
                    >
                        <Plus className="size-4" />
                        {t('categories.new')}
                    </button>
                </div>
            </TrackerCard>

            <div className="grid grid-cols-2 gap-1 rounded-xl bg-surface-low p-1">
                <button
                    type="button"
                    onClick={() => setActiveType('expense')}
                    className={`rounded-lg py-2.5 text-sm font-semibold transition-colors ${
                        activeType === 'expense'
                            ? 'bg-surface-high text-foreground shadow'
                            : 'text-muted-foreground'
                    }`}
                >
                    {t('categories.type_expense')}
                </button>
                <button
                    type="button"
                    onClick={() => setActiveType('income')}
                    className={`rounded-lg py-2.5 text-sm font-semibold transition-colors ${
                        activeType === 'income'
                            ? 'bg-surface-high text-foreground shadow'
                            : 'text-muted-foreground'
                    }`}
                >
                    {t('categories.type_income')}
                </button>
            </div>

            <p className="px-1 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                {activeType === 'expense'
                    ? t('categories.section_expenses')
                    : t('categories.section_incomes')}
            </p>

            <div className="space-y-2.5 lg:grid lg:grid-cols-2 lg:gap-3 lg:space-y-0 xl:grid-cols-3">
                {visibleCategories.map((category) => (
                    <div
                        key={category.id}
                        className="flex items-center gap-3 rounded-xl bg-surface-low p-3.5"
                    >
                        <CategoryIcon
                            icon={category.icon}
                            color={category.color}
                        />
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-semibold text-foreground">
                                {category.name}
                            </p>
                            {category.type === 'expense' ? (
                                <p className="text-xs text-muted-foreground">
                                    {category.expenses_count}{' '}
                                    {t('expenses.word', {
                                        count: category.expenses_count,
                                    })}{' '}
                                    · {formatAmount(category.total_usd)} USD
                                </p>
                            ) : (
                                <p className="text-xs text-muted-foreground">
                                    {category.incomes_count}{' '}
                                    {t('incomes.word', {
                                        count: category.incomes_count,
                                    })}{' '}
                                    · {formatAmount(category.income_total_usd)}{' '}
                                    USD
                                </p>
                            )}
                            {category.type === 'expense' &&
                                category.budget !== null &&
                                category.budget !== '0.00' && (
                                    <p
                                        className={`mt-0.5 text-[11px] font-medium ${
                                            category.monthly_spent >
                                            Number(category.budget)
                                                ? 'text-destructive'
                                                : 'text-emerald-400'
                                        }`}
                                    >
                                        {t('categories.budget_info', {
                                            budget: formatAmount(
                                                category.budget,
                                            ),
                                            spent: formatAmount(
                                                category.monthly_spent,
                                            ),
                                        })}
                                    </p>
                                )}
                        </div>
                        <button
                            type="button"
                            onClick={() => openEdit(category)}
                            className="inline-flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:text-foreground"
                            aria-label={t('categories.edit_aria', {
                                name: category.name,
                            })}
                        >
                            <Pencil className="size-4" />
                        </button>
                        <button
                            type="button"
                            onClick={() => destroy(category)}
                            disabled={isDeleteBlocked(category)}
                            className="inline-flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:text-destructive disabled:cursor-not-allowed disabled:opacity-30"
                            aria-label={t('categories.delete_aria', {
                                name: category.name,
                            })}
                            title={
                                isDeleteBlocked(category)
                                    ? t('common.delete_blocked')
                                    : t('common.delete')
                            }
                        >
                            <Trash2 className="size-4" />
                        </button>
                        <ChevronRight className="size-4 text-muted-foreground/50" />
                    </div>
                ))}
            </div>

            <button
                type="button"
                onClick={openCreate}
                className="fixed right-4 bottom-24 z-30 inline-flex size-14 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 text-primary-foreground shadow-xl shadow-emerald-500/30 transition-transform active:scale-95 md:right-[calc(50%-13rem)] lg:hidden"
                aria-label={t('categories.new')}
            >
                <Plus className="size-6" strokeWidth={2.5} />
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="rounded-2xl border-white/10 bg-surface-low">
                    <DialogHeader>
                        <DialogTitle className="font-display">
                            {editing
                                ? t('categories.edit_title')
                                : t('categories.create_title')}
                        </DialogTitle>
                        <DialogDescription className="text-muted-foreground">
                            {editing
                                ? t('categories.edit_description')
                                : t('categories.create_description')}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <span className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('categories.type_label')}
                            </span>
                            <div className="grid grid-cols-2 gap-1 rounded-lg bg-surface-high p-1">
                                <button
                                    type="button"
                                    disabled={Boolean(editing)}
                                    onClick={() => setData('type', 'expense')}
                                    className={`rounded-md py-2 text-xs font-semibold transition-colors disabled:opacity-60 ${
                                        data.type === 'expense'
                                            ? 'bg-emerald-500/20 text-emerald-400'
                                            : 'text-muted-foreground'
                                    }`}
                                >
                                    {t('categories.type_expense')}
                                </button>
                                <button
                                    type="button"
                                    disabled={Boolean(editing)}
                                    onClick={() => setData('type', 'income')}
                                    className={`rounded-md py-2 text-xs font-semibold transition-colors disabled:opacity-60 ${
                                        data.type === 'income'
                                            ? 'bg-blue-500/20 text-blue-400'
                                            : 'text-muted-foreground'
                                    }`}
                                >
                                    {t('categories.type_income')}
                                </button>
                            </div>
                        </div>

                        <div>
                            <label
                                htmlFor="category-name"
                                className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                {t('common.name')}
                            </label>
                            <input
                                id="category-name"
                                type="text"
                                maxLength={50}
                                value={data.name}
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                                placeholder={t('categories.name_placeholder')}
                                className="h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                            />
                            {errors.name && (
                                <p className="mt-1 text-xs text-destructive">
                                    {errors.name}
                                </p>
                            )}
                        </div>

                        <div>
                            <span className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('common.icon')}
                            </span>
                            <div className="flex flex-wrap gap-2">
                                {ICON_OPTIONS.map((icon) => (
                                    <button
                                        key={icon}
                                        type="button"
                                        onClick={() => setData('icon', icon)}
                                        className={`flex size-10 items-center justify-center rounded-lg transition-colors ${
                                            data.icon === icon
                                                ? 'bg-emerald-500/20 ring-2 ring-emerald-500'
                                                : 'bg-surface-high'
                                        }`}
                                        aria-label={t('common.icon_aria', {
                                            icon,
                                        })}
                                    >
                                        <CategoryIcon
                                            icon={icon}
                                            color={data.color}
                                            size="sm"
                                        />
                                    </button>
                                ))}
                            </div>
                            {errors.icon && (
                                <p className="mt-1 text-xs text-destructive">
                                    {errors.icon}
                                </p>
                            )}
                        </div>

                        <div>
                            <span className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('common.color')}
                            </span>
                            <div className="flex flex-wrap gap-2">
                                {PRESET_COLORS.map((color) => (
                                    <button
                                        key={color}
                                        type="button"
                                        onClick={() => setData('color', color)}
                                        className={`size-8 rounded-full transition-transform ${
                                            data.color === color
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
                            {errors.color && (
                                <p className="mt-1 text-xs text-destructive">
                                    {errors.color}
                                </p>
                            )}
                        </div>

                        {data.type === 'expense' && (
                            <div>
                                <label
                                    htmlFor="category-budget"
                                    className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    {t('categories.budget_label')}
                                </label>
                                <input
                                    id="category-budget"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    inputMode="decimal"
                                    value={data.budget}
                                    onChange={(event) =>
                                        setData('budget', event.target.value)
                                    }
                                    placeholder={t(
                                        'categories.budget_placeholder',
                                    )}
                                    className="h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                                />
                                {errors.budget && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {errors.budget}
                                    </p>
                                )}
                            </div>
                        )}

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 py-3 font-display text-sm font-bold text-primary-foreground disabled:opacity-60"
                        >
                            {processing
                                ? t('common.saving')
                                : editing
                                  ? t('common.update')
                                  : t('common.create')}
                        </button>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
