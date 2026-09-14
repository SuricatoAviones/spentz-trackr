import { Head, router, setLayoutProps } from '@inertiajs/react';
import {
    CheckCircle2,
    Database,
    Download,
    HardDrive,
    Plug,
    Server,
    UserPlus,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
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
import { update as systemApiUpdate } from '@/routes/admin/system/api';
import { update as systemRegistrationUpdate } from '@/routes/admin/system/registration';

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
    features,
}: {
    status: Status;
    counts: Counts;
    features: { api: boolean; registration: boolean };
}) {
    const { t } = useTranslation();
    const [generating, setGenerating] = useState(false);
    const [togglingApi, setTogglingApi] = useState(false);
    const [togglingRegistration, setTogglingRegistration] = useState(false);

    function toggleApi(enabled: boolean) {
        setTogglingApi(true);
        router.put(
            systemApiUpdate().url,
            { enabled },
            { onFinish: () => setTogglingApi(false), preserveScroll: true },
        );
    }

    function toggleRegistration(enabled: boolean) {
        setTogglingRegistration(true);
        router.put(
            systemRegistrationUpdate().url,
            { enabled },
            {
                onFinish: () => setTogglingRegistration(false),
                preserveScroll: true,
            },
        );
    }

    setLayoutProps({
        title: t('admin.system.title'),
        description: t('admin.system.subtitle'),
    });

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
            label: t('admin.system.health_db'),
            ok: status.database_ok,
            detail: status.database_ok
                ? t('admin.system.health_db_ok')
                : t('admin.system.health_db_fail'),
        },
        {
            label: t('admin.system.health_storage'),
            ok: status.storage_writable,
            detail: status.storage_writable
                ? t('admin.system.health_storage_ok')
                : t('admin.system.health_storage_fail'),
        },
    ];

    return (
        <>
            <Head title={t('admin.system.title')} />

            <div className="space-y-8">
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('admin.system.system_status')}
                            </CardTitle>
                            <CardDescription>
                                {t('admin.system.system_status_desc')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    {t('admin.system.environment')}
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
                                    {t('admin.system.database')}
                                </span>
                                <span className="font-medium">
                                    {status.database}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    {t('admin.system.cache')}
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
                                    {t('admin.system.data_volume')}
                                </CardTitle>
                                <CardDescription>
                                    {t('admin.system.data_volume_desc')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {[
                                    {
                                        icon: Server,
                                        label: t('admin.system.count_users'),
                                        value: counts.users,
                                    },
                                    {
                                        icon: Database,
                                        label: t('admin.system.count_expenses'),
                                        value: counts.expenses,
                                    },
                                    {
                                        icon: HardDrive,
                                        label: t('admin.system.count_receipts'),
                                        value: counts.receipts,
                                    },
                                    {
                                        icon: Database,
                                        label: t('admin.system.count_rates'),
                                        value: counts.exchange_rates,
                                    },
                                    {
                                        icon: Server,
                                        label: t(
                                            'admin.system.count_categories',
                                        ),
                                        value: counts.categories,
                                    },
                                    {
                                        icon: Server,
                                        label: t('admin.system.count_sources'),
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
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Plug className="size-4" />
                                    {t('admin.system.api_title')}
                                </CardTitle>
                                <CardDescription>
                                    {t('admin.system.api_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex items-center justify-between gap-3">
                                    <span
                                        className={
                                            features.api
                                                ? 'inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400'
                                                : 'inline-flex items-center gap-1.5 rounded-full bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground'
                                        }
                                    >
                                        {features.api ? (
                                            <CheckCircle2 className="size-3.5" />
                                        ) : (
                                            <XCircle className="size-3.5" />
                                        )}
                                        {features.api
                                            ? t('admin.system.api_enabled')
                                            : t('admin.system.api_disabled')}
                                    </span>

                                    <Button
                                        variant={
                                            features.api ? 'outline' : 'default'
                                        }
                                        disabled={togglingApi}
                                        onClick={() => toggleApi(!features.api)}
                                    >
                                        {features.api
                                            ? t('admin.system.api_turn_off')
                                            : t('admin.system.api_turn_on')}
                                    </Button>
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    {t('admin.system.api_tokens_note')}
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <UserPlus className="size-4" />
                                    {t('admin.system.registration_title')}
                                </CardTitle>
                                <CardDescription>
                                    {t('admin.system.registration_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex items-center justify-between gap-3">
                                    <span
                                        className={
                                            features.registration
                                                ? 'inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400'
                                                : 'inline-flex items-center gap-1.5 rounded-full bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground'
                                        }
                                    >
                                        {features.registration ? (
                                            <CheckCircle2 className="size-3.5" />
                                        ) : (
                                            <XCircle className="size-3.5" />
                                        )}
                                        {features.registration
                                            ? t(
                                                  'admin.system.registration_enabled',
                                              )
                                            : t(
                                                  'admin.system.registration_disabled',
                                              )}
                                    </span>

                                    <Button
                                        variant={
                                            features.registration
                                                ? 'outline'
                                                : 'default'
                                        }
                                        disabled={togglingRegistration}
                                        onClick={() =>
                                            toggleRegistration(
                                                !features.registration,
                                            )
                                        }
                                    >
                                        {features.registration
                                            ? t(
                                                  'admin.system.registration_turn_off',
                                              )
                                            : t(
                                                  'admin.system.registration_turn_on',
                                              )}
                                    </Button>
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    {t('admin.system.registration_note')}
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('admin.system.backup_title')}
                                </CardTitle>
                                <CardDescription>
                                    {t('admin.system.backup_desc')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button
                                    onClick={generateBackup}
                                    disabled={generating}
                                >
                                    <Download className="size-4" />
                                    {generating
                                        ? t('admin.system.backup_generating')
                                        : t('admin.system.backup_download')}
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
