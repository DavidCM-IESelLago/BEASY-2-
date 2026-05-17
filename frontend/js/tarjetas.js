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

// ── Cargar tarjetas ───────────────────────────────────────────────────────────
async function cargarTarjetas() {
    const res  = await apiFetch('tarjeta.php');
    const grid = document.getElementById('tarjeta-grid');

    if (!res || res.status !== 'success' || res.data.length === 0) {
        grid.innerHTML = `
            <div style="grid-column:1/-1; text-align:center; padding:60px; color:var(--text-muted);">
                <span class="material-symbols-outlined" style="font-size:48px;opacity:0.3;">credit_card_off</span>
                <p style="margin-top:12px;font-weight:600;">No cards found</p>
            </div>`;
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
        const logo = i % 2 === 0
            ? `<span style="font-weight:900;font-style:italic;font-size:20px;">VISA</span>`
            : `<div style="display:flex;">
                   <div style="width:24px;height:24px;background:#eb001b;border-radius:50%;opacity:0.9;"></div>
                   <div style="width:24px;height:24px;background:#f79e1b;border-radius:50%;margin-left:-12px;opacity:0.9;"></div>
               </div>`;

        const el = document.createElement('div');
        el.className   = `card-visual ${clase} ${estadoClass}`.trim();
        el.dataset.id  = t.id;
        el.innerHTML   = `
            <span class="card-badge ${badgeClass}">${t.estado}</span>
            <div style="display:flex;justify-content:space-between;">
                <span class="material-symbols-outlined" style="font-size:32px;">contactless</span>
                ${logo}
            </div>
            <div>
                <p style="font-size:11px;opacity:0.8;font-weight:600;text-transform:uppercase;">Available Balance</p>
                <h3 style="font-size:26px;font-weight:700;margin:4px 0 10px;">$${t.saldo}</h3>
                <div style="display:flex;justify-content:space-between;font-family:monospace;letter-spacing:2px;margin-top:8px;">
                    <span>${t.numero_oculto}</span>
                    <span style="font-size:12px;">${t.expiracion}</span>
                </div>
            </div>`;
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
