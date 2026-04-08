<?php

namespace App\Http\Controllers;

use App\Mail\NuevoComentarioMail;
use App\Models\Comentario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SugerenciasController extends Controller
{
    public function index()
    {
        return view('sugerencias.index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'tipo'       => 'required|in:sugerencia,reclamo',
            'comentario' => 'required|string|min:10|max:1000',
        ], [
            'tipo.required'       => 'Selecciona si es una sugerencia o reclamo.',
            'tipo.in'             => 'Tipo no válido.',
            'comentario.required' => 'El mensaje no puede estar vacío.',
            'comentario.min'      => 'El mensaje debe tener al menos 10 caracteres.',
            'comentario.max'      => 'El mensaje no puede superar los 1000 caracteres.',
        ]);

        $comentario = Comentario::create([
            'tipo'       => $request->tipo,
            'comentario' => $request->comentario,
        ]);

        Mail::to('adm.agricolae.h@gmail.com')->send(new NuevoComentarioMail($comentario));

        return response()->json([
            'success' => true,
            'message' => '¡Gracias! Tu ' . $request->tipo . ' fue registrada correctamente.',
        ]);
    }

    public function admin()
    {
        $comentarios = Comentario::latest()->get();
        $total       = $comentarios->count();
        $sugerencias = $comentarios->where('tipo', 'sugerencia')->count();
        $reclamos    = $comentarios->where('tipo', 'reclamo')->count();

        return view('sugerencias.admin', compact('comentarios', 'total', 'sugerencias', 'reclamos'));
    }
}
