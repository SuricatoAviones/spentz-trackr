<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credenciales del administrador por defecto
    |--------------------------------------------------------------------------
    |
    | Usadas por `php artisan admin:create` para crear o actualizar el usuario
    | administrador. Nunca valores hardcodeados en código: se leen de .env.
    |
    */

    'name' => env('ADMIN_NAME', 'Administrador'),

    'email' => env('ADMIN_EMAIL', 'admin@spenttrackr.com'),

    'password' => env('ADMIN_PASSWORD'),
];
