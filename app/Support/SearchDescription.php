<?php

namespace App\Support;

class SearchDescription
{
    public static function fit(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
        $padding = 'Consulta horarios, reserva tu asiento y viaja con operadores verificados en Loreto.';

        while (mb_strlen($text) < 140) {
            $text = trim($text.' '.$padding);
        }

        if (mb_strlen($text) > 160) {
            $cut = rtrim(mb_substr($text, 0, 157));
            $word = preg_replace('/\s+\S*$/u', '', $cut);
            $text = is_string($word) && mb_strlen($word) >= 140 ? $word : mb_substr($text, 0, 160);
        }

        return $text;
    }
}
