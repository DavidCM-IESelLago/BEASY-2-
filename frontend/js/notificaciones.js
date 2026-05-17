// js/notificaciones.js — funcionalidad unificada de notificaciones

let _notifPolling = null;

// ── Inicialización ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    _inicializarPanel();
    cargarNotificaciones();
    // Actualizar badge en segundo plano cada 60 segundos
    _notifPolling = setInterval(cargarNotificaciones, 60000);
});

/**
 * Crea el overlay y el botón "marcar todas" de forma dinámica,
 * así no hay que tocar cada HTML.
 */
function _inicializarPanel() {
    // Overlay oscuro detrás del panel
    if (!document.getElementById('notif-overlay')) {
        const overlay = document.createElement('div');
        overlay.id = 'notif-overlay';
        overlay.style.cssText = [
            'position:fixed', 'inset:0',
            'background:rgba(0,0,0,0.35)',
            'z-index:499',
            'display:none',
            'transition:opacity 0.2s',
        ].join(';');
        overlay.addEventListener('click', () => toggleNotificaciones(false));
        document.body.appendChild(overlay);
    }

    // Botón "Marcar todas" en la cabecera del panel
    const panel = document.getElementById('notifPanel');
    if (!panel || document.getElementById('btn-marcar-todas')) return;

    const header = panel.querySelector('div:first-child');
    if (!header) return;

    const btn = document.createElement('button');
    btn.id = 'btn-marcar-todas';
    btn.textContent = 'Marcar todas';
    btn.title = 'Marcar todas las notificaciones como leídas';
    btn.style.cssText = [
        'font-size:12px', 'font-weight:600',
        'color:#1a73e8', 'background:none',
        'border:1px solid rgba(26,115,232,0.3)',
        'border-radius:6px', 'cursor:pointer',
        'padding:4px 10px', 'font-family:inherit',
        'display:none', 'margin-right:8px',
        'transition:background 0.15s',
    ].join(';');
    btn.addEventListener('mouseenter', () => { btn.style.background = 'rgba(26,115,232,0.08)'; });
    btn.addEventListener('mouseleave', () => { btn.style.background = 'none'; });
    btn.addEventListener('click', marcarTodasLeidas);

    const closeBtn = header.querySelector('.material-symbols-outlined');
    if (closeBtn) {
        header.insertBefore(btn, closeBtn);
    } else {
        header.appendChild(btn);
    }
}

// ── Carga y renderizado ─────────────────────────────────────────────────────
async function cargarNotificaciones() {
    const datos = await apiFetch('notificaciones.php');
    if (!datos || datos.status !== 'success') return;

    const noLeidas = datos.data.filter(n => !n.leida).length;

    // Badge del botón de campana
    const badge = document.getElementById('badge-notificaciones');
    if (badge) {
        badge.textContent = noLeidas > 9 ? '9+' : noLeidas;
        badge.style.display = noLeidas > 0 ? 'flex' : 'none';
    }

    // Botón "Marcar todas" — solo visible si hay no leídas
    const btnTodas = document.getElementById('btn-marcar-todas');
    if (btnTodas) {
        btnTodas.style.display = noLeidas > 0 ? 'inline-block' : 'none';
    }

    // Lista de notificaciones
    const lista = document.getElementById('lista-notificaciones');
    if (!lista) return;

    while (lista.firstChild) lista.removeChild(lista.firstChild);

    if (datos.data.length === 0) {
        const vacio = document.createElement('div');
        vacio.style.cssText = 'display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 24px;gap:12px;color:#727785;';
        vacio.innerHTML = `
            <span class="material-symbols-outlined" style="font-size:40px;opacity:0.3;">notifications_off</span>
            <p style="font-size:13px;font-weight:500;">Sin notificaciones</p>
        `;
        lista.appendChild(vacio);
        return;
    }

    datos.data.forEach(notif => {
        lista.appendChild(_crearItemNotificacion(notif));
    });
}

/**
 * Devuelve el elemento DOM de una notificación individual.
 */
function _crearItemNotificacion(notif) {
    const esNoLeida = !notif.leida;

    const item = document.createElement('div');
    item.style.cssText = [
        'padding:16px',
        'border-bottom:1px solid #e1e3e4',
        `background:${esNoLeida ? '#f0f4ff' : 'transparent'}`,
        'transition:background 0.2s',
    ].join(';');

    const fecha = new Date(notif.fecha).toLocaleDateString('es-ES', {
        day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
    });

    // Icono contextual según el contenido del mensaje
    const iconColor = esNoLeida ? '#1a73e8' : '#727785';
    const iconBg    = esNoLeida ? 'rgba(26,115,232,0.12)' : '#f3f4f5';
    const iconName  = _iconoPorMensaje(notif.mensaje);

    const fila = document.createElement('div');
    fila.style.cssText = 'display:flex;gap:12px;align-items:flex-start;';

    const icono = document.createElement('div');
    icono.style.cssText = [
        'width:36px', 'height:36px', 'border-radius:50%',
        'flex-shrink:0', 'display:flex',
        'align-items:center', 'justify-content:center',
        `background:${iconBg}`, `color:${iconColor}`,
    ].join(';');
    icono.innerHTML = `<span class="material-symbols-outlined" style="font-size:18px;">${iconName}</span>`;

    const contenido = document.createElement('div');
    contenido.style.cssText = 'flex:1;min-width:0;';
    contenido.innerHTML = `
        <p style="font-size:13px;font-weight:${esNoLeida ? '600' : '400'};color:#191c1d;line-height:1.4;margin-bottom:4px;">${notif.mensaje}</p>
        <p style="font-size:11px;color:#727785;">${fecha}</p>
    `;

    if (esNoLeida) {
        const btnLeer = document.createElement('button');
        btnLeer.textContent = 'Marcar como leída';
        btnLeer.style.cssText = [
            'margin-top:6px', 'font-size:11px', 'font-weight:600',
            'color:#1a73e8', 'background:none', 'border:none',
            'cursor:pointer', 'padding:0', 'font-family:inherit',
        ].join(';');
        btnLeer.addEventListener('click', (e) => {
            e.stopPropagation();
            marcarLeida(notif.id);
        });
        contenido.appendChild(btnLeer);
    }

    fila.appendChild(icono);
    fila.appendChild(contenido);
    item.appendChild(fila);
    return item;
}

/**
 * Devuelve el nombre del icono de Material Symbols según el texto del mensaje.
 */
function _iconoPorMensaje(mensaje) {
    const m = (mensaje || '').toLowerCase();
    if (m.includes('transferid'))                    return 'swap_horiz';
    if (m.includes('bizum') && m.includes('enviado')) return 'send_money';
    if (m.includes('recibid') || m.includes('recibido')) return 'call_received';
    if (m.includes('tarjeta') && m.includes('bloqueada')) return 'credit_card_off';
    if (m.includes('tarjeta') && m.includes('cancelada')) return 'credit_card_off';
    if (m.includes('tarjeta') && m.includes('activada'))  return 'credit_card';
    if (m.includes('tarjeta'))                        return 'credit_card';
    if (m.includes('cuenta') && m.includes('creada')) return 'account_balance';
    if (m.includes('cuenta'))                         return 'account_balance';
    return 'notifications';
}

// ── Acciones ────────────────────────────────────────────────────────────────
async function marcarLeida(notificacionId) {
    const datos = await apiFetch('notificaciones.php', {
        method: 'PUT',
        body: JSON.stringify({ notificacion_id: notificacionId }),
    });
    if (!datos) return;
    cargarNotificaciones();
}

async function marcarTodasLeidas() {
    const btn = document.getElementById('btn-marcar-todas');
    if (btn) { btn.disabled = true; btn.textContent = 'Marcando...'; }

    const datos = await apiFetch('notificaciones.php', {
        method: 'PUT',
        body: JSON.stringify({ marcar_todas: true }),
    });

    if (btn) { btn.disabled = false; btn.textContent = 'Marcar todas'; }
    if (!datos) return;
    cargarNotificaciones();
}

// ── Toggle del panel ────────────────────────────────────────────────────────
function toggleNotificaciones(show) {
    const panel   = document.getElementById('notifPanel');
    const overlay = document.getElementById('notif-overlay');
    if (!panel) return;

    if (show) {
        panel.style.transform = 'translateX(0)';
        if (overlay) overlay.style.display = 'block';
        cargarNotificaciones(); // Datos frescos cada vez que se abre
        setTimeout(() => document.addEventListener('click', _cerrarNotifFuera), 50);
    } else {
        panel.style.transform = 'translateX(100%)';
        if (overlay) overlay.style.display = 'none';
        document.removeEventListener('click', _cerrarNotifFuera);
    }
}

function _cerrarNotifFuera(e) {
    const panel = document.getElementById('notifPanel');
    const btn   = document.getElementById('btn-notificaciones');
    if (!panel) return;
    if (!panel.contains(e.target) && (!btn || !btn.contains(e.target))) {
        toggleNotificaciones(false);
    }
}
