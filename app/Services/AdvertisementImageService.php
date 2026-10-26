<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdvertisementImageService
{
    public const MAX_WIDTH = 1200;

    public const MAX_HEIGHT = 675;

    public function store(UploadedFile $image): string
    {
        $contents = file_get_contents($image->getRealPath());
        $source = $contents === false ? false : @imagecreatefromstring($contents);

        if ($source === false) {
            throw ValidationException::withMessages(['banner_image' => 'No se pudo procesar la imagen. Usa un archivo JPG, PNG o WebP válido.']);
        }

        try {
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            $targetRatio = self::MAX_WIDTH / self::MAX_HEIGHT;
            $sourceRatio = $sourceWidth / $sourceHeight;

            if ($sourceRatio > $targetRatio) {
                $cropHeight = $sourceHeight;
                $cropWidth = (int) round($sourceHeight * $targetRatio);
                $sourceX = (int) floor(($sourceWidth - $cropWidth) / 2);
                $sourceY = 0;
            } else {
                $cropWidth = $sourceWidth;
                $cropHeight = (int) round($sourceWidth / $targetRatio);
                $sourceX = 0;
                $sourceY = (int) floor(($sourceHeight - $cropHeight) / 2);
            }

            $targetWidth = min(self::MAX_WIDTH, $cropWidth);
            $targetHeight = (int) round($targetWidth / $targetRatio);
            if ($targetHeight > $cropHeight) {
                $targetHeight = min(self::MAX_HEIGHT, $cropHeight);
                $targetWidth = (int) round($targetHeight * $targetRatio);
            }
            $target = imagecreatetruecolor($targetWidth, $targetHeight);

            if ($target === false) {
                throw new \RuntimeException('No se pudo preparar la imagen optimizada.');
            }

            try {
                imagealphablending($target, false);
                $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
                imagefill($target, 0, 0, $transparent);
                imagealphablending($target, true);
                imagesavealpha($target, true);
                imagecopyresampled($target, $source, 0, 0, $sourceX, $sourceY, $targetWidth, $targetHeight, $cropWidth, $cropHeight);
                ob_start();
                $encoded = imagewebp($target, null, 82);
                $optimized = ob_get_clean();

                if (! $encoded || ! is_string($optimized) || $optimized === '') {
                    throw new \RuntimeException('No se pudo codificar la imagen optimizada.');
                }

                $path = 'advertisements/'.Str::uuid().'.webp';
                Storage::disk('public')->put($path, $optimized);

                return $path;
            } finally {
                imagedestroy($target);
            }
        } catch (\Throwable $exception) {
            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            report($exception);
            throw ValidationException::withMessages(['banner_image' => 'No se pudo optimizar la imagen. Intenta con otro archivo JPG, PNG o WebP.']);
        } finally {
            imagedestroy($source);
        }
    }
}
