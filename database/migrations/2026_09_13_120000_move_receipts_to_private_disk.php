<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Los comprobantes vivían en `storage/app/public/receipts`, que el servidor web
 * sirve directo por el symlink `public/storage`: cualquiera con la URL veía la
 * factura de cualquier usuario, sin pasar por Laravel. Se mudan al disco
 * privado, desde donde solo salen por las rutas `*.receipts.show`.
 *
 * La ruta guardada en la BD (`receipts/xxxx.jpg`) no cambia: solo cambia el
 * disco, así que no hay que tocar ninguna fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->move(from: 'public', to: 'receipts');
    }

    public function down(): void
    {
        $this->move(from: 'receipts', to: 'public');
    }

    private function move(string $from, string $to): void
    {
        $source = Storage::disk($from);
        $target = Storage::disk($to);

        foreach ($source->files('receipts') as $path) {
            if ($target->exists($path)) {
                $source->delete($path);

                continue;
            }

            $stream = $source->readStream($path);

            if ($stream === null) {
                continue;
            }

            $expectedSize = $source->size($path);

            $target->writeStream($path, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            // Solo se borra el original si la copia llegó entera: perder un
            // comprobante es peor que dejar un fichero de más.
            if ($target->size($path) === $expectedSize) {
                $source->delete($path);
            }
        }
    }
};
