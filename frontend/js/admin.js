

let datosCargados = false;

document.addEventListener('DOMContentLoaded', async () => {
    const token = localStorage.getItem('jwt_token');
    if (!token) { window.location.href = 'inicio_sesion'; return; }

    const perfil = await apiFetch('perfil.php');
    if (!perfil || perfil.status !== 'success') {
        window.location.href = 'inicio_sesion';
        return;
    }

    const check = await apiFetch('admin.php?accion=incidencias');
    if (!check) {
        localStorage.removeItem('jwt_token');
        window.location.href = 'inicio_sesion';
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

function toggleModal(show) {
    document.getElementById('modalUser').style.display = show ? 'flex' : 'none';
}

function cerrarSesion() {
    localStorage.removeItem('jwt_token');
    window.location.href = 'inicio_sesion';
}

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

function mostrarTab(tab) {
    ['usuarios', 'cuentas', 'tarjetas'].forEach((t, i) => {
        document.getElementById(`tab-${t}`).style.display = t === tab ? 'block' : 'none';
        document.querySelectorAll('.tab')[i].classList.toggle('active', t === tab);
    });
}

function _td(texto, estilo = '') {
    const td = document.createElement('td');
    td.textContent = texto ?? '—';
    if (estilo) td.style.cssText = estilo;
    return td;
}

function _tdBadge(texto, claseExtra) {
    const td = document.createElement('td');
    const span = document.createElement('span');
    span.className = `badge ${claseExtra}`;
    span.textContent = texto;
    td.appendChild(span);
    return td;
}

function _tdStrong(texto1, texto2 = '') {
    const td = document.createElement('td');
    const strong = document.createElement('strong');
    strong.textContent = texto2 ? texto1 + ' ' + texto2 : texto1;
    td.appendChild(strong);
    return td;
}

async function cargarDatos() {
    const res = await apiFetch('admin.php?accion=datos');
    if (!res || res.status !== 'success') return;

    
    document.getElementById('count-usuarios').textContent       = res.usuarios.length;
    document.getElementById('loading-usuarios').style.display   = 'none';
    document.getElementById('tabla-usuarios').style.display     = 'table';
    const tbodyU = document.getElementById('body-usuarios');
    tbodyU.innerHTML = '';
    res.usuarios.forEach(u => {
        const tr = document.createElement('tr');
        tr.appendChild(_td('#' + u.id,        'font-weight:700;color:var(--text-muted);font-size:12px;'));
        tr.appendChild(_tdStrong(u.nombre, u.apellidos));
        tr.appendChild(_td(u.email,           'color:var(--text-muted);font-size:12px;'));
        tr.appendChild(_td(u.dni,             'font-family:monospace;font-size:12px;'));
        tr.appendChild(_tdBadge(u.rol,        `badge-${u.rol}`));
        tr.appendChild(_tdBadge(u.activo == 1 ? 'Activo' : 'Inactivo', u.activo == 1 ? 'badge-activa' : 'badge-cancelada'));
        tr.appendChild(_td(u.fecha_registro,  'color:var(--text-muted);font-size:12px;'));
        tr.appendChild(_td(u.ultimo_acceso ?? '—', 'color:var(--text-muted);font-size:12px;'));
        tbodyU.appendChild(tr);
    });

    
    document.getElementById('count-cuentas').textContent        = res.cuentas.length;
    const tbodyC = document.getElementById('body-cuentas');
    tbodyC.innerHTML = '';
    res.cuentas.forEach(c => {
        const saldoFmt = '$' + parseFloat(c.saldo).toLocaleString('en-US', { minimumFractionDigits: 2 });
        const tr = document.createElement('tr');
        tr.appendChild(_td('#' + c.id,       'font-weight:700;color:var(--text-muted);font-size:12px;'));
        tr.appendChild(_tdStrong(c.nombre, c.apellidos));
        tr.appendChild(_td(c.numero_cuenta,  'font-family:monospace;font-size:12px;'));
        tr.appendChild(_tdStrong(saldoFmt));
        tr.appendChild(_tdBadge(c.tipo,      `badge-${c.tipo}`));
        tr.appendChild(_tdBadge(c.activa == 1 ? 'Activa' : 'Inactiva', c.activa == 1 ? 'badge-activa' : 'badge-cancelada'));
        tr.appendChild(_td(c.fecha_creacion, 'color:var(--text-muted);font-size:12px;'));
        tbodyC.appendChild(tr);
    });

    
    document.getElementById('count-tarjetas').textContent       = res.tarjetas.length;
    const tbodyT = document.getElementById('body-tarjetas');
    tbodyT.innerHTML = '';
    res.tarjetas.forEach(t => {
        const numMask = '•••• •••• •••• ' + String(t.numero).slice(-4);
        const tr = document.createElement('tr');
        tr.appendChild(_td('#' + t.id,       'font-weight:700;color:var(--text-muted);font-size:12px;'));
        tr.appendChild(_tdStrong(t.nombre, t.apellidos));
        tr.appendChild(_td(numMask,          'font-family:monospace;font-size:12px;'));
        tr.appendChild(_td(t.cvv,            'font-family:monospace;'));
        tr.appendChild(_td(t.expiracion,     'font-weight:600;'));
        tr.appendChild(_tdBadge(t.estado,    `badge-${t.estado}`));
        tr.appendChild(_td(t.fecha_creacion, 'color:var(--text-muted);font-size:12px;'));
        tbodyT.appendChild(tr);
    });
}

async function cargarIncidencias() {
    document.getElementById('loading-incidencias').style.display = 'block';
    document.getElementById('tabla-incidencias').style.display   = 'none';

    const res = await apiFetch('admin.php?accion=incidencias');
    if (!res || res.status !== 'success') return;

    contarIncidencias(res.incidencias);
    document.getElementById('count-incidencias').textContent     = res.incidencias.length;
    document.getElementById('loading-incidencias').style.display = 'none';
    document.getElementById('tabla-incidencias').style.display   = 'table';

    const tbodyI = document.getElementById('body-incidencias');
    tbodyI.innerHTML = '';
    res.incidencias.forEach(i => {
        const tr = document.createElement('tr');
        tr.id = `fila-${i.id}`;

        tr.appendChild(_td('#' + i.id,         'font-weight:700;color:var(--text-muted);font-size:12px;'));
        tr.appendChild(_tdStrong(i.nombre, i.apellidos));
        tr.appendChild(_td(i.email,            'font-size:12px;color:var(--text-muted);'));
        tr.appendChild(_td(i.tipo,             'font-size:12px;max-width:130px;'));

        const tdDesc = _td(i.descripcion,      'font-size:12px;color:var(--text-muted);max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;');
        tdDesc.title = i.descripcion;
        tr.appendChild(tdDesc);

        tr.appendChild(_td(i.fecha_incidencia, 'font-size:12px;color:var(--text-muted);'));

        const tdBadge = document.createElement('td');
        const spanBadge = document.createElement('span');
        spanBadge.className = `badge badge-${i.estado}`;
        spanBadge.id = `badge-${i.id}`;
        spanBadge.textContent = i.estado;
        tdBadge.appendChild(spanBadge);
        tr.appendChild(tdBadge);

        const btn = document.createElement('button');
        btn.className = 'btn-close-issue';
        btn.id = `btn-${i.id}`;
        btn.disabled = i.estado === 'resuelta';
        btn.textContent = i.estado === 'resuelta' ? 'Cerrada' : 'Cerrar';
        const tdBtn = document.createElement('td');
        tdBtn.appendChild(btn);
        tr.appendChild(tdBtn);

        tbodyI.appendChild(tr);
    });
}

function contarIncidencias(incidencias) {
    const abiertas  = incidencias.filter(i => i.estado === 'abierta').length;
    const resueltas = incidencias.filter(i => i.estado === 'resuelta').length;
    document.getElementById('badge-abiertas').textContent      = `Abiertas: ${abiertas}`;
    document.getElementById('badge-resueltas').textContent     = `Resueltas: ${resueltas}`;
    document.getElementById('badge-nav-abiertas').textContent  = abiertas > 0 ? abiertas : '';
}

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
