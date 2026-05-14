// frontend/js/api.js
//const API_BASE = 'https://beasy.fwh.is/backend/api';
const API_BASE = 'http://localhost/backend/api/';

async function apiFetch(endpoint, opciones = {}) {
    const token = localStorage.getItem('jwt_token');
    const headers = {
        'Content-Type': 'application/json',
        ...(token && { 
        'Authorization': `Bearer ${token}`,
        'X-Authorization': `Bearer ${token}` // Añade esta línea de refuerzo
        })
    };

    // Combinar cabeceras con las que vengan en opciones
    const config = {
        ...opciones,
        headers: {
            ...headers,
            ...(opciones.headers || {})
        }
    };

    try {
        const respuesta = await fetch(API_BASE + endpoint, config);
        
        // Intentar parsear JSON, pero si falla, usar texto
        let datos;
        const contentType = respuesta.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            datos = await respuesta.json();
        } else {
            datos = { message: await respuesta.text() };
        }

        if (!respuesta.ok) {
            gestionarErrorHTTP(respuesta.status, datos.message);
            return null;
        }
        
        return datos; 
    } catch (error) {
    
        console.error('Error detallado: ', error);
        mostrarNotificacion('Error de red o servidor', 'error');
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
            setTimeout(() => window.location.href = '/frontend/login.html', 1500);
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

// Notificaciones toast para el usuario
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
    setTimeout(() => toast.remove(), 3500); // Auto-desaparecer
}