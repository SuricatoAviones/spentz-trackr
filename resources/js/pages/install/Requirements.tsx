import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, XCircle, ArrowRight } from 'lucide-react';
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

type RequirementsProps = {
    requirements: {
        php: { version: string; status: boolean };
        extensions: Record<string, { status: boolean }>;
        directories: Record<string, { status: boolean }>;
    };
};

export default function Requirements({ requirements }: RequirementsProps) {
    const phpOk = requirements.php.status;
    const extensionsOk = Object.values(requirements.extensions).every(
        (ext) => ext.status,
    );
    const directoriesOk = Object.values(requirements.directories).every(
        (dir) => dir.status,
    );
    const allOk = phpOk && extensionsOk && directoriesOk;

    const extensionLabels: Record<string, string> = {
        pdo: 'PDO',
        mbstring: 'MBString',
        openssl: 'OpenSSL',
        tokenizer: 'Tokenizer',
        xml: 'XML',
        curl: 'cURL',
        zip: 'Zip',
        bcmath: 'BCMath',
        gd: 'GD',
        fileinfo: 'Fileinfo',
    };

    const renderItem = (
        icon: React.ReactNode,
        label: string,
        status: boolean,
    ) => (
        <li className="flex items-center justify-between rounded-md border bg-muted/40 px-3 py-2 text-sm">
            <span className="flex items-center gap-2">
                {status ? (
                    <CheckCircle2 className="size-4 text-emerald-500" />
                ) : (
                    <XCircle className="size-4 text-destructive" />
                )}
                <span>{label}</span>
            </span>
            <span className={status ? 'text-emerald-600' : 'text-destructive'}>
                {status ? 'OK' : 'Falta'}
            </span>
        </li>
    );

    return (
        <>
            <Head title="Instalación" />

            <div className="flex min-h-screen flex-col bg-background px-4 py-8">
                <div className="mx-auto w-full max-w-2xl">
                    <div className="mb-8 flex flex-col items-center gap-4 text-center">
                        <AppLogoIcon className="size-12" />
                        <div>
                            <h1 className="font-display text-2xl font-bold text-foreground">
                                Bienvenido a Spentz Trackr
                            </h1>
                            <p className="text-muted-foreground">
                                Vamos a configurar tu instalación.
                            </p>
                        </div>
                        <StepIndicator current={1} />
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Requisitos del servidor</CardTitle>
                            <CardDescription>
                                Verifica que tu servidor cumpla con los
                                requisitos mínimos.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <section>
                                <h2 className="mb-2 text-sm font-semibold text-foreground">
                                    Versión de PHP
                                </h2>
                                <ul className="space-y-2">
                                    {renderItem(
                                        phpOk ? (
                                            <CheckCircle2 className="size-4 text-emerald-500" />
                                        ) : null,
                                        `PHP ${requirements.php.version}`,
                                        phpOk,
                                    )}
                                </ul>
                            </section>

                            <section>
                                <h2 className="mb-2 text-sm font-semibold text-foreground">
                                    Extensiones de PHP
                                </h2>
                                <ul className="space-y-2">
                                    {Object.entries(
                                        requirements.extensions,
                                    ).map(([key, ext]) =>
                                        renderItem(
                                            null,
                                            extensionLabels[key] ?? key,
                                            ext.status,
                                        ),
                                    )}
                                </ul>
                            </section>

                            <section>
                                <h2 className="mb-2 text-sm font-semibold text-foreground">
                                    Permisos de escritura
                                </h2>
                                <ul className="space-y-2">
                                    {Object.entries(
                                        requirements.directories,
                                    ).map(([dir, item]) =>
                                        renderItem(
                                            null,
                                            dir,
                                            'status' in item
                                                ? item.status
                                                : false,
                                        ),
                                    )}
                                </ul>
                            </section>
                        </CardContent>
                        <CardFooter>
                            <Button
                                asChild
                                disabled={!allOk}
                                className="w-full"
                            >
                                <Link href="/install/database">
                                    Continuar
                                    <ArrowRight />
                                </Link>
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </>
    );
}
