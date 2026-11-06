import { Fragment } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Markdown mínimo para las respuestas de la IA, renderizado a elementos de
 * React: nunca `dangerouslySetInnerHTML`. La respuesta del modelo puede traer
 * texto de las notas del propio usuario (o de una factura), así que se trata
 * como no confiable: no hay HTML crudo y los enlaces solo se aceptan http(s).
 *
 * Cubre lo que los modelos usan de verdad en un informe: títulos, párrafos,
 * negritas, cursivas, código, listas, citas, separadores y tablas.
 */
export function Markdown({
    content,
    className,
}: {
    content: string;
    className?: string;
}) {
    return (
        <div className={cn('space-y-2.5 text-sm leading-relaxed', className)}>
            {parseBlocks(content).map((block, index) => (
                <Fragment key={index}>{renderBlock(block)}</Fragment>
            ))}
        </div>
    );
}

type Block =
    | { type: 'heading'; level: number; text: string }
    | { type: 'paragraph'; text: string }
    | { type: 'code'; text: string }
    | { type: 'quote'; text: string }
    | { type: 'rule' }
    | {
          type: 'list';
          ordered: boolean;
          items: { text: string; depth: number }[];
      }
    | { type: 'table'; header: string[]; rows: string[][] };

const LIST_ITEM = /^(\s*)([-*+]|\d+[.)])\s+(.*)$/;
const TABLE_SEPARATOR = /^\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/;

function splitRow(line: string): string[] {
    return line
        .trim()
        .replace(/^\|/, '')
        .replace(/\|$/, '')
        .split('|')
        .map((cell) => cell.trim());
}

function parseBlocks(source: string): Block[] {
    const lines = source.replace(/\r\n?/g, '\n').split('\n');
    const blocks: Block[] = [];
    let index = 0;

    while (index < lines.length) {
        const line = lines[index];
        const trimmed = line.trim();

        if (trimmed === '') {
            index++;

            continue;
        }

        if (trimmed.startsWith('```')) {
            const code: string[] = [];
            index++;

            while (
                index < lines.length &&
                !lines[index].trim().startsWith('```')
            ) {
                code.push(lines[index]);
                index++;
            }

            index++;
            blocks.push({ type: 'code', text: code.join('\n') });

            continue;
        }

        const heading = /^(#{1,6})\s+(.*)$/.exec(trimmed);

        if (heading) {
            blocks.push({
                type: 'heading',
                level: heading[1].length,
                text: heading[2].replace(/#+$/, '').trim(),
            });
            index++;

            continue;
        }

        if (/^(-{3,}|\*{3,}|_{3,})$/.test(trimmed)) {
            blocks.push({ type: 'rule' });
            index++;

            continue;
        }

        if (
            trimmed.startsWith('|') &&
            index + 1 < lines.length &&
            TABLE_SEPARATOR.test(lines[index + 1].trim())
        ) {
            const header = splitRow(trimmed);
            const rows: string[][] = [];
            index += 2;

            while (
                index < lines.length &&
                lines[index].trim().startsWith('|')
            ) {
                rows.push(splitRow(lines[index]));
                index++;
            }

            blocks.push({ type: 'table', header, rows });

            continue;
        }

        if (trimmed.startsWith('>')) {
            const quote: string[] = [];

            while (
                index < lines.length &&
                lines[index].trim().startsWith('>')
            ) {
                quote.push(lines[index].trim().replace(/^>\s?/, ''));
                index++;
            }

            blocks.push({ type: 'quote', text: quote.join(' ') });

            continue;
        }

        const item = LIST_ITEM.exec(line);

        if (item) {
            const ordered = /\d/.test(item[2]);
            const items: { text: string; depth: number }[] = [];

            while (index < lines.length) {
                const current = LIST_ITEM.exec(lines[index]);

                if (current) {
                    items.push({
                        text: current[3],
                        depth: Math.min(3, Math.floor(current[1].length / 2)),
                    });
                    index++;

                    continue;
                }

                // Línea de continuación de un elemento (sangrada).
                if (
                    lines[index].trim() !== '' &&
                    /^\s{2,}/.test(lines[index]) &&
                    items.length > 0
                ) {
                    items[items.length - 1].text += ` ${lines[index].trim()}`;
                    index++;

                    continue;
                }

                break;
            }

            blocks.push({ type: 'list', ordered, items });

            continue;
        }

        const paragraph: string[] = [trimmed];
        index++;

        while (
            index < lines.length &&
            lines[index].trim() !== '' &&
            !LIST_ITEM.test(lines[index]) &&
            !/^(#{1,6}\s|```|>|\|)/.test(lines[index].trim())
        ) {
            paragraph.push(lines[index].trim());
            index++;
        }

        blocks.push({ type: 'paragraph', text: paragraph.join(' ') });
    }

    return blocks;
}

function renderBlock(block: Block): ReactNode {
    switch (block.type) {
        case 'heading': {
            const size =
                block.level <= 1
                    ? 'text-base'
                    : block.level === 2
                      ? 'text-[15px]'
                      : 'text-sm';

            return (
                <p
                    className={cn(
                        'pt-1 font-display font-bold text-foreground',
                        size,
                    )}
                >
                    {renderInline(block.text)}
                </p>
            );
        }
        case 'paragraph':
            return <p>{renderInline(block.text)}</p>;
        case 'code':
            return (
                <pre className="overflow-x-auto rounded-lg bg-surface-high p-3 font-mono text-xs">
                    {block.text}
                </pre>
            );
        case 'quote':
            return (
                <blockquote className="border-l-2 border-emerald-500/60 pl-3 text-muted-foreground">
                    {renderInline(block.text)}
                </blockquote>
            );
        case 'rule':
            return <hr className="border-border" />;
        case 'list': {
            const Tag = block.ordered ? 'ol' : 'ul';

            return (
                <Tag
                    className={cn(
                        'space-y-1 pl-5',
                        block.ordered ? 'list-decimal' : 'list-disc',
                    )}
                >
                    {block.items.map((item, index) => (
                        <li
                            key={index}
                            style={
                                item.depth > 0
                                    ? { marginLeft: `${item.depth}rem` }
                                    : undefined
                            }
                        >
                            {renderInline(item.text)}
                        </li>
                    ))}
                </Tag>
            );
        }
        case 'table':
            return (
                <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="w-full min-w-max text-left text-xs">
                        <thead className="bg-surface-high">
                            <tr>
                                {block.header.map((cell, index) => (
                                    <th
                                        key={index}
                                        className="px-3 py-2 font-semibold whitespace-nowrap text-foreground"
                                    >
                                        {renderInline(cell)}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {block.rows.map((row, rowIndex) => (
                                <tr key={rowIndex}>
                                    {row.map((cell, cellIndex) => (
                                        <td
                                            key={cellIndex}
                                            className="px-3 py-2 align-top tabular-nums"
                                        >
                                            {renderInline(cell)}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            );
    }
}

const INLINE =
    /(`[^`]+`)|(\*\*[^*]+\*\*|__[^_]+__)|(\*[^*\s][^*]*\*|_[^_\s][^_]*_)|(\[[^\]]+\]\([^)\s]+\))/;

function renderInline(text: string): ReactNode[] {
    const nodes: ReactNode[] = [];
    let rest = text;
    let key = 0;

    while (rest !== '') {
        const match = INLINE.exec(rest);

        if (!match) {
            nodes.push(rest);

            break;
        }

        if (match.index > 0) {
            nodes.push(rest.slice(0, match.index));
        }

        const token = match[0];

        if (match[1]) {
            nodes.push(
                <code
                    key={key++}
                    className="rounded bg-surface-high px-1 py-0.5 font-mono text-[0.85em]"
                >
                    {token.slice(1, -1)}
                </code>,
            );
        } else if (match[2]) {
            nodes.push(
                <strong key={key++} className="font-semibold text-foreground">
                    {renderInline(token.slice(2, -2))}
                </strong>,
            );
        } else if (match[3]) {
            nodes.push(<em key={key++}>{renderInline(token.slice(1, -1))}</em>);
        } else {
            const link = /^\[([^\]]+)\]\(([^)\s]+)\)$/.exec(token);
            const href = link?.[2] ?? '';

            nodes.push(
                link && /^https?:\/\//i.test(href) ? (
                    <a
                        key={key++}
                        href={href}
                        target="_blank"
                        rel="noopener noreferrer nofollow"
                        className="font-medium text-emerald-600 underline underline-offset-2 dark:text-emerald-400"
                    >
                        {link[1]}
                    </a>
                ) : (
                    (link?.[1] ?? token)
                ),
            );
        }

        rest = rest.slice(match.index + token.length);
    }

    return nodes;
}
