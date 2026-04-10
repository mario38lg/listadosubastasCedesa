<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class UsuarioController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('usuarios.create');
    }

    public function create(): View
    {
        return view('usuarios.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $urlAuth = (string) env('AUTH_INTERNAL_CREATE_USER_URL', 'https://media-vegetal-auth.cedesa.es/internal/usuarios');
        $secreto = (string) env('INTERNAL_USER_CREATE_SECRET', env('SUBASTAS_BRIDGE_TOKEN', 'token-puente-dev'));

        $respuestaAuth = Http::withHeaders([
            'X-Internal-Secret' => $secreto,
        ])->post($urlAuth, [
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => $datos['password'],
        ]);

        if (! $respuestaAuth->successful()) {
            $mensaje = 'No se pudo crear el usuario en auth.';
            $json = $respuestaAuth->json();
            if (is_array($json) && isset($json['message'])) {
                $mensaje = (string) $json['message'];
            }

            return back()->withErrors(['email' => $mensaje])->withInput();
        }

        User::create([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => Hash::make($datos['password']),
        ]);

        return redirect()->route('subastas.dashboard')->with('ok', 'Empleado creado correctamente.');
    }
}
