
const API_BASE = 'http://localhost/api/';

async function apiFetch(endpoint, opciones = {}) {
    const token = localStorage.getItem('jwt_token');
    const cabeceras = {
        'Content-Type': 'application/json',
        
        ...(token && { 
        'Authorization': `Bearer ${token}`,
        'X-Beasy-Token': localStorage.getItem('jwt_token')
    }),
        ...opciones.headers
    };

    try {
        const respuesta = await fetch(API_BASE + endpoint, { ...opciones, headers: cabeceras });
        
        
        const datos = await respuesta.json(); 

        if (!respuesta.ok) {
            gestionarErrorHTTP(respuesta.status, datos.message);
            return null;
        }
        
        return datos; 
    } catch (error) {
        
        console.error('Error detallado: ', error);
        return null;
    }
}

function gestionarErrorHTTP(status, mensaje) {
    switch (status) {
        case 400:
            mostrarNotificacion(mensaje || 'Datos incorrectos.', 'advertencia');
            break;

        case 401:
            mostrarNotificacion('Sesión expirada.Redirigiendo...', 'error');
            localStorage.removeItem('jwt_token');
            
            break;

        case 404:
            mostrarNotificacion('Recurso no encontrado.', 'advertencia');
            break;

        case 500:
            mostrarNotificacion('Error interno del servidor.', 'error');
            break;

        default:
            mostrarNotificacion(`Error inesperado (${status}).`, 'error');
    }
}

function mostrarNotificacion(mensaje, tipo = 'info') {
    const toast = document.createElement('div');
    const colores = {
        exito: '#1B5E20',
        error: '#B71C1C',
        advertencia: '#E65100',
        info: '#0A2540'
    };
    Object.assign(toast.style, {
        position: 'fixed',
        bottom: '20px',
        right: '20px',
        background: colores[tipo] || colores.info,
        color: 'white',
        padding: '12px 20px',
        borderRadius: '8px',
        zIndex: 9999,
        boxShadow: '0 4px 12px rgba(0,0,0,0.3)',
        maxWidth: '320px',
        fontSize: '14px'
    });
    toast.textContent = mensaje;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
}

function toggleSidebar(force) {
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    if (!sidebar) return;
    const open = force !== undefined ? force : !sidebar.classList.contains('open');
    sidebar.classList.toggle('open', open);
    if (backdrop) backdrop.classList.toggle('open', open);
}