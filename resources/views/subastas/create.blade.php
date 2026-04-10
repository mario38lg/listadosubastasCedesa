<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Subasta</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('css/views/subastas-create.css') }}">
</head>
<body>
    <div class="wrap">
        <div class="top-actions">
            <?php
                $urlScraper = 'https://media-vegetal.cedesa.es/crear-por-url';
                // Intenta primero desde query, luego desde sesion
                $bridgeEmail = request()->query('bridge_email') ?? session('bridge_email', '');
                $bridgeName = request()->query('bridge_name') ?? session('bridge_name', '');
                $bridgeToken = request()->query('bridge_token') ?? session('bridge_token', '');

                // Si no hay datos puente, usamos el usuario logeado actual.
                if ((! $bridgeEmail || ! $bridgeName || ! $bridgeToken) && auth()->check()) {
                    $bridgeEmail = auth()->user()->email;
                    $bridgeName = auth()->user()->name;
                    $bridgeToken = env('SUBASTAS_BRIDGE_TOKEN', 'token-puente-dev');
                }
                
                if ($bridgeEmail && $bridgeName && $bridgeToken) {
                    $urlScraper .= '?' . http_build_query([
                        'bridge_email' => $bridgeEmail,
                        'bridge_name' => $bridgeName,
                        'bridge_token' => $bridgeToken
                    ]);
                }
            ?>
            <a
                class="scraper-link"
                href="{{ $urlScraper }}"
                target="_self"
            >
                Crear por URL
            </a>
        </div>

        <div class="header">
            <h1>Nueva Subasta</h1>
            <p class="subtitle">El identificador se genera automáticamente</p>
        </div>

        @if (session('ok'))
            <div class="success">{{ session('ok') }}</div>
        @endif

        <form method="POST" action="{{ route('subastas.store') }}" enctype="multipart/form-data" class="form">
            @csrf

            <input type="hidden" name="bridge_email" value="{{ request()->query('bridge_email') }}">
            <input type="hidden" name="bridge_name" value="{{ request()->query('bridge_name') }}">
            <input type="hidden" name="bridge_token" value="{{ request()->query('bridge_token') }}">

            <!-- Esencial -->
            <div class="form-grid full">
                <div class="field">
                    <label>Datos del Bien Subastado *</label>
                    <textarea name="datos_bien_subastado" required>{{ old('datos_bien_subastado') }}</textarea>
                    @error('datos_bien_subastado')
                        <div class="error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label>Estado *</label>
                    <select name="estado" required>
                        <option value="">Selecciona</option>
                        <option value="Activa" {{ old('estado') == 'Activa' ? 'selected' : '' }}>Activa</option>
                        <option value="Cancelada" {{ old('estado') == 'Cancelada' ? 'selected' : '' }}>Cancelada</option>
                        <option value="Finalizada" {{ old('estado') == 'Finalizada' ? 'selected' : '' }}>Finalizada</option>
                    </select>
                    @error('estado')
                        <div class="error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="field">
                    <label>Valor Subasta</label>
                    <input type="number" step="0.01" name="valor_subasta" value="{{ old('valor_subasta') }}">
                    @error('valor_subasta')
                        <div class="error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="section-title">Fechas</div>
            <div class="form-grid">
                <div class="field">
                    <label>Inicio</label>
                    <input type="datetime-local" name="fecha_inicio" value="{{ old('fecha_inicio') }}">
                    @error('fecha_inicio')
                        <div class="error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="field">
                    <label>Conclusión</label>
                    <input type="datetime-local" name="fecha_conclusion" value="{{ old('fecha_conclusion') }}">
                    @error('fecha_conclusion')
                        <div class="error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="section-title">Importes (€)</div>
            <div class="form-grid">
                <div class="field">
                    <label>Cantidad Reclamada</label>
                    <input type="number" step="0.01" name="cantidad_reclamada" value="{{ old('cantidad_reclamada') }}">
                </div>
                <div class="field">
                    <label>Tasación</label>
                    <input type="number" step="0.01" name="tasacion" value="{{ old('tasacion') }}">
                </div>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label>Puja Mínima</label>
                    <input type="number" step="0.01" name="puja_minima" value="{{ old('puja_minima') }}">
                </div>
                <div class="field">
                    <label>Depósito</label>
                    <input type="number" step="0.01" name="importe_deposito" value="{{ old('importe_deposito') }}">
                </div>
            </div>

            <div class="section-title">Detalles</div>
            <div class="form-grid full">
                <div class="field">
                    <label>Lotes</label>
                    <input type="text" name="lotes" value="{{ old('lotes') }}" placeholder="Ej: Lote A, Lote B...">
                </div>
            </div>

            <div class="form-grid full">
                <div class="field">
                    <label>Tramos entre Pujas</label>
                    <input type="text" name="tramos_entre_pujas" value="{{ old('tramos_entre_pujas') }}" placeholder="Ej: 100, 500, 1000">
                </div>
            </div>

            <div class="form-grid full">
                <div class="field">
                    <label>Observaciones</label>
                    <textarea name="observaciones">{{ old('observaciones') }}</textarea>
                </div>
            </div>

            <div class="form-grid full">
                <div class="field">
                    <label>Documentos</label>
                    <input type="file" name="documentos_adjuntos[]" multiple>
                    @error('documentos_adjuntos')
                        <div class="error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="button-group">
                <a href="{{ route('subastas.dashboard') }}" class="back-btn">Cancelar</a>
                <button type="submit">Crear Subasta</button>
            </div>
        </form>
    </div>
</body>
</html>
