import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Database as DatabaseIcon,
    Server,
    File as FileIcon,
} from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { StepIndicator } from '@/components/install/StepIndicator';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { postJson } from '@/lib/install-http';
import { cn } from '@/lib/utils';

type Connection = 'sqlite' | 'mysql' | 'pgsql';

type DatabaseProps = {
    defaults: {
        connection: string;
        host: string;
        port: string;
        database: string;
        username: string;
        password: string;
    };
};

const CONNECTIONS: {
    id: Connection;
    label: string;
    icon: typeof DatabaseIcon;
    hint: string;
}[] = [
    {
        id: 'sqlite',
        label: 'SQLite',
        icon: FileIcon,
        hint: 'Recomendado para pruebas o uso personal. No requiere servidor.',
    },
    {
        id: 'mysql',
        label: 'MySQL',
        icon: DatabaseIcon,
        hint: 'La opción más popular para producción.',
    },
    {
        id: 'pgsql',
        label: 'PostgreSQL',
        icon: Server,
        hint: 'Base de datos avanzada de código abierto.',
    },
];

export default function Database({ defaults }: DatabaseProps) {
    const [connection, setConnection] = useState<Connection>(
        (defaults.connection as Connection) === 'sqlite' ? 'sqlite' : 'mysql',
    );
    const [form, setForm] = useState({
        host: defaults.host,
        port: connection === 'pgsql' ? '5432' : defaults.port,
        database: defaults.database,
        username: defaults.username,
        password: defaults.password,
    });
    const [busy, setBusy] = useState(false);
    const [serverError, setServerError] = useState<string | null>(null);

    const handleNext = async () => {
        setBusy(true);
        setServerError(null);

        try {
            const response = await postJson('/install/database', {
                ...form,
                connection,
            });

            if (!response.ok) {
                const data = (await response.json().catch(() => ({}))) as {
                    message?: string;
                };

                throw new Error(
                    data.message ?? 'Error al guardar la base de datos.',
                );
            }

            router.visit('/install/app');
        } catch (error) {
            setServerError(
                error instanceof Error
                    ? error.message
                    : 'Error al guardar la base de datos.',
            );
            setBusy(false);
        }
    };

    const needsServer = connection !== 'sqlite';

    return (
        <>
            <Head title="Base de datos" />

            <div className="flex min-h-screen flex-col bg-background px-4 py-8">
                <div className="mx-auto w-full max-w-2xl">
                    <div className="mb-8 flex flex-col items-center gap-4 text-center">
                        <AppLogoIcon className="size-12" />
                        <div>
                            <h1 className="font-display text-2xl font-bold text-foreground">
                                Configura tu base de datos
                            </h1>
                            <p className="text-muted-foreground">
                                Elige el motor de base de datos que prefieras.
                            </p>
                        </div>
                        <StepIndicator current={2} />
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Motor de base de datos</CardTitle>
                            <CardDescription>
                                Spentz Trackr funciona con SQLite, MySQL o
                                PostgreSQL.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid gap-3 sm:grid-cols-3">
                                {CONNECTIONS.map(
                                    ({ id, label, icon: Icon, hint }) => (
                                        <button
                                            key={id}
                                            type="button"
                                            onClick={() => setConnection(id)}
                                            className={cn(
                                                'flex flex-col items-start gap-2 rounded-lg border p-4 text-left transition-colors',
                                                connection === id
                                                    ? 'border-emerald-500 bg-emerald-500/10'
                                                    : 'border-input hover:border-muted-foreground',
                                            )}
                                        >
                                            <Icon className="size-5 text-muted-foreground" />
                                            <span className="text-sm font-semibold">
                                                {label}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {hint}
                                            </span>
                                        </button>
                                    ),
                                )}
                            </div>

                            {needsServer && (
                                <div className="grid gap-4 rounded-lg border bg-muted/40 p-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="host">Host</Label>
                                        <Input
                                            id="host"
                                            value={form.host}
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    host: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="port">Puerto</Label>
                                        <Input
                                            id="port"
                                            value={form.port}
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    port: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="database">
                                            Nombre de la base de datos
                                        </Label>
                                        <Input
                                            id="database"
                                            value={form.database}
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    database: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="username">
                                            Usuario
                                        </Label>
                                        <Input
                                            id="username"
                                            value={form.username}
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    username: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2 sm:col-span-2">
                                        <Label htmlFor="password">
                                            Contraseña
                                        </Label>
                                        <Input
                                            id="password"
                                            type="password"
                                            value={form.password}
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    password: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                </div>
                            )}
                        </CardContent>
                        {serverError && (
                            <p className="mx-6 mb-4 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                                {serverError}
                            </p>
                        )}
                        <CardFooter className="justify-between">
                            <Button variant="ghost" asChild>
                                <Link href="/install">
                                    <ArrowLeft />
                                    Atrás
                                </Link>
                            </Button>
                            <Button onClick={handleNext} disabled={busy}>
                                {busy ? 'Guardando...' : 'Continuar'}
                                {!busy && <ArrowRight />}
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </>
    );
}
