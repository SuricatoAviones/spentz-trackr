import { Link, router, setLayoutProps, useHttp } from '@inertiajs/react';
import {
    ArrowUp,
    Check,
    Copy,
    Loader2,
    Plus,
    Receipt,
    RotateCcw,
    Settings2,
    Sparkles,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Markdown } from '@/components/ai/markdown';
import { useClipboard } from '@/hooks/use-clipboard';
import { aiFailure, isCancelled } from '@/lib/ai';
import { CURRENCY_META, formatAmount, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { ask as assistantAsk } from '@/routes/ai/assistant';
import { edit as aiSettingsEdit } from '@/routes/ai/settings';
import { store as expensesStore } from '@/routes/expenses';
import type { AssistantReply, ExpenseProposal, TrackingType } from '@/types';

type ChatMessage = {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    proposals?: ExpenseProposal[];
    error?: boolean;
};

/**
 * La conversación vive solo en el navegador (sessionStorage): el servidor no
 * guarda chats. Así sobrevive a ir a ver un gasto y volver, y muere con la
 * pestaña.
 */
const STORAGE_KEY = 'spentz.assistant.v1';

/** Mensajes que se reenvían como contexto: el servidor acepta hasta 30. */
const HISTORY_SENT = 20;

/** Tope de longitud por mensaje en el historial (ver AskAssistantRequest). */
const MAX_HISTORY_CHARS = 12000;

const SUGGESTIONS = [
    'month_summary',
    'statement_last_month',
    'top_categories',
    'budget',
    'compare',
    'savings_tips',
    'recurring',
    'register_expense',
] as const;

function loadConversation(): ChatMessage[] {
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        const parsed = raw ? (JSON.parse(raw) as unknown) : [];

        return Array.isArray(parsed) ? (parsed as ChatMessage[]) : [];
    } catch {
        return [];
    }
}

function saveConversation(messages: ChatMessage[]): void {
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(messages));
    } catch {
        // Modo privado o almacenamiento lleno: el chat sigue funcionando.
    }
}

function newId(): string {
    return `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

export default function Assistant({
    assistant,
}: {
    assistant: {
        configured: boolean;
        provider: string | null;
        model: string | null;
    };
    trackingType: TrackingType;
}) {
    const { t } = useTranslation();
    const [messages, setMessages] = useState<ChatMessage[]>(loadConversation);
    const [draft, setDraft] = useState('');
    const scroller = useRef<HTMLDivElement>(null);
    const http = useHttp<
        { messages: { role: string; content: string }[] },
        { data: AssistantReply }
    >({ messages: [] });

    setLayoutProps({
        title: t('ai.assistant.title'),
        description: assistant.provider
            ? t('ai.assistant.powered_by', {
                  provider: assistant.provider,
                  model: assistant.model,
              })
            : t('ai.assistant.subtitle'),
    });

    useEffect(() => {
        saveConversation(messages);

        const end = scroller.current;

        if (messages.length > 0 && typeof end?.scrollIntoView === 'function') {
            end.scrollIntoView({ behavior: 'smooth', block: 'end' });
        }
    }, [messages]);

    async function send(
        text: string,
        base: ChatMessage[] = messages,
    ): Promise<void> {
        const content = text.trim();

        if (content === '' || http.processing) {
            return;
        }

        const userMessage: ChatMessage = {
            id: newId(),
            role: 'user',
            content,
        };
        const conversation = [
            ...base.filter((message) => !message.error),
            userMessage,
        ];

        setMessages(conversation);
        setDraft('');

        http.setData(
            'messages',
            conversation.slice(-HISTORY_SENT).map((message) => ({
                role: message.role,
                content: message.content.slice(0, MAX_HISTORY_CHARS),
            })),
        );

        try {
            const response = await http.post(assistantAsk().url);

            if (!response) {
                setMessages((current) => [
                    ...current,
                    {
                        id: newId(),
                        role: 'assistant',
                        content: t('ai.assistant.invalid_message'),
                        error: true,
                    },
                ]);

                return;
            }

            setMessages((current) => [
                ...current,
                {
                    id: newId(),
                    role: 'assistant',
                    content: response.data.reply,
                    proposals: response.data.proposals,
                },
            ]);
        } catch (failure) {
            if (isCancelled(failure)) {
                return;
            }

            setMessages((current) => [
                ...current,
                {
                    id: newId(),
                    role: 'assistant',
                    content: aiFailure(failure, t('ai.assistant.error_generic'))
                        .message,
                    error: true,
                },
            ]);
        }
    }

    function retry(): void {
        const lastUser = [...messages]
            .reverse()
            .find((message) => message.role === 'user');

        if (!lastUser) {
            return;
        }

        // Se reenvía la última pregunta sobre la conversación previa a ella.
        const withoutError = messages.filter((message) => !message.error);
        const index = withoutError.lastIndexOf(lastUser);

        void send(
            lastUser.content,
            index >= 0 ? withoutError.slice(0, index) : withoutError,
        );
    }

    function reset(): void {
        http.cancel();
        setMessages([]);
    }

    if (!assistant.configured) {
        return (
            <div className="mx-auto max-w-lg">
                <section className="rounded-xl bg-surface-low px-5 py-8 text-center">
                    <span className="mx-auto flex size-12 items-center justify-center rounded-full bg-violet-500/15 text-violet-600 dark:text-violet-400">
                        <Sparkles className="size-6" />
                    </span>
                    <h2 className="mt-4 font-display text-lg font-bold text-foreground">
                        {t('ai.assistant.setup_title')}
                    </h2>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {t('ai.assistant.setup_desc')}
                    </p>
                    <Link
                        href={aiSettingsEdit().url}
                        className="mt-5 inline-flex items-center gap-2 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 px-4 py-2.5 text-xs font-bold text-primary-foreground"
                    >
                        <Settings2 className="size-4" />
                        {t('ai.assistant.setup_cta')}
                    </Link>
                </section>
            </div>
        );
    }

    return (
        <div className="mx-auto flex max-w-3xl flex-col">
            <div className="flex-1 space-y-4 pb-40 md:pb-36">
                {messages.length === 0 ? (
                    <section className="rounded-xl bg-gradient-to-br from-violet-500/15 to-emerald-500/10 px-5 py-6">
                        <span className="flex size-10 items-center justify-center rounded-full bg-violet-500/20 text-violet-600 dark:text-violet-300">
                            <Sparkles className="size-5" />
                        </span>
                        <h2 className="mt-3 font-display text-lg font-bold text-foreground">
                            {t('ai.assistant.empty_title')}
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('ai.assistant.empty_desc')}
                        </p>
                        <div className="mt-4 grid gap-2 sm:grid-cols-2">
                            {SUGGESTIONS.map((suggestion) => (
                                <button
                                    key={suggestion}
                                    type="button"
                                    onClick={() =>
                                        void send(
                                            t(
                                                `ai.assistant.suggestion_${suggestion}`,
                                            ),
                                        )
                                    }
                                    className="rounded-lg bg-background/70 px-3 py-2.5 text-left text-xs font-medium text-foreground transition-colors hover:bg-background"
                                >
                                    {t(`ai.assistant.suggestion_${suggestion}`)}
                                </button>
                            ))}
                        </div>
                    </section>
                ) : (
                    messages.map((message) => (
                        <MessageBubble
                            key={message.id}
                            message={message}
                            onRetry={retry}
                        />
                    ))
                )}

                {http.processing && (
                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                        <Loader2 className="size-4 animate-spin text-violet-500" />
                        {t('ai.assistant.thinking')}
                    </div>
                )}
                <div ref={scroller} />
            </div>

            <div className="fixed inset-x-0 bottom-[4.5rem] z-30 border-t border-border bg-background/90 backdrop-blur-xl md:bottom-0 md:left-64">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        void send(draft);
                    }}
                    className="mx-auto flex w-full max-w-3xl items-end gap-2 px-4 py-3 md:px-6"
                >
                    {messages.length > 0 && (
                        <button
                            type="button"
                            onClick={reset}
                            className="flex size-10 shrink-0 items-center justify-center rounded-full bg-surface-low text-muted-foreground transition-colors hover:text-foreground"
                            aria-label={t('ai.assistant.new_chat')}
                            title={t('ai.assistant.new_chat')}
                        >
                            <Plus className="size-4" />
                        </button>
                    )}
                    <textarea
                        value={draft}
                        rows={1}
                        maxLength={4000}
                        onChange={(event) => setDraft(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' && !event.shiftKey) {
                                event.preventDefault();
                                void send(draft);
                            }
                        }}
                        placeholder={t('ai.assistant.placeholder')}
                        aria-label={t('ai.assistant.placeholder')}
                        className="max-h-40 min-h-10 flex-1 resize-none rounded-2xl bg-surface-low px-4 py-2.5 text-sm text-foreground placeholder:text-muted-foreground/70 focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                    />
                    <button
                        type="submit"
                        disabled={http.processing || draft.trim() === ''}
                        className="flex size-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 text-primary-foreground transition-opacity disabled:opacity-40"
                        aria-label={t('ai.assistant.send')}
                    >
                        <ArrowUp className="size-4" strokeWidth={2.5} />
                    </button>
                </form>
                <p className="mx-auto max-w-3xl px-4 pb-2 text-center text-[10px] text-muted-foreground/80 md:px-6">
                    {t('ai.assistant.disclaimer')}
                </p>
            </div>
        </div>
    );
}

function MessageBubble({
    message,
    onRetry,
}: {
    message: ChatMessage;
    onRetry: () => void;
}) {
    const { t } = useTranslation();
    const [copied, copy] = useClipboard();

    if (message.role === 'user') {
        return (
            <div className="flex justify-end">
                <p className="max-w-[85%] rounded-2xl rounded-br-md bg-emerald-600 px-4 py-2.5 text-sm whitespace-pre-wrap text-white">
                    {message.content}
                </p>
            </div>
        );
    }

    if (message.error) {
        return (
            <div className="rounded-2xl rounded-bl-md bg-destructive/10 px-4 py-3 text-sm text-destructive">
                <p>{message.content}</p>
                <button
                    type="button"
                    onClick={onRetry}
                    className="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold"
                >
                    <RotateCcw className="size-3.5" />
                    {t('ai.assistant.retry')}
                </button>
            </div>
        );
    }

    return (
        <div className="space-y-2">
            <div className="rounded-2xl rounded-bl-md bg-surface-low px-4 py-3 text-foreground">
                <Markdown content={message.content} />
                <div className="mt-2 flex justify-end">
                    <button
                        type="button"
                        onClick={() => void copy(message.content)}
                        className="inline-flex items-center gap-1 text-[10px] font-medium text-muted-foreground hover:text-foreground"
                    >
                        {copied === message.content ? (
                            <Check className="size-3" />
                        ) : (
                            <Copy className="size-3" />
                        )}
                        {copied === message.content
                            ? t('ai.assistant.copied')
                            : t('ai.assistant.copy')}
                    </button>
                </div>
            </div>
            {message.proposals?.map((proposal, index) => (
                <ProposalCard key={index} proposal={proposal} />
            ))}
        </div>
    );
}

/**
 * Gasto propuesto por la IA. Guardarlo es una decisión del usuario: el botón
 * manda los datos a `expenses.store`, que valida y congela como siempre.
 */
function ProposalCard({ proposal }: { proposal: ExpenseProposal }) {
    const { t } = useTranslation();
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<string[]>([]);
    const currency = CURRENCY_META[proposal.currency];

    function save(): void {
        setSaving(true);
        setErrors([]);

        router.post(
            expensesStore().url,
            {
                description: proposal.description,
                amount: proposal.amount,
                currency: proposal.currency,
                spent_at: proposal.spent_at,
                category_id: proposal.category_id,
                payment_source_id: proposal.payment_source_id,
                payment_method: proposal.payment_method ?? '',
                note: proposal.note ?? '',
            },
            {
                onError: (validation) =>
                    setErrors(Object.values(validation).map(String)),
                onFinish: () => setSaving(false),
            },
        );
    }

    return (
        <div className="rounded-xl border border-emerald-500/30 bg-emerald-500/5 px-4 py-3">
            <p className="flex items-center gap-1.5 text-[11px] font-semibold tracking-wider text-emerald-700 uppercase dark:text-emerald-400">
                <Receipt className="size-3.5" />
                {t('ai.assistant.proposal_title')}
            </p>
            <div className="mt-2 flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="truncate text-sm font-semibold text-foreground">
                        {proposal.description}
                    </p>
                    <p className="text-[11px] text-muted-foreground">
                        {[
                            proposal.category_name,
                            proposal.source_name,
                            formatDate(proposal.spent_at),
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                </div>
                <p
                    className="shrink-0 font-display text-lg font-bold tabular-nums"
                    style={{ color: currency.color }}
                >
                    {formatAmount(proposal.amount)} {currency.label}
                </p>
            </div>
            {errors.length > 0 && (
                <ul className="mt-2 space-y-0.5 text-xs text-destructive">
                    {errors.map((error) => (
                        <li key={error}>{error}</li>
                    ))}
                </ul>
            )}
            <button
                type="button"
                onClick={save}
                disabled={saving}
                className={cn(
                    'mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 py-2 text-xs font-bold text-primary-foreground disabled:opacity-60',
                )}
            >
                {saving ? (
                    <Loader2 className="size-3.5 animate-spin" />
                ) : (
                    <Check className="size-3.5" />
                )}
                {t('ai.assistant.proposal_save')}
            </button>
        </div>
    );
}
