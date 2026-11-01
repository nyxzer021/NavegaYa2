<?php

namespace App\View\Components;

use App\Support\SearchDescription;
use Illuminate\View\Component;
use Illuminate\View\View;

class GuestLayout extends Component
{
    public function __construct(
        public string $title = 'Cuenta de viajero · NavegaYA',
        public string $description = 'Ingresa o crea tu cuenta de NavegaYA para consultar reservas, descargar boletos y seguir tus viajes fluviales y aéreos por Loreto.',
    ) {
        $this->description = SearchDescription::fit($this->description);
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.guest');
    }
}
