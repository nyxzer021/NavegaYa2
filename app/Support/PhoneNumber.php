<?php

namespace App\Support;

class PhoneNumber
{
    public static function international(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            $digits = '51'.$digits;
        }

        return $digits;
    }

    public static function whatsappUrl(?string $number, ?string $message = null): ?string
    {
        $digits = self::international($number);
        if (! $digits) {
            return null;
        }

        return 'https://wa.me/'.$digits.(filled($message) ? '?text='.rawurlencode($message) : '');
    }

    public static function telephoneUrl(?string $number): ?string
    {
        $digits = self::international($number);

        return $digits ? 'tel:+'.$digits : null;
    }
}
