<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Punto único para los comprobantes. Antes vivían en el disco `public`, que se
 * sirve directo desde `public/storage` sin pasar por el middleware de Laravel:
 * cualquiera con la URL veía las facturas de cualquier usuario. Ahora viven en
 * un disco privado y solo salen por rutas autorizadas.
 */
final class ReceiptStorage
{
    public const DISK = 'receipts';

    /** Subcarpeta dentro del disco; se conserva para no reescribir las rutas ya guardadas. */
    public const FOLDER = 'receipts';

    public static function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    /**
     * Guarda el fichero subido y devuelve la ruta relativa que va a la BD.
     */
    public static function store(UploadedFile $file): string
    {
        return (string) $file->store(self::FOLDER, self::DISK);
    }

    public static function delete(string $path): void
    {
        self::disk()->delete($path);
    }

    /**
     * Descarga en línea del comprobante. `inline` (no `attachment`) porque la
     * UI lo muestra en un visor; el nombre original es texto del usuario, y
     * Symfony lo sanea al construir el Content-Disposition.
     */
    public static function response(string $path, string $originalName): StreamedResponse
    {
        abort_unless(self::disk()->exists($path), 404);

        return self::disk()->response($path, $originalName);
    }
}
