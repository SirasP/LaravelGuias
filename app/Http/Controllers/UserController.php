<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Listado de usuarios del sistema (solo admin).
     */
    public function index(): View
    {
        $movimientos = DB::table('users')
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get();

        return view('usuarios.index', compact('movimientos'));
    }

    public function create(): View
    {
        return view('users.create');
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // vuelve a la lista (cambia la ruta si tu lista se llama distinto)
        return redirect()->route('dashboard')->with('success', 'Usuario creado ✅');

    }
    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('dashboard')->with('success', 'Usuario eliminado correctamente');

    }
    public function toggleActive(Request $request, User $user)
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $user->is_active = $validated['is_active'];
        $user->save();

        return response()->json([
            'ok' => true,
            'is_active' => (bool) $user->is_active,
            'message' => $user->is_active ? 'Usuario activado' : 'Usuario desactivado',
        ]);
    }

    public function updateRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => ['required', 'in:admin,viewer,bodeguero'],
        ]);

        $user->role = $validated['role'];
        $user->save();

        return response()->json([
            'ok' => true,
            'role' => $user->role,
            'message' => 'Rol actualizado',
        ]);
    }
}
