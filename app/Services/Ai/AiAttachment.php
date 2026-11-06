<?php

namespace App\Services\Ai;

use GdImage;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Imagen o PDF que acompaña a un mensaje, ya en base64.
 *
 * Las fotos de móvil pesan 4-8 MB y algunos proveedores rechazan imágenes de
 * más de 5 MB; además, cuantos más píxeles, más tokens se cobran al usuario.
 * Si GD está disponible se reducen a `ai.image_max_side`. Si no, o si falla, se
 * envía el original: es una optimización, nunca un requisito.
 */
final class AiAttachment
{
    public function __construct(
        public readonly string $mimeType,
        public readonly string $base64,
        public readonly string $name = 'document',
    ) {}

    public static function fromUploadedFile(UploadedFile $file): self
    {
        $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType());
        $contents = (string) file_get_contents($file->getRealPath());
        $name = $file->getClientOriginalName() !== '' ? $file->getClientOriginalName() : 'document';

        if (str_starts_with($mime, 'image/')) {
            [$contents, $mime] = self::downscale($contents, $mime);
        }

        return new self($mime, base64_encode($contents), $name);
    }

    public function isPdf(): bool
    {
        return $this->mimeType === 'application/pdf';
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    public function dataUrl(): string
    {
        return "data:{$this->mimeType};base64,{$this->base64}";
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function downscale(string $contents, string $mime): array
    {
        if (! extension_loaded('gd') || ! function_exists('imagecreatefromstring')) {
            return [$contents, $mime];
        }

        try {
            $info = @getimagesizefromstring($contents);
            $maxSide = max(256, (int) config('ai.image_max_side', 2000));

            if ($info === false) {
                return [$contents, $mime];
            }

            [$width, $height] = $info;

            if (max($width, $height) <= $maxSide && strlen($contents) <= 4 * 1024 * 1024) {
                return [$contents, $mime];
            }

            $source = @imagecreatefromstring($contents);

            if (! $source instanceof GdImage) {
                return [$contents, $mime];
            }

            $scale = min(1, $maxSide / max($width, $height));
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));

            $target = imagecreatetruecolor($targetWidth, $targetHeight);
            imagefill($target, 0, 0, (int) imagecolorallocate($target, 255, 255, 255));
            imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            ob_start();
            imagejpeg($target, null, 85);
            $resized = (string) ob_get_clean();

            imagedestroy($source);
            imagedestroy($target);

            return $resized !== '' ? [$resized, 'image/jpeg'] : [$contents, $mime];
        } catch (Throwable) {
            return [$contents, $mime];
        }
    }
}
