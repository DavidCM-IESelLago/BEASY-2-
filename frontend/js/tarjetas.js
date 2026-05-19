// js/tarjetas.js

// ── Estado ────────────────────────────────────────────────────────────────────
let tarjetaSeleccionada = null;
let todasLasTarjetas    = [];

// ── Inicialización ────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    const token = localStorage.getItem('jwt_token');
    if (!token) { window.location.href = 'inicio_sesion.html'; return; }

    const sesion = await apiFetch('validate_helper.php');
    if (!sesion || sesion.status !== 'success') {
        localStorage.removeItem('jwt_token');
        window.location.href = 'inicio_sesion.html';
        return;
    }

    document.body.style.display = 'flex';
    await Promise.all([cargarPerfil(), cargarTarjetas()]);
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

// ── Helper: logo de tarjeta ───────────────────────────────────────────────────
function _crearLogo(clase) {
    if (clase === 'card-blue') {
        const span = document.createElement('span');
        span.style.cssText = 'font-weight:900;font-style:italic;font-size:20px;';
        span.textContent = 'VISA';
        return span;
    }
    const wrap = document.createElement('div');
    wrap.style.cssText = 'display:flex;';
    const c1 = document.createElement('div');
    c1.style.cssText = 'width:24px;height:24px;background:#eb001b;border-radius:50%;opacity:0.9;';
    const c2 = document.createElement('div');
    c2.style.cssText = 'width:24px;height:24px;background:#f79e1b;border-radius:50%;margin-left:-12px;opacity:0.9;';
    wrap.appendChild(c1);
    wrap.appendChild(c2);
    return wrap;
}

// ── Cargar tarjetas ───────────────────────────────────────────────────────────
async function cargarTarjetas() {
    const res  = await apiFetch('tarjeta.php');
    const grid = document.getElementById('tarjeta-grid');

    if (!res || res.status !== 'success' || res.data.length === 0) {
        const divEmpty = document.createElement('div');
        divEmpty.style.cssText = 'grid-column:1/-1;text-align:center;padding:60px;color:var(--text-muted);';
        const spanEmpty = document.createElement('span');
        spanEmpty.className = 'material-symbols-outlined';
        spanEmpty.style.cssText = 'font-size:48px;opacity:0.3;';
        spanEmpty.textContent = 'credit_card_off';
        const pEmpty = document.createElement('p');
        pEmpty.style.cssText = 'margin-top:12px;font-weight:600;';
        pEmpty.textContent = 'No cards found';
        divEmpty.appendChild(spanEmpty);
        divEmpty.appendChild(pEmpty);
        grid.appendChild(divEmpty);
        return;
    }

    todasLasTarjetas = res.data;
    grid.innerHTML   = '';

    res.data.forEach((t, i) => {
        const clase       = i % 2 === 0 ? 'card-blue' : 'card-dark';
        const estadoClass = t.estado === 'bloqueada' ? 'card-blocked'
                          : t.estado === 'cancelada'  ? 'card-cancelled' : '';
        const badgeClass  = t.estado === 'activa'     ? 'card-badge-active'
                          : t.estado === 'bloqueada'  ? 'card-badge-blocked' : 'card-badge-cancelled';


        const el = document.createElement('div');
        el.className   = `card-visual ${clase} ${estadoClass}`.trim();
        el.dataset.id  = t.id;
        // Badge de estado
        const badge = document.createElement('span');
        badge.className = `card-badge ${badgeClass}`;
        badge.textContent = t.estado;

        // Fila: contactless + logo
        const fila = document.createElement('div');
        fila.style.cssText = 'display:flex;justify-content:space-between;';
        const spanContactless = document.createElement('span');
        spanContactless.className = 'material-symbols-outlined';
        spanContactless.style.cssText = 'font-size:32px;';
        spanContactless.textContent = 'contactless';
        fila.appendChild(spanContactless);
        fila.appendChild(_crearLogo(clase));

        // Bloque inferior: saldo + número
        const info = document.createElement('div');
        const pBalance = document.createElement('p');
        pBalance.style.cssText = 'font-size:11px;opacity:0.8;font-weight:600;text-transform:uppercase;';
        pBalance.textContent = 'Available Balance';
        const h3 = document.createElement('h3');
        h3.style.cssText = 'font-size:26px;font-weight:700;margin:4px 0 10px;';
        h3.textContent = '$' + t.saldo;
        const numRow = document.createElement('div');
        numRow.style.cssText = 'display:flex;justify-content:space-between;font-family:monospace;letter-spacing:2px;margin-top:8px;';
        const spanNum = document.createElement('span');
        spanNum.textContent = t.numero_oculto;
        const spanExp = document.createElement('span');
        spanExp.style.cssText = 'font-size:12px;';
        spanExp.textContent = t.expiracion;
        numRow.appendChild(spanNum);
        numRow.appendChild(spanExp);
        info.appendChild(pBalance);
        info.appendChild(h3);
        info.appendChild(numRow);

        el.appendChild(badge);
        el.appendChild(fila);
        el.appendChild(info);
        el.addEventListener('click', () => seleccionarTarjeta(t, el, clase));
        grid.appendChild(el);
    });
}

// ── Selección de tarjeta ──────────────────────────────────────────────────────
function seleccionarTarjeta(t, el, clase) {
    document.querySelectorAll('.card-visual').forEach(c => c.classList.remove('card-selected'));
    el.classList.add('card-selected');
    tarjetaSeleccionada = t;

    document.getElementById('panel-placeholder').style.display = 'none';
    document.getElementById('panel-contenido').style.display   = 'block';

    const visual = document.getElementById('panel-tarjeta-visual');
    visual.className = `card-visual ${clase}`;

    document.getElementById('panel-numero').textContent    = t.numero_oculto;
    document.getElementById('panel-expiracion').textContent = 'Expires ' + t.expiracion;
    document.getElementById('panel-numero-corto').textContent = t.numero_oculto;
    document.getElementById('panel-exp-dato').textContent  = t.expiracion;

    actualizarChipEstado('panel-estado-chip', t.estado);
    actualizarBotonesEstado(t.estado);
}

function actualizarChipEstado(id, estado) {
    const chip    = document.getElementById(id);
    const estadoEn = estado === 'activa' ? 'active' : estado === 'bloqueada' ? 'blocked' : 'cancelled';
    chip.className   = `status-chip chip-${estadoEn}`;
    chip.textContent = estado.charAt(0).toUpperCase() + estado.slice(1);
}

function actualizarBotonesEstado(estado) {
    const btnFreeze   = document.getElementById('btn-freeze');
    const btnCancelar = document.getElementById('btn-cancelar');
    const wrap  = document.getElementById('freeze-icon-wrap');
    const icon  = document.getElementById('freeze-icon');
    const label = document.getElementById('freeze-label');

    const cancelada = estado === 'cancelada';
    btnCancelar.disabled = cancelada;
    btnFreeze.disabled   = cancelada;

    if (estado === 'bloqueada') {
        wrap.style.background = '#e8f0fe';
        wrap.style.color      = 'var(--primary)';
        icon.textContent      = 'lock_open';
        label.textContent     = 'Unfreeze';
    } else {
        wrap.style.background = '#ffdad6';
        wrap.style.color      = 'var(--error)';
        icon.textContent      = 'block';
        label.textContent     = 'Freeze';
    }
}

function cerrarPanel() {
    document.getElementById('panel-placeholder').style.display = 'flex';
    document.getElementById('panel-contenido').style.display   = 'none';
    document.querySelectorAll('.card-visual').forEach(c => c.classList.remove('card-selected'));
    tarjetaSeleccionada = null;
}

// ── Modal datos de tarjeta ────────────────────────────────────────────────────
function abrirModalDatos() {
    if (!tarjetaSeleccionada) return;
    const t = tarjetaSeleccionada;
    const numeroFormateado = t.numero.replace(/\s+/g, '').replace(/(.{4})/g, '$1 ').trim();

    document.getElementById('modal-numero-completo').textContent = numeroFormateado;
    document.getElementById('modal-expiracion').textContent      = 'Expires ' + t.expiracion;
    document.getElementById('modal-num').textContent             = numeroFormateado;
    document.getElementById('modal-cvv').textContent             = t.cvv;
    document.getElementById('modal-exp').textContent             = t.expiracion;
    actualizarChipEstado('modal-estado', t.estado);

    document.getElementById('modalDatos').style.display = 'flex';
}

function cerrarModalDatos() {
    document.getElementById('modalDatos').style.display = 'none';
}

// ── Freeze / Unfreeze ─────────────────────────────────────────────────────────
async function toggleFreeze() {
    if (!tarjetaSeleccionada) return;
    const nuevoEstado = tarjetaSeleccionada.estado === 'bloqueada' ? 'activa' : 'bloqueada';
    await cambiarEstado(nuevoEstado);
}

// ── Cancelar tarjeta ──────────────────────────────────────────────────────────
function cancelarTarjeta() {
    if (!tarjetaSeleccionada || tarjetaSeleccionada.estado === 'cancelada') return;
    document.getElementById('modalCancelar').style.display = 'flex';
}

function cerrarModalCancelar() {
    document.getElementById('modalCancelar').style.display = 'none';
}

async function confirmarCancelacion() {
    cerrarModalCancelar();
    await cambiarEstado('cancelada');
}

// ── Cambiar estado ────────────────────────────────────────────────────────────
async function cambiarEstado(nuevoEstado) {
    const res = await apiFetch('tarjeta.php', {
        method: 'PUT',
        body: JSON.stringify({ tarjeta_id: tarjetaSeleccionada.id, estado: nuevoEstado })
    });

    if (!res || res.status !== 'success') { alert('Error al actualizar la tarjeta'); return; }

    tarjetaSeleccionada.estado = nuevoEstado;
    const idx = todasLasTarjetas.findIndex(t => t.id === tarjetaSeleccionada.id);
    if (idx !== -1) todasLasTarjetas[idx].estado = nuevoEstado;

    const cardEl = document.querySelector(`.card-visual[data-id="${tarjetaSeleccionada.id}"]`);
    if (cardEl) {
        const badge      = cardEl.querySelector('.card-badge');
        const badgeClass = nuevoEstado === 'activa'    ? 'card-badge-active'
                         : nuevoEstado === 'bloqueada' ? 'card-badge-blocked' : 'card-badge-cancelled';
        badge.className  = `card-badge ${badgeClass}`;
        badge.textContent = nuevoEstado;
        cardEl.classList.toggle('card-blocked',   nuevoEstado === 'bloqueada');
        cardEl.classList.toggle('card-cancelled',  nuevoEstado === 'cancelada');
    }

    actualizarChipEstado('panel-estado-chip', nuevoEstado);
    actualizarBotonesEstado(nuevoEstado);
}
