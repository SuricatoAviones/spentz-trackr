import { Link, useHttp } from '@inertiajs/react';
import { AlertTriangle, Loader2, ScanLine, Sparkles } from 'lucide-react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { aiFailure, isCancelled } from '@/lib/ai';
import { scan as receiptsScan } from '@/routes/ai/receipts';
import { edit as aiSettingsEdit } from '@/routes/ai/settings';
import type { AiScanCapabilities, ReceiptDraft } from '@/types';

/**
 * "Leer factura con IA" dentro del formulario de gasto.
 *
 * Sube la foto o el PDF, recibe un borrador y se lo pasa al formulario, que
 * rellena los campos. No guarda nada: el usuario revisa y pulsa Guardar.
 */
export function ReceiptScanner({
    capabilities,
    onDraft,
}: {
    capabilities: AiScanCapabilities;
    onDraft: (draft: ReceiptDraft, file: File) => void;
}) {
    const { t } = useTranslation();
    const input = useRef<HTMLInputElement>(null);
    const [error, setError] = useState<string | null>(null);
    const [warnings, setWarnings] = useState<string[]>([]);
    const [scannedName, setScannedName] = useState<string | null>(null);
    const http = useHttp<{ file: File | null }, { data: ReceiptDraft }>({
        file: null,
    });

    if (!capabilities.available) {
        return (
            <Link
                href={aiSettingsEdit().url}
                className="flex items-center gap-3 rounded-xl border border-dashed border-border bg-surface-low px-4 py-3 text-left"
            >
                <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-violet-500/15 text-violet-600 dark:text-violet-400">
                    <Sparkles className="size-4" />
                </span>
                <span className="min-w-0">
                    <span className="block text-sm font-medium text-foreground">
                        {t('ai.scan.cta_setup')}
                    </span>
                    <span className="block text-[11px] text-muted-foreground">
                        {t('ai.scan.cta_setup_hint')}
                    </span>
                </span>
            </Link>
        );
    }

    async function handleFile(file: File | undefined) {
        if (!file) {
            return;
        }

        setError(null);
        setWarnings([]);
        setScannedName(null);
        http.setData('file', file);

        try {
            const response = await http.post(receiptsScan().url);

            // 422 de validación: useHttp lo deja en `errors` y no lanza.
            if (!response) {
                return;
            }

            onDraft(response.data, file);
            setScannedName(file.name);
            setWarnings(response.data.warnings);
        } catch (failure) {
            if (!isCancelled(failure)) {
                setError(
                    aiFailure(failure, t('ai.scan.error_generic')).message,
                );
            }
        } finally {
            if (input.current) {
                input.current.value = '';
            }
        }
    }

    const accept = capabilities.pdf
        ? 'image/jpeg,image/png,image/webp,application/pdf'
        : 'image/jpeg,image/png,image/webp';

    return (
        <section className="rounded-xl bg-gradient-to-br from-violet-500/15 to-emerald-500/10 p-4">
            <button
                type="button"
                disabled={http.processing}
                onClick={() => input.current?.click()}
                className="flex w-full items-center gap-3 text-left disabled:opacity-70"
            >
                <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-violet-500/20 text-violet-600 dark:text-violet-300">
                    {http.processing ? (
                        <Loader2 className="size-5 animate-spin" />
                    ) : (
                        <ScanLine className="size-5" />
                    )}
                </span>
                <span className="min-w-0 flex-1">
                    <span className="block text-sm font-semibold text-foreground">
                        {http.processing
                            ? t('ai.scan.reading')
                            : t('ai.scan.title')}
                    </span>
                    <span className="block text-[11px] text-muted-foreground">
                        {http.processing
                            ? t('ai.scan.reading_hint')
                            : capabilities.pdf
                              ? t('ai.scan.hint_pdf')
                              : t('ai.scan.hint')}
                    </span>
                </span>
                <Sparkles className="size-4 shrink-0 text-violet-500" />
            </button>
            <input
                ref={input}
                type="file"
                accept={accept}
                className="hidden"
                onChange={(event) => void handleFile(event.target.files?.[0])}
            />

            {http.errors.file && (
                <p className="mt-2 text-xs text-destructive">
                    {http.errors.file}
                </p>
            )}
            {error && <p className="mt-2 text-xs text-destructive">{error}</p>}

            {scannedName && (
                <p className="mt-2 text-[11px] text-emerald-700 dark:text-emerald-400">
                    {t('ai.scan.done', { name: scannedName })}
                </p>
            )}
            {warnings.length > 0 && (
                <ul className="mt-2 space-y-1">
                    {warnings.map((warning) => (
                        <li
                            key={warning}
                            className="flex items-start gap-1.5 text-[11px] text-amber-700 dark:text-amber-400"
                        >
                            <AlertTriangle className="mt-px size-3.5 shrink-0" />
                            {t(`ai.scan.warning_${warning}`)}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
