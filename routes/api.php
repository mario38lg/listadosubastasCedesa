<?php

use App\Http\Controllers\ApiSubastasController;
use Illuminate\Support\Facades\Route;


// API interna: crear subasta desde scraper (protegida por secreto en cabecera).
Route::post('/subastas/importar', [ApiSubastasController::class, 'storeFromScraperApi']);
