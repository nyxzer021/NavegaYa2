<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class WeatherController extends Controller
{
    public function index(): View
    {
        return view('weather.index', ['locations' => [
            'iquitos' => ['name' => 'Iquitos', 'lat' => -3.7437, 'lon' => -73.2516],
            'nauta' => ['name' => 'Nauta', 'lat' => -4.5061, 'lon' => -73.5757],
            'yurimaguas' => ['name' => 'Yurimaguas', 'lat' => -5.9018, 'lon' => -76.1223],
            'requena' => ['name' => 'Requena', 'lat' => -5.0622, 'lon' => -73.8528],
            'contamana' => ['name' => 'Contamana', 'lat' => -7.3333, 'lon' => -75.0167],
        ]]);
    }
}
