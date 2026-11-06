<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * El historial lo manda el cliente (el servidor no guarda conversaciones), así
 * que se acota: número de mensajes, longitud de cada uno y que el último sea
 * del usuario. Sin esos topes, una petición podía mandar al proveedor —y
 * cobrarle al usuario— un contexto arbitrariamente grande.
 */
class AskAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'messages' => ['required', 'array', 'min:1', 'max:'.(int) config('ai.chat.max_history', 30)],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:12000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $messages = (array) $this->input('messages');
            $last = end($messages);

            if (! is_array($last) || ($last['role'] ?? null) !== 'user') {
                $validator->errors()->add('messages', __('validation.app.ai_last_message_user'));

                return;
            }

            $limit = (int) config('ai.chat.max_message_length', 4000);

            if (mb_strlen(trim((string) ($last['content'] ?? ''))) > $limit) {
                $validator->errors()->add('messages', __('validation.app.ai_message_too_long', ['max' => $limit]));
            }
        });
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    public function history(): array
    {
        return array_values(array_map(
            fn (array $message): array => [
                'role' => (string) $message['role'],
                'content' => trim((string) $message['content']),
            ],
            $this->validated('messages'),
        ));
    }
}
