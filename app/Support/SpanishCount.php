<?php

namespace App\Support;

class SpanishCount
{
    public static function phrase(int $count, string $singular, string $plural): string
    {
        return $count.' '.($count === 1 ? $singular : $plural);
    }
}
