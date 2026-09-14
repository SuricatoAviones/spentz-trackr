<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Interruptores de instancia
    |--------------------------------------------------------------------------
    |
    | Estos son los valores POR DEFECTO. Un administrador puede cambiarlos en
    | caliente desde el panel (Sistema), y esa preferencia —guardada en
    | `app_settings`— manda sobre lo que diga el .env.
    |
    | El .env sigue sirviendo para arrancar una instancia ya configurada: por
    | ejemplo API_ENABLED=false deja la API cerrada desde el primer arranque,
    | sin tener que entrar al panel a apagarla.
    |
    */

    /*
     | API REST en /api/v1. Apagarla devuelve 503 en todos sus endpoints y
     | oculta la documentación. No revoca los tokens ya emitidos: al volver a
     | encenderla siguen siendo válidos.
     |
     | Si no consumes la API desde ninguna app, tenerla apagada reduce la
     | superficie expuesta.
     */
    'api' => env('API_ENABLED', true),

    /*
     | Registro de usuarios. Apagarlo devuelve 404 en el formulario web y en
     | POST /api/v1/auth/register: una instancia privada no debería anunciar
     | siquiera que existe la puerta.
     |
     | No se comprueba desde `config/fortify.php` porque ese array se construye
     | al cargar la configuración, antes de que la base de datos esté
     | disponible, y rompería `config:cache`. La feature de Fortify queda
     | siempre registrada y el corte lo hace `EnsureRegistrationEnabled`.
     */
    'registration' => env('REGISTRATION_ENABLED', true),

];
