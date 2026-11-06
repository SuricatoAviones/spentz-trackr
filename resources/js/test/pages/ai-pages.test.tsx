/**
 * Smoke tests de las pantallas de IA y del estado de cuenta, con la forma real
 * de los presenters (ver fixtures.ts). Las pruebas de PHP solo miran props;
 * esto atrapa la pantalla en blanco que deja una forma equivocada.
 */
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, test } from 'vitest';
import Assistant from '@/pages/ai/assistant';
import AiSettings from '@/pages/ai/settings';
import Statement from '@/pages/reports/statement';
import type {
    AccountStatement,
    AiProviderOption,
    AiSettingSummary,
} from '@/types';
import {
    aiProviderFixture,
    aiSettingFixture,
    statementFixture,
} from '../fixtures';
import { setPageProps } from '../setup';

beforeEach(() => {
    sessionStorage.clear();
    setPageProps({ features: { ai: true }, errors: {} });
});

describe('ajustes de IA', () => {
    test('enseña el proveedor conectado sin la clave', () => {
        render(
            <AiSettings
                setting={aiSettingFixture() as AiSettingSummary}
                providers={[
                    aiProviderFixture() as AiProviderOption,
                    aiProviderFixture({
                        value: 'ollama',
                        label: 'Ollama',
                        requires_key: false,
                        requires_base_url: true,
                        available: false,
                    }) as AiProviderOption,
                ]}
                customEndpointsAllowed={false}
            />,
        );

        expect(
            screen.getAllByText('Anthropic (Claude)').length,
        ).toBeGreaterThan(0);
        expect(screen.getByText('••••abcd')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Ollama/ })).toBeDisabled();
        expect(
            screen.getByText(/AI_ALLOW_CUSTOM_ENDPOINTS/),
        ).toBeInTheDocument();
    });

    test('sin proveedor invita a conectarse', () => {
        render(
            <AiSettings
                setting={null}
                providers={[aiProviderFixture() as AiProviderOption]}
                customEndpointsAllowed
            />,
        );

        expect(
            screen.getByText(/Aún no conectaste ningún proveedor/),
        ).toBeInTheDocument();
    });
});

describe('asistente', () => {
    test('sin proveedor manda a configurarlo', () => {
        render(
            <Assistant
                assistant={{ configured: false, provider: null, model: null }}
                trackingType="both"
            />,
        );

        expect(
            screen.getByText('Conecta tu proveedor de IA'),
        ).toBeInTheDocument();
    });

    test('con proveedor ofrece sugerencias', () => {
        render(
            <Assistant
                assistant={{
                    configured: true,
                    provider: 'OpenAI',
                    model: 'gpt-4.1-mini',
                }}
                trackingType="both"
            />,
        );

        expect(screen.getByText('¿En qué te ayudo?')).toBeInTheDocument();
        expect(
            screen.getByText('Dame el estado de cuenta del mes pasado'),
        ).toBeInTheDocument();
    });

    test('recupera la conversación de la pestaña', () => {
        sessionStorage.setItem(
            'spentz.assistant.v1',
            JSON.stringify([
                { id: '1', role: 'user', content: '¿Cuánto gasté?' },
                {
                    id: '2',
                    role: 'assistant',
                    content: 'Gastaste **$42,50**.',
                    proposals: [
                        {
                            description: 'Café',
                            amount: 2,
                            currency: 'usd',
                            spent_at: '2026-11-06',
                            category_id: 3,
                            category_name: 'Comida',
                            payment_source_id: 5,
                            source_name: 'Efectivo',
                            payment_method: null,
                            note: null,
                        },
                    ],
                },
            ]),
        );

        render(
            <Assistant
                assistant={{
                    configured: true,
                    provider: 'OpenAI',
                    model: 'gpt-4.1-mini',
                }}
                trackingType="both"
            />,
        );

        expect(screen.getByText('¿Cuánto gasté?')).toBeInTheDocument();
        expect(screen.getByText('$42,50')).toBeInTheDocument();
        expect(screen.getByText('Gasto propuesto')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /Guardar gasto/ }),
        ).toBeEnabled();
    });
});

describe('estado de cuenta', () => {
    test('pinta totales, desglose y movimientos con el acumulado', () => {
        render(
            <Statement
                statement={statementFixture() as AccountStatement}
                aiAvailable
            />,
        );

        expect(screen.getByText('Sueldo de octubre')).toBeInTheDocument();
        expect(screen.getAllByText('Mercado').length).toBeGreaterThan(0);
        expect(screen.getByText('Gastos por categoría')).toBeInTheDocument();
        expect(screen.getByText('Presupuesto del mes')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /Analizar con IA/ }),
        ).toBeInTheDocument();
    });

    test('sin movimientos lo dice', () => {
        render(
            <Statement
                statement={
                    statementFixture({
                        movements: [],
                        largest_expenses: [],
                        expense_categories: [],
                        sources: [],
                        budget: null,
                    }) as AccountStatement
                }
                aiAvailable={false}
            />,
        );

        expect(
            screen.getByText('No hay movimientos en este periodo.'),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/Conecta un proveedor de IA/),
        ).toBeInTheDocument();
    });
});
