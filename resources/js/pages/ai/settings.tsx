import {
    Link,
    router,
    setLayoutProps,
    useForm,
    useHttp,
} from '@inertiajs/react';
import {
    BarChart3,
    CheckCircle2,
    ExternalLink,
    FileText,
    KeyRound,
    Loader2,
    MessageSquare,
    PlugZap,
    ScanLine,
    ShieldCheck,
    Unplug,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { TrackerCard } from '@/components/tracker/tracker-card';
import { aiFailure, isCancelled } from '@/lib/ai';
import { cn } from '@/lib/utils';
import { assistant as aiAssistant } from '@/routes/ai';
import {
    destroy as aiSettingsDestroy,
    test as aiSettingsTest,
    update as aiSettingsUpdate,
} from '@/routes/ai/settings';
import { create as expensesCreate } from '@/routes/expenses';
import { statement as reportsStatement } from '@/routes/reports';
import type {
    AiProviderCode,
    AiProviderOption,
    AiSettingSummary,
} from '@/types';

const INPUT =
    'mt-1.5 h-11 w-full rounded-lg bg-surface-high px-3 text-sm text-foreground placeholder:text-muted-foreground/60 focus:ring-2 focus:ring-emerald-500/50 focus:outline-none';

export default function AiSettings({
    setting,
    providers,
    customEndpointsAllowed,
}: {
    setting: AiSettingSummary | null;
    providers: AiProviderOption[];
    customEndpointsAllowed: boolean;
}) {
    const { t } = useTranslation();

    setLayoutProps({
        title: t('ai.settings.title'),
        description: t('ai.settings.subtitle'),
    });

    const { data, setData, put, processing, errors } = useForm({
        provider: (setting?.provider ?? 'openai') as AiProviderCode,
        model: setting?.model ?? '',
        api_key: '',
        base_url: setting?.base_url ?? '',
    });

    const testing = useHttp<Record<string, never>, { message: string }>({});
    const [testResult, setTestResult] = useState<{
        ok: boolean;
        message: string;
    } | null>(null);

    const selected =
        providers.find((provider) => provider.value === data.provider) ??
        providers[0];
    const keepsStoredKey =
        setting !== null &&
        setting.provider === data.provider &&
        setting.has_key;

    function chooseProvider(provider: AiProviderOption): void {
        if (!provider.available) {
            return;
        }

        setData((values) => ({
            ...values,
            provider: provider.value,
            model:
                setting?.provider === provider.value
                    ? (setting.model ?? '')
                    : '',
            api_key: '',
            base_url:
                setting?.provider === provider.value && setting.base_url
                    ? setting.base_url
                    : (provider.default_base_url ?? ''),
        }));
        setTestResult(null);
    }

    function save(event: React.FormEvent): void {
        event.preventDefault();
        setTestResult(null);
        put(aiSettingsUpdate().url, {
            preserveScroll: true,
            onSuccess: () => setData('api_key', ''),
        });
    }

    async function testConnection(): Promise<void> {
        setTestResult(null);

        try {
            const response = await testing.post(aiSettingsTest().url);

            setTestResult({
                ok: true,
                message: response?.message ?? t('ai.settings.test_ok'),
            });
            router.reload({ only: ['setting'] });
        } catch (failure) {
            if (!isCancelled(failure)) {
                setTestResult({
                    ok: false,
                    message: aiFailure(failure, t('ai.settings.test_failed'))
                        .message,
                });
            }
        }
    }

    function disconnect(): void {
        if (!confirm(t('ai.settings.disconnect_confirm'))) {
            return;
        }

        router.delete(aiSettingsDestroy().url, {
            preserveScroll: true,
            onSuccess: () => {
                setTestResult(null);
                setData((values) => ({ ...values, api_key: '', model: '' }));
            },
        });
    }

    return (
        <div className="grid gap-4 lg:grid-cols-5 lg:gap-5">
            <form onSubmit={save} className="space-y-4 lg:col-span-3">
                <TrackerCard title={t('ai.settings.provider_title')}>
                    <div className="grid grid-cols-2 gap-2 px-4 pt-3 pb-4 sm:grid-cols-3">
                        {providers.map((provider) => {
                            const active = provider.value === data.provider;

                            return (
                                <button
                                    key={provider.value}
                                    type="button"
                                    disabled={!provider.available}
                                    onClick={() => chooseProvider(provider)}
                                    aria-pressed={active}
                                    className={cn(
                                        'rounded-lg px-3 py-2.5 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-40',
                                        active
                                            ? 'bg-emerald-500/15 ring-1 ring-emerald-500/50'
                                            : 'bg-surface-high hover:bg-surface-high/70',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'block truncate text-xs font-semibold',
                                            active
                                                ? 'text-emerald-700 dark:text-emerald-400'
                                                : 'text-foreground',
                                        )}
                                    >
                                        {provider.label}
                                    </span>
                                    <span className="mt-1 flex flex-wrap gap-1">
                                        {provider.supports_vision && (
                                            <Badge>
                                                {t('ai.settings.badge_vision')}
                                            </Badge>
                                        )}
                                        {provider.supports_pdf && (
                                            <Badge>PDF</Badge>
                                        )}
                                        {provider.requires_base_url && (
                                            <Badge>
                                                {t(
                                                    'ai.settings.badge_self_hosted',
                                                )}
                                            </Badge>
                                        )}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                    {!customEndpointsAllowed && (
                        <p className="px-4 pb-4 text-[11px] text-muted-foreground">
                            {t('ai.settings.custom_disabled')}
                        </p>
                    )}
                    {errors.provider && (
                        <p className="px-4 pb-4 text-xs text-destructive">
                            {errors.provider}
                        </p>
                    )}
                </TrackerCard>

                <TrackerCard title={t('ai.settings.credentials_title')}>
                    <div className="space-y-4 px-4 pt-3 pb-4">
                        {selected.requires_base_url && (
                            <label className="block">
                                <span className="text-xs font-medium text-muted-foreground">
                                    {t('ai.settings.base_url')}
                                </span>
                                <input
                                    type="url"
                                    inputMode="url"
                                    autoComplete="off"
                                    placeholder={
                                        selected.default_base_url ??
                                        'https://example.com/v1'
                                    }
                                    value={data.base_url}
                                    onChange={(event) =>
                                        setData('base_url', event.target.value)
                                    }
                                    className={INPUT}
                                />
                                <span className="mt-1 block text-[11px] text-muted-foreground">
                                    {t('ai.settings.base_url_hint')}
                                </span>
                                {errors.base_url && (
                                    <span className="mt-1 block text-xs text-destructive">
                                        {errors.base_url}
                                    </span>
                                )}
                            </label>
                        )}

                        <label className="block">
                            <span className="flex items-center justify-between gap-2 text-xs font-medium text-muted-foreground">
                                <span>
                                    {selected.requires_key
                                        ? t('ai.settings.api_key')
                                        : t('ai.settings.api_key_optional')}
                                </span>
                                {selected.key_url && (
                                    <a
                                        href={selected.key_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400"
                                    >
                                        {t('ai.settings.get_key')}
                                        <ExternalLink className="size-3" />
                                    </a>
                                )}
                            </span>
                            <span className="relative block">
                                <KeyRound className="pointer-events-none absolute top-1/2 left-3 mt-[3px] size-4 -translate-y-1/2 text-muted-foreground" />
                                <input
                                    type="password"
                                    autoComplete="off"
                                    spellCheck={false}
                                    placeholder={
                                        keepsStoredKey
                                            ? t('ai.settings.api_key_keep', {
                                                  masked: setting?.masked_key,
                                              })
                                            : t(
                                                  'ai.settings.api_key_placeholder',
                                              )
                                    }
                                    value={data.api_key}
                                    onChange={(event) =>
                                        setData('api_key', event.target.value)
                                    }
                                    className={cn(INPUT, 'pl-9 font-mono')}
                                />
                            </span>
                            {errors.api_key && (
                                <span className="mt-1 block text-xs text-destructive">
                                    {errors.api_key}
                                </span>
                            )}
                        </label>

                        <label className="block">
                            <span className="text-xs font-medium text-muted-foreground">
                                {t('ai.settings.model')}
                            </span>
                            <input
                                type="text"
                                list="ai-models"
                                autoComplete="off"
                                spellCheck={false}
                                placeholder={
                                    selected.default_model ??
                                    t('ai.settings.model_placeholder')
                                }
                                value={data.model}
                                onChange={(event) =>
                                    setData('model', event.target.value)
                                }
                                className={cn(INPUT, 'font-mono')}
                            />
                            <datalist id="ai-models">
                                {selected.models.map((model) => (
                                    <option key={model} value={model} />
                                ))}
                            </datalist>
                            <span className="mt-1 block text-[11px] text-muted-foreground">
                                {selected.default_model
                                    ? t('ai.settings.model_hint', {
                                          model: selected.default_model,
                                      })
                                    : t('ai.settings.model_hint_custom')}
                            </span>
                            {selected.models.length > 0 && (
                                <span className="mt-2 flex flex-wrap gap-1.5">
                                    {selected.models.map((model) => (
                                        <button
                                            key={model}
                                            type="button"
                                            onClick={() =>
                                                setData('model', model)
                                            }
                                            className={cn(
                                                'rounded-full px-2.5 py-1 font-mono text-[10px] transition-colors',
                                                (data.model ||
                                                    selected.default_model) ===
                                                    model
                                                    ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                                                    : 'bg-surface-high text-muted-foreground hover:text-foreground',
                                            )}
                                        >
                                            {model}
                                        </button>
                                    ))}
                                </span>
                            )}
                            {errors.model && (
                                <span className="mt-1 block text-xs text-destructive">
                                    {errors.model}
                                </span>
                            )}
                        </label>

                        {!selected.supports_vision && (
                            <p className="rounded-lg bg-amber-500/10 px-3 py-2 text-[11px] text-amber-700 dark:text-amber-400">
                                {t('ai.settings.no_vision_warning')}
                            </p>
                        )}

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 py-2.5 text-xs font-bold text-primary-foreground disabled:opacity-60"
                        >
                            {processing
                                ? t('common.saving')
                                : t('ai.settings.save')}
                        </button>
                    </div>
                </TrackerCard>
            </form>

            <div className="space-y-4 lg:col-span-2">
                <TrackerCard title={t('ai.settings.status_title')}>
                    <div className="px-4 pt-3 pb-4">
                        {setting ? (
                            <>
                                <div className="flex items-center gap-3">
                                    <span
                                        className={cn(
                                            'flex size-10 shrink-0 items-center justify-center rounded-full',
                                            setting.ready
                                                ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                                : 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
                                        )}
                                    >
                                        <PlugZap className="size-5" />
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block truncate text-sm font-semibold text-foreground">
                                            {setting.provider_label}
                                        </span>
                                        <span className="block truncate font-mono text-[11px] text-muted-foreground">
                                            {setting.resolved_model}
                                        </span>
                                    </span>
                                </div>
                                <dl className="mt-3 space-y-1.5 text-[11px]">
                                    <div className="flex justify-between gap-2">
                                        <dt className="text-muted-foreground">
                                            {t('ai.settings.status_key')}
                                        </dt>
                                        <dd className="font-mono text-foreground">
                                            {setting.masked_key ??
                                                t('ai.settings.status_no_key')}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-2">
                                        <dt className="text-muted-foreground">
                                            {t('ai.settings.status_verified')}
                                        </dt>
                                        <dd className="text-foreground">
                                            {setting.last_verified_at
                                                ? new Date(
                                                      setting.last_verified_at,
                                                  ).toLocaleString()
                                                : t('ai.settings.status_never')}
                                        </dd>
                                    </div>
                                </dl>

                                <div className="mt-4 grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        onClick={() => void testConnection()}
                                        disabled={
                                            testing.processing || !setting.ready
                                        }
                                        className="inline-flex items-center justify-center gap-1.5 rounded-lg bg-surface-high py-2.5 text-xs font-semibold text-foreground disabled:opacity-50"
                                    >
                                        {testing.processing ? (
                                            <Loader2 className="size-3.5 animate-spin" />
                                        ) : (
                                            <PlugZap className="size-3.5" />
                                        )}
                                        {t('ai.settings.test')}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={disconnect}
                                        className="inline-flex items-center justify-center gap-1.5 rounded-lg bg-surface-high py-2.5 text-xs font-semibold text-destructive"
                                    >
                                        <Unplug className="size-3.5" />
                                        {t('ai.settings.disconnect')}
                                    </button>
                                </div>

                                {testResult && (
                                    <p
                                        className={cn(
                                            'mt-3 flex items-start gap-1.5 text-xs',
                                            testResult.ok
                                                ? 'text-emerald-700 dark:text-emerald-400'
                                                : 'text-destructive',
                                        )}
                                    >
                                        {testResult.ok ? (
                                            <CheckCircle2 className="mt-px size-3.5 shrink-0" />
                                        ) : (
                                            <XCircle className="mt-px size-3.5 shrink-0" />
                                        )}
                                        {testResult.message}
                                    </p>
                                )}
                            </>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {t('ai.settings.status_empty')}
                            </p>
                        )}
                    </div>
                </TrackerCard>

                <TrackerCard title={t('ai.settings.features_title')}>
                    <div className="divide-y divide-border px-4">
                        <FeatureLink
                            href={expensesCreate().url}
                            icon={<ScanLine className="size-4" />}
                            title={t('ai.settings.feature_scan')}
                            description={t('ai.settings.feature_scan_desc')}
                        />
                        <FeatureLink
                            href={aiAssistant().url}
                            icon={<MessageSquare className="size-4" />}
                            title={t('ai.settings.feature_chat')}
                            description={t('ai.settings.feature_chat_desc')}
                        />
                        <FeatureLink
                            href={reportsStatement().url}
                            icon={<FileText className="size-4" />}
                            title={t('ai.settings.feature_statement')}
                            description={t(
                                'ai.settings.feature_statement_desc',
                            )}
                        />
                        <FeatureLink
                            href={aiAssistant().url}
                            icon={<BarChart3 className="size-4" />}
                            title={t('ai.settings.feature_reports')}
                            description={t('ai.settings.feature_reports_desc')}
                        />
                    </div>
                </TrackerCard>

                <section className="flex gap-3 rounded-xl border border-border px-4 py-4">
                    <ShieldCheck className="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    <div className="space-y-1.5 text-[11px] text-muted-foreground">
                        <p className="text-xs font-semibold text-foreground">
                            {t('ai.settings.privacy_title')}
                        </p>
                        <p>{t('ai.settings.privacy_key')}</p>
                        <p>{t('ai.settings.privacy_data')}</p>
                        <p>{t('ai.settings.privacy_costs')}</p>
                    </div>
                </section>
            </div>
        </div>
    );
}

function Badge({ children }: { children: React.ReactNode }) {
    return (
        <span className="rounded bg-background/60 px-1.5 py-px text-[9px] font-semibold tracking-wide text-muted-foreground uppercase">
            {children}
        </span>
    );
}

function FeatureLink({
    href,
    icon,
    title,
    description,
}: {
    href: string;
    icon: React.ReactNode;
    title: string;
    description: string;
}) {
    return (
        <Link href={href} className="flex items-start gap-3 py-3">
            <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-violet-500/15 text-violet-600 dark:text-violet-400">
                {icon}
            </span>
            <span className="min-w-0">
                <span className="block text-sm font-medium text-foreground">
                    {title}
                </span>
                <span className="block text-[11px] text-muted-foreground">
                    {description}
                </span>
            </span>
        </Link>
    );
}
