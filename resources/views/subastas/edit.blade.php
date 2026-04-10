<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Subasta | Subastas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('css/views/subastas-edit.css') }}">
</head>
<body>
    <div class="wrap">
        <div class="top">
            <h1>Editar Subasta</h1>
            <a href="{{ route('subastas.dashboard') }}" class="back">← Volver</a>
        </div>

        @if (session('ok'))
            <div class="success">{{ session('ok') }}</div>
        @endif

        @if ($errors->any())
            <div class="error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="card">
            <div class="info-section">
                <div class="info-label">Identificador</div>
                <div class="info-value">{{ $subasta->identificador }}</div>
            </div>

            <div class="info-section">
                <div class="info-label">Valor actual</div>
                <div class="price">{{ number_format((float) ($subasta->valor_subasta ?? 0), 2, ',', '.') }} €</div>
            </div>

            <form method="POST" action="{{ route('subastas.updateStatus', $subasta->id) }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="estado">Estado de la Subasta</label>
                    <select id="estado" name="estado" required>
                        <option value="">Selecciona un estado</option>
                        <option value="Pendiente" {{ $subasta->estado === 'Pendiente' ? 'selected' : '' }}>Pendiente</option>
                        <option value="Activa" {{ $subasta->estado === 'Activa' ? 'selected' : '' }}>Activa</option>
                        <option value="Cancelada" {{ $subasta->estado === 'Cancelada' ? 'selected' : '' }}>Cancelada</option>
                        <option value="Finalizada" {{ $subasta->estado === 'Finalizada' ? 'selected' : '' }}>Finalizada</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="submit" class="btn-save">Guardar cambios</button>
                    <a href="{{ route('subastas.dashboard') }}" class="btn-cancel">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
