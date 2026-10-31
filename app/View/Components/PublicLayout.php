<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class PublicLayout extends Component
{
    public function __construct(
        public string $title = 'NavegaYA - Transporte Fluvial y Aéreo en Loreto',
        public string $current = '',
        public string $description = '',
        public ?string $image = null,
    ) {
        if ($this->description === '') {
            $pageName = str($this->title)->before(' · ')->before(' - ')->toString();
            $this->description = $pageName === 'NavegaYA'
                ? 'Compra pasajes fluviales y aéreos, consulta rutas y descubre servicios verificados para viajar por Loreto.'
                : "{$pageName} en NavegaYA: información clara para consultar servicios, organizar tu viaje y descubrir opciones verificadas en Loreto.";
        }
    }

    public function render(): View
    {
        return view('layouts.app');
    }
}
