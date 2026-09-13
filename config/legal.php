<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identidad del operador de la instancia
    |--------------------------------------------------------------------------
    |
    | Spentz Trackr es auto-hospedable: el responsable del tratamiento de datos
    | NO es quien escribe el software, sino quien levanta cada instancia. Estos
    | valores rellenan los términos y la política de datos, así que cada
    | operador debe ponerlos en su .env antes de abrir el registro.
    |
    */

    'operator' => env('LEGAL_OPERATOR', 'el operador de esta instancia'),

    'contact_email' => env('LEGAL_CONTACT_EMAIL', env('ADMIN_EMAIL', 'admin@example.com')),

    'jurisdiction' => env('LEGAL_JURISDICTION', 'la República Bolivariana de Venezuela'),

    /*
     | Fecha de entrada en vigor que se muestra en ambos documentos. Cámbiala
     | cuando modifiques el texto: es lo que permite a un usuario saber si lo
     | que aceptó sigue siendo lo que está publicado.
     */
    'effective_date' => env('LEGAL_EFFECTIVE_DATE', '2026-09-13'),

];
