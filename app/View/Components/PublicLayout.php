<?php

namespace App\View\Components;

use App\Support\SearchDescription;
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
                ? 'Compra pasajes fluviales y aéreos en Loreto, consulta horarios oficiales, reserva tu asiento y descubre destinos verificados de la Amazonía peruana.'
                : "{$pageName} en NavegaYA: consulta horarios, reserva tu asiento y descubre servicios verificados para viajar por Loreto.";
        }

        $this->description = SearchDescription::fit($this->description);
    }

    public function render(): View
    {
        return view('layouts.app');
    }
}
