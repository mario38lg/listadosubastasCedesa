const formulariosDeBorrado = document.querySelectorAll('.js-delete-form');

        async function borrarSubastaSinRecargar(evento) {
            evento.preventDefault();

            const formulario = evento.currentTarget;
            const boton = formulario.querySelector('button[type="submit"]');
            const fila = formulario.closest('tr[data-subasta-row]');

            if (!boton || !fila) {
                return;
            }

            boton.disabled = true;

            try {
                const datos = new FormData(formulario);
                const respuesta = await fetch(formulario.action, {
                    method: 'POST',
                    body: datos,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });

                if (!respuesta.ok) {
                    throw new Error('No se pudo eliminar la subasta.');
                }

                fila.remove();

                const filasRestantes = document.querySelectorAll('tr[data-subasta-row]').length;
                if (filasRestantes === 0) {
                    const contenedorTabla = document.querySelector('.table-wrap');
                    const scrollTabla = document.querySelector('.table-scroll');

                    if (scrollTabla) {
                        scrollTabla.remove();
                    }

                    if (contenedorTabla && !contenedorTabla.querySelector('.empty')) {
                        const mensajeVacio = document.createElement('div');
                        mensajeVacio.className = 'empty';
                        mensajeVacio.textContent = 'No hay subastas disponibles.';
                        contenedorTabla.appendChild(mensajeVacio);
                    }
                }
            } catch (error) {
                alert('Error al borrar la subasta. Intenta otra vez.');
                boton.disabled = false;
            }
        }

        formulariosDeBorrado.forEach(function (formulario) {
            formulario.addEventListener('submit', borrarSubastaSinRecargar);
        });