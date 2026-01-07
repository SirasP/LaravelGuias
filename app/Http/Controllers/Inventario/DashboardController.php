<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // KPIs (mock)
        $kilosHoy = 1254.750;
        $kilosMes = 32890.420;
        $kilosTotal = 1845230.125;

        // DATOS PARA EL GRÁFICO (últimos 7 días)
        $chartLabels = collect(range(6, 0))
            ->map(fn($i) => now()->subDays($i)->format('d/m'));

        $chartLabels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        $chartData = [820, 950, 1100, 980, 1250, 1400, 1254];

        return view('index', compact(
            'kilosHoy',
            'kilosMes',
            'kilosTotal',
            'chartLabels',
            'chartData'
        ));
    }


}
