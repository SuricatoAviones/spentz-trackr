import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Loader2 } from 'lucide-react';
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

type AppSetupProps = {
    defaults: {
        app_name: string;
        app_url: string;
        app_locale: string;
        timezone: string;
    };
    database?: {
        connection: string;
        host?: string;
        port?: string;
        database?: string;
        username?: string;
        password?: string;
    };
    errors?: Record<string, string[]>;
};

export default function AppSetup({
    defaults,
    database,
    errors = {},
}: AppSetupProps) {
    const [form, setForm] = useState({
        app_name: defaults.app_name,
        app_url: defaults.app_url,
        app_locale: defaults.app_locale,
        timezone: defaults.timezone,
        admin_name: '',
        admin_email: '',
        admin_password: '',
        admin_password_confirmation: '',
    });
    const [busy, setBusy] = useState(false);
    const [serverError, setServerError] = useState<string | null>(null);

    const field = (key: string) => errors[key] ?? [];

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setServerError(null);

        try {
            const response = await postJson('/install/execute', {
                ...form,
                ...(database ?? {}),
                db_connection: database?.connection ?? 'sqlite',
                db_host: database?.host,
                db_port: database?.port,
                db_database: database?.database,
                db_username: database?.username,
                db_password: database?.password,
            });

            const data = (await response.json().catch(() => ({}))) as {
                message?: string;
                errors?: Record<string, string[]>;
            };

            if (!response.ok) {
                const firstFieldError = Object.values(
                    data.errors ?? {},
                )[0]?.[0];

                throw new Error(
                    firstFieldError ??
                        data.message ??
                        'Ocurrió un error durante la instalación.',
                );
            }

            window.location.href = '/install/finish';
        } catch (error) {
            setServerError(
                error instanceof Error
                    ? error.message
                    : 'Ocurrió un error durante la instalación.',
            );
            setBusy(false);
        }
    };

    return (
        <>
            <Head title="Aplicación y administrador" />

            <div className="flex min-h-screen flex-col bg-background px-4 py-8">
                <div className="mx-auto w-full max-w-2xl">
                    <div className="mb-8 flex flex-col items-center gap-4 text-center">
                        <AppLogoIcon className="size-12" />
                        <div>
                            <h1 className="font-display text-2xl font-bold text-foreground">
                                Configura tu aplicación
                            </h1>
                            <p className="text-muted-foreground">
                                Datos del sitio y primer usuario administrador.
                            </p>
                        </div>
                        <StepIndicator current={3} />
                    </div>

                    <Card>
                        <form onSubmit={handleSubmit}>
                            <CardHeader>
                                <CardTitle>Detalles de la aplicación</CardTitle>
                                <CardDescription>
                                    Estos valores se guardarán en tu archivo
                                    .env
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="app_name">
                                            Nombre de la aplicación
                                        </Label>
                                        <Input
                                            id="app_name"
                                            value={form.app_name}
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    app_name: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="app_url">
                                            URL del sitio
                                        </Label>
                                        <Input
                                            id="app_url"
                                            value={form.app_url}
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    app_url: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="app_locale">
                                            Idioma
                                        </Label>
                                        <select
                                            id="app_locale"
                                            value={form.app_locale}
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    app_locale: e.target.value,
                                                })
                                            }
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
                                        >
                                            <option value="es">Español</option>
                                            <option value="en">English</option>
                                        </select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="timezone">
                                            Zona horaria
                                        </Label>
                                        <Input
                                            id="timezone"
                                            value={form.timezone}
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    timezone: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="h-px bg-border" />

                                <div>
                                    <h2 className="text-sm font-semibold">
                                        Cuenta de administrador
                                    </h2>
                                    <p className="mb-4 text-sm text-muted-foreground">
                                        Este será el primer usuario con acceso
                                        al panel de administración.
                                    </p>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2 sm:col-span-2">
                                            <Label htmlFor="admin_name">
                                                Nombre
                                            </Label>
                                            <Input
                                                id="admin_name"
                                                value={form.admin_name}
                                                onChange={(e) =>
                                                    setForm({
                                                        ...form,
                                                        admin_name:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                            {field('admin_name')[0] && (
                                                <p className="text-sm text-destructive">
                                                    {field('admin_name')[0]}
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-2 sm:col-span-2">
                                            <Label htmlFor="admin_email">
                                                Correo electrónico
                                            </Label>
                                            <Input
                                                id="admin_email"
                                                type="email"
                                                value={form.admin_email}
                                                onChange={(e) =>
                                                    setForm({
                                                        ...form,
                                                        admin_email:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                            {field('admin_email')[0] && (
                                                <p className="text-sm text-destructive">
                                                    {field('admin_email')[0]}
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="admin_password">
                                                Contraseña
                                            </Label>
                                            <Input
                                                id="admin_password"
                                                type="password"
                                                value={form.admin_password}
                                                onChange={(e) =>
                                                    setForm({
                                                        ...form,
                                                        admin_password:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                            {field('admin_password')[0] && (
                                                <p className="text-sm text-destructive">
                                                    {field('admin_password')[0]}
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="admin_password_confirmation">
                                                Confirmar contraseña
                                            </Label>
                                            <Input
                                                id="admin_password_confirmation"
                                                type="password"
                                                value={
                                                    form.admin_password_confirmation
                                                }
                                                onChange={(e) =>
                                                    setForm({
                                                        ...form,
                                                        admin_password_confirmation:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                        </div>
                                    </div>
                                </div>

                                {serverError && (
                                    <p className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                                        {serverError}
                                    </p>
                                )}
                            </CardContent>
                            <CardFooter className="justify-between">
                                <Button type="button" variant="ghost" asChild>
                                    <Link href="/install/database">
                                        <ArrowLeft />
                                        Atrás
                                    </Link>
                                </Button>
                                <Button type="submit" disabled={busy}>
                                    {busy ? (
                                        <>
                                            <Loader2 className="animate-spin" />
                                            Instalando...
                                        </>
                                    ) : (
                                        <>
                                            Instalar
                                            <ArrowRight />
                                        </>
                                    )}
                                </Button>
                            </CardFooter>
                        </form>
                    </Card>
                </div>
            </div>
        </>
    );
}
