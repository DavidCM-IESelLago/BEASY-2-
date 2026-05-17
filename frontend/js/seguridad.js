// js/seguridad.js

// ── Inicialización ────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    const token = localStorage.getItem('jwt_token');
    if (!token) { window.location.href = 'inicio_sesion.html'; return; }

    try {
        const respuesta = await apiFetch('validate_helper.php');
        if (respuesta && respuesta.status === 'success') {
            document.body.style.display = 'flex';
            cargarPerfil();
        } else {
            localStorage.removeItem('jwt_token');
            window.location.href = 'inicio_sesion.html';
        }
    } catch (e) {
        document.body.style.display = 'flex';
    }
});

// ── Perfil ────────────────────────────────────────────────────────────────────
async function cargarPerfil() {
    const datos = await apiFetch('perfil.php');
    if (!datos || datos.status !== 'success') return;

    const nombreCompleto = datos.nombre + ' ' + datos.apellidos;
    const avatarUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(datos.iniciales)}&background=005bbf&color=fff`;

    document.getElementById('header-avatar').src        = avatarUrl;
    document.getElementById('header-nombre').textContent = datos.nombre + ' ' + datos.apellidos.split(' ')[0] + '.';
    document.getElementById('modal-avatar').src          = `https://ui-avatars.com/api/?name=${encodeURIComponent(nombreCompleto)}&background=005bbf&color=fff`;
    document.getElementById('modal-nombre').textContent  = nombreCompleto;
    document.getElementById('modal-email').textContent   = datos.email;
    document.getElementById('modal-id').textContent      = '#' + String(datos.id).padStart(6, '0');
}

// ── Modal usuario ─────────────────────────────────────────────────────────────
function toggleModal(show) {
    document.getElementById('modalUser').style.display = show ? 'flex' : 'none';
}

function cerrarSesion() {
    localStorage.removeItem('jwt_token');
    window.location.href = 'inicio_sesion.html';
}

// ── Stepper del formulario ────────────────────────────────────────────────────
function irAlPaso(n) {
    document.querySelectorAll('.form-step').forEach(sec => sec.classList.add('hidden'));
    const paso = document.getElementById('paso-' + n);
    if (paso) paso.classList.remove('hidden');

    if (n === 3) {
        document.getElementById('summary-type').innerText = document.getElementById('select_tipo').value;
        document.getElementById('summary-desc').innerText = document.getElementById('textarea_mensaje').value;
    }
}

// ── Enviar reporte ────────────────────────────────────────────────────────────
async function enviarReporte() {
    const tipo    = document.getElementById('select_tipo').value;
    const mensaje = document.getElementById('textarea_mensaje').value;
    const fecha   = new Date().toISOString().split('T')[0];

    try {
        const data = await apiFetch('CrearIncidencia.php', {
            method: 'POST',
            body: JSON.stringify({ tipo, mensaje, fecha })
        });

        if (data.status === 'success') {
            document.getElementById('num-ticket').textContent = data.ticket;
            irAlPaso(4);
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('No se pudo enviar el reporte. Revisa la consola.');
    }
}
