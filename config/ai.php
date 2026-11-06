<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Asistente de IA
    |--------------------------------------------------------------------------
    |
    | Cada usuario conecta su propio proveedor con su propia clave: la
    | instancia no paga ni intermedia el consumo. El interruptor general
    | (encender o apagar la IA para todos) vive en `config/features.php` y en
    | el panel admin; aquí solo hay límites técnicos que fija el operador.
    |
    */

    /*
     | Permite los proveedores que apuntan a un servidor elegido por el usuario
     | (Ollama, endpoint OpenAI-compatible propio). Apagado por defecto: con él
     | encendido cualquier usuario puede hacer que el servidor abra conexiones a
     | direcciones arbitrarias, incluida la red interna (SSRF). Enciéndelo solo
     | en una instancia personal o si confías en todos los usuarios.
     */
    'allow_custom_endpoints' => (bool) env('AI_ALLOW_CUSTOM_ENDPOINTS', false),

    /*
     | Segundos de espera por respuesta del proveedor. Leer una factura con un
     | modelo grande puede tardar; el límite evita colgar el worker para siempre.
     */
    'timeout' => (int) env('AI_TIMEOUT', 60),

    'connect_timeout' => (int) env('AI_CONNECT_TIMEOUT', 10),

    /*
     | Leer un estado de cuenta de varias páginas tarda bastante más que una
     | factura: esa petición concreta puede esperar hasta este tope.
     */
    'import_timeout' => (int) env('AI_IMPORT_TIMEOUT', 120),

    /*
     | Importación de movimientos desde un estado de cuenta: archivos por
     | lectura (PDF o capturas) y filas máximas por importación.
     */
    'import' => [
        'max_files' => 5,
        'max_rows' => 200,
    ],

    /*
     | Límite por usuario de peticiones a la IA (lectura de facturas, chat,
     | análisis). Protege la clave del propio usuario de un bucle o un abuso y
     | el servidor de peticiones largas encadenadas.
     */
    'rate_limit' => [
        'per_minute' => (int) env('AI_RATE_LIMIT_PER_MINUTE', 10),
        'per_day' => (int) env('AI_RATE_LIMIT_PER_DAY', 300),
    ],

    /*
     | Las fotos se reducen a este lado mayor (px) antes de enviarse, si la
     | extensión GD está disponible: menos tokens, menos latencia y por debajo
     | del límite de 5 MB por imagen de algunos proveedores.
     */
    'image_max_side' => 2000,

    'chat' => [
        'max_history' => 30,
        'max_message_length' => 4000,
        'max_tool_rounds' => 6,
    ],

];
