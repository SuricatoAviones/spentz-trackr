/**
 * Importar un estado de cuenta: el flujo completo en el navegador (subir →
 * revisar → confirmar) con la forma real de ScanStatementAction. Lo que se
 * vigila: los duplicados salen desmarcados, una fila incompleta bloquea la
 * importación y lo que se envía es exactamente lo revisado.
 */
import { router } from '@inertiajs/react';
import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, test, vi } from 'vitest';
import ImportStatement from '@/pages/ai/import';
import AiSettings from '@/pages/ai/settings';
import type { AiProviderOption } from '@/types';
import { aiProviderFixture } from '../fixtures';
import { setHttpResponse, setPageProps } from '../setup';

const categories = [
    {
        id: 3,
        name: 'Comida',
        icon: 'utensils',
        color: '#10B981',
        type: 'expense' as const,
    },
    {
        id: 7,
        name: 'Sueldo',
        icon: 'briefcase',
        color: '#3B82F6',
        type: 'income' as const,
    },
];

const sources = [
    { id: 5, name: 'Banesco', icon: 'landmark', color: '#3B82F6' },
];

function row(overrides: Record<string, unknown> = {}) {
    return {
        key: 0,
        type: 'expense',
        date: '2026-10-03',
        description: 'Compra POS Farmatodo',
        amount: '365.00',
        currency: 'ves',
        category_id: 3,
        payment_method: 'pago_movil',
        exchange_rate: 36.5,
        rate_provider: 'bcv',
        note: 'Ref. 1 · Importado del estado de cuenta de Banesco',
        duplicate_of: null,
        selected: true,
        warnings: [],
        ...overrides,
    };
}

function renderImport(available = true) {
    return render(
        <ImportStatement
            capabilities={{ available, pdf: true }}
            categories={categories}
            sources={sources}
            trackingType="both"
            limits={{ max_files: 5, max_rows: 200 }}
        />,
    );
}

async function readStatement(container: HTMLElement) {
    const input = container.querySelector('input[type="file"]');

    fireEvent.change(input as HTMLInputElement, {
        target: {
            files: [
                new File(['%PDF'], 'extracto.pdf', { type: 'application/pdf' }),
            ],
        },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Leer movimientos' }));

    await screen.findByText('BANESCO');
}

describe('importar estado de cuenta', () => {
    test('sin proveedor manda a configurarlo', () => {
        renderImport(false);

        expect(
            screen.getByText('Conecta tu proveedor de IA'),
        ).toBeInTheDocument();
    });

    test('lee, deja los duplicados fuera y envía lo revisado', async () => {
        setHttpResponse({
            data: {
                bank: 'BANESCO',
                currency: 'ves',
                payment_source_id: 5,
                tracking: { expenses: true, incomes: true },
                warnings: [],
                rows: [
                    row(),
                    row({
                        key: 1,
                        description: 'Farmatodo repetido',
                        duplicate_of: 9,
                        selected: false,
                        warnings: ['duplicate'],
                    }),
                    row({
                        key: 2,
                        type: 'income',
                        description: 'Nómina',
                        category_id: 7,
                        payment_method: null,
                        amount: '3825.00',
                        exchange_rate: 38.25,
                    }),
                ],
            },
        });

        const { container } = renderImport();

        await readStatement(container);

        expect(screen.getByText('Ya registrado')).toBeInTheDocument();
        expect(screen.getByDisplayValue('Banesco')).toBeInTheDocument();

        fireEvent.click(
            screen.getByRole('button', { name: 'Importar 2 movimientos' }),
        );

        expect(router.post).toHaveBeenCalledTimes(1);

        const [, payload] = vi.mocked(router.post).mock.calls[0] as [
            string,
            { payment_source_id: number; rows: Record<string, unknown>[] },
        ];

        expect(payload.payment_source_id).toBe(5);
        expect(payload.rows.map((sent) => sent.description)).toEqual([
            'Compra POS Farmatodo',
            'Nómina',
        ]);
        // La tasa leída del día viaja con su proveedor; el ingreso no lleva método de pago.
        expect(payload.rows[0]).toMatchObject({
            exchange_rate: '36.5',
            rate_provider: 'bcv',
            payment_method: 'pago_movil',
        });
        expect(payload.rows[1]).toMatchObject({
            type: 'income',
            payment_method: '',
        });
    });

    test('una fila marcada sin categoría bloquea la importación', async () => {
        setHttpResponse({
            data: {
                bank: 'BANESCO',
                currency: 'ves',
                payment_source_id: 5,
                tracking: { expenses: true, incomes: true },
                warnings: [],
                rows: [row({ category_id: null })],
            },
        });

        const { container } = renderImport();

        await readStatement(container);

        expect(screen.getByText(/Falta: categoría/)).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Importar 1 movimiento' }),
        ).toBeDisabled();
    });
});

describe('opencode en ajustes', () => {
    test('enseña el plan y avisa de los modelos gratuitos', () => {
        setPageProps({ features: { ai: true }, errors: {} });

        render(
            <AiSettings
                setting={null}
                providers={[
                    aiProviderFixture({
                        value: 'opencode',
                        label: 'OpenCode',
                        default_model: 'claude-opus-5-5',
                        default_base_url:
                            'https://opencode.ai/inference/openai/v1',
                        endpoint_options: [
                            {
                                key: 'payg',
                                value: 'https://opencode.ai/inference/openai/v1',
                            },
                            {
                                key: 'go',
                                value: 'https://opencode.ai/inference/go/openai/v1',
                            },
                        ],
                        supports_pdf: false,
                    }) as AiProviderOption,
                ]}
                customEndpointsAllowed={false}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Pago por uso' }),
        ).toHaveAttribute('aria-pressed', 'true');
        expect(screen.getByRole('button', { name: 'Go' })).toHaveAttribute(
            'aria-pressed',
            'false',
        );
        expect(
            screen.getByText(/Los modelos gratuitos solo funcionan/),
        ).toBeInTheDocument();
    });
});
