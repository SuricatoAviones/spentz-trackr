<?php

/*
 | Texto de los documentos legales. Marcadores disponibles (los sustituye
 | LegalController con los valores de config/legal.php): :operator, :email,
 | :jurisdiction, :date.
 |
 | Debe mantenerse con las mismas claves que lang/en/legal.php — lo comprueba
 | tests/Unit/I18nDictionaryTest.php.
 */

return [

    'terms' => [
        'title' => 'Términos y condiciones',
        'subtitle' => 'Condiciones de uso de esta instancia de Spentz Trackr.',
        'updated' => 'En vigor desde el :date',
        'intro' => 'Este documento regula el uso de esta instancia de Spentz Trackr, operada por :operator. Al crear una cuenta o usar el servicio aceptas estas condiciones. Si no estás de acuerdo con alguna, no uses el servicio.',
        'sections' => [

            'service' => [
                'heading' => '1. Qué es este servicio',
                'body' => [
                    'Spentz Trackr es una aplicación de registro de gastos e ingresos personales en varias monedas (USD, bolívares y USDT). Sirve para anotar y consultar movimientos que tú mismo introduces.',
                    'Es una herramienta de registro, no un servicio financiero: no mueve dinero, no se conecta a tus cuentas bancarias, no ejecuta pagos y no interviene en ninguna transacción real.',
                    'El software es auto-hospedable. Esta instancia concreta la mantiene :operator, que es a quien debes dirigirte por cualquier asunto relativo al servicio.',
                ],
            ],

            'account' => [
                'heading' => '2. Tu cuenta',
                'body' => [
                    'Necesitas una cuenta para usar el servicio. Debes facilitar un correo electrónico válido y mantener actualizada la información de tu perfil.',
                    'Eres responsable de la confidencialidad de tu contraseña y de toda la actividad que ocurra bajo tu cuenta. Si sospechas de un acceso no autorizado, cambia la contraseña de inmediato: al hacerlo se cierran automáticamente el resto de sesiones y se revocan todos los tokens de API emitidos.',
                    'Puedes activar verificación en dos pasos o llaves de acceso (passkeys) desde los ajustes de seguridad. Te recomendamos hacerlo.',
                    'No puedes ceder tu cuenta a terceros ni usar la cuenta de otra persona.',
                ],
            ],

            'acceptable_use' => [
                'heading' => '3. Uso aceptable',
                'body' => [
                    'Te comprometes a no usar el servicio para actividades ilícitas, ni a intentar acceder a datos de otras cuentas, interrumpir el servicio, saltarte los límites de uso, o extraer datos de forma masiva y automatizada.',
                    'Los comprobantes que subas deben ser imágenes de tus propios justificantes. No subas contenido de terceros sin permiso ni material ilegal.',
                    ':operator puede suspender o eliminar una cuenta que incumpla estas condiciones, o que ponga en riesgo el servicio o a otros usuarios.',
                ],
            ],

            'rates' => [
                'heading' => '4. Tasas de cambio',
                'body' => [
                    'El servicio consulta tasas de cambio publicadas por una fuente pública externa (ve.dolarapi.com) y las guarda para convertir los importes que registras.',
                    'Esas tasas son orientativas. No se garantiza su exactitud, su disponibilidad ni que coincidan con la tasa que aplique tu banco o casa de cambio. Puedes introducir una tasa manual en cualquier momento.',
                    'Cada movimiento guarda la tasa vigente en el momento de registrarlo y no se recalcula después. Los informes históricos reflejan lo que valía entonces, no lo que valdría hoy.',
                ],
            ],

            'no_advice' => [
                'heading' => '5. No es asesoramiento financiero',
                'body' => [
                    'Nada de lo que muestra la aplicación —totales, informes, presupuestos, metas de ahorro o conversiones— constituye asesoramiento financiero, fiscal, contable ni de inversión.',
                    'Las decisiones que tomes a partir de esta información son tuyas. Para cualquier asunto fiscal o contable consulta a un profesional.',
                ],
            ],

            'availability' => [
                'heading' => '6. Disponibilidad y copias de seguridad',
                'body' => [
                    'El servicio se presta "tal cual" y "según disponibilidad". Puede haber cortes por mantenimiento, fallos técnicos o causas ajenas a :operator.',
                    'Aunque :operator pueda realizar copias de seguridad, no se garantiza la recuperación de datos perdidos. Puedes exportar tus movimientos en CSV desde la sección de informes cuando quieras, y te recomendamos hacerlo con regularidad.',
                ],
            ],

            'liability' => [
                'heading' => '7. Garantías y responsabilidad',
                'body' => [
                    'El servicio se ofrece sin garantías de ningún tipo, expresas o implícitas, incluidas las de comerciabilidad, adecuación a un fin concreto y ausencia de errores.',
                    'En la medida en que lo permita la ley aplicable, :operator no responderá por daños indirectos, pérdida de beneficios, pérdida de datos ni perjuicios derivados de decisiones tomadas a partir de la información mostrada.',
                    'Nada en estas condiciones limita responsabilidades que no puedan excluirse legalmente.',
                ],
            ],

            'termination' => [
                'heading' => '8. Cancelación',
                'body' => [
                    'Puedes eliminar tu cuenta cuando quieras desde los ajustes de perfil. Al hacerlo se borran tus movimientos, categorías, metas y los ficheros de tus comprobantes.',
                    'El borrado es inmediato y no se puede deshacer. Exporta lo que quieras conservar antes de eliminarla.',
                ],
            ],

            'changes' => [
                'heading' => '9. Cambios en estas condiciones',
                'body' => [
                    'Estas condiciones pueden actualizarse. La fecha de entrada en vigor que aparece arriba indica la versión publicada.',
                    'Si el cambio es sustancial, :operator procurará avisar con antelación razonable. Seguir usando el servicio después de un cambio implica aceptarlo.',
                ],
            ],

            'law' => [
                'heading' => '10. Ley aplicable y contacto',
                'body' => [
                    'Estas condiciones se rigen por la legislación de :jurisdiction.',
                    'Para cualquier duda sobre este documento escribe a :email.',
                ],
            ],

        ],
    ],

    'privacy' => [
        'title' => 'Política de datos',
        'subtitle' => 'Qué datos guarda esta instancia, para qué, y qué puedes hacer con ellos.',
        'updated' => 'En vigor desde el :date',
        'intro' => 'Esta política explica cómo se tratan tus datos personales en esta instancia de Spentz Trackr. El responsable del tratamiento es :operator, no los autores del software: cada instancia se despliega y administra por separado.',
        'sections' => [

            'what' => [
                'heading' => '1. Qué datos se guardan',
                'body' => [
                    'Datos de cuenta: tu nombre, tu correo electrónico, la huella criptográfica (hash) de tu contraseña —nunca la contraseña en claro—, tu idioma preferido y, si las activas, la configuración de verificación en dos pasos y tus llaves de acceso.',
                    'Datos financieros que tú introduces: gastos e ingresos con su importe, moneda, fecha, descripción, notas, categoría, origen de pago, comisiones, la tasa de cambio aplicada y sus equivalentes en USD y USDT. También tus metas de ahorro, pagos recurrentes y presupuesto mensual.',
                    'Comprobantes: las imágenes que subas asociadas a un movimiento.',
                    'Datos técnicos: una cookie de sesión para mantenerte identificado, y los registros del servidor, que pueden incluir tu dirección IP, la fecha y la página solicitada.',
                ],
            ],

            'what_not' => [
                'heading' => '2. Qué NO se hace con tus datos',
                'body' => [
                    'No se venden, alquilan ni ceden a terceros con fines comerciales.',
                    'No hay publicidad, ni rastreadores de terceros, ni herramientas de analítica externas.',
                    'No se elaboran perfiles ni se toman decisiones automatizadas sobre ti.',
                    'No se accede a tus cuentas bancarias: todos los movimientos los escribes tú.',
                ],
            ],

            'why' => [
                'heading' => '3. Para qué se usan',
                'body' => [
                    'Para prestarte el servicio: identificarte, guardar tus movimientos y mostrarte tus informes.',
                    'Para la seguridad de la cuenta: detectar y limitar intentos de acceso indebidos, y permitirte cerrar sesiones.',
                    'Para el funcionamiento técnico: diagnosticar errores y mantener la instancia en marcha.',
                    'La base legal es la ejecución del servicio que solicitas al registrarte y el interés legítimo en mantenerlo seguro.',
                ],
            ],

            'third_parties' => [
                'heading' => '4. Terceros',
                'body' => [
                    'La aplicación hace una única llamada saliente: consulta las tasas de cambio publicadas en ve.dolarapi.com. Esa petición no incluye ningún dato tuyo — solo pregunta el precio del dólar.',
                    'El resto depende de cómo :operator haya desplegado la instancia: el proveedor de alojamiento y, si está configurado, el servicio de correo que envía los mensajes de verificación y recuperación de contraseña tratarán datos por cuenta del operador.',
                ],
            ],

            'security' => [
                'heading' => '5. Cómo se protegen',
                'body' => [
                    'Las contraseñas se guardan con bcrypt; nadie, ni el administrador, puede leerlas.',
                    'Cada cuenta solo puede ver sus propios datos: las consultas están limitadas por usuario y comprobadas por políticas de acceso.',
                    'Los comprobantes se guardan fuera del directorio público del servidor y solo se sirven tras comprobar que quien los pide es el dueño del movimiento.',
                    'Al cambiar la contraseña se cierran las demás sesiones y se revocan los tokens de API.',
                    'Ningún sistema es invulnerable. Usa una contraseña única y activa la verificación en dos pasos.',
                ],
            ],

            'retention' => [
                'heading' => '6. Cuánto tiempo se conservan',
                'body' => [
                    'Tus datos se conservan mientras la cuenta exista.',
                    'Al eliminar la cuenta se borran tus movimientos, categorías, orígenes, metas, pagos recurrentes y los ficheros de tus comprobantes. La operación es inmediata e irreversible.',
                    'Los tokens de API caducan a los 90 días. Los registros del servidor se conservan el tiempo que :operator haya configurado en su alojamiento.',
                ],
            ],

            'rights' => [
                'heading' => '7. Tus derechos',
                'body' => [
                    'Acceso y portabilidad: puedes consultar todos tus datos en la aplicación y exportar tus movimientos en CSV desde la sección de informes.',
                    'Rectificación: puedes editar o borrar cualquier movimiento, y cambiar tu nombre y tu correo desde los ajustes de perfil.',
                    'Supresión: puedes eliminar tu cuenta y todo su contenido desde los ajustes de perfil.',
                    'Para ejercer cualquier otro derecho, o si algo de lo anterior no te funciona, escribe a :email.',
                ],
            ],

            'cookies' => [
                'heading' => '8. Cookies',
                'body' => [
                    'Se usa una cookie de sesión, imprescindible para mantenerte identificado tras iniciar sesión, y una cookie técnica que recuerda si prefieres el tema claro u oscuro.',
                    'No hay cookies de publicidad ni de seguimiento, por lo que no se solicita consentimiento para ellas.',
                ],
            ],

            'minors' => [
                'heading' => '9. Menores',
                'body' => [
                    'El servicio no está dirigido a menores de edad. Si :operator detecta una cuenta de un menor sin autorización de sus responsables legales, la eliminará.',
                ],
            ],

            'changes' => [
                'heading' => '10. Cambios y contacto',
                'body' => [
                    'Esta política puede actualizarse; la fecha de entrada en vigor indica la versión publicada.',
                    'Para cualquier duda sobre tus datos escribe a :email.',
                ],
            ],

        ],
    ],

];
