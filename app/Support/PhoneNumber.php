<?php

namespace App\Support;

final class PhoneNumber
{
    public static function peru(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        if (preg_match('/^9\d{8}$/', $digits) === 1) {
            $digits = '51'.$digits;
        }
        if (preg_match('/^519\d{8}$/', $digits) !== 1 || $digits === '51900000000') {
            return null;
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
