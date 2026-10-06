<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class PublicLayout extends Component
{
    public function __construct(
        public string $title = 'NavegaYA - Transporte Fluvial y Aéreo en Loreto',
        public string $current = '',
        public string $description = 'Pasajes fluviales y aéreos en Loreto.',
    ) {}

    public function render(): View
    {
        return view('layouts.app');
    }
}
