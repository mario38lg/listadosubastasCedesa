<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Subasta | Subastas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('css/views/subastas-show.css') }}">
</head>
<body>
    <div class="wrap">
        <div class="top">
            <h1>Ver Subasta</h1>
            <a href="{{ route('subastas.dashboard') }}" class="back">← Volver</a>
        </div>

        <div class="card">
            <h2 class="card-title">Detalle de la Subasta</h2>
            @php
                $docs = $subasta->documentos_adjuntos ?? [];
            @endphp

            <table class="detail-table">
                <thead>
                    <tr>
                        <th>Campo</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="campo">Identificador</td>
                        <td>{{ $subasta->identificador }}</td>
                    </tr>
                    <tr>
                        <td class="campo">Estado</td>
                        <td>
                            <span class="estado {{ strtolower($subasta->estado) }}">{{ $subasta->estado }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="campo">Fecha de inicio</td>
                        <td>{{ $subasta->fecha_inicio?->format('d/m/Y H:i') ?? 'No hay' }}</td>
                    </tr>
                    <tr>
                        <td class="campo">Fecha de conclusión</td>
                        <td>{{ $subasta->fecha_conclusion?->format('d/m/Y H:i') ?? 'No hay' }}</td>
                    </tr>
                    <tr>
                        <td class="campo">Cantidad reclamada</td>
                        <td class="price">{{ number_format((float) ($subasta->cantidad_reclamada ?? 0), 2, ',', '.') }} €</td>
                    </tr>
                    <tr>
                        <td class="campo">Valor actual</td>
                        <td class="price">{{ number_format((float) ($subasta->valor_subasta ?? 0), 2, ',', '.') }} €</td>
                    </tr>
                    <tr>
                        <td class="campo">Tasación</td>
                        <td class="price">{{ number_format((float) ($subasta->tasacion ?? 0), 2, ',', '.') }} €</td>
                    </tr>
                    <tr>
                        <td class="campo">Puja mínima</td>
                        <td class="price">{{ number_format((float) ($subasta->puja_minima ?? 0), 2, ',', '.') }} €</td>
                    </tr>
                    <tr>
                        <td class="campo">Importe depósito</td>
                        <td class="price">{{ number_format((float) ($subasta->importe_deposito ?? 0), 2, ',', '.') }} €</td>
                    </tr>
                    <tr>
                        <td class="campo">Datos del bien</td>
                        <td>
                            @if($subasta->datos_bien_subastado)
                                <span style="white-space: pre-wrap; word-break: break-word;">{{ $subasta->datos_bien_subastado }}</span>
                            @else
                                <span style="color: #666; font-style: italic;">No especificado</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="campo">Lotes</td>
                        <td>{{ $subasta->lotes ?? 'No hay' }}</td>
                    </tr>
                    <tr>
                        <td class="campo">Tramos entre pujas</td>
                        <td>{{ $subasta->tramos_entre_pujas ?? 'No hay' }}</td>
                    </tr>
                    <tr>
                        <td class="campo">Observaciones</td>
                        <td>
                            @if($subasta->observaciones)
                                <span style="white-space: pre-wrap; word-break: break-word;">{{ $subasta->observaciones }}</span>
                            @else
                                <span style="color: #666; font-style: italic;">Sin observaciones</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="campo">Documentos adjuntos</td>
                        <td>
                            @if(is_array($docs) && count($docs) > 0)
                                <div style="display: grid; gap: 8px;">
                                    @foreach($docs as $doc)
                                        @php
                                            $titulo = null;
                                            $url = null;

                                            if (is_array($doc)) {
                                                $titulo = $doc['titulo'] ?? 'Documento adjunto';
                                                $url = $doc['url'] ?? null;
                                            } elseif (is_string($doc)) {
                                                if (preg_match('/^(.*)\((https?:\/\/[^)]+)\)$/', $doc, $m)) {
                                                    $titulo = trim($m[1]);
                                                    $url = trim($m[2]);
                                                } elseif (str_starts_with($doc, 'http://') || str_starts_with($doc, 'https://')) {
                                                    $titulo = 'Documento adjunto';
                                                    $url = $doc;
                                                } else {
                                                    $titulo = 'Documento adjunto';
                                                    $url = \Illuminate\Support\Facades\Storage::url($doc);
                                                }
                                            }
                                        @endphp

                                        @if(!empty($url))
                                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" download style="color: #4ade80; text-decoration: none; font-weight: 700;">
                                                {{ $titulo ?: 'Documento adjunto' }} - Descargar
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <span style="color: #666; font-style: italic;">Sin documentos adjuntos</span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="actions">
            <a href="{{ route('subastas.edit', $subasta->id) }}" class="btn-edit">Editar</a>
            <a href="{{ route('subastas.dashboard') }}" class="btn-back">Volver al Inicio</a>
        </div>
    </div>
</body>
</html>
