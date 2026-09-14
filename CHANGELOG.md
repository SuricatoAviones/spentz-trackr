# Changelog

Todos los cambios reseñables de Spentz Trackr se anotan aquí.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y el
versionado es [SemVer](https://semver.org/lang/es/).

Para una instancia auto-hospedada, **la versión es lo que te permite saber qué estás
corriendo y qué cambia al actualizar**. `php artisan app:update` hace `git pull`: si sigues
`main` vas al día pero sin red; si te sitúas en un tag, sabes exactamente qué tienes.

## Qué significa cada número

- **MAYOR** — hay que hacer algo a mano para actualizar (una variable nueva obligatoria en
  el `.env`, un cambio que rompe la API, una migración que no se puede deshacer).
- **MENOR** — funcionalidad nueva; actualizar es `app:update` y nada más.
- **PARCHE** — correcciones y seguridad, sin cambios de comportamiento.

Cualquier entrada que exija una acción del operador va marcada con **⚠ Acción requerida**.

## [No publicado]

Nada todavía.

## [1.0.0] — 2026-09-13

Primera versión etiquetada. La aplicación llevaba tiempo funcionando; este tag fija un punto
conocido al que volver.

### Añadido

- **Tarjetas de crédito.** Banco, límite, moneda, ciclo de corte y pago, tasa de interés,
  cortes y abonos. El saldo es híbrido: el corte que teclea el usuario es la verdad y entre
  cortes se proyecta, siempre marcado como estimación. Los abonos no cuentan como gasto.
- **Términos y condiciones** (`/terminos`) y **política de datos** (`/privacidad`), públicas
  y en español e inglés. **⚠ Acción requerida:** rellena `LEGAL_OPERATOR`,
  `LEGAL_CONTACT_EMAIL` y `LEGAL_JURISDICTION` antes de abrir el registro al público.
- **Interruptores de instancia** en el panel de administración: encender o apagar la API
  REST y abrir o cerrar el registro de usuarios, sin tocar el servidor.
- **Restauración de copias**: `php artisan backup:restore fichero.json`. Las contraseñas no
  viajan en la copia, así que los usuarios restaurados deben recuperar la suya.
- Tests de render del front-end con Vitest, que atrapan las pantallas en blanco que las
  pruebas de PHP no pueden ver.

### Corregido

- Las páginas de detalle y edición de gastos e ingresos devolvían el modelo crudo en lugar
  del presenter, y quedaban **en blanco** sin error en el servidor.
- Tema claro: acentos sin contraste y dos textos blancos invisibles sobre fondo blanco.
- El favicon no se veía (el SVG referenciaba una imagen externa, que los navegadores no
  cargan) y los iconos `maskable` del PWA recortaban el nombre de la app.
- La copia de seguridad omitía ingresos, metas, aportes, pagos recurrentes y tarjetas.

### Seguridad

- **⚠ Acción requerida:** `TRUSTED_PROXIES` ya no vale `*` por defecto. Si despliegas detrás
  de un proxy inverso, indica su red; si no, `$request->ip()` será la del proxy y todos los
  usuarios compartirán el mismo cupo de intentos de acceso. Con `*`, cualquiera podía
  falsificar su IP y el host de los enlaces de recuperación de contraseña.
- **Los comprobantes dejan de ser públicos.** Estaban en un disco servido directamente por
  el servidor web: cualquiera con la URL veía la factura de cualquier usuario. Ahora salen
  por una ruta que comprueba el propietario. La migración mueve los ficheros existentes.
- Cambiar la contraseña revoca ahora las demás sesiones y los tokens de API.
- Cabeceras de seguridad (CSP, X-Frame-Options, HSTS sobre HTTPS) en todas las respuestas.
- La política de contraseñas se aplica siempre, no solo en producción.
- **⚠ Acción requerida:** `.env.docker` ya no trae contraseñas por defecto que funcionaban.
  Pon las tuyas antes de desplegar.
- Un administrador ya no puede quitarse el rol ni dejar la instancia sin ninguno activo.

[No publicado]: https://github.com/SuricatoAviones/spentz-trackr/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/SuricatoAviones/spentz-trackr/releases/tag/v1.0.0
