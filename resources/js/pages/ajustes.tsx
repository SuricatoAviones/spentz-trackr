import { Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ChevronRight,
    Landmark,
    LogOut,
    RefreshCw,
    Settings2,
    Tag,
} from 'lucide-react';
import { TrackerCard } from '@/components/tracker/tracker-card';
import { formatRate } from '@/lib/format';
import { logout } from '@/routes';
import { index as categoriesIndex } from '@/routes/categories';
import { sync as rateSync, update as rateUpdate } from '@/routes/exchange-rate';
import { index as sourcesIndex } from '@/routes/sources';
import type { RateInfo } from '@/types';

export default function Ajustes({
    rate,
    monthlyExpenseCount,
}: {
    rate: RateInfo;
    monthlyExpenseCount: number;
}) {
    const { auth } = usePage().props;
    const user = auth.user;

    const { data, setData, put, processing, errors } = useForm({
        rate: Number(rate.rate) > 0 ? String(rate.rate) : '',
    });

    function saveRate(event: React.FormEvent) {
        event.preventDefault();
        put(rateUpdate().url);
    }

    function syncRates() {
        router.post(rateSync().url, {}, { preserveScroll: true });
    }

    function logoutUser() {
        router.post(logout().url);
    }

    const initials = user.name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

    const rateBadge =
        rate.provider === 'user'
            ? { label: 'Manual', className: 'bg-blue-500/15 text-blue-400' }
            : rate.provider === 'paralelo'
              ? {
                    label: 'Paralelo',
                    className: 'bg-amber-500/15 text-amber-400',
                }
              : {
                    label: rate.provider === 'none' ? 'Sin tasa' : 'BCV',
                    className: 'bg-emerald-500/15 text-emerald-400',
                };

    return (
        <div className="grid gap-4 lg:grid-cols-5 lg:gap-5">
            <div className="space-y-4 lg:col-span-2">
                <TrackerCard>
                <Link
                    href="/settings/profile"
                    className="flex items-center gap-3 px-4 py-4"
                >
                    <span className="flex size-12 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 font-display text-base font-bold text-primary-foreground">
                        {initials}
                    </span>
                    <span className="min-w-0 flex-1">
                        <span className="block truncate text-sm font-semibold text-foreground">
                            {user.name}
                        </span>
                        <span className="block truncate text-xs text-muted-foreground">
                            {user.email}
                        </span>
                    </span>
                    <ChevronRight className="size-4 text-muted-foreground" />
                </Link>
            </TrackerCard>

            <TrackerCard title="Tasa de cambio Bs/USD">
                <form onSubmit={saveRate} className="px-4 pt-3 pb-4">
                    <div className="flex items-center justify-between">
                        <span className="text-xs text-muted-foreground">
                            Tasa del día
                        </span>
                        <span
                            className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${rateBadge.className}`}
                        >
                            {rateBadge.label}
                        </span>
                    </div>
                    <input
                        type="number"
                        step="0.0001"
                        min="0.0001"
                        inputMode="decimal"
                        value={data.rate}
                        onChange={(event) =>
                            setData('rate', event.target.value)
                        }
                        className="mt-2 w-full bg-transparent font-display text-3xl font-extrabold text-amber-400 tabular-nums focus:outline-none"
                        aria-label="Tasa de cambio Bs/USD"
                    />
                    <p className="mt-1 text-[11px] text-muted-foreground">
                        {rate.provider === 'user'
                            ? 'Tasa fijada por ti: tendrá prioridad sobre la automática.'
                            : 'Edita este valor y guarda para fijar tu propia tasa manual.'}
                    </p>
                    {errors.rate && (
                        <p className="mt-1 text-xs text-destructive">
                            {errors.rate}
                        </p>
                    )}
                    <button
                        type="submit"
                        disabled={processing}
                        className="mt-3 w-full rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 py-2.5 text-xs font-bold text-primary-foreground disabled:opacity-60"
                    >
                        {processing ? 'Guardando...' : 'Guardar tasa manual'}
                    </button>
                    <div className="mt-3 flex items-center justify-between border-t border-white/5 pt-3">
                        <span className="text-[11px] text-muted-foreground">
                            Última sincronización: {rate.rate_date} ·{' '}
                            {formatRate(rate.rate)}
                        </span>
                        <button
                            type="button"
                            onClick={syncRates}
                            className="inline-flex items-center gap-1.5 text-[11px] font-semibold text-emerald-400"
                        >
                            <RefreshCw className="size-3.5" />
                            Sincronizar
                        </button>
                    </div>
                </form>
            </TrackerCard>
            </div>

            <div className="space-y-4 lg:col-span-3">
                <TrackerCard title="Preferencias">
                <div className="divide-y divide-white/5 px-4">
                    <Link
                        href={categoriesIndex().url}
                        className="flex items-center justify-between py-3.5"
                    >
                        <span className="flex items-center gap-3 text-sm text-foreground">
                            <Tag className="size-4 text-muted-foreground" />
                            Categorías
                        </span>
                        <ChevronRight className="size-4 text-muted-foreground" />
                    </Link>
                    <Link
                        href={sourcesIndex().url}
                        className="flex items-center justify-between py-3.5"
                    >
                        <span className="flex items-center gap-3 text-sm text-foreground">
                            <Landmark className="size-4 text-muted-foreground" />
                            Orígenes de pago
                        </span>
                        <ChevronRight className="size-4 text-muted-foreground" />
                    </Link>
                    <Link
                        href="/settings/appearance"
                        className="flex items-center justify-between py-3.5"
                    >
                        <span className="flex items-center gap-3 text-sm text-foreground">
                            <Settings2 className="size-4 text-muted-foreground" />
                            Apariencia
                        </span>
                        <ChevronRight className="size-4 text-muted-foreground" />
                    </Link>
                    <Link
                        href="/settings/security"
                        className="flex items-center justify-between py-3.5"
                    >
                        <span className="flex items-center gap-3 text-sm text-foreground">
                            <Settings2 className="size-4 text-muted-foreground" />
                            Seguridad y contraseña
                        </span>
                        <ChevronRight className="size-4 text-muted-foreground" />
                    </Link>
                    <div className="flex items-center justify-between py-3.5">
                        <span className="text-sm text-foreground">
                            Gastos este mes
                        </span>
                        <span className="text-sm font-semibold text-muted-foreground tabular-nums">
                            {monthlyExpenseCount}
                        </span>
                    </div>
                </div>
            </TrackerCard>

            <button
                type="button"
                onClick={logoutUser}
                className="flex w-full items-center justify-center gap-2 rounded-xl bg-surface-low py-3.5 text-sm font-semibold text-destructive"
            >
                <LogOut className="size-4" />
                Cerrar sesión
            </button>

            <p className="pb-2 text-center text-[11px] text-muted-foreground/70">
                Spent Trackr v1.0.0
            </p>
            </div>
        </div>
    );
}
