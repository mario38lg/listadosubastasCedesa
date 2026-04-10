<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureBridgeAuthenticated
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if (Auth::check()) {
            return $next($request);
        }

        $email = (string) ($request->input('bridge_email') ?? $request->query('bridge_email', ''));
        $name = (string) ($request->input('bridge_name') ?? $request->query('bridge_name', ''));
        $tokenRecibido = (string) ($request->input('bridge_token') ?? $request->query('bridge_token', ''));

        if ($email === '' || $name === '' || $tokenRecibido === '') {
            return $this->redirigirAlLogin();
        }

        $tokenCorrecto = (string) env('SUBASTAS_BRIDGE_TOKEN', 'token-puente-dev');
        if (! hash_equals($tokenCorrecto, $tokenRecibido)) {
            return $this->redirigirAlLogin();
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(Str::random(40)),
            ]
        );

        Auth::login($user);
        $request->session()->regenerate();

        // Guardamos datos puente para poder reenviarlos a otros microservicios (scraper).
        $request->session()->put([
            'bridge_email' => $email,
            'bridge_name' => $name,
            'bridge_token' => $tokenRecibido,
        ]);

        return $next($request);
    }

    private function redirigirAlLogin(): RedirectResponse
    {
        $urlLogin = (string) env('AUTH_LOGIN_URL', 'https://media-vegetal-auth.cedesa.es/login');

        return redirect()->away($urlLogin);
    }
}
