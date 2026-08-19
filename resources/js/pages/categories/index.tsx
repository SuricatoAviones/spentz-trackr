import { router, useForm } from '@inertiajs/react';
import { ChevronRight, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
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
    budget: string | null;
    is_system: boolean;
    expenses_count: number;
    total_usd: number;
    monthly_spent: number;
};

export default function CategoriesIndex({
    categories,
    monthlyCount,
}: {
    categories: Category[];
    monthlyCount: number;
}) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Category | null>(null);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        icon: 'tag',
        color: '#10B981',
        budget: '',
    });

    function openCreate() {
        setEditing(null);
        reset();
        setOpen(true);
    }

    function openEdit(category: Category) {
        setEditing(category);
        setData({
            name: category.name,
            icon: category.icon,
            color: category.color,
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
        if (confirm(`¿Eliminar la categoría "${category.name}"?`)) {
            router.delete(categoriesDestroy(category.id).url);
        }
    }

    return (
        <div className="space-y-4">
            <TrackerCard className="px-4 py-3">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm font-semibold text-foreground">
                            Resumen del mes
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {categories.length} categorías · {monthlyCount}{' '}
                            gastos este mes
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={openCreate}
                        className="hidden items-center gap-2 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 px-4 py-2 text-xs font-bold text-primary-foreground lg:inline-flex"
                    >
                        <Plus className="size-4" />
                        Nueva categoría
                    </button>
                </div>
            </TrackerCard>

            <div className="space-y-2.5 lg:grid lg:grid-cols-2 lg:gap-3 lg:space-y-0 xl:grid-cols-3">
                {categories.map((category) => (
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
                            <p className="text-xs text-muted-foreground">
                                {category.expenses_count}{' '}
                                {category.expenses_count === 1
                                    ? 'gasto'
                                    : 'gastos'}{' '}
                                · {formatAmount(category.total_usd)} USD
                            </p>
                            {category.budget !== null &&
                                category.budget !== '0.00' && (
                                    <p
                                        className={`mt-0.5 text-[11px] font-medium ${
                                            category.monthly_spent >
                                            Number(category.budget)
                                                ? 'text-destructive'
                                                : 'text-emerald-400'
                                        }`}
                                    >
                                        Presupuesto{' '}
                                        {formatAmount(category.budget)} USD ·{' '}
                                        {formatAmount(category.monthly_spent)}{' '}
                                        usado
                                    </p>
                                )}
                        </div>
                        <button
                            type="button"
                            onClick={() => openEdit(category)}
                            className="inline-flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:text-foreground"
                            aria-label={`Editar ${category.name}`}
                        >
                            <Pencil className="size-4" />
                        </button>
                        <button
                            type="button"
                            onClick={() => destroy(category)}
                            disabled={category.expenses_count > 0}
                            className="inline-flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:text-destructive disabled:cursor-not-allowed disabled:opacity-30"
                            aria-label={`Eliminar ${category.name}`}
                            title={
                                category.expenses_count > 0
                                    ? 'No se puede eliminar: tiene gastos asociados'
                                    : 'Eliminar'
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
                aria-label="Nueva categoría"
            >
                <Plus className="size-6" strokeWidth={2.5} />
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="rounded-2xl border-white/10 bg-surface-low">
                    <DialogHeader>
                        <DialogTitle className="font-display">
                            {editing ? 'Editar categoría' : 'Nueva categoría'}
                        </DialogTitle>
                        <DialogDescription className="text-muted-foreground">
                            {editing
                                ? 'Actualiza los datos de la categoría.'
                                : 'Crea una categoría para clasificar tus gastos.'}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label
                                htmlFor="category-name"
                                className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Nombre
                            </label>
                            <input
                                id="category-name"
                                type="text"
                                maxLength={50}
                                value={data.name}
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                                placeholder="Ej: Vivienda"
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
                                Icono
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
                                        aria-label={`Icono ${icon}`}
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
                                Color
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
                                        aria-label={`Color ${color}`}
                                    />
                                ))}
                            </div>
                            {errors.color && (
                                <p className="mt-1 text-xs text-destructive">
                                    {errors.color}
                                </p>
                            )}
                        </div>

                        <div>
                            <label
                                htmlFor="category-budget"
                                className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Presupuesto mensual (USD)
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
                                placeholder="Sin límite"
                                className="h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                            />
                            {errors.budget && (
                                <p className="mt-1 text-xs text-destructive">
                                    {errors.budget}
                                </p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 py-3 font-display text-sm font-bold text-primary-foreground disabled:opacity-60"
                        >
                            {processing
                                ? 'Guardando...'
                                : editing
                                  ? 'ACTUALIZAR'
                                  : 'CREAR'}
                        </button>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
