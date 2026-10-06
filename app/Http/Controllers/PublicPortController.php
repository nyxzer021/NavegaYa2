<?php

namespace App\Http\Controllers;

use App\Models\Port;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicPortController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['buscar' => ['nullable', 'string', 'max:100'], 'tipo' => ['nullable', 'in:fluvial,aereo']]);
        $search = $data['buscar'] ?? null;
        $type = $data['tipo'] ?? null;
        $ports = Port::query()->withCount(['originRoutes', 'destinationRoutes'])->where('is_active', true)
            ->when($search, fn ($q) => $q->where(fn ($x) => $x->where('name', 'ilike', "%{$search}%")->orWhere('city', 'ilike', "%{$search}%")->orWhere('river', 'ilike', "%{$search}%")))
            ->when($type === 'fluvial', fn ($q) => $q->whereNotIn('port_type', ['aerodromo', 'aeropuerto', 'pista']))
            ->when($type === 'aereo', fn ($q) => $q->whereIn('port_type', ['aerodromo', 'aeropuerto', 'pista']))
            ->orderBy('city')->orderBy('name')->get();

        return view('ports.index', compact('ports', 'search', 'type'));
    }
}
