<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Empleado</title>
    <link rel="stylesheet" href="{{ asset('css/views/usuarios-create.css') }}">
</head>
<body>
    <div class="container">
        <h1>Registrar</h1>
        <p><a class="back-btn" href="{{ route('subastas.dashboard') }}">Volver al listado de subastas</a></p>

        @if ($errors->any())
            <div class="error-box">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('usuarios.store') }}">
            @csrf

            <label for="name">Nombre</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required>

            <label for="password">Contrasena</label>
            <input type="password" id="password" name="password" required>

            <button class="btn" type="submit">Guardar Empleado</button>
        </form>
    </div>
</body>
</html>
