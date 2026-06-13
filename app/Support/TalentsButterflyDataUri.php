<?php

namespace App\Support;

class TalentsButterflyDataUri
{
    /**
     * Recorta a borboleta do logo Talents e retorna data URI clareada para decoração no PDF.
     */
    public static function get(): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $path = public_path('images/logo.png');
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $source = @imagecreatefrompng($path);
        if ($source === false) {
            return null;
        }

        imagesavealpha($source, true);

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width <= 0 || $height <= 0) {
            imagedestroy($source);

            return null;
        }

        // A borboleta ocupa aproximadamente o terço esquerdo do logo completo.
        $cropWidth = (int) round($width * 0.38);
        $cropHeight = $height;

        $cropped = imagecreatetruecolor($cropWidth, $cropHeight);
        if ($cropped === false) {
            imagedestroy($source);

            return null;
        }

        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        $transparent = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
        imagefill($cropped, 0, 0, $transparent);

        imagecopy($cropped, $source, 0, 0, 0, 0, $cropWidth, $cropHeight);
        imagedestroy($source);

        // Clarear para roxo suave decorativo (fundo transparente).
        for ($x = 0; $x < $cropWidth; $x++) {
            for ($y = 0; $y < $cropHeight; $y++) {
                $rgba = imagecolorat($cropped, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;

                if ($alpha >= 127) {
                    continue;
                }

                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                // Mistura com lavanda clara e reduz opacidade.
                $mix = 0.55;
                $newR = (int) round($r * (1 - $mix) + 180 * $mix);
                $newG = (int) round($g * (1 - $mix) + 150 * $mix);
                $newB = (int) round($b * (1 - $mix) + 200 * $mix);
                $newAlpha = min(127, (int) round($alpha + 40));

                $color = imagecolorallocatealpha($cropped, $newR, $newG, $newB, $newAlpha);
                imagesetpixel($cropped, $x, $y, $color);
            }
        }

        ob_start();
        $ok = imagepng($cropped);
        $raw = ob_get_clean();
        imagedestroy($cropped);

        if (! $ok || $raw === false || $raw === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($raw);
    }
}
