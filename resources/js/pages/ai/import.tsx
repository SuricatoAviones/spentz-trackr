import { Link, router, setLayoutProps, useHttp } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckSquare,
    FileUp,
    Loader2,
    Square,
    Upload,
    X,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { TrackerCard } from '@/components/tracker/tracker-card';
import { aiFailure, isCancelled } from '@/lib/ai';
import { CURRENCY_META } from '@/lib/format';
import type { CurrencyCode } from '@/lib/format';
import { cn } from '@/lib/utils';
import { scan as importScan, store as importStore } from '@/routes/ai/import';
import { edit as aiSettingsEdit } from '@/routes/ai/settings';
import type {
    AiScanCapabilities,
    ImportCategoryOption,
    ImportRow,
    SourceOption,
    StatementImportDraft,
    TrackingType,
} from '@/types';

const FIELD =
    'h-9 w-full rounded-lg bg-surface-high px-2.5 text-xs text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none';

type EditableRow = ImportRow & { amountInput: string; rateInput: string };

function toEditable(row: ImportRow): EditableRow {
    return {
        ...row,
        amountInput: row.amount,
        rateInput: row.exchange_rate !== null ? String(row.exchange_rate) : '',
    };
}

/** Qué le falta a una fila marcada para poder importarse. */
function missingFields(row: EditableRow): string[] {
    const missing: string[] = [];

    if (!row.date) {
        missing.push('date');
    }

    if (!row.currency) {
        missing.push('currency');
    }

    if (!row.category_id) {
        missing.push('category');
    }

    if (!(Number(row.amountInput) > 0)) {
        missing.push('amount');
    }

    if (row.description.trim() === '') {
        missing.push('description');
    }

    return missing;
}

export default function ImportStatement({
    capabilities,
    categories,
    sources,
    trackingType,
    limits,
}: {
    capabilities: AiScanCapabilities;
    categories: ImportCategoryOption[];
    sources: SourceOption[];
    trackingType: TrackingType;
    limits: { max_files: number; max_rows: number };
}) {
    const { t } = useTranslation();
    const input = useRef<HTMLInputElement>(null);
    const [files, setFiles] = useState<File[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [draft, setDraft] = useState<StatementImportDraft | null>(null);
    const [rows, setRows] = useState<EditableRow[]>([]);
    const [sourceId, setSourceId] = useState<number | null>(null);
    const [serverErrors, setServerErrors] = useState<Record<string, string>>(
        {},
    );
    const [importing, setImporting] = useState(false);
    const reader = useHttp<{ files: File[] }, { data: StatementImportDraft }>({
        files: [],
    });

    setLayoutProps({
        title: t('ai.import.title'),
        description: t('ai.import.subtitle'),
    });

    // `files` o `files.N`: el primer error de validación de la subida.
    const uploadError =
        Object.values(reader.errors as Record<string, string>)[0] ?? null;
    const tracksExpenses = trackingType !== 'income';
    const tracksIncomes = trackingType !== 'expenses';
    const selected = rows.filter((row) => row.selected);
    const incomplete = selected.filter((row) => missingFields(row).length > 0);
    const needsSource =
        selected.some((row) => row.type === 'expense') && sourceId === null;

    const categoriesByType = useMemo(
        () => ({
            expense: categories.filter(
                (category) => category.type === 'expense',
            ),
            income: categories.filter((category) => category.type === 'income'),
        }),
        [categories],
    );

    function addFiles(list: FileList | null): void {
        if (!list) {
            return;
        }

        setError(null);
        setFiles((current) =>
            [...current, ...Array.from(list)].slice(0, limits.max_files),
        );

        if (input.current) {
            input.current.value = '';
        }
    }

    async function read(): Promise<void> {
        if (files.length === 0) {
            return;
        }

        setError(null);
        reader.setData('files', files);

        try {
            const response = await reader.post(importScan().url);

            if (!response) {
                return;
            }

            setDraft(response.data);
            setRows(response.data.rows.map(toEditable));
            setSourceId(response.data.payment_source_id);
            setServerErrors({});
        } catch (failure) {
            if (!isCancelled(failure)) {
                setError(
                    aiFailure(failure, t('ai.import.error_generic')).message,
                );
            }
        }
    }

    function update(key: number, patch: Partial<EditableRow>): void {
        setRows((current) =>
            current.map((row) => {
                if (row.key !== key) {
                    return row;
                }

                const next = { ...row, ...patch };

                // Cambiar el tipo invalida la categoría (gasto ≠ ingreso).
                if (patch.type && patch.type !== row.type) {
                    next.category_id = null;
                    next.payment_method = null;
                }

                return next;
            }),
        );
    }

    function selectAll(value: boolean): void {
        setRows((current) =>
            current.map((row) => ({
                ...row,
                selected:
                    value &&
                    (row.type === 'expense' ? tracksExpenses : tracksIncomes),
            })),
        );
    }

    function confirm(): void {
        const submitted = selected;

        setImporting(true);
        setServerErrors({});

        router.post(
            importStore().url,
            {
                payment_source_id: sourceId,
                rows: submitted.map((row) => ({
                    type: row.type,
                    date: row.date ?? '',
                    description: row.description,
                    amount: row.amountInput,
                    currency: row.currency ?? '',
                    category_id: row.category_id ?? '',
                    payment_method:
                        row.type === 'expense' && row.currency === 'ves'
                            ? (row.payment_method ?? '')
                            : '',
                    exchange_rate:
                        row.currency === 'ves' && row.rateInput !== ''
                            ? row.rateInput
                            : '',
                    rate_provider:
                        row.currency === 'ves' && row.rateInput !== ''
                            ? String(row.rateInput) ===
                              String(row.exchange_rate ?? '')
                                ? (row.rate_provider ?? 'custom')
                                : 'custom'
                            : '',
                    note: row.note ?? '',
                })),
            },
            {
                // Los errores llegan como `rows.N.campo` sobre lo enviado:
                // se traducen a la clave de la fila para pintarlos en su sitio.
                onError: (errors) => {
                    const mapped: Record<string, string> = {};

                    Object.entries(errors).forEach(([field, message]) => {
                        const match = /^rows\.(\d+)\.(.+)$/.exec(field);

                        if (match) {
                            const row = submitted[Number(match[1])];

                            if (row) {
                                mapped[`${row.key}`] = String(message);
                            }
                        } else {
                            mapped[field] = String(message);
                        }
                    });

                    setServerErrors(mapped);
                },
                onFinish: () => setImporting(false),
            },
        );
    }

    if (!capabilities.available) {
        return (
            <div className="mx-auto max-w-lg">
                <section className="rounded-xl bg-surface-low px-5 py-8 text-center">
                    <span className="mx-auto flex size-12 items-center justify-center rounded-full bg-violet-500/15 text-violet-600 dark:text-violet-400">
                        <FileUp className="size-6" />
                    </span>
                    <h2 className="mt-4 font-display text-lg font-bold text-foreground">
                        {t('ai.import.setup_title')}
                    </h2>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {t('ai.import.setup_desc')}
                    </p>
                    <Link
                        href={aiSettingsEdit().url}
                        className="mt-5 inline-flex items-center gap-2 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 px-4 py-2.5 text-xs font-bold text-primary-foreground"
                    >
                        {t('ai.assistant.setup_cta')}
                    </Link>
                </section>
            </div>
        );
    }

    if (draft === null) {
        return (
            <div className="mx-auto max-w-2xl space-y-4">
                <TrackerCard title={t('ai.import.upload_title')}>
                    <div className="px-4 pt-3 pb-4">
                        <p className="text-xs text-muted-foreground">
                            {capabilities.pdf
                                ? t('ai.import.upload_hint_pdf', {
                                      max: limits.max_files,
                                  })
                                : t('ai.import.upload_hint', {
                                      max: limits.max_files,
                                  })}
                        </p>

                        <button
                            type="button"
                            onClick={() => input.current?.click()}
                            onDragOver={(event) => event.preventDefault()}
                            onDrop={(event) => {
                                event.preventDefault();
                                addFiles(event.dataTransfer.files);
                            }}
                            disabled={
                                reader.processing ||
                                files.length >= limits.max_files
                            }
                            className="mt-3 flex w-full flex-col items-center gap-2 rounded-xl border-2 border-dashed border-border px-4 py-8 text-center transition-colors hover:border-emerald-500/50 disabled:opacity-60"
                        >
                            <Upload className="size-6 text-muted-foreground" />
                            <span className="text-sm font-medium text-foreground">
                                {t('ai.import.drop')}
                            </span>
                        </button>
                        <input
                            ref={input}
                            type="file"
                            multiple
                            accept={
                                capabilities.pdf
                                    ? 'image/jpeg,image/png,image/webp,application/pdf'
                                    : 'image/jpeg,image/png,image/webp'
                            }
                            className="hidden"
                            onChange={(event) => addFiles(event.target.files)}
                        />

                        {files.length > 0 && (
                            <ul className="mt-3 space-y-1.5">
                                {files.map((file, index) => (
                                    <li
                                        key={`${file.name}-${index}`}
                                        className="flex items-center justify-between gap-2 rounded-lg bg-surface-high px-3 py-2 text-xs"
                                    >
                                        <span className="truncate text-foreground">
                                            {file.name}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setFiles((current) =>
                                                    current.filter(
                                                        (_, i) => i !== index,
                                                    ),
                                                )
                                            }
                                            className="text-muted-foreground hover:text-destructive"
                                            aria-label={t(
                                                'ai.import.remove_file',
                                            )}
                                        >
                                            <X className="size-3.5" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {(uploadError || error) && (
                            <p className="mt-2 text-xs text-destructive">
                                {uploadError ?? error}
                            </p>
                        )}

                        <button
                            type="button"
                            onClick={() => void read()}
                            disabled={files.length === 0 || reader.processing}
                            className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-br from-violet-500 to-violet-700 py-2.5 text-xs font-bold text-white disabled:opacity-50"
                        >
                            {reader.processing && (
                                <Loader2 className="size-3.5 animate-spin" />
                            )}
                            {reader.processing
                                ? t('ai.import.reading')
                                : t('ai.import.read')}
                        </button>
                        {reader.processing && (
                            <p className="mt-2 text-center text-[11px] text-muted-foreground">
                                {t('ai.import.reading_hint')}
                            </p>
                        )}
                    </div>
                </TrackerCard>

                <p className="text-[11px] text-muted-foreground">
                    {t('ai.import.privacy')}
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-4 pb-24">
            <section className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-surface-low p-4">
                <div className="min-w-0">
                    <p className="text-sm font-semibold text-foreground">
                        {draft.bank ?? t('ai.import.unknown_bank')}
                    </p>
                    <p className="text-[11px] text-muted-foreground">
                        {t('ai.import.summary', {
                            count: rows.length,
                            selected: selected.length,
                        })}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => selectAll(true)}
                        className="inline-flex items-center gap-1.5 rounded-full bg-surface-high px-3 py-1.5 text-[11px] font-semibold text-foreground"
                    >
                        <CheckSquare className="size-3.5" />
                        {t('ai.import.select_all')}
                    </button>
                    <button
                        type="button"
                        onClick={() => selectAll(false)}
                        className="inline-flex items-center gap-1.5 rounded-full bg-surface-high px-3 py-1.5 text-[11px] font-semibold text-foreground"
                    >
                        <Square className="size-3.5" />
                        {t('ai.import.select_none')}
                    </button>
                </div>
            </section>

            {draft.warnings.length > 0 && (
                <ul className="space-y-1">
                    {draft.warnings.map((warning) => (
                        <li
                            key={warning}
                            className="flex items-start gap-1.5 text-xs text-amber-700 dark:text-amber-400"
                        >
                            <AlertTriangle className="mt-px size-3.5 shrink-0" />
                            {t(`ai.import.warning_${warning}`, {
                                max: limits.max_rows,
                            })}
                        </li>
                    ))}
                </ul>
            )}

            {tracksExpenses && (
                <label className="block rounded-xl bg-surface-low p-4">
                    <span className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                        {t('ai.import.source')}
                    </span>
                    <select
                        value={sourceId ?? ''}
                        onChange={(event) =>
                            setSourceId(
                                event.target.value
                                    ? Number(event.target.value)
                                    : null,
                            )
                        }
                        className={cn(FIELD, 'mt-1.5 h-10 text-sm')}
                    >
                        <option value="">
                            {t('ai.import.source_placeholder')}
                        </option>
                        {sources.map((source) => (
                            <option key={source.id} value={source.id}>
                                {source.name}
                            </option>
                        ))}
                    </select>
                    <span className="mt-1 block text-[11px] text-muted-foreground">
                        {t('ai.import.source_hint')}
                    </span>
                    {serverErrors.payment_source_id && (
                        <span className="mt-1 block text-xs text-destructive">
                            {serverErrors.payment_source_id}
                        </span>
                    )}
                </label>
            )}

            <div className="space-y-2">
                {rows.map((row) => (
                    <RowEditor
                        key={row.key}
                        row={row}
                        categories={categoriesByType[row.type]}
                        canSwitchType={tracksExpenses && tracksIncomes}
                        error={serverErrors[`${row.key}`]}
                        onChange={(patch) => update(row.key, patch)}
                    />
                ))}
            </div>

            <div className="fixed inset-x-0 bottom-[4.5rem] z-30 border-t border-border bg-background/90 backdrop-blur-xl md:bottom-0 md:left-64">
                <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-4 py-3 md:px-6 lg:px-8">
                    <button
                        type="button"
                        onClick={() => {
                            setDraft(null);
                            setRows([]);
                        }}
                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-3.5" />
                        {t('ai.import.back')}
                    </button>
                    <div className="flex min-w-0 items-center gap-3">
                        {(incomplete.length > 0 || needsSource) && (
                            <span className="hidden text-[11px] text-amber-700 sm:inline dark:text-amber-400">
                                {needsSource
                                    ? t('ai.import.needs_source')
                                    : t('ai.import.incomplete', {
                                          count: incomplete.length,
                                      })}
                            </span>
                        )}
                        <button
                            type="button"
                            onClick={confirm}
                            disabled={
                                importing ||
                                selected.length === 0 ||
                                incomplete.length > 0 ||
                                needsSource
                            }
                            className="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 px-4 py-2.5 text-xs font-bold text-primary-foreground disabled:opacity-50"
                        >
                            {importing && (
                                <Loader2 className="size-3.5 animate-spin" />
                            )}
                            {t('ai.import.confirm', { count: selected.length })}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

function RowEditor({
    row,
    categories,
    canSwitchType,
    error,
    onChange,
}: {
    row: EditableRow;
    categories: ImportCategoryOption[];
    canSwitchType: boolean;
    error?: string;
    onChange: (patch: Partial<EditableRow>) => void;
}) {
    const { t } = useTranslation();
    const missing = row.selected ? missingFields(row) : [];
    const isExpense = row.type === 'expense';

    return (
        <article
            className={cn(
                'rounded-xl border p-3 transition-colors',
                row.selected
                    ? 'border-border bg-surface-low'
                    : 'border-transparent bg-surface-low/50 opacity-70',
                (missing.length > 0 || error) && 'border-destructive/50',
            )}
        >
            <div className="flex items-start gap-3">
                <input
                    type="checkbox"
                    checked={row.selected}
                    onChange={(event) =>
                        onChange({ selected: event.target.checked })
                    }
                    className="mt-2 size-4 shrink-0 accent-emerald-600"
                    aria-label={t('ai.import.select_row')}
                />
                <div className="grid min-w-0 flex-1 gap-2 sm:grid-cols-12">
                    <input
                        type="text"
                        value={row.description}
                        maxLength={255}
                        onChange={(event) =>
                            onChange({ description: event.target.value })
                        }
                        className={cn(FIELD, 'sm:col-span-5')}
                        aria-label={t('ai.import.col_description')}
                    />
                    <input
                        type="date"
                        value={row.date ?? ''}
                        onChange={(event) =>
                            onChange({ date: event.target.value || null })
                        }
                        className={cn(FIELD, 'sm:col-span-3')}
                        aria-label={t('ai.import.col_date')}
                    />
                    <div className="flex gap-1.5 sm:col-span-4">
                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            inputMode="decimal"
                            value={row.amountInput}
                            onChange={(event) =>
                                onChange({ amountInput: event.target.value })
                            }
                            className={cn(
                                FIELD,
                                'flex-1 text-right font-semibold tabular-nums',
                                isExpense
                                    ? 'text-foreground'
                                    : 'text-blue-600 dark:text-blue-400',
                            )}
                            aria-label={t('ai.import.col_amount')}
                        />
                        <select
                            value={row.currency ?? ''}
                            onChange={(event) =>
                                onChange({
                                    currency:
                                        (event.target.value as CurrencyCode) ||
                                        null,
                                })
                            }
                            className={cn(FIELD, 'w-20')}
                            aria-label={t('ai.import.col_currency')}
                        >
                            <option value="">—</option>
                            {(Object.keys(CURRENCY_META) as CurrencyCode[]).map(
                                (code) => (
                                    <option key={code} value={code}>
                                        {CURRENCY_META[code].label}
                                    </option>
                                ),
                            )}
                        </select>
                    </div>

                    <div className="flex flex-wrap items-center gap-1.5 sm:col-span-12">
                        {canSwitchType ? (
                            <div className="flex overflow-hidden rounded-lg bg-surface-high text-[11px] font-semibold">
                                {(['expense', 'income'] as const).map(
                                    (type) => (
                                        <button
                                            key={type}
                                            type="button"
                                            onClick={() => onChange({ type })}
                                            className={cn(
                                                'px-2.5 py-1.5',
                                                row.type === type
                                                    ? type === 'expense'
                                                        ? 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-400'
                                                        : 'bg-blue-500/20 text-blue-700 dark:text-blue-400'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            {t(`ai.import.type_${type}`)}
                                        </button>
                                    ),
                                )}
                            </div>
                        ) : (
                            <span className="rounded-lg bg-surface-high px-2.5 py-1.5 text-[11px] font-semibold text-muted-foreground">
                                {t(`ai.import.type_${row.type}`)}
                            </span>
                        )}

                        <select
                            value={row.category_id ?? ''}
                            onChange={(event) =>
                                onChange({
                                    category_id: event.target.value
                                        ? Number(event.target.value)
                                        : null,
                                })
                            }
                            className={cn(
                                FIELD,
                                'w-auto min-w-40 flex-1 sm:flex-none',
                            )}
                            aria-label={t('ai.import.col_category')}
                        >
                            <option value="">
                                {t('ai.import.category_placeholder')}
                            </option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>

                        {row.currency === 'ves' && (
                            <label className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                {t('ai.import.rate')}
                                <input
                                    type="number"
                                    step="0.0001"
                                    min="0.0001"
                                    inputMode="decimal"
                                    value={row.rateInput}
                                    onChange={(event) =>
                                        onChange({
                                            rateInput: event.target.value,
                                        })
                                    }
                                    placeholder={t('ai.import.rate_auto')}
                                    className={cn(
                                        FIELD,
                                        'w-24 text-amber-700 tabular-nums dark:text-amber-400',
                                    )}
                                />
                            </label>
                        )}

                        {row.duplicate_of !== null && (
                            <span className="rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-400">
                                {t('ai.import.duplicate')}
                            </span>
                        )}
                        {row.warnings
                            .filter((warning) => warning !== 'duplicate')
                            .map((warning) => (
                                <span
                                    key={warning}
                                    className="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                                >
                                    {t(`ai.import.row_warning_${warning}`)}
                                </span>
                            ))}
                    </div>

                    {(missing.length > 0 || error) && (
                        <p className="text-[11px] text-destructive sm:col-span-12">
                            {error ??
                                t('ai.import.row_missing', {
                                    fields: missing
                                        .map((field) =>
                                            t(`ai.import.field_${field}`),
                                        )
                                        .join(', '),
                                })}
                        </p>
                    )}
                </div>
            </div>
        </article>
    );
}
