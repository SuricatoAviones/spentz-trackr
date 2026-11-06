<?php

namespace App\Services\Ai;

use stdClass;

/**
 * Herramienta (function calling) que el modelo puede invocar.
 *
 * `parameters` es un JSON Schema reducido al subconjunto que aceptan los tres
 * protocolos: type, properties, required, enum, items y description. Nada de
 * `additionalProperties` ni `$ref`: Gemini los rechaza con un 400.
 */
final class AiTool
{
    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @param  list<string>  $required
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $properties = [],
        public readonly array $required = [],
    ) {}

    public function hasParameters(): bool
    {
        return $this->properties !== [];
    }

    /**
     * El esquema listo para `json_encode`. `properties` vacío tiene que salir
     * como `{}` y no como `[]`, o OpenAI y Anthropic lo rechazan.
     *
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        $schema = [
            'type' => 'object',
            'properties' => $this->properties === [] ? new stdClass : $this->properties,
        ];

        if ($this->required !== []) {
            $schema['required'] = $this->required;
        }

        return $schema;
    }
}
