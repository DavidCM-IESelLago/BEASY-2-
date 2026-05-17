// js/admin.js

// ── Estado ────────────────────────────────────────────────────────────────────
let datosCargados = false;

// ── Inicialización ────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    const token = localStorage.getItem('jwt_token');
    if (!token) { window.location.href = 'inicio_sesion.html'; return; }

    const perfil = await apiFetch('perfil.php');
    if (!perfil || perfil.status !== 'success') {
        window.location.href = 'inicio_sesion.html';
        return;
    }

    const check = await apiFetch('admin.php?accion=incidencias');
    if (!check) {
        localStorage.removeItem('jwt_token');
        window.location.href = 'inicio_sesion.html';
        return;
    }

    document.body.style.display = 'flex';

    const nombreCompleto = perfil.nombre + ' ' + perfil.apellidos;
    const avatarUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(perfil.iniciales)}&background=005bbf&color=fff`;

    document.getElementById('header-avatar').src        = avatarUrl;
    document.getElementById('header-nombre').textContent = perfil.nombre + ' ' + perfil.apellidos.split(' ')[0] + '.';
    document.getElementById('modal-avatar').src          = `https://ui-avatars.com/api/?name=${encodeURIComponent(nombreCompleto)}&background=005bbf&color=fff`;
    document.getElementById('modal-nombre').textContent  = nombreCompleto;
    document.getElementById('modal-email').textContent   = perfil.email;
    document.getElementById('modal-id').textContent      = '#' + String(perfil.id).padStart(6, '0');

    contarIncidencias(check.incidencias);
    await cargarDatos();
    datosCargados = true;
});

// ── Modal usuario ─────────────────────────────────────────────────────────────
function toggleModal(show) {
    document.getElementById('modalUser').style.display = show ? 'flex' : 'none';
}

function cerrarSesion() {
    localStorage.removeItem('jwt_token');
    window.location.href = 'inicio_sesion.html';
}

// ── Selección de sección ──────────────────────────────────────────────────────
async function seleccionar(opcion) {
    ['datos', 'incidencias'].forEach(o => {
        document.getElementById(`card-${o}`).classList.toggle('active', o === opcion);
        document.getElementById(`card-${o}`).classList.toggle('admin',  o === opcion);
        document.getElementById(`nav-${o}`).classList.toggle('activo',  o === opcion);
    });

    document.getElementById('panel-datos').style.display       = opcion === 'datos'       ? 'block' : 'none';
    document.getElementById('panel-incidencias').style.display = opcion === 'incidencias' ? 'block' : 'none';

    const titulos = { datos: 'Base de datos', incidencias: 'Incidencias' };
    document.getElementById('header-titulo').textContent = titulos[opcion];
    document.getElementById('page-title').textContent   = titulos[opcion];

    if (opcion === 'incidencias') await cargarIncidencias();
}

// ── Tabs de datos ─────────────────────────────────────────────────────────────
function mostrarTab(tab) {
    ['usuarios', 'cuentas', 'tarjetas'].forEach((t, i) => {
        document.getElementById(`tab-${t}`).style.display = t === tab ? 'block' : 'none';
        document.querySelectorAll('.tab')[i].classList.toggle('active', t === tab);
    });
}

// ── Cargar datos de la BD ─────────────────────────────────────────────────────
async function cargarDatos() {
    const res = await apiFetch('admin.php?accion=datos');
    if (!res || res.status !== 'success') return;

    // Usuarios
    document.getElementById('count-usuarios').textContent       = res.usuarios.length;
    document.getElementById('loading-usuarios').style.display   = 'none';
    document.getElementById('tabla-usuarios').style.display     = 'table';
    document.getElementById('body-usuarios').innerHTML = res.usuarios.map(u => `
        <tr>
            <td style="font-weight:700; color:var(--text-muted); font-size:12px;">#${u.id}</td>
            <td><strong>${u.nombre} ${u.apellidos}</strong></td>
            <td style="color:var(--text-muted); font-size:12px;">${u.email}</td>
            <td style="font-family:monospace; font-size:12px;">${u.dni}</td>
            <td><span class="badge badge-${u.rol}">${u.rol}</span></td>
            <td><span class="badge ${u.activo == 1 ? 'badge-activa' : 'badge-cancelada'}">${u.activo == 1 ? 'Activo' : 'Inactivo'}</span></td>
            <td style="color:var(--text-muted); font-size:12px;">${u.fecha_registro}</td>
            <td style="color:var(--text-muted); font-size:12px;">${u.ultimo_acceso ?? '—'}</td>
        </tr>
    `).join('');

    // Cuentas
    document.getElementById('count-cuentas').textContent        = res.cuentas.length;
    document.getElementById('body-cuentas').innerHTML = res.cuentas.map(c => `
        <tr>
            <td style="font-weight:700; color:var(--text-muted); font-size:12px;">#${c.id}</td>
            <td><strong>${c.nombre} ${c.apellidos}</strong></td>
            <td style="font-family:monospace; font-size:12px;">${c.numero_cuenta}</td>
            <td><strong>$${parseFloat(c.saldo).toLocaleString('en-US', { minimumFractionDigits: 2 })}</strong></td>
            <td><span class="badge badge-${c.tipo}">${c.tipo}</span></td>
            <td><span class="badge ${c.activa == 1 ? 'badge-activa' : 'badge-cancelada'}">${c.activa == 1 ? 'Activa' : 'Inactiva'}</span></td>
            <td style="color:var(--text-muted); font-size:12px;">${c.fecha_creacion}</td>
        </tr>
    `).join('');

    // Tarjetas
    document.getElementById('count-tarjetas').textContent       = res.tarjetas.length;
    document.getElementById('body-tarjetas').innerHTML = res.tarjetas.map(t => `
        <tr>
            <td style="font-weight:700; color:var(--text-muted); font-size:12px;">#${t.id}</td>
            <td><strong>${t.nombre} ${t.apellidos}</strong></td>
            <td style="font-family:monospace; font-size:12px;">•••• •••• •••• ${t.numero.slice(-4)}</td>
            <td style="font-family:monospace;">${t.cvv}</td>
            <td style="font-weight:600;">${t.expiracion}</td>
            <td><span class="badge badge-${t.estado}">${t.estado}</span></td>
            <td style="color:var(--text-muted); font-size:12px;">${t.fecha_creacion}</td>
        </tr>
    `).join('');
}

// ── Incidencias ───────────────────────────────────────────────────────────────
async function cargarIncidencias() {
    document.getElementById('loading-incidencias').style.display = 'block';
    document.getElementById('tabla-incidencias').style.display   = 'none';

    const res = await apiFetch('admin.php?accion=incidencias');
    if (!res || res.status !== 'success') return;

    contarIncidencias(res.incidencias);
    document.getElementById('count-incidencias').textContent     = res.incidencias.length;
    document.getElementById('loading-incidencias').style.display = 'none';
    document.getElementById('tabla-incidencias').style.display   = 'table';

    document.getElementById('body-incidencias').innerHTML = res.incidencias.map(i => `
        <tr id="fila-${i.id}">
            <td style="font-weight:700; color:var(--text-muted); font-size:12px;">#${i.id}</td>
            <td><strong>${i.nombre} ${i.apellidos}</strong></td>
            <td style="font-size:12px; color:var(--text-muted);">${i.email}</td>
            <td style="font-size:12px; max-width:130px;">${i.tipo}</td>
            <td style="font-size:12px; color:var(--text-muted); max-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${i.descripcion}">${i.descripcion}</td>
            <td style="font-size:12px; color:var(--text-muted);">${i.fecha_incidencia}</td>
            <td><span class="badge badge-${i.estado}" id="badge-${i.id}">${i.estado}</span></td>
            <td>
                <button class="btn-close-issue" id="btn-${i.id}"
                    ${i.estado === 'resuelta' ? 'disabled' : ''}
                    onclick="cerrarIncidencia(${i.id})">
                    ${i.estado === 'resuelta' ? 'Cerrada' : 'Cerrar'}
                </button>
            </td>
        </tr>
    `).join('');
}

function contarIncidencias(incidencias) {
    const abiertas  = incidencias.filter(i => i.estado === 'abierta').length;
    const resueltas = incidencias.filter(i => i.estado === 'resuelta').length;
    document.getElementById('badge-abiertas').textContent      = `Abiertas: ${abiertas}`;
    document.getElementById('badge-resueltas').textContent     = `Resueltas: ${resueltas}`;
    document.getElementById('badge-nav-abiertas').textContent  = abiertas > 0 ? abiertas : '';
}

// ── Cerrar incidencia ─────────────────────────────────────────────────────────
async function cerrarIncidencia(id) {
    const btn = document.getElementById(`btn-${id}`);
    btn.disabled    = true;
    btn.textContent = 'Cerrando...';

    const res = await apiFetch('admin.php', {
        method: 'PUT',
        body: JSON.stringify({ incidencia_id: id })
    });

    if (res && res.status === 'success') {
        const badge = document.getElementById(`badge-${id}`);
        badge.className   = 'badge badge-resuelta';
        badge.textContent = 'resuelta';
        btn.textContent   = 'Cerrada';

        const abiertas  = document.querySelectorAll('.badge-abierta').length;
        const resueltas = document.querySelectorAll('.badge-resuelta').length;
        document.getElementById('badge-abiertas').textContent     = `Abiertas: ${abiertas}`;
        document.getElementById('badge-resueltas').textContent    = `Resueltas: ${resueltas}`;
        document.getElementById('badge-nav-abiertas').textContent = abiertas > 0 ? abiertas : '';
    } else {
        btn.disabled    = false;
        btn.textContent = 'Cerrar';
    }
}
