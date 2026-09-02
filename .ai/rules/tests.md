---
paths:
  - 'tests/**'
---

# Tests

## Tests Inertia: build de Vite + import de Assert
Los tests que renderizan páginas Inertia fallan con "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" cuando el manifest de `public/build` está desactualizado. Ejecutar `npm run build` después de crear/renombrar páginas antes de correr `php artisan test`. En los tests con `assertInertia(fn (Assert $page) ...)` incluir siempre `use Inertia\Testing\AssertableInertia as Assert;` en el archivo.

## Tests de respuestas stream: streamedContent() y sin assertDownload
Storage::disk(...)->response() envía Content-Disposition inline, no attachment: en tests usar assertHeader('content-type', ...) + streamedContent(), NO assertDownload (falla buscando attachment). Los streams (CSV/JSON backup/receipts) no capturan contenido con assertSee; usar $response->streamedContent().

## Header Accept-Language por defecto en tests (Symfony)
Symfony inyecta `HTTP_ACCEPT_LANGUAGE: en-us,en;q=0.5` por defecto en todo request de test (vendor/symfony/http-foundation/Request.php). Cualquier lógica de detección de idioma del navegador en tests resuelve 'en' sin que se envíe header. La app es spanish-first: SetLocale resuelve usuario → sesión → APP_LOCALE (es) → navegador, así los tests existentes con flashes en español no se rompen.
