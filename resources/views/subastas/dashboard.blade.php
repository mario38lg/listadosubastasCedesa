<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Subastas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('css/views/subastas-dashboard.css') }}?v=11">
</head>
<body>
    @php
        $estadoActual = request('estado', '');
        $fechaDesdeActual = request('fecha_desde', '');
        $fechaHastaActual = request('fecha_hasta', '');
        $ordenClasificacion = request('orden_clasificacion', '');
        $ordenFechaConclusion = request('orden_fecha_conclusion', 'desc');
        $ordenValorTasacion = request('orden_valor_tasacion', '');
    @endphp
    <div class="wrap">
        <div class="top">
            <h1>Listado de subastas</h1>
            <div class="actions">
                <a class="create" href="{{ route('usuarios.create') }}">Registrar</a>
                <a class="create" href="{{ route('subastas.kanban') }}">Editar Clasificación</a>
                <a class="create" href="{{ route('subastas.create') }}">Crear subasta</a>
                <form method="POST" action="{{ route('subastas.logout') }}" style="margin: 0;">
                    @csrf
                    <button class="logout" type="submit">Cerrar sesión</button>
                </form>
            </div>
        </div>

        <form method="GET" action="{{ route('subastas.dashboard') }}" class="filters-panel">
            <div class="filters-row filters-row-top">
                <div class="filter-group">
                    <label for="estado">Estado</label>
                    <select id="estado" name="estado" onchange="this.form.requestSubmit()">
                        <option value="">Todos</option>
                        <option value="Pendiente" {{ $estadoActual === 'Pendiente' ? 'selected' : '' }}>Pendiente</option>
                        <option value="Activa" {{ $estadoActual === 'Activa' ? 'selected' : '' }}>Activa</option>
                        <option value="Cancelada" {{ $estadoActual === 'Cancelada' ? 'selected' : '' }}>Cancelada</option>
                        <option value="Finalizada" {{ $estadoActual === 'Finalizada' ? 'selected' : '' }}>Finalizada</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="fecha_desde">Fecha desde</label>
                    <input id="fecha_desde" type="date" name="fecha_desde" value="{{ $fechaDesdeActual }}">
                </div>

                <div class="filter-group">
                    <label for="fecha_hasta">Fecha hasta</label>
                    <input id="fecha_hasta" type="date" name="fecha_hasta" value="{{ $fechaHastaActual }}">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="filter-button">Filtrar</button>
                    <a href="{{ route('subastas.dashboard') }}" class="filter-reset">Limpiar</a>
                </div>
            </div>

            <div class="filters-row filters-row-bottom">
                <div class="filter-group">
                    <label for="orden_clasificacion">Orden clasificación</label>
                    <select id="orden_clasificacion" name="orden_clasificacion" onchange="this.form.requestSubmit()">
                        <option value="" {{ $ordenClasificacion === '' ? 'selected' : '' }}>Sin ordenar</option>
                        <option value="asc" {{ $ordenClasificacion === 'asc' ? 'selected' : '' }}>Ascendente</option>
                        <option value="desc" {{ $ordenClasificacion === 'desc' ? 'selected' : '' }}>Descendente</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="orden_fecha_conclusion">Orden fecha conclusión</label>
                    <select id="orden_fecha_conclusion" name="orden_fecha_conclusion" onchange="this.form.requestSubmit()">
                        <option value="" {{ $ordenFechaConclusion === '' ? 'selected' : '' }}>Sin ordenar</option>
                        <option value="asc" {{ $ordenFechaConclusion === 'asc' ? 'selected' : '' }}>Ascendente</option>
                        <option value="desc" {{ $ordenFechaConclusion === 'desc' ? 'selected' : '' }}>Descendente</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="orden_valor_tasacion">Orden valor tasación</label>
                    <select id="orden_valor_tasacion" name="orden_valor_tasacion" onchange="this.form.requestSubmit()">
                        <option value="" {{ $ordenValorTasacion === '' ? 'selected' : '' }}>Sin ordenar</option>
                        <option value="asc" {{ $ordenValorTasacion === 'asc' ? 'selected' : '' }}>Ascendente</option>
                        <option value="desc" {{ $ordenValorTasacion === 'desc' ? 'selected' : '' }}>Descendente</option>
                    </select>
                </div>
            </div>
        </form>

        <section class="table-wrap">
            @if($subastas->isEmpty())
                <div class="empty">No hay subastas disponibles.</div>
            @else
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Bien subastado</th>
                                <th>Identificador</th>
                                <th>Clasificación</th>
                                <th>Fecha de conclusión</th>
                                <th>Valor de tasación</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($subastas as $subasta)
                            <tr data-subasta-row>
                                <td>{{ $subasta->datos_bien_subastado ?? 'Sin especificar' }}</td>
                                <td>{{ $subasta->identificador }}</td>
                                <td>{{ $subasta->clasificacion ?? 'nueva' }}</td>
                                <td>{{ $subasta->fecha_conclusion ? $subasta->fecha_conclusion->format('d/m/Y H:i') : 'No definida' }}</td>
                                <td>
                                    @php
                                        $valorTasacionMostrado = $subasta->valor_tasacion ?? $subasta->tasacion;
                                    @endphp
                                    {{ $valorTasacionMostrado ? number_format((float) $valorTasacionMostrado, 2, ',', '.') . ' €' : '0,00 €' }}
                                </td>
                                <td>{{ $subasta->estado }}</td>
                                <td>
                                    <a href="{{ route('subastas.show', $subasta->id) }}" class="btn-view">Ver</a>
                                    <a href="{{ route('subastas.note', $subasta->id) }}" class="btn-note">Ver nota</a>
                                    <a href="{{ route('subastas.edit', $subasta->id) }}" class="btn-edit">Editar</a>
                                    <form method="POST" action="{{ route('subastas.destroy', $subasta->id) }}" class="js-delete-form" style="display: inline-block; margin: 0;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-delete">Borrar</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

        <script src="{{ asset('js/views/subastas-dashboard.js') }}" defer></script>
</body>
</html>
