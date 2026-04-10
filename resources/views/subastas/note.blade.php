<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota de Subasta | Subastas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/views/subastas-note.css') }}?v=4">
</head>
<body>
    <div class="wrap">
        <div class="top">
            <h1>Notas de la Subasta</h1>
            <a href="{{ route('subastas.dashboard') }}" class="back">← Volver</a>
        </div>

        <div class="card">
            <div class="info-section">
                <div class="info-label">Identificador</div>
                <div class="info-value">{{ $subasta->identificador }}</div>
            </div>

            @if ($errors->any())
                <div class="error-message">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            @if (session('success'))
                <div class="success-message">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Historial de notas -->
            <div class="notes-container">
                <h2>Historial de Notas</h2>

                @if ($notas->isEmpty())
                    <div class="empty-notes">
                        No hay notas aún. Crea la primera nota haciendo clic en el botón de abajo.
                    </div>
                @else
                    @foreach ($notas as $nota)
                        <div class="note-item">
                            <div class="note-header">
                                <span class="note-author">
                                    {{ $nota->usuario->name ?? 'Usuario desconocido' }}
                                </span>
                                <span class="note-datetime">
                                    {{ $nota->created_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}
                                </span>
                            </div>
                            <div class="note-content-text">
                                {{ $nota->contenido }}
                            </div>
                            <div class="note-actions-inline">
                                <button type="button" class="btn-edit js-edit-note-btn" data-note-id="{{ $nota->id }}">Editar</button>
                                <form method="POST" action="{{ route('subastas.destroyNote', [$subasta->id, $nota->id]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-delete-note">Eliminar</button>
                                </form>
                            </div>
                            <form method="POST" action="{{ route('subastas.updateNote', [$subasta->id, $nota->id]) }}" class="edit-note-form" id="edit-note-form-{{ $nota->id }}">
                                @csrf
                                @method('PUT')
                                <div class="form-group">
                                    <label for="contenido-editar-{{ $nota->id }}">Editar nota</label>
                                    <textarea id="contenido-editar-{{ $nota->id }}" name="contenido" required>{{ $nota->contenido }}</textarea>
                                </div>
                                <div class="button-group">
                                    <button type="submit" class="btn-edit">Guardar cambios</button>
                                    <button type="button" class="btn-back js-cancel-edit-note" data-note-id="{{ $nota->id }}">Cancelar</button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                @endif
            </div>

            <!-- Botón para mostrar formulario -->
            <button class="add-note-btn" id="toggleFormBtn">+ Agregar Nueva Nota</button>

            <!-- Formulario para agregar nota (oculto por defecto) -->
            <div class="add-note-section" id="noteForm">
                <h3>Nueva Nota</h3>
                <form method="POST" action="{{ route('subastas.storeNote', $subasta->id) }}">
                    @csrf
                    <div class="form-group">
                        <label for="contenido">Tu nota:</label>
                        <textarea 
                            id="contenido" 
                            name="contenido" 
                            placeholder="Escribe tu nota aquí..." 
                            required
                            @error('contenido') aria-invalid="true" @enderror
                        ></textarea>
                        @error('contenido')
                            <small style="color: #ff6b6b;">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="button-group">
                        <button type="submit" class="btn-edit">Guardar Nota</button>
                        <button type="button" class="btn-view" id="cancelFormBtn">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="actions">
            <a href="{{ route('subastas.show', $subasta->id) }}" class="btn-view">Ver subasta</a>
            <a href="{{ route('subastas.edit', $subasta->id) }}" class="btn-edit">Editar</a>
            <a href="{{ route('subastas.dashboard') }}" class="btn-back">Volver al listado</a>
        </div>
    </div>

    <script>
        const toggleBtn = document.getElementById('toggleFormBtn');
        const noteForm = document.getElementById('noteForm');
        const cancelBtn = document.getElementById('cancelFormBtn');
        const contenidoInput = document.getElementById('contenido');
        const editButtons = document.querySelectorAll('.js-edit-note-btn');
        const cancelEditButtons = document.querySelectorAll('.js-cancel-edit-note');

        // Mostrar formulario al hacer click en el botón
        toggleBtn.addEventListener('click', () => {
            noteForm.classList.toggle('active');
            if (noteForm.classList.contains('active')) {
                contenidoInput.focus();
                toggleBtn.textContent = '- Cerrar';
            } else {
                toggleBtn.textContent = '+ Agregar Nueva Nota';
            }
        });

        // Ocultar formulario al hacer click en cancelar
        cancelBtn.addEventListener('click', () => {
            noteForm.classList.remove('active');
            toggleBtn.textContent = '+ Agregar Nueva Nota';
            contenidoInput.value = '';
        });

        // Mostrar formulario de edición de una nota concreta
        editButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const noteId = button.dataset.noteId;
                const form = document.getElementById(`edit-note-form-${noteId}`);

                if (!form) {
                    return;
                }

                form.classList.add('active');
                const textarea = form.querySelector('textarea');
                if (textarea) {
                    textarea.focus();
                }
            });
        });

        // Ocultar formulario de edición
        cancelEditButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const noteId = button.dataset.noteId;
                const form = document.getElementById(`edit-note-form-${noteId}`);

                if (!form) {
                    return;
                }

                form.classList.remove('active');
            });
        });
    </script>
</body>
</html>
