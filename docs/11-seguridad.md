# 11 — Seguridad y documentos legales

Estado de los controles de seguridad de la app y de las dos páginas legales públicas.
Salió de una auditoría completa del código, la configuración y el despliegue; cada
apartado dice **qué se arregló, por qué era explotable y qué test lo sostiene**.

Las invariantes que no deben deshacerse están además en `.ai/rules/general.md` y
`.ai/rules/controllers.md`, que es lo que se lee antes de editar.

---

## 1. Proxies y host de confianza

`TRUSTED_PROXIES` valía `*` por defecto, lo que convertía a **todo cliente** en un proxy
de confianza. Dos consecuencias reales, ambas verificadas antes de arreglarlas:

- `X-Forwarded-Host` reescribía la raíz de `url()` y `route()`. Como el correo de
  recuperación de contraseña se construye con `route()`, un atacante pedía el reset de
  una víctima, la víctima recibía un correo legítimo y el enlace apuntaba al host del
  atacante: **toma de cuenta**.
- `X-Forwarded-For` falsificaba `$request->ip()`. El limitador de Fortify indexa por
  `email|IP` y `api.auth` por IP, así que rotando la cabecera se conseguía **fuerza bruta
  sin límite**.

**Ahora:** `bootstrap/app.php` no confía en ningún proxy salvo que `TRUSTED_PROXIES` lo
nombre; `trustHosts` está fijado al host de `APP_URL`; y en producción
`URL::useOrigin(APP_URL)` clava la raíz de todas las URLs generadas.

> **Al desplegar detrás de un proxy hay que rellenar `TRUSTED_PROXIES`** con su red (en
> docker compose, `172.16.0.0/12`). Si se deja vacío estando detrás de Traefik/nginx,
> `$request->ip()` será siempre la del proxy y **todos los usuarios compartirán el mismo
> cupo de intentos de login**. Falla cerrado, no abierto, pero conviene ajustarlo.

Tests: `tests/Feature/ProxyTrustTest.php`.

## 2. Comprobantes privados

Los recibos se guardaban en el disco `public`, que el servidor web sirve directamente a
través del symlink `public/storage`, **fuera del middleware de Laravel**: cualquiera con
la URL veía la factura de cualquier usuario. La única protección era que el nombre del
fichero fuese un hash aleatorio, o sea, seguridad por oscuridad.

**Ahora:** disco `receipts` (privado, `storage/app/private`), un único punto de acceso en
`App\Support\ReceiptStorage`, y dos rutas con política:

| Ruta | Comprobación |
|---|---|
| `expenses.receipts.show` | `authorize('view', $expense)` + el recibo debe pertenecer a ese gasto |
| `incomes.receipts.show` | `authorize('view', $income)` + el recibo debe pertenecer a ese ingreso |

Los presenters publican esa ruta en vez de `Storage::url()`. La migración
`2026_09_13_120000_move_receipts_to_private_disk` mueve los ficheros de instalaciones ya
desplegadas (comprueba el tamaño antes de borrar el original).

**No añadir `url` ni `serve` al disco `receipts`**: reintroduce el agujero.

Tests: `tests/Feature/ReceiptAccessTest.php`.

## 3. Borrado de cuenta

Las filas caen por `cascadeOnDelete`, pero la cascada de la base de datos no sabe nada del
disco: los ficheros de comprobantes quedaban huérfanos y (con el punto 2) accesibles para
siempre. Un hook `deleting` en `User` los borra, así que vale igual para "eliminar mi
cuenta" y para el borrado desde el panel admin.

## 4. Cabeceras de seguridad

No se mandaba ninguna. `App\Http\Middleware\SecurityHeaders` va el primero del grupo `web`
y añade CSP, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy` y
HSTS (solo sobre HTTPS).

**Limitación conocida:** la CSP lleva `'unsafe-inline'` y `'unsafe-eval'` en `script-src`
porque Inertia inyecta el payload de página en un atributo y Vite necesita `eval` en
desarrollo. Endurecerlo exige nonces en `resources/views/app.blade.php` y es un trabajo
aparte. Lo que cierra el clickjacking (`frame-ancestors 'none'`) y la inyección de `<base>`
sí está activo.

**El origen del dev server de Vite se lee de `public/hot`, no se adivina**, y Vite debe
publicar en IPv4. Esto costó dos intentos, así que conviene entender el porqué:

1. La primera versión permitía una lista fija de `localhost`/`127.0.0.1`. No servía: con el
   `localhost` por defecto, Node (≥17) resuelve antes `::1` y Vite publicaba en
   `http://[::1]:5173`, que esa lista no cubre.
2. Leer el origen de `public/hot` tampoco bastó. **La gramática de CSP no admite literales
   IPv6**: un `host-source` solo acepta letras, dígitos y guiones, así que `http://[::1]:5173`
   no hace match por mucho que se escriba en la directiva. El navegador seguía bloqueando
   `@vite/client`, `app.tsx`, `app.css` y las fuentes del directivo `@fonts` — con el origen
   literalmente presente en la política.

La solución de raíz está en `vite.config.ts`: `server.host = '127.0.0.1'`, que sí es
expresable. `SecurityHeaders` lee el origen de `public/hot` (la URL exacta que usará el
navegador) y deriva el `ws://` del HMR, que solo se añade a `connect-src`. Como red, si el
origen llegara con corchetes **no se manda cabecera CSP**: una política que no puede nombrar
ese origen no protege de nada y solo deja la app en blanco en desarrollo.

En producción no hay fichero `hot` y la política vuelve a ser estricta (`'self'` y poco más).

Si algún día se sirven fuentes o imágenes desde un dominio externo, hay que añadirlo a
`font-src`/`img-src` explícitamente: `default-src 'self'` no lo hereda hacia fuera.

Tests: `tests/Feature/SecurityHeadersTest.php`.

## 5. Revocación de acceso al cambiar contraseña

Cambiar la clave no servía de nada frente a alguien ya dentro: su sesión y sus tokens de
API seguían valiendo. `App\Support\AccountAccess::revoke()` borra los tokens Sanctum y las
filas de sesión del usuario. Se llama en el cambio propio (conservando la sesión actual,
que además se regenera) y en el reset que hace un admin (todas).

Tests: `tests/Feature/PasswordRevokesAccessTest.php`.

## 6. Interruptores de instancia: registro y API REST

Dos interruptores en **Panel admin → Sistema**, sin tocar el servidor:

| Interruptor | Apagado devuelve | Alcance |
|---|---|---|
| Registro de usuarios | **404** en `/register` y en `POST /api/v1/auth/register` | Una instancia privada no anuncia que tiene una puerta cerrada |
| API REST | **503** en todo `/api/v1` + oculta la documentación | Quien integra necesita distinguir "apagado" de "ruta mal escrita" |

`config/features.php` (que lee `.env`) fija el **valor de arranque**; lo que un admin cambie
se guarda en `app_settings` y **manda a partir de ahí**. Se consulta siempre por
`App\Support\Features`, nunca leyendo `config()` a pelo, o el interruptor del panel no
tendría efecto.

Apagar la API **no revoca los tokens**: es reversible, y al reencenderla las integraciones
siguen funcionando. Para cortar accesos de verdad está el reset de contraseña, que sí revoca
(punto 5). Cerrar el registro no impide que un admin siga dando de alta usuarios desde el
panel, y el login deja de ofrecer el enlace de "crear cuenta" para no mandar a un 404.

El gate del registro **no puede vivir en `config/fortify.php`**: ese array se construye al
cargar la configuración —sin base de datos y horneado por `config:cache`—, así que un
interruptor en caliente nunca surtiría efecto. La feature de Fortify queda siempre registrada
y el corte lo hace `EnsureRegistrationEnabled`.

Ambos cambios quedan en la auditoría del panel.

Tests: `tests/Feature/Admin/{ApiToggle,RegistrationToggleAdmin}Test.php`.

## 7. Valores por defecto del despliegue

`.env.docker` traía credenciales que **funcionaban**: `ADMIN_PASSWORD=CambiaEstaClave123`,
`DB_PASSWORD=secret`, `MYSQL_ROOT_PASSWORD=rootsecret`. Un `docker compose up` sin editar
dejaba un admin con contraseña conocida. Ahora vienen vacíos, con instrucciones. Las
variables de seguridad están documentadas en `.env.example` y en las dos guías de
despliegue.

## 8. Política de contraseñas siempre activa

`Password::defaults()` devolvía `null` fuera de producción, así que un `APP_ENV=local`
olvidado —fácil con un `.env` escrito a mano— aceptaba contraseñas de un carácter.
Ahora aplica siempre: 12 caracteres, mayúsculas y minúsculas, número y símbolo. Fuera de
producción solo se omite `uncompromised()`, que necesita red.

## 9. Guardas del rol de administrador

`destroy` y `suspend` ya se protegían contra uno mismo, pero nada impedía **quitarse el rol**
ni **borrar o suspender al último administrador activo**. Sin instalador que rescate la
instancia (ADR-008), eso deja el panel inaccesible de forma irreversible. Las tres puertas
comparten ahora la misma guarda.

Tests: `tests/Feature/Admin/AdminRoleGuardsTest.php`.

## 10. Otros endurecimientos

| Qué | Antes | Ahora |
|---|---|---|
| `POST /exchange-rate/sync` y `POST /api/v1/rates/sync` | sin techo: un usuario podía usar la instancia como amplificador contra ve.dolarapi.com | `throttle:rates.sync` — 6/min por usuario |
| Login de la API | solo se calculaba bcrypt si el email existía, y el tiempo delataba qué correos están registrados | se compara siempre, contra un hash señuelo si no hay usuario |
| Props compartidas de Inertia | se publicaba el modelo `User` entero, así que cualquier columna nueva se filtraba al cliente | lista explícita de 9 campos |
| Backup del admin | omitía ingresos, recibos de ingresos, items, metas, aportes y pagos recurrentes | las 12 tablas; sigue sin exportar hashes ni secretos 2FA |
| `storage/app.key` y `.env` en Docker | permisos a merced del umask | `umask 077` + `chmod 600` |

Tests: `tests/Feature/HardeningTest.php`.

---

## Lo que ya estaba bien

Conviene no romperlo:

- **Aislamiento entre usuarios.** Todas las claves foráneas se validan con
  `Rule::exists(...)->where('user_id', $this->user()->id)`, así que no se puede colgar un
  gasto de la categoría de otro. Las políticas son consistentes y hay `authorize()` en cada
  recurso propio.
- `is_admin` fuera de `$fillable` (solo `forceFill`), `password` con cast `hashed`, secretos
  2FA y `remember_token` en `#[Hidden]`.
- **Inyección de fórmulas CSV** tratada en `CsvExporter::cell()`.
- Subidas limitadas a jpg/png/webp: sin SVG, no hay XSS almacenado por ahí.
- Cero SQL crudo con entrada de usuario; todos los `selectRaw` son literales.
- El service worker nunca cachea respuestas autenticadas.
- CORS cerrado por defecto y sin credenciales; tokens Sanctum con caducidad de 90 días;
  suspensión aplicada en web y API; acciones de admin auditadas con el id del actor.

## Auditoría de dependencias

`composer audit` y `npm audit --omit=dev`: **0 vulnerabilidades**. Conviene repetirlo antes
de cada despliegue.

## Hueco conocido

Ningún test renderiza React: las pruebas de Inertia solo comprueban props del payload. Una
forma equivocada o un crash de render pasan la suite en verde y solo se ven en el navegador
(ver la regla del presenter en `.ai/rules/controllers.md`). Cerrarlo del todo requiere
montar vitest + jsdom con unos smoke tests de render; está pendiente.

---

# Documentos legales

## Rutas y estructura

| Ruta | URL | Documento |
|---|---|---|
| `legal.terms` | `/terminos` | Términos y condiciones |
| `legal.privacy` | `/privacidad` | Política de datos |

Son **públicas a propósito**: hay que poder leerlas antes de crear una cuenta. Se enlazan
desde el pie de la landing y desde el layout de autenticación (login, registro y
recuperación).

| Pieza | Dónde |
|---|---|
| Texto | `lang/es/legal.php` y `lang/en/legal.php` |
| Identidad del operador | `config/legal.php` |
| Controlador | `app/Http/Controllers/LegalController.php` |
| Página React (compartida) | `resources/js/pages/legal/document.tsx` |
| Etiquetas de UI | clave `legal.*` en `resources/js/i18n/{es,en}.json` |

El texto va en `lang/` y no en los JSON del frontend porque es prosa larga que no tiene por
qué viajar en el bundle de JavaScript, y porque así se actualiza lo legal sin tocar React.
La página solo maqueta la estructura `{title, subtitle, updated, intro, sections[]}`.

## Configuración obligatoria del operador

Spentz Trackr es auto-hospedable: **el responsable del tratamiento de datos es quien levanta
cada instancia, no quien escribe el software**. Por eso la identidad va en el `.env`:

```env
LEGAL_OPERATOR="Nombre de la persona o empresa que opera la instancia"
LEGAL_CONTACT_EMAIL=datos@tudominio.com
LEGAL_JURISDICTION="la República Bolivariana de Venezuela"
LEGAL_EFFECTIVE_DATE=2026-09-13
```

Si no se definen, se usan valores genéricos (`el operador de esta instancia`, y el
`ADMIN_EMAIL` como contacto). **Rellenarlos antes de abrir el registro al público.**

`LEGAL_EFFECTIVE_DATE` es lo que permite a un usuario saber si lo que aceptó sigue siendo lo
que está publicado: hay que moverla cada vez que se cambie el texto.

Los marcadores `:operator`, `:email`, `:jurisdiction` y `:date` se sustituyen en
`LegalController`, porque `Lang::get()` sobre un array anidado devuelve el árbol sin
resolver. Un test comprueba que nunca se muestran en crudo.

## Alcance del texto

Los documentos describen **lo que la aplicación hace de verdad**, no promesas genéricas:
qué campos se guardan, que la única llamada saliente es a `ve.dolarapi.com` y no lleva datos
personales, que las contraseñas van con bcrypt, que los comprobantes viven fuera del
directorio público, que cambiar la contraseña cierra las demás sesiones, y que borrar la
cuenta borra también los ficheros.

> **No son un dictamen jurídico.** Son un texto base honesto y ajustado a la aplicación,
> redactado para que un operador tenga algo publicable desde el primer día. Antes de abrir
> una instancia al público conviene que lo revise alguien con criterio legal en la
> jurisdicción que corresponda, sobre todo los apartados de responsabilidad, ley aplicable y
> derechos de los usuarios.

## Añadir o cambiar una cláusula

1. Editar `lang/es/legal.php` **y** `lang/en/legal.php` con las mismas claves.
2. Mover `LEGAL_EFFECTIVE_DATE`.
3. Correr `php artisan test --filter=LegalPages`.

El test `es and en legal files keep identical key sets` falla si una sección existe solo en
un idioma — sin él, un lector en inglés se quedaría sin una cláusula y nadie se enteraría.

Tests: `tests/Feature/LegalPagesTest.php`.
