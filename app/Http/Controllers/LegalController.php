<?php

namespace App\Http\Controllers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Términos y política de datos. Públicos a propósito: alguien tiene que poder
 * leerlos ANTES de registrarse.
 *
 * El texto vive en lang/{es,en}/legal.php y la identidad del operador en
 * config/legal.php, porque la app es auto-hospedable y el responsable del
 * tratamiento cambia en cada despliegue.
 */
class LegalController extends Controller
{
    public function terms(): Response
    {
        return $this->render('terms');
    }

    public function privacy(): Response
    {
        return $this->render('privacy');
    }

    private function render(string $document): Response
    {
        /** @var array<string, mixed> $content */
        $content = Lang::get("legal.{$document}");

        return Inertia::render('legal/document', [
            'document' => $document,
            'content' => $this->fill($content),
        ]);
    }

    /**
     * `Lang::get()` sobre un array anidado devuelve el árbol sin sustituir los
     * marcadores, así que se recorre a mano.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function fill(array $content): array
    {
        $replacements = [
            ':operator' => (string) config('legal.operator'),
            ':email' => (string) config('legal.contact_email'),
            ':jurisdiction' => (string) config('legal.jurisdiction'),
            ':date' => $this->effectiveDate(),
        ];

        array_walk_recursive($content, function (&$value) use ($replacements): void {
            if (is_string($value)) {
                $value = strtr($value, $replacements);
            }
        });

        return $content;
    }

    private function effectiveDate(): string
    {
        $configured = (string) config('legal.effective_date');

        try {
            $date = new Carbon($configured);
        } catch (\Throwable) {
            return $configured;
        }

        // isoFormat('LL') respeta el idioma activo: "13 de septiembre de 2026"
        // en es, "September 13, 2026" en en. Un formato fijo con "de"
        // intercalado se rompería en inglés. El idioma de Carbon ya lo fija
        // `SetLocale` en cada petición, así que no hay que pasarlo aquí.
        return $date->isoFormat('LL');
    }
}
