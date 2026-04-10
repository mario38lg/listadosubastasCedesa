<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kanban | Subastas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/views/subastas-kanban.css') }}?v=6">
</head>
<body>
    <div class="topbar">
        <h1>Tablero Kanban de Subastas</h1>
        <div class="top-actions">
            <a class="btn" href="{{ route('subastas.dashboard') }}">Volver al listado de subastas</a>
        </div>
    </div>

    <div class="board">
        @foreach ($columnas as $nombreColumna => $subastasColumna)
            @php
                $sumaValorColumna = collect($subastasColumna)->sum(function ($subasta): float {
                    return (float) ($subasta->valor_subasta ?? 0);
                });
            @endphp
            <section class="column" data-clasificacion="{{ $nombreColumna }}">
                <header class="column-head">
                    <span class="column-title {{ $nombreColumna === 'Urgente' ? 'is-urgente' : '' }}">{{ $nombreColumna }}</span>
                    <span class="count">{{ number_format($sumaValorColumna, 2, ',', '.') }} €</span>
                </header>

                <div class="cards" data-columna="{{ $nombreColumna }}">
                    @foreach ($subastasColumna as $subasta)
                        <article class="card" data-id="{{ $subasta->id }}" data-valor="{{ (float) ($subasta->valor_subasta ?? 0) }}">
                            <div class="id">{{ $subasta->identificador }}</div>
                            <p class="title">{{ $subasta->datos_bien_subastado ?: 'Sin descripcion' }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const columnas = document.querySelectorAll('.cards');

        const formatearEuros = (numero) => {
            return new Intl.NumberFormat('es-ES', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(numero) + ' €';
        };

        const actualizarTotalesColumnas = () => {
            const columnasKanban = document.querySelectorAll('.column');

            columnasKanban.forEach((columna) => {
                const tarjetas = columna.querySelectorAll('.card');
                let suma = 0;

                tarjetas.forEach((tarjeta) => {
                    const valor = Number(tarjeta.getAttribute('data-valor') || 0);
                    suma += valor;
                });

                const totalNodo = columna.querySelector('.count');
                if (totalNodo) {
                    totalNodo.textContent = formatearEuros(suma);
                }
            });
        };

        columnas.forEach((columna) => {
            Sortable.create(columna, {
                group: 'kanban-subastas',
                animation: 150,
                onEnd: async (evt) => {
                    const card = evt.item;
                    const subastaId = card.getAttribute('data-id');
                    const nuevaClasificacion = evt.to.getAttribute('data-columna');

                    // Actualizamos totales al instante, sin recargar.
                    actualizarTotalesColumnas();

                    try {
                        const response = await fetch(`/subastas/${subastaId}/clasificacion`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                clasificacion: nuevaClasificacion
                            })
                        });

                        if (!response.ok) {
                            throw new Error('No se pudo actualizar la clasificacion');
                        }
                    } catch (error) {
                        evt.from.appendChild(card);
                        actualizarTotalesColumnas();
                        alert('Error al actualizar. Vuelve a intentarlo.');
                    }
                }
            });
        });
    </script>
</body>
</html>
