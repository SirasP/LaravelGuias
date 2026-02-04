<?php

namespace App\Http\Controllers\FuelControl;

use App\Http\Controllers\Controller;

class ProductoController extends Controller
{
    public function index()
    {
        return view('fuelcontrol.productos.index');
    }
}