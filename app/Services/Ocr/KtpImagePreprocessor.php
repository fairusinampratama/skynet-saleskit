<?php

namespace App\Services\Ocr;

class KtpImagePreprocessor
{
    /**
     * @return array<string, string>
     */
    public function variants(string $path, string $workDirectory): array
    {
        $variants = ['original' => $path];

        if (! extension_loaded('gd') || ! is_file($path)) {
            return $variants;
        }

        if (! is_dir($workDirectory)) {
            mkdir($workDirectory, 0775, true);
        }

        $normalized = $workDirectory.'/normalized.jpg';

        if ($this->normalize($path, $normalized)) {
            $variants['normalized'] = $normalized;
        }

        foreach ([
            'upscaled' => ['upscale'],
            'high_contrast' => ['upscale', 'grayscale', 'contrast'],
            'threshold' => ['upscale', 'grayscale', 'contrast', 'threshold'],
            'sharpened_threshold' => ['upscale', 'grayscale', 'contrast', 'sharpen', 'threshold'],
        ] as $variant => $operations) {
            $target = $workDirectory.'/'.$variant.'.jpg';

            if ($this->process($path, $target, $operations)) {
                $variants[$variant] = $target;
            }
        }

        return $variants;
    }

    private function normalize(string $source, string $target): bool
    {
        $bytes = file_get_contents($source);

        if ($bytes === false) {
            return false;
        }

        $image = @imagecreatefromstring($bytes);

        if (! $image) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = max(1, 1800 / max($width, 1));
        $targetWidth = (int) round($width * $scale);
        $targetHeight = (int) round($height * $scale);

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagefilter($resized, IMG_FILTER_GRAYSCALE);
        imagefilter($resized, IMG_FILTER_CONTRAST, -20);
        imagefilter($resized, IMG_FILTER_SMOOTH, -2);

        $written = imagejpeg($resized, $target, 92);

        imagedestroy($image);
        imagedestroy($resized);

        return $written;
    }

    /**
     * @param  array<int, string>  $operations
     */
    private function process(string $source, string $target, array $operations): bool
    {
        $bytes = file_get_contents($source);

        if ($bytes === false) {
            return false;
        }

        $image = @imagecreatefromstring($bytes);

        if (! $image) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = in_array('upscale', $operations, true) ? max(1, 2400 / max($width, 1)) : 1;
        $targetWidth = (int) round($width * $scale);
        $targetHeight = (int) round($height * $scale);

        $processed = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled($processed, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        if (in_array('grayscale', $operations, true)) {
            imagefilter($processed, IMG_FILTER_GRAYSCALE);
        }

        if (in_array('contrast', $operations, true)) {
            imagefilter($processed, IMG_FILTER_CONTRAST, -35);
        }

        if (in_array('sharpen', $operations, true)) {
            imageconvolution($processed, [
                [-1, -1, -1],
                [-1, 16, -1],
                [-1, -1, -1],
            ], 8, 0);
        }

        if (in_array('threshold', $operations, true)) {
            imagefilter($processed, IMG_FILTER_GRAYSCALE);
            imagefilter($processed, IMG_FILTER_CONTRAST, -45);
            imagefilter($processed, IMG_FILTER_BRIGHTNESS, 10);
            $this->threshold($processed);
        }

        $written = imagejpeg($processed, $target, 94);

        imagedestroy($image);
        imagedestroy($processed);

        return $written;
    }

    private function threshold(\GdImage $image, int $threshold = 150): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $black = imagecolorallocate($image, 0, 0, 0);
        $white = imagecolorallocate($image, 255, 255, 255);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $red = ($rgb >> 16) & 0xFF;
                $green = ($rgb >> 8) & 0xFF;
                $blue = $rgb & 0xFF;
                $gray = (int) round(($red + $green + $blue) / 3);

                imagesetpixel($image, $x, $y, $gray >= $threshold ? $white : $black);
            }
        }
    }
}
