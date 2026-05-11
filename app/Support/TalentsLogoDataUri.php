<?php

namespace App\Support;

class TalentsLogoDataUri
{
    /**
     * Carrega o logo Talents como data URI para DomPDF.
     */
    public static function get(): ?string
    {
        $candidates = [
            base_path('logo.png'),
            base_path('../logo.png'),
            public_path('images/logo.png'),
            public_path('logo.png'),
        ];

        foreach ($candidates as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }
            $raw = @file_get_contents($path);
            if ($raw === false || $raw === '') {
                continue;
            }
            $info = @getimagesizefromstring($raw);
            if ($info === false) {
                continue;
            }
            $mime = $info['mime'] ?? 'image/png';
            if (! in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)) {
                continue;
            }

            return 'data:'.$mime.';base64,'.base64_encode($raw);
        }

        return null;
    }
}
