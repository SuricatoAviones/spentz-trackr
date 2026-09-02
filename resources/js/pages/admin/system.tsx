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
    const { t } = useTranslation();
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
            label: t('admin:system.health_db'),
            ok: status.database_ok,
            detail: status.database_ok
                ? t('admin:system.health_db_ok')
                : t('admin:system.health_db_fail'),
        },
        {
            label: t('admin:system.health_storage'),
            ok: status.storage_writable,
            detail: status.storage_writable
                ? t('admin:system.health_storage_ok')
                : t('admin:system.health_storage_fail'),
        },
    ];

    return (
        <>
            <Head title={t('admin:system.title')} />

            <div className="space-y-8 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h2 className="text-xl font-semibold tracking-tight">
                        {t('admin:system.title')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t('admin:system.subtitle')}
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('admin:system.system_status')}
                            </CardTitle>
                            <CardDescription>
                                {t('admin:system.system_status_desc')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    {t('admin:system.environment')}
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
                                    {t('admin:system.database')}
                                </span>
                                <span className="font-medium">
                                    {status.database}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    {t('admin:system.cache')}
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
                                    {t('admin:system.data_volume')}
                                </CardTitle>
                                <CardDescription>
                                    {t('admin:system.data_volume_desc')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {[
                                    {
                                        icon: Server,
                                        label: t('admin:system.count_users'),
                                        value: counts.users,
                                    },
                                    {
                                        icon: Database,
                                        label: t('admin:system.count_expenses'),
                                        value: counts.expenses,
                                    },
                                    {
                                        icon: HardDrive,
                                        label: t('admin:system.count_receipts'),
                                        value: counts.receipts,
                                    },
                                    {
                                        icon: Database,
                                        label: t('admin:system.count_rates'),
                                        value: counts.exchange_rates,
                                    },
                                    {
                                        icon: Server,
                                        label: t('admin:system.count_categories'),
                                        value: counts.categories,
                                    },
                                    {
                                        icon: Server,
                                        label: t('admin:system.count_sources'),
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
                                    {t('admin:system.backup_title')}
                                </CardTitle>
                                <CardDescription>
                                    {t('admin:system.backup_desc')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button
                                    onClick={generateBackup}
                                    disabled={generating}
                                >
                                    <Download className="size-4" />
                                    {generating
                                        ? t('admin:system.backup_generating')
                                        : t('admin:system.backup_download')}
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}