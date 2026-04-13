<?php

namespace App\Http\Controllers;

use App\Models\NotaSubasta;
use App\Models\Subasta;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WebSubastasController extends Controller
{
    // Este metodo muestra el tablero Kanban por clasificacion.
    public function kanban(): View
    {
        $this->intentarLoginDesdeAuth(request());

        if (! Auth::check()) {
            abort(403, 'Debes iniciar sesion.');
        }

        $subastas = Subasta::query()->orderByDesc('id')->get();

        $columnas = [
            'Nueva' => [],
            'Urgente' => [],
            'Para Licitar' => [],
            'Para revisar' => [],
            'Descartada' => [],
        ];

        foreach ($subastas as $subasta) {
            $clasificacion = $this->normalizarClasificacionKanban((string) ($subasta->clasificacion ?? ''));

            if (! array_key_exists($clasificacion, $columnas)) {
                $clasificacion = 'Nueva';
            }

            $columnas[$clasificacion][] = $subasta;
        }

        return view('subastas.kanban', [
            'columnas' => $columnas,
        ]);
    }

    // Este metodo muestra el formulario para crear subasta.
    public function create(): View
    {
        // Intentamos login puente por si venimos desde Auth con firma.
        $this->intentarLoginDesdeAuth(request());

        // Si no hay sesion, paramos aqui.
        if (! Auth::check()) {
            abort(403, 'Debes iniciar sesion para crear una subasta.');
        }

        return view('subastas.create');
    }

    // Este metodo guarda una subasta creada manualmente desde formulario.
    public function store(Request $request): RedirectResponse
    {
        // Intentamos login puente por si venimos desde Auth con firma.
        $this->intentarLoginDesdeAuth($request);

        // Si no hay sesion, paramos aqui.
        if (! Auth::check()) {
            abort(403, 'Debes iniciar sesion para crear una subasta.');
        }

        // Validamos datos basicos del formulario.
        $datosDelFormulario = $request->validate([
            'estado' => ['required', 'in:Pendiente,Activa,Cancelada,Finalizada'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_conclusion' => ['nullable', 'date'],
            'cantidad_reclamada' => ['nullable', 'numeric', 'min:0'],
            'valor_subasta' => ['nullable', 'numeric', 'min:0'],
            'tasacion' => ['nullable', 'numeric', 'min:0'],
            'puja_minima' => ['nullable', 'numeric', 'min:0'],
            'importe_deposito' => ['nullable', 'numeric', 'min:0'],
            'datos_bien_subastado' => ['nullable', 'string'],
            'lotes' => ['nullable', 'string', 'max:255'],
            'tramos_entre_pujas' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string'],
            'documentos_adjuntos' => ['nullable', 'array'],
            'documentos_adjuntos.*' => ['file', 'max:10240'],
        ]);

        // Preparamos un array para guardar rutas de archivos subidos.
        $rutasDeDocumentosGuardados = [];

        // Si hay archivos, los guardamos uno por uno.
        if ($request->hasFile('documentos_adjuntos')) {
            $archivosSubidos = $request->file('documentos_adjuntos');

            foreach ($archivosSubidos as $archivoActual) {
                $rutaGuardada = $archivoActual->store('subastas', 'public');
                $rutasDeDocumentosGuardados[] = $rutaGuardada;
            }
        }

        // Creamos la subasta en base de datos.
        Subasta::create([
            'user_id' => Auth::id(),
            'identificador' => $this->generarIdentificador(),
            'estado' => $datosDelFormulario['estado'],
            'tipo_subasta' => null,
            'cuenta_expediente' => null,
            'anuncio_boe' => null,
            'fecha_inicio' => $datosDelFormulario['fecha_inicio'] ?? null,
            'fecha_conclusion' => $datosDelFormulario['fecha_conclusion'] ?? null,
            'cantidad_reclamada' => $datosDelFormulario['cantidad_reclamada'] ?? null,
            'valor_subasta' => $datosDelFormulario['valor_subasta'] ?? null,
            'tasacion' => $datosDelFormulario['tasacion'] ?? null,
            'puja_minima' => $datosDelFormulario['puja_minima'] ?? null,
            'importe_deposito' => $datosDelFormulario['importe_deposito'] ?? null,
            'datos_bien_subastado' => $datosDelFormulario['datos_bien_subastado'] ?? null,
            'lotes' => $datosDelFormulario['lotes'] ?? null,
            'tramos_entre_pujas' => $datosDelFormulario['tramos_entre_pujas'] ?? null,
            'observaciones' => $datosDelFormulario['observaciones'] ?? null,
            'documentos_adjuntos' => $rutasDeDocumentosGuardados,
        ]);

        return redirect()->route('subastas.dashboard')->with('ok', 'Subasta creada exitosamente.');
    }

    // Este metodo muestra la vista de edicion usando la vista create reutilizada.
    public function edit(Subasta $subasta): View
    {
        $this->intentarLoginDesdeAuth(request());

        if (! Auth::check()) {
            abort(403, 'Debes iniciar sesion para editar una subasta.');
        }

        return view('subastas.create', [
            'subasta' => $subasta,
        ]);
    }

    // Este metodo muestra la vista de edicion dedicada.
    public function showEdit(Subasta $subasta): View
    {
        $this->intentarLoginDesdeAuth(request());

        if (! Auth::check()) {
            abort(403, 'Debes iniciar sesion para editar una subasta.');
        }

        return view('subastas.edit', [
            'subasta' => $subasta,
        ]);
    }

    // Este metodo guarda cambios de una subasta.
    public function update(Request $request, Subasta $subasta): RedirectResponse
    {
        $this->intentarLoginDesdeAuth($request);

        if (! Auth::check()) {
            abort(403, 'Debes iniciar sesion para editar una subasta.');
        }

        $datosDelFormulario = $request->validate([
            'estado' => ['required', 'in:Pendiente,Activa,Cancelada,Finalizada'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_conclusion' => ['nullable', 'date'],
            'cantidad_reclamada' => ['nullable', 'numeric', 'min:0'],
            'valor_subasta' => ['nullable', 'numeric', 'min:0'],
            'tasacion' => ['nullable', 'numeric', 'min:0'],
            'puja_minima' => ['nullable', 'numeric', 'min:0'],
            'importe_deposito' => ['nullable', 'numeric', 'min:0'],
            'datos_bien_subastado' => ['nullable', 'string'],
            'lotes' => ['nullable', 'string', 'max:255'],
            'tramos_entre_pujas' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $subasta->update([
            'estado' => $datosDelFormulario['estado'],
            'fecha_inicio' => $datosDelFormulario['fecha_inicio'] ?? null,
            'fecha_conclusion' => $datosDelFormulario['fecha_conclusion'] ?? null,
            'cantidad_reclamada' => $datosDelFormulario['cantidad_reclamada'] ?? null,
            'valor_subasta' => $datosDelFormulario['valor_subasta'] ?? null,
            'tasacion' => $datosDelFormulario['tasacion'] ?? null,
            'puja_minima' => $datosDelFormulario['puja_minima'] ?? null,
            'importe_deposito' => $datosDelFormulario['importe_deposito'] ?? null,
            'datos_bien_subastado' => $datosDelFormulario['datos_bien_subastado'] ?? null,
            'lotes' => $datosDelFormulario['lotes'] ?? null,
            'tramos_entre_pujas' => $datosDelFormulario['tramos_entre_pujas'] ?? null,
            'observaciones' => $datosDelFormulario['observaciones'] ?? null,
        ]);

        return redirect()->route('subastas.edit', $subasta)->with('ok', 'Subasta actualizada.');
    }

    // Este metodo cambia solo el estado.
    public function updateStatus(Request $request, Subasta $subasta): RedirectResponse
    {
        $this->intentarLoginDesdeAuth($request);

        if (! Auth::check()) {
            abort(403, 'Debes iniciar sesion para editar una subasta.');
        }

        $datosDelFormulario = $request->validate([
            'estado' => ['required', 'in:Pendiente,Activa,Cancelada,Finalizada'],
        ]);

        $subasta->update([
            'estado' => $datosDelFormulario['estado'],
        ]);

        return redirect()->route('subastas.dashboard')->with('ok', 'Subasta actualizada correctamente.');
    }

    // Actualiza clasificacion al mover tarjeta en Kanban.
    public function updateClasificacionKanban(Request $request, Subasta $subasta): JsonResponse
    {
        $this->intentarLoginDesdeAuth($request);

        if (! Auth::check()) {
            return response()->json([
                'ok' => false,
                'message' => 'Debes iniciar sesion.',
            ], 403);
        }

        $datos = $request->validate([
            'clasificacion' => ['required', 'in:Nueva,Urgente,Para Licitar,Para revisar,Descartada'],
        ]);

        $clasificacionNormalizada = $this->normalizarClasificacionKanban($datos['clasificacion']);

        $subasta->update([
            'clasificacion' => $clasificacionNormalizada,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Clasificacion actualizada.',
            'data' => [
                'id' => $subasta->id,
                'clasificacion' => $subasta->clasificacion,
            ],
        ]);
    }

    private function normalizarClasificacionKanban(string $clasificacion): string
    {
        $valor = trim(mb_strtolower($clasificacion, 'UTF-8'));

        return match ($valor) {
            'nueva' => 'Nueva',
            'urgente' => 'Urgente',
            'para licitar' => 'Para Licitar',
            'aprobada' => 'Para revisar',
            'para revisar' => 'Para revisar',
            'descartada' => 'Descartada',
            default => 'Nueva',
        };
    }

    // Este metodo muestra el dashboard visual.
    public function dashboard(): View
    {
        $this->intentarLoginDesdeAuth(request());

        if (! Auth::check()) {
            abort(403, 'Debes iniciar sesion.');
        }

        $request = request();

        $subastasQuery = Subasta::query();

        $estado = trim((string) $request->query('estado', ''));
        if ($estado !== '') {
            $subastasQuery->where('estado', $estado);
        }

        $fechaDesde = trim((string) $request->query('fecha_desde', ''));
        if ($fechaDesde !== '') {
            $fechaDesdeNormalizada = Carbon::createFromFormat('Y-m-d', $fechaDesde)->startOfDay();
            $subastasQuery->where('fecha_conclusion', '>=', $fechaDesdeNormalizada);
        }

        $fechaHasta = trim((string) $request->query('fecha_hasta', ''));
        if ($fechaHasta !== '') {
            $fechaHastaNormalizada = Carbon::createFromFormat('Y-m-d', $fechaHasta)->endOfDay();
            $subastasQuery->where('fecha_conclusion', '<=', $fechaHastaNormalizada);
        }

        $direccionClasificacion = strtolower((string) $request->query('orden_clasificacion', ''));
        if (in_array($direccionClasificacion, ['asc', 'desc'], true)) {
            $subastasQuery->orderBy('clasificacion', $direccionClasificacion);
        }

        $direccionFechaConclusion = strtolower((string) $request->query('orden_fecha_conclusion', 'desc'));
        if (in_array($direccionFechaConclusion, ['asc', 'desc'], true)) {
            $subastasQuery->orderBy('fecha_conclusion', $direccionFechaConclusion);
        }

        $direccionValorTasacion = strtolower((string) $request->query('orden_valor_tasacion', ''));
        if (in_array($direccionValorTasacion, ['asc', 'desc'], true)) {
            $subastasQuery->orderBy('valor_tasacion', $direccionValorTasacion);
        }

        $subastas = $subastasQuery->get();

        return view('subastas.dashboard', [
            'subastas' => $subastas,
        ]);
    }

    // Este metodo muestra detalle visual de una subasta.
    public function show(Subasta $subasta): View
    {
        $this->intentarLoginDesdeAuth(request());

        if (! Auth::check()) {
            abort(403, 'Debes iniciar sesion para ver una subasta.');
        }

        return view('subastas.show', [
            'subasta' => $subasta,
        ]);
    }

    // Este metodo muestra la vista de nota de una subasta.
    public function showNote(Subasta $subasta): View
    {
        $this->intentarLoginDesdeAuth(request());

        if (! Auth::check()) {
            abort(403, 'Debes iniciar sesion para ver la nota.');
        }

        // Traer todas las notas ordenadas por más antigua primero
        $notas = $subasta->notas()->with('usuario')->orderBy('created_at', 'asc')->get();

        return view('subastas.note', [
            'subasta' => $subasta,
            'notas' => $notas,
        ]);
    }

    // Este metodo guarda una nueva nota en una subasta
    public function storeNote(Request $request, Subasta $subasta): RedirectResponse
    {
        $this->intentarLoginDesdeAuth($request);

        if (! Auth::check()) {
            abort(403, 'Debes iniciar sesion para crear una nota.');
        }

        $request->validate([
            'contenido' => 'required|string|min:1|max:2000',
        ]);

        NotaSubasta::create([
            'subasta_id' => $subasta->id,
            'user_id' => Auth::id(),
            'contenido' => trim($request->input('contenido')),
        ]);

        return redirect()->route('subastas.note', $subasta->id)->with('success', 'Nota agregada correctamente.');
    }

    // Este metodo actualiza una nota existente de una subasta.
    public function updateNote(Request $request, Subasta $subasta, NotaSubasta $nota): RedirectResponse
    {
        $this->intentarLoginDesdeAuth($request);

        if (! Auth::check()) {
            abort(403, 'Debes iniciar sesion para editar una nota.');
        }

        if ((int) $nota->subasta_id !== (int) $subasta->id) {
            abort(404);
        }

        $request->validate([
            'contenido' => 'required|string|min:1|max:2000',
        ]);

        $nota->update([
            'contenido' => trim((string) $request->input('contenido')),
        ]);

        return redirect()->route('subastas.note', $subasta->id)->with('success', 'Nota actualizada correctamente.');
    }

    // Este metodo elimina una nota existente de una subasta.
    public function destroyNote(Request $request, Subasta $subasta, NotaSubasta $nota): RedirectResponse
    {
        $this->intentarLoginDesdeAuth($request);

        if (! Auth::check()) {
            abort(403, 'Debes iniciar sesion para eliminar una nota.');
        }

        if ((int) $nota->subasta_id !== (int) $subasta->id) {
            abort(404);
        }

        $nota->delete();

        return redirect()->route('subastas.note', $subasta->id)->with('success', 'Nota eliminada correctamente.');
    }

    // Este metodo elimina una subasta.
    public function destroy(Request $request, Subasta $subasta): JsonResponse|RedirectResponse
    {
        $this->intentarLoginDesdeAuth($request);

        if (! Auth::check()) {
            abort(403, 'Debes iniciar sesion para eliminar una subasta.');
        }

        $subasta->delete();

        $esperaJson = $request->expectsJson() || $request->wantsJson() || $request->ajax();

        if ($esperaJson) {
            return response()->json([
                'ok' => true,
                'message' => 'Subasta eliminada.',
            ]);
        }

        return redirect()->route('subastas.dashboard')->with('ok', 'Subasta eliminada.');
    }

    // Este metodo cierra la sesion local del microservicio listado.
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $urlLogin = (string) env('AUTH_LOGIN_URL', 'https://media-vegetal-auth.cedesa.es/login');

        return redirect()->away($urlLogin);
    }

    // Este metodo genera un identificador unico tipo SUB-XXXXXXX.
    private function generarIdentificador(): string
    {
        do {
            $identificador = 'SUB-' . strtoupper(Str::random(8));
        } while (Subasta::where('identificador', $identificador)->exists());

        return $identificador;
    }

    // Este metodo intenta hacer login puente desde Auth usando un token simple.
    private function intentarLoginDesdeAuth(Request $request): void
    {
        // Si ya hay sesion, no hacemos nada.
        if (Auth::check()) {
            return;
        }

        // Leemos parametros del puente desde query o body.
        $email = (string) ($request->input('bridge_email') ?? $request->query('bridge_email', ''));
        $name = (string) ($request->input('bridge_name') ?? $request->query('bridge_name', ''));
        $tokenRecibido = (string) ($request->input('bridge_token') ?? $request->query('bridge_token', ''));

        // Si falta cualquier dato, no podemos hacer login puente.
        if ($email === '' || $name === '' || $tokenRecibido === '') {
            return;
        }

        // Leemos token correcto desde .env.
        $tokenCorrecto = (string) env('SUBASTAS_BRIDGE_TOKEN', 'token-puente-dev');

        // Si el token no coincide, no iniciamos sesion.
        if (! hash_equals($tokenCorrecto, $tokenRecibido)) {
            return;
        }

        // Si la firma es valida, creamos/recuperamos usuario y hacemos login local.
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(Str::random(40)),
            ]
        );

        Auth::login($user);
        $request->session()->regenerate();
        
        // Guardamos parametros de bridge en sesion para uso posterior
        $request->session()->put([
            'bridge_email' => $email,
            'bridge_name' => $name,
            'bridge_token' => $tokenRecibido,
        ]);
    }
}
