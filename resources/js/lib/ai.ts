/**
 * Mensaje legible de un fallo de `useHttp` contra una ruta de IA.
 *
 * Las rutas de IA responden `{ message, reason }` con 422/429/502/503/504
 * (ver `App\Services\Ai\AiException`). `useHttp` resuelve los 422 de
 * validación por su cuenta y lanza el resto como `HttpResponseError`, cuyo
 * `response.data` es el cuerpo en texto.
 */
export type AiFailure = {
    message: string;
    reason: string | null;
};

export function aiFailure(error: unknown, fallback: string): AiFailure {
    const raw =
        typeof error === 'object' && error !== null && 'response' in error
            ? (error as { response?: { data?: unknown } }).response?.data
            : undefined;

    if (typeof raw === 'string' && raw !== '') {
        try {
            const body = JSON.parse(raw) as {
                message?: unknown;
                reason?: unknown;
            };

            return {
                message:
                    typeof body.message === 'string' && body.message !== ''
                        ? body.message
                        : fallback,
                reason: typeof body.reason === 'string' ? body.reason : null,
            };
        } catch {
            return { message: fallback, reason: null };
        }
    }

    return { message: fallback, reason: null };
}

/**
 * Peticiones canceladas (el usuario navegó o abortó): no son un error que
 * haya que enseñar.
 */
export function isCancelled(error: unknown): boolean {
    return (
        error instanceof Error &&
        (error.name === 'AbortError' || error.name === 'HttpCancelledError')
    );
}
