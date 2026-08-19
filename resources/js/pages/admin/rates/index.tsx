import { Head, router, useForm } from '@inertiajs/react';
import { RefreshCw, Users } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatRate } from '@/lib/format';
import { sync as ratesSync, update as ratesUpdate } from '@/routes/admin/rates';

type TodayRates = {
    date: string;
    bcv: string | null;
    paralelo: string | null;
};

type ManualOverride = {
    id: number;
    rate: string;
    user: {
        id: number;
        name: string;
        email: string;
    };
};

type HistoryEntry = {
    id: number;
    rate: string;
    provider: string;
    source: 'api' | 'manual' | 'seed';
    rate_date: string;
    user_name: string | null;
};

const PROVIDER_LABEL: Record<string, string> = {
    bcv: 'BCV',
    paralelo: 'Paralelo',
    user: 'Manual',
    dolarapi: 'dolarapi',
};

const SOURCE_LABEL: Record<HistoryEntry['source'], string> = {
    api: 'API',
    manual: 'Manual',
    seed: 'Semilla',
};

export default function AdminRatesIndex({
    today,
    manualToday,
    history,
}: {
    today: TodayRates;
    manualToday: ManualOverride[];
    history: HistoryEntry[];
}) {
    const { data, setData, put, processing, errors } = useForm({
        bcv: today.bcv ? String(today.bcv) : '',
        paralelo: today.paralelo ? String(today.paralelo) : '',
    });

    function saveRates(event: React.FormEvent) {
        event.preventDefault();
        put(ratesUpdate().url, { preserveScroll: true });
    }

    function syncRates() {
        router.post(ratesSync().url, {}, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Tasas de cambio - Panel admin" />

            <div className="space-y-8 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h2 className="text-xl font-semibold tracking-tight">
                        Tasas de cambio
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Tasas globales Bs/USD usadas para precargar los
                        formularios de gasto.
                    </p>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle className="text-base">
                                Tasa del día ({today.date})
                            </CardTitle>
                            <CardDescription>
                                Sobrescribe la tasa del día para toda la
                                plataforma. La sincronización automática no la
                                reemplazará hoy.
                            </CardDescription>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={syncRates}
                        >
                            <RefreshCw className="size-4" />
                            Sincronizar con dolarapi
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={saveRates}
                            className="space-y-4"
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="rate-bcv">BCV</Label>
                                    <Input
                                        id="rate-bcv"
                                        type="number"
                                        step="0.0001"
                                        min="0.0001"
                                        inputMode="decimal"
                                        value={data.bcv}
                                        onChange={(event) =>
                                            setData('bcv', event.target.value)
                                        }
                                        placeholder="Ej: 36.5000"
                                    />
                                    {errors.bcv && (
                                        <p className="text-xs text-destructive">
                                            {errors.bcv}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="rate-paralelo">
                                        Paralelo
                                    </Label>
                                    <Input
                                        id="rate-paralelo"
                                        type="number"
                                        step="0.0001"
                                        min="0.0001"
                                        inputMode="decimal"
                                        value={data.paralelo}
                                        onChange={(event) =>
                                            setData(
                                                'paralelo',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Ej: 37.2000"
                                    />
                                    {errors.paralelo && (
                                        <p className="text-xs text-destructive">
                                            {errors.paralelo}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <Button type="submit" disabled={processing}>
                                {processing ? 'Guardando...' : 'Guardar tasas'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Users className="size-4" />
                            Tasas manuales de usuarios hoy
                        </CardTitle>
                        <CardDescription>
                            Los usuarios que fijaron su propia tasa hoy. Sus
                            gastos usan su tasa manual, no la global.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-2">
                        {manualToday.length === 0 ? (
                            <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                                Ningún usuario fijó una tasa manual hoy.
                            </p>
                        ) : (
                            <div className="divide-y">
                                {manualToday.map((override) => (
                                    <div
                                        key={override.id}
                                        className="flex items-center justify-between gap-4 px-4 py-3"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {override.user.name}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {override.user.email}
                                            </p>
                                        </div>
                                        <Badge variant="outline">
                                            {formatRate(override.rate)} Bs/USD
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Historial reciente
                        </CardTitle>
                        <CardDescription>
                            Últimos 50 registros de tasas guardadas.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs tracking-wider text-muted-foreground uppercase">
                                        <th className="px-4 py-3 font-medium">
                                            Fecha
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Origen
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Proveedor
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium md:table-cell">
                                            Usuario
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Tasa
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {history.map((entry) => (
                                        <tr
                                            key={entry.id}
                                            className="hover:bg-accent/50"
                                        >
                                            <td className="px-4 py-3">
                                                {entry.rate_date}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant={
                                                        entry.source ===
                                                        'manual'
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {
                                                        SOURCE_LABEL[
                                                            entry.source
                                                        ]
                                                    }
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3">
                                                {PROVIDER_LABEL[
                                                    entry.provider
                                                ] ?? entry.provider}
                                            </td>
                                            <td className="hidden px-4 py-3 md:table-cell">
                                                {entry.user_name ?? (
                                                    <span className="text-muted-foreground">
                                                        Global
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium tabular-nums">
                                                {formatRate(entry.rate)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {history.length === 0 && (
                            <p className="px-4 py-12 text-center text-sm text-muted-foreground">
                                Aún no hay tasas registradas.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}