<?php

namespace App\Support;

use App\Models\Organization;

class CompanyPublicPresentation
{
    /**
     * @return array{menu: array{bar: string, stroke: string, tabs: array<string, array{bg: string, border: string, text: string}>}, cargo: array{pequeno: float, mediano: float, grande: float, port_note: string}}
     */
    public static function defaults(): array
    {
        return [
            'menu' => [
                'bar' => '#062c21',
                'stroke' => '#fbbf24',
                'tabs' => [
                    'informacion' => ['bg' => '#ecfdf5', 'border' => '#6ee7b7', 'text' => '#064e3b'],
                    'galeria' => ['bg' => '#e0f2fe', 'border' => '#38bdf8', 'text' => '#0c4a6e'],
                    'flota' => ['bg' => '#d1fae5', 'border' => '#047857', 'text' => '#064e3b'],
                    'salidas' => ['bg' => '#fbbf24', 'border' => '#f59e0b', 'text' => '#022c22'],
                    'opiniones' => ['bg' => '#fffbeb', 'border' => '#fbbf24', 'text' => '#78350f'],
                ],
            ],
            'cargo' => [
                'pequeno' => 15.0,
                'mediano' => 20.0,
                'grande' => 35.0,
                'port_note' => '',
            ],
        ];
    }

    /**
     * @return list<array{title: string, key: ?string, fields: array<string, string>}>
     */
    public static function colorFields(): array
    {
        return [
            ['title' => 'Barra', 'key' => null, 'fields' => ['menu_bar' => 'Fondo', 'menu_stroke' => 'Trazo']],
            ['title' => 'Información', 'key' => 'informacion', 'fields' => ['tab_informacion_bg' => 'Fondo', 'tab_informacion_border' => 'Borde', 'tab_informacion_text' => 'Texto']],
            ['title' => 'Galería', 'key' => 'galeria', 'fields' => ['tab_galeria_bg' => 'Fondo', 'tab_galeria_border' => 'Borde', 'tab_galeria_text' => 'Texto']],
            ['title' => 'Flota', 'key' => 'flota', 'fields' => ['tab_flota_bg' => 'Fondo', 'tab_flota_border' => 'Borde', 'tab_flota_text' => 'Texto']],
            ['title' => 'Salidas', 'key' => 'salidas', 'fields' => ['tab_salidas_bg' => 'Fondo', 'tab_salidas_border' => 'Borde', 'tab_salidas_text' => 'Texto']],
            ['title' => 'Testimonios', 'key' => 'opiniones', 'fields' => ['tab_opiniones_bg' => 'Fondo', 'tab_opiniones_border' => 'Borde', 'tab_opiniones_text' => 'Texto']],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array{menu: array{bar: string, stroke: string, tabs: array<string, array{bg: string, border: string, text: string}>}, cargo: array{pequeno: float, mediano: float, grande: float, port_note: string}}
     */
    public static function resolve(?array $stored): array
    {
        $defaults = self::defaults();
        $menu = is_array($stored['menu'] ?? null) ? $stored['menu'] : [];
        $tabs = is_array($menu['tabs'] ?? null) ? $menu['tabs'] : [];
        $resolvedTabs = [];
        foreach ($defaults['menu']['tabs'] as $key => $colors) {
            $saved = is_array($tabs[$key] ?? null) ? $tabs[$key] : [];
            $resolvedTabs[$key] = [
                'bg' => self::hex(isset($saved['bg']) ? (string) $saved['bg'] : null, $colors['bg']),
                'border' => self::hex(isset($saved['border']) ? (string) $saved['border'] : null, $colors['border']),
                'text' => self::hex(isset($saved['text']) ? (string) $saved['text'] : null, $colors['text']),
            ];
        }
        $cargo = is_array($stored['cargo'] ?? null) ? $stored['cargo'] : [];

        return [
            'menu' => [
                'bar' => self::hex(isset($menu['bar']) ? (string) $menu['bar'] : null, $defaults['menu']['bar']),
                'stroke' => self::hex(isset($menu['stroke']) ? (string) $menu['stroke'] : null, $defaults['menu']['stroke']),
                'tabs' => $resolvedTabs,
            ],
            'cargo' => [
                'pequeno' => self::price($cargo['pequeno'] ?? null, $defaults['cargo']['pequeno']),
                'mediano' => self::price($cargo['mediano'] ?? null, $defaults['cargo']['mediano']),
                'grande' => self::price($cargo['grande'] ?? null, $defaults['cargo']['grande']),
                'port_note' => self::note($cargo['port_note'] ?? null),
            ],
        ];
    }

    /**
     * @return array{menu: array{bar: string, stroke: string, tabs: array<string, array{bg: string, border: string, text: string}>}, cargo: array{pequeno: float, mediano: float, grande: float, port_note: string}}
     */
    public static function forOrganization(Organization $organization): array
    {
        $stored = $organization->public_presentation;

        return self::resolve(is_array($stored) ? $stored : null);
    }

    /**
     * @param  array{menu: array{bar: string, stroke: string, tabs: array<string, array{bg: string, border: string, text: string}>}, cargo: array{pequeno: float, mediano: float, grande: float, port_note: string}}  $presentation
     */
    /**
     * @return array<string, string>
     */
    public static function brandFieldValues(): array
    {
        $presentation = self::defaults();
        $values = [];
        foreach (self::colorFields() as $group) {
            foreach ($group['fields'] as $name => $label) {
                $values[$name] = self::valueFor($presentation, $name);
            }
        }

        return $values;
    }

    public static function valueFor(array $presentation, string $field): string
    {
        if ($field === 'menu_bar') {
            return $presentation['menu']['bar'];
        }
        if ($field === 'menu_stroke') {
            return $presentation['menu']['stroke'];
        }
        if (preg_match('/^tab_(informacion|galeria|flota|salidas|opiniones)_(bg|border|text)$/', $field, $matches) === 1) {
            return $presentation['menu']['tabs'][$matches[1]][$matches[2]];
        }

        return '';
    }

    /**
     * @param  array{menu: array{bar: string, stroke: string, tabs: array<string, array{bg: string, border: string, text: string}>}}  $presentation
     */
    public static function menuStyle(array $presentation): string
    {
        $style = '--menu-bar:'.$presentation['menu']['bar'].';--menu-stroke:'.$presentation['menu']['stroke'].';';
        foreach ($presentation['menu']['tabs'] as $key => $colors) {
            $style .= '--tab-'.$key.'-bg:'.$colors['bg'].';--tab-'.$key.'-border:'.$colors['border'].';--tab-'.$key.'-text:'.$colors['text'].';';
        }

        return $style;
    }

    public static function hex(?string $value, string $fallback): string
    {
        $value = strtolower(trim((string) $value));

        return preg_match('/^#[0-9a-f]{6}$/', $value) === 1 ? $value : $fallback;
    }

    private static function price(mixed $value, float $fallback): float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return $fallback;
        }
        $price = round((float) $value, 2);
        if ($price < 0 || $price > 9999) {
            return $fallback;
        }

        return $price;
    }

    private static function note(mixed $value): string
    {
        $note = trim((string) $value);

        return mb_strlen($note) > 180 ? mb_substr($note, 0, 180) : $note;
    }
}
