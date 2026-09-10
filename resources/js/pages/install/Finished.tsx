import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CheckCircle2 } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { StepIndicator } from '@/components/install/StepIndicator';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export default function Finished() {
    return (
        <>
            <Head title="Instalación completada" />

            <div className="flex min-h-screen flex-col bg-background px-4 py-8">
                <div className="mx-auto w-full max-w-2xl">
                    <div className="mb-8 flex flex-col items-center gap-4 text-center">
                        <AppLogoIcon className="size-12" />
                        <div>
                            <h1 className="font-display text-2xl font-bold text-foreground">
                                Instalación completada
                            </h1>
                            <p className="text-muted-foreground">
                                ¡Todo listo para comenzar!
                            </p>
                        </div>
                        <StepIndicator current={4} />
                    </div>

                    <Card>
                        <CardHeader className="text-center">
                            <div className="mx-auto mb-2 flex size-16 items-center justify-center rounded-full bg-emerald-500/15">
                                <CheckCircle2 className="size-10 text-emerald-500" />
                            </div>
                            <CardTitle>Spentz Trackr está instalado</CardTitle>
                            <CardDescription>
                                Tu cuenta de administrador fue creada
                                correctamente. Ya puedes iniciar sesión con el
                                correo y la contraseña que configuraste.
                            </CardDescription>
                        </CardHeader>
                        <CardFooter>
                            <Button asChild className="w-full">
                                <Link href="/login">
                                    Iniciar sesión
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
