<?php

namespace App\Http\Controllers;

use App\Models\Subasta;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ApiSubastasController extends Controller
{
    // Este metodo devuelve una lista simple de subastas para API.
    

    // Este metodo recibe datos del scraper y crea subasta en BD.
    public function storeFromScraperApi(Request $request): JsonResponse
    {
        // Paso 1: leer secreto correcto desde .env.
        $secretoCorrecto = (string) env('SCRAPER_INGEST_SECRET', 'dev-scraper-secret');

        // Paso 2: leer secreto que llega desde el scraper.
        $secretoRecibido = (string) $request->header('X-Scraper-Secret', '');

        // Paso 3: comparar secretos de forma segura.
        if (! hash_equals($secretoCorrecto, $secretoRecibido)) {
            return response()->json([
                'ok' => false,
                'message' => 'No autorizado para crear subastas desde scraper.',
            ], 401);
        }

        // Paso 4: validar datos recibidos.
        $datosRecibidos = $request->validate([
            'identificador' => ['nullable', 'string', 'max:255'],
            'anuncio_boe' => ['nullable', 'string', 'max:1200'],
            'estado' => ['nullable', 'string', 'max:255'],
            'fecha_inicio' => ['nullable', 'string', 'max:255'],
            'fecha_conclusion' => ['nullable', 'string', 'max:255'],
            'cantidad_reclamada' => ['nullable', 'numeric'],
            'lotes' => ['nullable', 'string', 'max:255'],
            'valor_subasta' => ['nullable', 'numeric'],
            'valor_tasacion' => ['nullable', 'numeric'],
            'tasacion' => ['nullable', 'numeric'],
            'puja_minima' => ['nullable', 'numeric'],
            'tramos_entre_pujas' => ['nullable', 'string', 'max:255'],
            'importe_deposito' => ['nullable', 'numeric'],
            'datos_bien_subastado' => ['nullable', 'string'],
            'observaciones' => ['nullable', 'string'],
            'documentos_adjuntos' => ['nullable', 'array'],
            'documentos_adjuntos.*.titulo' => ['nullable', 'string'],
            'documentos_adjuntos.*.url' => ['nullable', 'string'],
            'owner_email' => ['nullable', 'email', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'owner_token' => ['nullable', 'string', 'max:255'],
        ]);

        // Paso 5: decidir el propietario final de la subasta importada.
        $usuarioPropietario = null;

        $ownerEmail = trim((string) ($datosRecibidos['owner_email'] ?? ''));
        $ownerName = trim((string) ($datosRecibidos['owner_name'] ?? ''));
        $ownerToken = trim((string) ($datosRecibidos['owner_token'] ?? ''));
        $tokenPuenteCorrecto = (string) env('SUBASTAS_BRIDGE_TOKEN', 'token-puente-dev');

        if ($ownerEmail !== '' && $ownerName !== '' && $ownerToken !== '' && hash_equals($tokenPuenteCorrecto, $ownerToken)) {
            $usuarioPropietario = User::firstOrCreate(
                ['email' => $ownerEmail],
                [
                    'name' => $ownerName,
                    'password' => Hash::make(Str::random(40)),
                    'email_verified_at' => now(),
                ]
            );
        }

        if (! $usuarioPropietario) {
            $usuarioPropietario = User::firstOrCreate(
                ['email' => 'scraper@subastas.local'],
                [
                    'name' => 'Scraper Service',
                    'password' => Hash::make(Str::random(40)),
                    'email_verified_at' => now(),
                ]
            );
        }

        // Parseamos fechas antes de crear o actualizar para no perder la hora.
        $fechaInicioParseada = $this->parsearFechaSinRegex($datosRecibidos['fecha_inicio'] ?? null);
        $fechaConclusionParseada = $this->parsearFechaSinRegex($datosRecibidos['fecha_conclusion'] ?? null);

        Log::info('Scraper import fechas recibidas', [
            'identificador' => $datosRecibidos['identificador'] ?? null,
            'anuncio_boe' => $datosRecibidos['anuncio_boe'] ?? null,
            'fecha_inicio_raw' => $datosRecibidos['fecha_inicio'] ?? null,
            'fecha_conclusion_raw' => $datosRecibidos['fecha_conclusion'] ?? null,
            'fecha_inicio_parseada' => $fechaInicioParseada,
            'fecha_conclusion_parseada' => $fechaConclusionParseada,
            'payload_keys' => array_keys($datosRecibidos),
        ]);

        // Paso 6: comprobar si ya existe identificador.
        $anuncioBoeRecibido = '';
        if (isset($datosRecibidos['anuncio_boe'])) {
            $anuncioBoeRecibido = trim((string) $datosRecibidos['anuncio_boe']);
        }

        if ($anuncioBoeRecibido !== '') {
            $subastaExistentePorAnuncio = Subasta::where('anuncio_boe', $anuncioBoeRecibido)->first();

            if ($subastaExistentePorAnuncio) {
                // Actualizar los datos en lugar de solo devolverlos
                $subastaExistentePorAnuncio->update([
                    'identificador' => $datosRecibidos['identificador'] ?? $subastaExistentePorAnuncio->identificador,
                    'estado' => $datosRecibidos['estado'] ?? $subastaExistentePorAnuncio->estado,
                    'fecha_inicio' => $fechaInicioParseada ?? $subastaExistentePorAnuncio->fecha_inicio,
                    'fecha_conclusion' => $fechaConclusionParseada ?? $subastaExistentePorAnuncio->fecha_conclusion,
                    'cantidad_reclamada' => $datosRecibidos['cantidad_reclamada'] ?? $subastaExistentePorAnuncio->cantidad_reclamada,
                    'valor_subasta' => $datosRecibidos['valor_subasta'] ?? $subastaExistentePorAnuncio->valor_subasta,
                    'valor_tasacion' => $datosRecibidos['valor_tasacion'] ?? $datosRecibidos['tasacion'] ?? $subastaExistentePorAnuncio->valor_tasacion,
                    'tasacion' => $datosRecibidos['tasacion'] ?? $subastaExistentePorAnuncio->tasacion,
                    'puja_minima' => $datosRecibidos['puja_minima'] ?? $subastaExistentePorAnuncio->puja_minima,
                    'importe_deposito' => $datosRecibidos['importe_deposito'] ?? $subastaExistentePorAnuncio->importe_deposito,
                    'datos_bien_subastado' => $datosRecibidos['datos_bien_subastado'] ?? $subastaExistentePorAnuncio->datos_bien_subastado,
                    'lotes' => $datosRecibidos['lotes'] ?? $subastaExistentePorAnuncio->lotes,
                    'tramos_entre_pujas' => $datosRecibidos['tramos_entre_pujas'] ?? $subastaExistentePorAnuncio->tramos_entre_pujas,
                    'observaciones' => $datosRecibidos['observaciones'] ?? $subastaExistentePorAnuncio->observaciones,
                    'documentos_adjuntos' => isset($datosRecibidos['documentos_adjuntos']) ? $datosRecibidos['documentos_adjuntos'] : $subastaExistentePorAnuncio->documentos_adjuntos,
                ]);

                return response()->json([
                    'ok' => true,
                    'message' => 'La subasta ya existia por anuncio BOE. Se ha actualizado con los nuevos datos.',
                    'data' => [
                        'id' => $subastaExistentePorAnuncio->id,
                        'identificador' => $subastaExistentePorAnuncio->identificador,
                        'estado' => $subastaExistentePorAnuncio->estado,
                    ],
                ], 200);
            }
        }

        $identificadorRecibido = '';
        if (isset($datosRecibidos['identificador'])) {
            $identificadorRecibido = trim((string) $datosRecibidos['identificador']);
        }

        if ($identificadorRecibido !== '') {
            $subastaExistente = Subasta::where('identificador', $identificadorRecibido)->first();

            if ($subastaExistente) {
                $subastaExistente->update([
                    'estado' => $datosRecibidos['estado'] ?? $subastaExistente->estado,
                    'fecha_inicio' => $fechaInicioParseada ?? $subastaExistente->fecha_inicio,
                    'fecha_conclusion' => $fechaConclusionParseada ?? $subastaExistente->fecha_conclusion,
                    'cantidad_reclamada' => $datosRecibidos['cantidad_reclamada'] ?? $subastaExistente->cantidad_reclamada,
                    'valor_subasta' => $datosRecibidos['valor_subasta'] ?? $subastaExistente->valor_subasta,
                    'valor_tasacion' => $datosRecibidos['valor_tasacion'] ?? $datosRecibidos['tasacion'] ?? $subastaExistente->valor_tasacion,
                    'tasacion' => $datosRecibidos['tasacion'] ?? $subastaExistente->tasacion,
                    'puja_minima' => $datosRecibidos['puja_minima'] ?? $subastaExistente->puja_minima,
                    'importe_deposito' => $datosRecibidos['importe_deposito'] ?? $subastaExistente->importe_deposito,
                    'datos_bien_subastado' => $datosRecibidos['datos_bien_subastado'] ?? $subastaExistente->datos_bien_subastado,
                    'lotes' => $datosRecibidos['lotes'] ?? $subastaExistente->lotes,
                    'tramos_entre_pujas' => $datosRecibidos['tramos_entre_pujas'] ?? $subastaExistente->tramos_entre_pujas,
                    'observaciones' => $datosRecibidos['observaciones'] ?? $subastaExistente->observaciones,
                    'documentos_adjuntos' => isset($datosRecibidos['documentos_adjuntos']) ? $datosRecibidos['documentos_adjuntos'] : $subastaExistente->documentos_adjuntos,
                ]);

                return response()->json([
                    'ok' => true,
                    'message' => 'La subasta ya existia por identificador. Se ha actualizado con los nuevos datos.',
                    'data' => [
                        'id' => $subastaExistente->id,
                        'identificador' => $subastaExistente->identificador,
                        'estado' => $subastaExistente->estado,
                    ],
                ], 200);
            }
        }

        // Paso 7: preparar identificador final.
        $identificadorFinal = $identificadorRecibido;
        if ($identificadorFinal === '') {
            $identificadorFinal = $this->generarIdentificador();
        }

        // Paso 8: preparar estado final.
        $estadoFinal = trim((string) ($datosRecibidos['estado'] ?? ''));
        if ($estadoFinal === '') {
            $estadoFinal = 'Activa';
        }

        // Paso 9: parsear importes con funciones basicas, sin regex.
        $cantidadReclamadaParseada = $this->parsearImporteSinRegex($datosRecibidos['cantidad_reclamada'] ?? null);
        $valorSubastaParseado = $this->parsearImporteSinRegex($datosRecibidos['valor_subasta'] ?? null);
        $tasacionParseada = $this->parsearImporteSinRegex($datosRecibidos['tasacion'] ?? null);
        $valorTasacionParseado = $this->parsearImporteSinRegex($datosRecibidos['valor_tasacion'] ?? $datosRecibidos['tasacion'] ?? null);
        $pujaMinimaParseada = $this->parsearImporteSinRegex($datosRecibidos['puja_minima'] ?? null);
        $importeDepositoParseado = $this->parsearImporteSinRegex($datosRecibidos['importe_deposito'] ?? null);

        // Paso 10: normalizar documentos adjuntos.
        $documentosNormalizados = $this->normalizarDocumentosAdjuntos($datosRecibidos['documentos_adjuntos'] ?? null);

        // Paso 11: crear subasta.
        $subastaCreada = Subasta::create([
            'user_id' => $usuarioPropietario->id,
            'identificador' => $identificadorFinal,
            'estado' => $estadoFinal,
            'tipo_subasta' => 'No hay',
            'cuenta_expediente' => 'No hay',
            'anuncio_boe' => $anuncioBoeRecibido !== '' ? $anuncioBoeRecibido : null,
            'fecha_inicio' => $fechaInicioParseada,
            'fecha_conclusion' => $fechaConclusionParseada,
            'cantidad_reclamada' => $cantidadReclamadaParseada,
            'valor_subasta' => $valorSubastaParseado,
            'valor_tasacion' => $valorTasacionParseado,
            'tasacion' => $tasacionParseada,
            'puja_minima' => $pujaMinimaParseada,
            'importe_deposito' => $importeDepositoParseado,
            'datos_bien_subastado' => $this->normalizarTextoConNoHay($datosRecibidos['datos_bien_subastado'] ?? null),
            'lotes' => $this->normalizarTextoConNoHay($datosRecibidos['lotes'] ?? null),
            'tramos_entre_pujas' => $this->normalizarTextoConNoHay($datosRecibidos['tramos_entre_pujas'] ?? null),
            'observaciones' => $this->normalizarTextoConNoHay($datosRecibidos['observaciones'] ?? null),
            'documentos_adjuntos' => $documentosNormalizados,
        ]);

        // Si viene con observaciones, crear la primera nota automaticamente
        $observacionesRecibidas = trim((string) ($datosRecibidos['observaciones'] ?? ''));
        if ($observacionesRecibidas !== '' && $observacionesRecibidas !== 'No hay') {
            \App\Models\NotaSubasta::create([
                'subasta_id' => $subastaCreada->id,
                'user_id' => $usuarioPropietario->id,
                'contenido' => $observacionesRecibidas,
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Subasta creada correctamente en listadoSubastas.',
            'data' => [
                'id' => $subastaCreada->id,
                'identificador' => $subastaCreada->identificador,
                'estado' => $subastaCreada->estado,
            ],
        ], 201);
    }

    // Genera un identificador unico tipo SUB-XXXXXXX.
    private function generarIdentificador(): string
    {
        do {
            $identificadorGenerado = 'SUB-' . strtoupper(Str::random(8));
        } while (Subasta::where('identificador', $identificadorGenerado)->exists());

        return $identificadorGenerado;
    }

    // Parsea importes con reglas basicas.
    // Acepta formatos tipo: 1.234,56 | 1234.56 | 1200
    private function parsearImporteSinRegex(?string $textoOriginal): ?float
    {
        if ($textoOriginal === null) {
            return 0.0;
        }

        $textoLimpio = trim($textoOriginal);
        if ($textoLimpio === '') {
            return 0.0;
        }

        $textoEnMinusculas = strtolower($textoLimpio);
        if (str_contains($textoEnMinusculas, 'sin') || str_contains($textoEnMinusculas, 'no hay') || str_contains($textoEnMinusculas, 'n/a')) {
            return 0.0;
        }

        // Limpiamos simbolos tipicos.
        $textoSinSimbolos = str_replace(['EUR', '€', ' '], '', $textoLimpio);

        // Nos quedamos solo con numeros, coma, punto y signo negativo.
        $permitidos = '';
        $longitud = strlen($textoSinSimbolos);
        for ($i = 0; $i < $longitud; $i++) {
            $caracter = $textoSinSimbolos[$i];
            if (ctype_digit($caracter) || $caracter === ',' || $caracter === '.' || $caracter === '-') {
                $permitidos .= $caracter;
            }
        }

        if ($permitidos === '' || $permitidos === '-' || $permitidos === ',' || $permitidos === '.') {
            return 0.0;
        }

        // Si trae ambos separadores, asumimos punto miles y coma decimal (formato ES).
        if (str_contains($permitidos, '.') && str_contains($permitidos, ',')) {
            $permitidos = str_replace('.', '', $permitidos);
            $permitidos = str_replace(',', '.', $permitidos);
        } elseif (str_contains($permitidos, ',')) {
            // Si solo trae coma, la usamos como decimal.
            $permitidos = str_replace(',', '.', $permitidos);
        }

        if (! is_numeric($permitidos)) {
            return 0.0;
        }

        return (float) $permitidos;
    }

    // Parsea fecha con estrategia directa.
    // Soporta textos con "ISO:" y fechas normales que strtotime entienda.
    private function parsearFechaSinRegex(?string $textoOriginal): ?string
    {
        if ($textoOriginal === null) {
            return null;
        }

        $textoLimpio = trim($textoOriginal);
        if ($textoLimpio === '') {
            return null;
        }

        // Si aparece "ISO:", usamos ese valor primero porque incluye hora y zona.
        $candidato = $textoLimpio;
        $posicionIso = stripos($textoLimpio, 'ISO:');
        if ($posicionIso !== false) {
            $candidato = trim(substr($textoLimpio, $posicionIso + 4));

            if (str_contains($candidato, ')')) {
                $partesIso = explode(')', $candidato);
                $candidato = trim($partesIso[0]);
            }

            if ($candidato !== '') {
                try {
                    $fechaIso = new \DateTimeImmutable($candidato);

                    return $fechaIso->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
                    // Si falla el parseo ISO, seguimos con el parseo normal.
                }
            }
        }

        // Limpiamos ruido habitual.
        $candidato = str_replace(')', '', $candidato);
        $candidato = str_replace('T', ' ', $candidato);

        // Si hay texto entre parentesis al final, lo quitamos.
        if (str_contains($candidato, '(')) {
            $partes = explode('(', $candidato);
            $candidato = trim($partes[0]);
        }

        if ($candidato === '') {
            return null;
        }

        // Quitamos prefijo de etiqueta si viene en el mismo texto (p.ej. "Fecha de conclusión\t...").
        if (str_contains($candidato, "\t")) {
            $partesConTab = explode("\t", $candidato);
            $candidato = trim(end($partesConTab));
        }

        // Limpiamos sufijos de zona textual comunes.
        foreach ([' CET', ' CEST', ' UTC'] as $zonaTexto) {
            if (str_ends_with($candidato, $zonaTexto)) {
                $candidato = trim(substr($candidato, 0, -strlen($zonaTexto)));
            }
        }

        // Parseo explicito de formatos ES para no depender del locale de strtotime.
        $formatos = [
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd-m-Y H:i:s',
            'd-m-Y H:i',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
        ];

        foreach ($formatos as $formato) {
            $fecha = \DateTimeImmutable::createFromFormat($formato, $candidato);
            if ($fecha instanceof \DateTimeImmutable) {
                return $fecha->format('Y-m-d H:i:s');
            }
        }

        $timestamp = strtotime($candidato);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    // Limpia la estructura de documentos adjuntos para guardar solo titulo + url.
    private function normalizarDocumentosAdjuntos(?array $documentosRecibidos): ?array
    {
        if ($documentosRecibidos === null) {
            return null;
        }

        if (count($documentosRecibidos) === 0) {
            return null;
        }

        $documentosNormalizados = [];

        foreach ($documentosRecibidos as $documentoActual) {
            if (! is_array($documentoActual)) {
                continue;
            }

            $titulo = 'Documento adjunto';
            if (isset($documentoActual['titulo'])) {
                $tituloLeido = trim((string) $documentoActual['titulo']);
                if ($tituloLeido !== '') {
                    $titulo = $tituloLeido;
                }
            }

            $url = '';
            if (isset($documentoActual['url'])) {
                $url = trim((string) $documentoActual['url']);
            }

            if ($url === '') {
                continue;
            }

            $fila = [];
            $fila['titulo'] = $titulo;
            $fila['url'] = $url;
            $documentosNormalizados[] = $fila;
        }

        if (count($documentosNormalizados) === 0) {
            return null;
        }

        return $documentosNormalizados;
    }

    // Convierte vacios/N/A/sin/no hay al texto estandar "No hay".
    private function normalizarTextoConNoHay(?string $texto): string
    {
        $valor = trim((string) $texto);
        if ($valor === '') {
            return 'No hay';
        }

        $valorEnMinusculas = strtolower($valor);
        if ($valorEnMinusculas === 'n/a' || str_contains($valorEnMinusculas, 'sin') || str_contains($valorEnMinusculas, 'no hay')) {
            return 'No hay';
        }

        return $valor;
    }
}
