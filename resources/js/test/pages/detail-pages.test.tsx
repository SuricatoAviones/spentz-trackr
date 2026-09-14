/**
 * Smoke tests de render.
 *
 * El hueco que cierran: las pruebas de Inertia en PHP solo comprueban props del
 * payload, nunca renderizan React. Dos veces se coló una pantalla en blanco con
 * respuesta 200 y sin un error en los logs del servidor — una por leer
 * `expense.source` cuando el controlador mandaba el modelo crudo, y otra por una
 * página pública que caía en un layout que exige usuario.
 *
 * Aquí se monta cada página con la forma real del presenter y se comprueba que
 * pinta algo reconocible. No sustituyen a un test de comportamiento: atrapan la
 * clase de fallo que deja la pantalla vacía.
 */
import { render, screen } from '@testing-library/react';
import { describe, expect, test } from 'vitest';
import CreditCardsIndex from '@/pages/credit-cards/index';
import CreditCardShow from '@/pages/credit-cards/show';
import ExpenseShow from '@/pages/expenses/show';
import IncomeShow from '@/pages/incomes/show';
import LegalDocument from '@/pages/legal/document';
import {
    creditCardDetailFixture,
    creditCardFixture,
    expenseFixture,
    incomeFixture,
    rateFixture,
} from '../fixtures';
import { setPageProps } from '../setup';

describe('páginas de detalle', () => {
    test('el detalle de un gasto pinta importe, categoría y origen', () => {
        render(<ExpenseShow expense={expenseFixture()} />);

        expect(screen.getByText('Mercado semanal')).toBeInTheDocument();
        // `source` es el campo que faltaba cuando el controlador mandaba el
        // modelo crudo (llegaba como `payment_source`) y dejaba todo en blanco.
        expect(screen.getByText('Banesco')).toBeInTheDocument();
        expect(screen.getByText('Comida')).toBeInTheDocument();
    });

    test('el detalle de un ingreso pinta importe y categoría', () => {
        render(<IncomeShow income={incomeFixture()} />);

        expect(screen.getByText('Salario')).toBeInTheDocument();
        expect(screen.getByText('Sueldo')).toBeInTheDocument();
    });
});

describe('tarjetas de crédito', () => {
    test('el listado pinta la tarjeta con su disponible', () => {
        render(
            <CreditCardsIndex
                cards={[creditCardFixture()]}
                rate={rateFixture}
            />,
        );

        expect(screen.getByText('Banesco')).toBeInTheDocument();
        expect(screen.getByText(/Visa Clásica/)).toBeInTheDocument();
        expect(screen.getByText('18%')).toBeInTheDocument();
    });

    test('el listado vacío ofrece crear la primera tarjeta', () => {
        render(<CreditCardsIndex cards={[]} rate={rateFixture} />);

        // Si la clave i18n no existiera, aquí saldría el literal "cards.empty_title".
        expect(
            screen.getByText('Todavía no tienes tarjetas'),
        ).toBeInTheDocument();
    });

    test('la ficha avisa de que el saldo es una estimación', () => {
        render(
            <CreditCardShow
                card={creditCardDetailFixture()}
                rate={rateFixture}
                recentCharges={[]}
            />,
        );

        expect(screen.getByText(/Estimación/)).toBeInTheDocument();
    });

    test('sin movimientos tras el corte no se presenta como estimación', () => {
        const card = creditCardDetailFixture({
            balance: {
                ...creditCardFixture().balance,
                is_estimate: false,
                charges_since_cut: 0,
                payments_since_cut: 0,
            },
        });

        render(
            <CreditCardShow
                card={card}
                rate={rateFixture}
                recentCharges={[]}
            />,
        );

        expect(screen.queryByText(/Estimación/)).not.toBeInTheDocument();
    });
});

describe('páginas legales', () => {
    test('se pintan sin usuario autenticado', () => {
        // El caso que las dejó en blanco: visitante anónimo.
        setPageProps({ auth: { user: null } });

        render(
            <LegalDocument
                document="terms"
                content={{
                    title: 'Términos y condiciones',
                    subtitle: 'Condiciones de uso.',
                    updated: 'En vigor desde el 13 de septiembre de 2026',
                    intro: 'Este documento regula el uso.',
                    sections: {
                        service: {
                            heading: '1. Qué es este servicio',
                            body: ['Una app de registro de gastos.'],
                        },
                    },
                }}
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'Términos y condiciones' }),
        ).toBeInTheDocument();
        expect(screen.getByText('1. Qué es este servicio')).toBeInTheDocument();
    });
});
