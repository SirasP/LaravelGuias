<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Camion;
use App\Models\AgrakRegistro;
class CamionController extends Controller
{

    public function create(Request $request)
    {
        $patente = $request->get('patente');

        $camiones = Camion::orderBy('patente_norm')->get();

        return view('agrak.create', compact('patente', 'camiones'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'patente_norm' => ['nullable', 'string', 'max:20'],
            'patente_nueva' => ['nullable', 'string', 'max:20'],
            'alias' => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string'],
            'patente_detectada' => ['required', 'string'],
        ]);

        // 1️⃣ Resolver patente correcta
        $patenteCorrecta = $request->patente_norm;

        if (!$patenteCorrecta && $request->patente_nueva) {
            $patenteCorrecta = strtoupper(trim($request->patente_nueva));
        }

        if (!$patenteCorrecta) {
            return back()
                ->withErrors(['patente_norm' => 'Debes seleccionar o ingresar una patente válida.'])
                ->withInput();
        }

        // 2️⃣ Crear / asegurar camión
        Camion::firstOrCreate(
            ['patente_norm' => $patenteCorrecta],
            [
                'alias' => $request->alias,
                'observaciones' => $request->observaciones,
                'activo' => true,
            ]
        );

        // 3️⃣ 🔥 CORREGIR AGRak (ESTO ES LO QUE FALTABA)
        AgrakRegistro::where('patente_norm', $request->patente_detectada)
            ->update([
                'patente_norm' => $patenteCorrecta,
            ]);

        return redirect()
            ->route('agrak.index', ['view' => 'group'])
            ->with('ok', "Patente corregida: {$request->patente_detectada} → {$patenteCorrecta}");
    }


}
