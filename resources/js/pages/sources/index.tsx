import { router, useForm } from '@inertiajs/react';
import { ChevronRight, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { CategoryIcon } from '@/components/tracker/category-icon';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatAmount } from '@/lib/format';
import {
    destroy as sourcesDestroy,
    store as sourcesStore,
    update as sourcesUpdate,
} from '@/routes/sources';

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
    'bitcoin',
    'landmark',
    'wallet',
    'banknote',
    'credit-card',
    'smartphone',
    'receipt',
];

type Source = {
    id: number;
    name: string;
    icon: string;
    color: string;
    is_system: boolean;
    expenses_count: number;
    total_usd: number;
};

export default function SourcesIndex({ sources }: { sources: Source[] }) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Source | null>(null);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        icon: 'wallet',
        color: '#3B82F6',
    });

    function openCreate() {
        setEditing(null);
        reset();
        setOpen(true);
    }

    function openEdit(source: Source) {
        setEditing(source);
        setData({ name: source.name, icon: source.icon, color: source.color });
        setOpen(true);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (editing) {
            put(sourcesUpdate({ source: editing.id }).url, {
                onSuccess: () => {
                    setOpen(false);
                    reset();
                },
            });
        } else {
            post(sourcesStore().url, {
                onSuccess: () => {
                    setOpen(false);
                    reset();
                },
            });
        }
    }

    function destroy(source: Source) {
        if (confirm(`¿Eliminar el origen "${source.name}"?`)) {
            router.delete(sourcesDestroy(source.id).url);
        }
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <p className="text-sm font-medium text-muted-foreground">
                    {sources.length}{' '}
                    {sources.length === 1 ? 'origen' : 'orígenes'} de pago
                </p>
                <button
                    type="button"
                    onClick={openCreate}
                    className="hidden items-center gap-2 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 px-4 py-2 text-xs font-bold text-primary-foreground lg:inline-flex"
                >
                    <Plus className="size-4" />
                    Nuevo origen
                </button>
            </div>

            <div className="space-y-2.5 lg:grid lg:grid-cols-2 lg:gap-3 lg:space-y-0 xl:grid-cols-3">
                {sources.map((source) => (
                    <div
                        key={source.id}
                        className="flex items-center gap-3 rounded-xl bg-surface-low p-3.5"
                    >
                        <CategoryIcon icon={source.icon} color={source.color} />
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-semibold text-foreground">
                                {source.name}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {source.expenses_count}{' '}
                                {source.expenses_count === 1
                                    ? 'gasto'
                                    : 'gastos'}{' '}
                                · {formatAmount(source.total_usd)} USD
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => openEdit(source)}
                            className="inline-flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:text-foreground"
                            aria-label={`Editar ${source.name}`}
                        >
                            <Pencil className="size-4" />
                        </button>
                        <button
                            type="button"
                            onClick={() => destroy(source)}
                            disabled={source.expenses_count > 0}
                            className="inline-flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:text-destructive disabled:cursor-not-allowed disabled:opacity-30"
                            aria-label={`Eliminar ${source.name}`}
                            title={
                                source.expenses_count > 0
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
                aria-label="Nuevo origen"
            >
                <Plus className="size-6" strokeWidth={2.5} />
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="rounded-2xl border-white/10 bg-surface-low">
                    <DialogHeader>
                        <DialogTitle className="font-display">
                            {editing ? 'Editar origen' : 'Nuevo origen'}
                        </DialogTitle>
                        <DialogDescription className="text-muted-foreground">
                            {editing
                                ? 'Actualiza los datos del origen de pago.'
                                : 'Crea un origen de pago (billetera, banco, efectivo...).'}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label
                                htmlFor="source-name"
                                className="mb-1.5 block text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Nombre
                            </label>
                            <input
                                id="source-name"
                                type="text"
                                maxLength={50}
                                value={data.name}
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                                placeholder="Ej: Zelle"
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
