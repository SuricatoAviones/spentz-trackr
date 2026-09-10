import { Head, router, useForm } from '@inertiajs/react';
import { RefreshCw, Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';
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
    paralelo: 'rates:provider_paralelo',
    user: 'rates:provider_manual',
    dolarapi: 'dolarapi',
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
    const { t } = useTranslation();

    const sourceLabel = (source: HistoryEntry['source']): string =>
        t(`admin:rates.source_${source}`);

    const providerLabel = (provider: string): string =>
        PROVIDER_LABEL[provider] ? t(PROVIDER_LABEL[provider]) : provider;

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
            <Head title={t('admin:rates.title')} />

            <div className="space-y-8 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h2 className="text-xl font-semibold tracking-tight">
                        {t('admin:rates.title')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t('admin:rates.subtitle')}
                    </p>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle className="text-base">
                                {t('admin:rates.today_rate', {
                                    date: today.date,
                                })}
                            </CardTitle>
                            <CardDescription>
                                {t('admin:rates.today_rate_desc')}
                            </CardDescription>
                        </div>
                        <Button variant="outline" size="sm" onClick={syncRates}>
                            <RefreshCw className="size-4" />
                            {t('admin:rates.sync_dolarapi')}
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={saveRates} className="space-y-4">
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
                                        placeholder={t(
                                            'admin:rates.bcv_placeholder',
                                        )}
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
                                        placeholder={t(
                                            'admin:rates.paralelo_placeholder',
                                        )}
                                    />
                                    {errors.paralelo && (
                                        <p className="text-xs text-destructive">
                                            {errors.paralelo}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <Button type="submit" disabled={processing}>
                                {processing
                                    ? t('common:saving')
                                    : t('admin:rates.save_rates')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Users className="size-4" />
                            {t('admin:rates.manual_today')}
                        </CardTitle>
                        <CardDescription>
                            {t('admin:rates.manual_today_desc')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-2">
                        {manualToday.length === 0 ? (
                            <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                                {t('admin:rates.no_manual_today')}
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
                            {t('admin:rates.recent_history')}
                        </CardTitle>
                        <CardDescription>
                            {t('admin:rates.recent_history_desc')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs tracking-wider text-muted-foreground uppercase">
                                        <th className="px-4 py-3 font-medium">
                                            {t('admin:expenses.col_date')}
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            {t('admin:expenses.col_source')}
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            {t('admin:rates.provider_col')}
                                        </th>
                                        <th className="hidden px-4 py-3 font-medium md:table-cell">
                                            {t('admin:expenses.filter_user')}
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            {t('admin:rates.rate_col')}
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
                                                    {sourceLabel(entry.source)}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3">
                                                {providerLabel(entry.provider)}
                                            </td>
                                            <td className="hidden px-4 py-3 md:table-cell">
                                                {entry.user_name ?? (
                                                    <span className="text-muted-foreground">
                                                        {t(
                                                            'admin:rates.global',
                                                        )}
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
                                {t('admin:rates.no_rates')}
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
