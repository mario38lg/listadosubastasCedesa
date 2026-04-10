<?php

use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\WebSubastasController;
use Illuminate\Support\Facades\Route;

// Primera pagina publica: buzon del scraper.
Route::get('/', function () {
    $urlBuzonPublico = (string) env('SCRAPER_PUBLIC_URL', 'https://media-vegetal.cedesa.es/crear-por-url');

    return redirect()->away($urlBuzonPublico);
});

// Ruta requerida por middleware auth para invitados.
Route::get('/login', function () {
    return redirect()->away((string) env('AUTH_LOGIN_URL', 'https://media-vegetal-auth.cedesa.es/login'));
})->name('login');

// Registro publico deshabilitado en este microservicio.
Route::get('/register', function () {
    abort(404);
})->name('register');

// Zona privada completa: solo usuarios autenticados por puente de microservicios.
Route::middleware('bridge.auth')->group(function (): void {
    // Gestion de usuarios.
    Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/create', [UsuarioController::class, 'create'])->name('usuarios.create');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');

    // Subastas.
    Route::get('/dashboard', [WebSubastasController::class, 'dashboard'])->name('subastas.dashboard');
    Route::get('/kanban', [WebSubastasController::class, 'kanban'])->name('subastas.kanban');
    Route::get('/subastas/create', [WebSubastasController::class, 'create'])->name('subastas.create');
    Route::post('/subastas', [WebSubastasController::class, 'store'])->name('subastas.store');
    Route::get('/subastas/{subasta}', [WebSubastasController::class, 'show'])->name('subastas.show');
    Route::get('/subastas/{subasta}/nota', [WebSubastasController::class, 'showNote'])->name('subastas.note');
    Route::post('/subastas/{subasta}/nota', [WebSubastasController::class, 'storeNote'])->name('subastas.storeNote');
    Route::put('/subastas/{subasta}/nota/{nota}', [WebSubastasController::class, 'updateNote'])->name('subastas.updateNote');
    Route::delete('/subastas/{subasta}/nota/{nota}', [WebSubastasController::class, 'destroyNote'])->name('subastas.destroyNote');
    Route::get('/subastas/{subasta}/edit', [WebSubastasController::class, 'showEdit'])->name('subastas.edit');
    Route::put('/subastas/{subasta}/status', [WebSubastasController::class, 'updateStatus'])->name('subastas.updateStatus');
    Route::put('/subastas/{subasta}/clasificacion', [WebSubastasController::class, 'updateClasificacionKanban'])->name('subastas.updateClasificacionKanban');
    Route::delete('/subastas/{subasta}', [WebSubastasController::class, 'destroy'])->name('subastas.destroy');
    Route::post('/logout', [WebSubastasController::class, 'logout'])->name('subastas.logout');
});
