<?php

namespace App\Services\Ai;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

/**
 * Cualquier fallo al hablar con el proveedor de IA, ya traducido para el
 * usuario. El motivo (`reason`) viaja también en la respuesta para que un
 * cliente pueda reaccionar (p. ej. mandar a Ajustes ante `invalid_key`).
 *
 * No se reporta al log: casi siempre es configuración del propio usuario (una
 * clave caducada, un modelo mal escrito, cuota agotada) y llenaría el log de
 * ruido sin nada que el operador pueda arreglar.
 */
final class AiException extends RuntimeException
{
    public const NOT_CONFIGURED = 'not_configured';

    public const DISABLED = 'disabled';

    public const INVALID_KEY = 'invalid_key';

    public const RATE_LIMITED = 'rate_limited';

    public const MODEL_NOT_FOUND = 'model_not_found';

    public const BAD_REQUEST = 'bad_request';

    public const UNSUPPORTED_ATTACHMENT = 'unsupported_attachment';

    public const UNAVAILABLE = 'unavailable';

    public const TIMEOUT = 'timeout';

    public const REFUSED = 'refused';

    public const INVALID_RESPONSE = 'invalid_response';

    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly int $status = 422,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function because(string $reason, ?string $detail = null, ?Throwable $previous = null): self
    {
        $message = __("messages.ai_error_{$reason}");

        if ($detail !== null && $detail !== '') {
            $message .= ' ('.$detail.')';
        }

        return new self($reason, $message, self::statusFor($reason), $previous);
    }

    private static function statusFor(string $reason): int
    {
        return match ($reason) {
            self::RATE_LIMITED => 429,
            self::DISABLED => 503,
            self::UNAVAILABLE, self::INVALID_RESPONSE, self::REFUSED => 502,
            self::TIMEOUT => 504,
            default => 422,
        };
    }

    public function report(): void
    {
        // Ver el docblock: error del usuario o del proveedor, no de la app.
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'reason' => $this->reason,
        ], $this->status);
    }
}
