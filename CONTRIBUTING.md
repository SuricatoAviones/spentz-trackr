# Contribuir a Spentz Trackr

Gracias por pasarte. Este documento cuenta lo que el código no dice solo.

**El proyecto se desarrolla en español**: documentación, comentarios, mensajes de commit y
esta guía. La aplicación en cambio es bilingüe (ES/EN) y debe seguir siéndolo. Si te resulta
más cómodo escribir en inglés en un issue, adelante — nadie te lo va a tener en cuenta.

## Lo primero: las reglas del repositorio

Antes de tocar nada, abre **`.ai/rules/index.md`**. Es un mapa de rutas a ficheros de reglas,
y cada regla documenta una decisión ya tomada o una trampa en la que alguien ya cayó.

Están escritas para asistentes de IA, pero se leen igual de bien: cuentan **por qué** las
cosas están como están. Casi todas nacieron de un fallo real en producción. Ejemplos:

- Por qué las páginas consumen un *presenter* y nunca el modelo de Eloquent (saltárselo deja
  la pantalla en blanco, con respuesta 200 y sin error en los logs).
- Por qué los acentos de color llevan pareja `text-emerald-600 dark:text-emerald-400`.
- Por qué Vite tiene que publicar en IPv4 (la gramática de CSP no admite IPv6 y la app
  arranca en blanco).

Si tu cambio contradice una regla, no pasa nada: cámbiala y explica en el PR por qué ya no
aplica. Lo que no vale es ignorarla en silencio.

`docs/04-arquitectura.md` tiene los ADRs, con las decisiones de fondo y su razonamiento.

## Poner el proyecto en marcha

```bash
composer setup      # instala, crea .env, genera la clave, migra y compila
composer run dev    # servidor + cola + vite, todo a la vez
```

Necesitas PHP 8.4+, Node 22+ y Composer. Con eso arranca en SQLite sin configurar nada más.

Un administrador para entrar al panel:

```bash
php artisan admin:create   # lee ADMIN_* del .env y REESCRIBE la clave en cada ejecución
```

## Antes de abrir un PR

```bash
composer run ci:check
```

Tiene que quedar en verde. Corre eslint, prettier, TypeScript, PHPStan (nivel 7), Pint y las
dos baterías de tests. Es exactamente lo que corre el CI, así que no habrá sorpresas.

Si te quedas sin memoria, está documentado en `CLAUDE.md`: `php artisan test` lanza un
subproceso que ignora `-d memory_limit`. Usa `php -d memory_limit=2G vendor/bin/pest`.

## Tests

Hay dos capas y cubren cosas distintas:

| Capa | Dónde | Qué atrapa |
|---|---|---|
| PHP (Pest) | `tests/` | Lógica de dominio, autorización, forma de los payloads |
| React (Vitest) | `resources/js/test/` | Que las páginas **pinten algo** con la forma real de los props |

La segunda existe por una razón concreta: las pruebas de Inertia en PHP comprueban props,
**nunca renderizan React**. Dos veces se coló una pantalla en blanco con respuesta 200 y sin
un solo error en los logs del servidor. Si tocas una página o un presenter, añade o ajusta su
test de render.

Los fixtures de `resources/js/test/fixtures.ts` imitan la salida exacta de los presenters de
PHP. Si cambias un presenter, cambia el fixture: si te olvidas, una de las dos capas se pone
roja, que es justo lo que se busca.

## Qué mirar según lo que toques

**Dinero.** Cada transacción congela su tasa al escribirse y los informes **nunca**
recalculan con la tasa de hoy. Si tu cambio recalcula algo histórico, casi seguro está mal.

**Idiomas.** `resources/js/i18n/es.json` y `en.json` deben tener exactamente las mismas
claves — hay un test que lo comprueba. Nunca uses `:` en una clave del frontend: i18next lo
lee como separador de espacio de nombres y el texto sale crudo. Siempre con puntos.

**Rutas.** Tras cambiar `routes/`, corre `php artisan wayfinder:generate --with-form` e
importa siempre desde el módulo agrupado (`@/routes/expenses`), nunca desde `@/routes`.

**Base de datos.** Nada de SQL específico de un motor: se despliega sobre SQLite, MySQL y
PostgreSQL. `YEAR()` no existe en SQLite.

**Copias de seguridad.** Si añades una tabla con datos de usuario, añádela a
`App\Support\BackupSchema`. Una copia que pierde datos en silencio es peor que no tenerla.

## Commits y ramas

Commits en español con prefijo de tipo: `feat(ámbito):`, `fix(ámbito):`, `docs:`, `chore:`.
El cuerpo cuenta **por qué**, no qué — el diff ya dice qué.

Rama desde `main`, un tema por PR. Un PR que arregla tres cosas distintas es tres PRs.

## Publicar una versión

1. Sube `'version'` en `config/app.php`.
2. Mueve lo que haya en `[No publicado]` del `CHANGELOG.md` a una sección con la versión y
   la fecha. Marca con **⚠ Acción requerida** todo lo que obligue al operador a hacer algo.
3. `git tag vX.Y.Z && git push --tags`.

El workflow de release comprueba que el tag y `config/app.php` coincidan, y publica las notas
tomadas del `CHANGELOG`. Si no cuadran, falla a propósito.

## Seguridad

**No abras un issue público por un fallo de seguridad.** Ver `SECURITY.md`.

## Ideas antes de código

Para algo grande, abre un issue primero. El proyecto tiene un alcance deliberadamente
acotado —gastos e ingresos personales multi-moneda, pensado para Venezuela— y es más
agradable discutir el encaje antes que después de que escribas trescientas líneas.
