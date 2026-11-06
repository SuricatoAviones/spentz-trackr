<?php

namespace App\Services\Ai;

/**
 * Saca un objeto JSON de la respuesta de un modelo.
 *
 * No todos los proveedores tienen modo JSON y los que lo tienen no siempre lo
 * respetan: llega envuelto en ```json, precedido de una frase o con un bloque
 * `<think>` delante. Se prueba de lo más estricto a lo más laxo.
 */
final class JsonExtractor
{
    /**
     * @return array<string, mixed>|null
     */
    public static function decode(string $text): ?array
    {
        $text = trim((string) preg_replace('/<think>.*?<\/think>/s', '', $text));

        $candidates = [$text];

        if (preg_match('/```(?:json)?\s*(.+?)```/s', $text, $fenced) === 1) {
            $candidates[] = $fenced[1];
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $candidates[] = substr($text, $start, $end - $start + 1);
        }

        foreach ($candidates as $candidate) {
            $decoded = json_decode(trim($candidate), true);

            if (is_array($decoded) && ! array_is_list($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
