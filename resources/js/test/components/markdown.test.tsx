/**
 * El Markdown de la IA se pinta con elementos de React, nunca con HTML crudo:
 * la respuesta puede citar texto de las notas del usuario o de una factura.
 */
import { render, screen } from '@testing-library/react';
import { describe, expect, test } from 'vitest';
import { Markdown } from '@/components/ai/markdown';

describe('Markdown de la IA', () => {
    test('pinta títulos, negritas, listas y tablas', () => {
        const { container } = render(
            <Markdown
                content={[
                    '## Resumen',
                    'Gastaste **$42,50** este mes.',
                    '',
                    '- Comida',
                    '- Transporte',
                    '',
                    '| Categoría | USD |',
                    '|---|---:|',
                    '| Comida | 30,00 |',
                ].join('\n')}
            />,
        );

        expect(screen.getByText('Resumen')).toBeInTheDocument();
        expect(screen.getByText('$42,50').tagName).toBe('STRONG');
        expect(container.querySelectorAll('li')).toHaveLength(2);
        expect(container.querySelector('th')?.textContent).toBe('Categoría');
        expect(container.querySelector('td')?.textContent).toBe('Comida');
    });

    test('el HTML se queda como texto', () => {
        const { container } = render(
            <Markdown content={'<img src=x onerror="alert(1)"> <b>hola</b>'} />,
        );

        expect(container.querySelector('img')).toBeNull();
        expect(container.querySelector('b')).toBeNull();
        expect(container.textContent).toContain('<img src=x');
    });

    test('solo enlaza URLs http(s)', () => {
        const { container } = render(
            <Markdown
                content={
                    '[malo](javascript:alert(1)) y [bueno](https://bcv.org.ve)'
                }
            />,
        );

        const links = container.querySelectorAll('a');

        expect(links).toHaveLength(1);
        expect(links[0].getAttribute('href')).toBe('https://bcv.org.ve');
        expect(links[0].getAttribute('rel')).toContain('noopener');
        expect(container.textContent).toContain('malo');
    });
});
