async function cargarNotificaciones() {
    // GET — notificaciones.php usa middleware validateToken.php
    const datos = await apiFetch('notificaciones.php');
    if (!datos) return;
    // datos viene con: { status, cantidad, data: [{id, mensaje, leida, fecha}] }
    const badge = document.getElementById('badge-notificaciones');
    const noLeidas = datos.data.filter(n => n.leida === 0).length;
    badge.textContent = noLeidas;
    // Actualizar contador en navbar
    const lista = document.getElementById('lista-notificaciones');
    lista.innerHTML = '';
    datos.data.forEach(notif => {
        const item = document.createElement('div');
        item.className = `notificacion ${notif.leida ? 'leida' : 'no-leida'}`;
        item.innerHTML = `
            <p>${notif.mensaje}</p>
            <small>${notif.fecha}</small>
            ${!notif.leida ? `<button onclick="marcarLeida(${notif.id})">Marcar leída</button>` : ''}
        `;
        lista.appendChild(item);
    });
}

async function marcarLeida(notificacionId) {
    // PUT — body con notificacion_id
    const datos = await apiFetch('notificaciones.php', {
        method: 'PUT',
        body: JSON.stringify({ notificacion_id: notificacionId })
    });
    if (!datos) return;
    cargarNotificaciones(); // Refrescar lista y badge
}

document.addEventListener('DOMContentLoaded', cargarNotificaciones);