import '@testing-library/jest-dom/vitest';

import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';
import { initI18n } from '@/i18n';

/*
 * Se inicializa el i18n real con el diccionario real: si una página usa una
 * clave que no existe, el texto sale crudo y el test puede verlo. Mockearlo
 * devolviendo la clave escondería justo esa clase de fallo.
 */
initI18n({
    locale: 'es',
    translations: { messages: {}, admin: {} },
});

/*
 * `@inertiajs/react` habla con un runtime que en los tests no existe (historial,
 * peticiones, el elemento raíz). Se sustituye por lo mínimo que usan las
 * páginas. El objeto de página lo fija cada test con `setPageProps()`.
 */
let pageProps: Record<string, unknown> = {};

export function setPageProps(props: Record<string, unknown>): void {
    pageProps = props;
}

vi.mock('@inertiajs/react', async () => {
    const React = await import('react');

    return {
        Head: () => null,
        Link: ({ children, href, ...rest }: Record<string, unknown>) =>
            React.createElement(
                'a',
                { href: typeof href === 'string' ? href : '#', ...rest },
                children as React.ReactNode,
            ),
        // Las páginas llaman a esto en el cuerpo del componente para fijar el
        // título del layout; aquí no hay layout que actualizar.
        setLayoutProps: () => undefined,
        usePage: () => ({ props: pageProps, url: '/' }),
        router: {
            get: vi.fn(),
            post: vi.fn(),
            put: vi.fn(),
            patch: vi.fn(),
            delete: vi.fn(),
            reload: vi.fn(),
        },
        useForm: (initial: Record<string, unknown> = {}) => {
            const [data, setDataState] = React.useState(initial);

            return {
                data,
                errors: {} as Record<string, string>,
                processing: false,
                setData: (key: string, value: unknown) =>
                    setDataState((current) => ({ ...current, [key]: value })),
                setDefaults: () => undefined,
                clearErrors: () => undefined,
                reset: () => setDataState(initial),
                post: vi.fn(),
                put: vi.fn(),
                patch: vi.fn(),
                delete: vi.fn(),
                transform: vi.fn(),
            };
        },
    };
});

afterEach(() => {
    cleanup();
    pageProps = {};
});
