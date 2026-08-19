import { Head, router } from '@inertiajs/react';
import {
    CheckCircle2,
    Database,
    Download,
    HardDrive,
    Server,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatAmount } from '@/lib/format';
import { backup as systemBackup } from '@/routes/admin/system';

type Status = {
    environment: string;
    php_version: string;
    laravel_version: string;
    database: string;
    database_ok: boolean;
    cache_driver: string;
    storage_writable: boolean;
};

type Counts = {
    users: number;
    expenses: number;
    receipts: number;
    exchange_rates: number;
    categories: number;
    payment_sources: number;
};

export default function AdminSystem({
    status,
    counts,
}: {
    status: Status;
    counts: Counts;
}) {
    const [generating, setGenerating] = useState(false);

    function generateBackup() {
        setGenerating(true);
        router.post(
            systemBackup().url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setGenerating(false),
            },
        );
    }

    const healthItems = [
        {
            label: 'Conexión a la base de datos',
            ok: status.database_ok,
            detail: status.database_ok
                ? 'Respondiendo correctamente'
                : 'No se pudo conectar',
        },
        {
            label: 'Almacenamiento',
            ok: status.storage_writable,
            detail: status.storage_writable
                ? 'Directorio storage escribible'
                : 'storage no es escribible',
        },
    ];

    return (
        <>
            <Head title="Sistema - Panel admin" />

            <div className="space-y-8 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h2 className="text-xl font-semibold tracking-tight">
                        Sistema y backup
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Estado general de la aplicación y respaldo de datos.
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Estado del sistema
                            </CardTitle>
                            <CardDescription>
                                Entorno y servicios de la aplicación.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Entorno
                                </span>
                                <span className="font-medium">
                                    {status.environment}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    PHP
                                </span>
                                <span className="font-medium tabular-nums">
                                    {status.php_version}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Laravel
                                </span>
                                <span className="font-medium tabular-nums">
                                    {status.laravel_version}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Base de datos
                                </span>
                                <span className="font-medium">
                                    {status.database}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Caché
                                </span>
                                <span className="font-medium">
                                    {status.cache_driver}
                                </span>
                            </div>
                            <div className="divide-y rounded-lg border">
                                {healthItems.map((item) => (
                                    <div
                                        key={item.label}
                                        className="flex items-center justify-between gap-2 px-3 py-2.5"
                                    >
                                        <span className="font-medium">
                                            {item.label}
                                        </span>
                                        <span
                                            className={`flex items-center gap-1.5 text-xs ${item.ok ? 'text-emerald-500' : 'text-destructive'}`}
                                        >
                                            {item.ok ? (
                                                <CheckCircle2 className="size-4" />
                                            ) : (
                                                <XCircle className="size-4" />
                                            )}
                                            {item.detail}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Volumen de datos
                                </CardTitle>
                                <CardDescription>
                                    Registros almacenados en la plataforma.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {[
                                    {
                                        icon: Server,
                                        label: 'Usuarios',
                                        value: counts.users,
                                    },
                                    {
                                        icon: Database,
                                        label: 'Gastos',
                                        value: counts.expenses,
                                    },
                                    {
                                        icon: HardDrive,
                                        label: 'Comprobantes',
                                        value: counts.receipts,
                                    },
                                    {
                                        icon: Database,
                                        label: 'Tasas',
                                        value: counts.exchange_rates,
                                    },
                                    {
                                        icon: Server,
                                        label: 'Categorías',
                                        value: counts.categories,
                                    },
                                    {
                                        icon: Server,
                                        label: 'Orígenes',
                                        value: counts.payment_sources,
                                    },
                                ].map((item) => (
                                    <div
                                        key={item.label}
                                        className="rounded-lg bg-surface-low p-3"
                                    >
                                        <item.icon className="size-4 text-muted-foreground" />
                                        <p className="mt-2 text-lg font-semibold tabular-nums">
                                            {formatAmount(item.value)}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {item.label}
                                        </p>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Backup de la base de datos
                                </CardTitle>
                                <CardDescription>
                                    Descarga un respaldo JSON con todos los
                                    registros (usuarios, categorías, orígenes,
                                    tasas, gastos y comprobantes). Los archivos
                                    de comprobantes no se incluyen.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button
                                    onClick={generateBackup}
                                    disabled={generating}
                                >
                                    <Download className="size-4" />
                                    {generating
                                        ? 'Generando...'
                                        : 'Descargar backup'}
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}