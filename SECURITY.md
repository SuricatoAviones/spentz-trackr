# Política de seguridad

Spentz Trackr guarda datos financieros personales: cuánto gana alguien, en qué lo gasta y
fotos de sus comprobantes. Un fallo aquí no es una molestia, es una filtración.

## Cómo reportar un fallo

**No abras un issue público.** Un issue con un fallo explotable deja a todas las instancias
desplegadas expuestas mientras se arregla.

Usa el canal privado de GitHub: en la pestaña **Security** del repositorio,
**Report a vulnerability**. Llega solo a quien mantiene el proyecto.

Si prefieres el correo, escribe a la dirección de contacto del `README`. Pon
`[seguridad]` en el asunto.

### Qué incluir

Con esto se puede reproducir y arreglar rápido:

- Qué falla y qué consigue alguien aprovechándolo.
- Pasos para reproducirlo (una petición `curl` vale más que un párrafo).
- Versión afectada (`config/app.php` o el pie del panel de sistema) y cómo está desplegada:
  Docker, cPanel, VPS.
- Si hay proxy inverso delante y qué valor tiene `TRUSTED_PROXIES`.

No hace falta que sea un informe formal. Una descripción clara basta.

### Qué esperar

Es un proyecto mantenido por poca gente y sin acuerdos de nivel de servicio, así que no
prometo plazos que no pueda cumplir. Lo que sí hago:

- Confirmar que lo he recibido.
- Decirte si lo considero un fallo de seguridad y por qué, aunque la respuesta sea que no.
- Avisarte cuando esté corregido, y darte crédito en el `CHANGELOG` si quieres.

Te pido a cambio un margen razonable antes de hacerlo público.

## Versiones con soporte

Solo la última versión publicada. No hay ramas de mantenimiento: si usas un tag antiguo,
actualiza antes de reportar.

## Qué cuenta y qué no

**Sí:** acceder a datos de otra cuenta, saltarse la autenticación o los límites de intentos,
ejecutar código, XSS o CSRF, escalar a administrador, filtrar comprobantes o secretos.

**No, o no directamente:**

- Fallos que necesitan que el atacante ya sea administrador de la instancia. El panel cruza
  los datos de todos los usuarios **por diseño**.
- Cosas que dependen de cómo esté desplegada la instancia: `APP_DEBUG=true` en producción,
  la base de datos abierta a internet, ausencia de HTTPS. Eso es configuración del operador
  y está documentado en `docs/11-seguridad.md`.
- Los comprobantes de una cuenta **eliminada** siguen borrándose; si encuentras un caso en
  el que no, sí es un fallo.
- Informes generados con un escáner automático, sin comprobar que el fallo es real.

## Lo que ya se sabe

`docs/11-seguridad.md` documenta los controles vigentes, las decisiones detrás de cada uno y
los huecos conocidos. Antes de reportar, mira si ya está ahí; si está descrito como
limitación aceptada y no estás de acuerdo con el razonamiento, cuéntamelo igualmente — ese
argumento también es útil.

## Para quien opera una instancia

Hay tres ajustes que el código no puede decidir por ti y que importan:

- `TRUSTED_PROXIES` con la red de tu proxy inverso, nunca `*`.
- `APP_DEBUG=false` y `APP_ENV=production`.
- `SESSION_SECURE_COOKIE=true` en cuanto tengas HTTPS.

Están explicados en `docs/11-seguridad.md` y en las guías de despliegue.
