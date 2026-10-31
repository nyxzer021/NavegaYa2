<?php

namespace App\Support;

final class PhoneNumber
{
    public static function peru(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            return '51'.$digits;
        }

        return $digits;
    }

    public static function display(?string $value): ?string
    {
        $digits = self::peru($value);
        if (! $digits) {
            return null;
        }
        if (preg_match('/^51(\d{3})(\d{3})(\d{3})$/', $digits, $matches)) {
            return '+51 '.$matches[1].' '.$matches[2].' '.$matches[3];
        }

        return '+'.$digits;
    }
}
