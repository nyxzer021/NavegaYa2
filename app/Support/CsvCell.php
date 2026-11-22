<?php

namespace App\Support;

/**
 * Evita que un texto escrito por un pasajero o un empleado (por ejemplo "=HYPERLINK(...)")
 * se ejecute como fórmula al abrir el CSV en Excel.
 */
class CsvCell
{
    /**
     * @param  array<int, mixed>  $row
     * @return array<int, mixed>
     */
    public static function row(array $row): array
    {
        return array_map(self::safe(...), $row);
    }

    public static function safe(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }
        if (preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
