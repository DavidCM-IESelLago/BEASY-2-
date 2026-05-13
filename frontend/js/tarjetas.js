// js/tarjetas.js

// ==========================================
// 1. FUNCIONES PRINCIPALES
// ==========================================

// Cargar tarjetas existentes (GET con JWT)
async function cargarTarjetas() { 
    const datos = await apiFetch('tarjeta.php'); 
    
    if (!datos) return; 
    
    const contenedor = document.getElementById('lista-tarjetas'); 
    
    // Limpiar de forma segura (sin innerHTML)
    while (contenedor.firstChild) {
        contenedor.removeChild(contenedor.firstChild);
    }
    
    // datos.data viene del JSON: {"status":"success","data":[{...}]} 
    datos.data.forEach(tarjeta => { 
        // Creamos la caja de la tarjeta
        const cajaTarjeta = document.createElement('div');
        cajaTarjeta.className = 'tarjeta-item';
        
        // Creamos el texto del número
        const textoNumero = document.createElement('p');
        textoNumero.textContent = tarjeta.numero;
        
        // Creamos los detalles (Caducidad y CVV)
        const textoDetalles = document.createElement('p');
        textoDetalles.textContent = `Caduca: ${tarjeta.expiracion}  |  CVV: ${tarjeta.cvv}`;
        
        // Creamos el estado
        const textoEstado = document.createElement('span');
        textoEstado.textContent = tarjeta.estado.toUpperCase();
        
        // Creamos el botón de bloquear
        const botonBloquear = document.createElement('button');
        botonBloquear.textContent = 'Bloquear';
        // (Opcional para el futuro: aquí podrías añadirle un evento al botón para que llame a bloquearTarjeta)
        
        // Metemos todo dentro de la caja de la tarjeta
        cajaTarjeta.appendChild(textoNumero);
        cajaTarjeta.appendChild(textoDetalles);
        cajaTarjeta.appendChild(textoEstado);
        cajaTarjeta.appendChild(botonBloquear);
        
        // Añadimos la tarjeta completa al contenedor principal
        contenedor.appendChild(cajaTarjeta); 
    }); 
}

// Generar nueva tarjeta (POST con cuenta_id en el body)
async function generarTarjeta(cuentaId) { 
    // tarjeta.php espera: { "cuenta_id": X } 
    const datos = await apiFetch('tarjeta.php', { 
        method: 'POST', 
        body: JSON.stringify({ cuenta_id: cuentaId }) 
    }); 
    
    if (!datos) return; 
    
    // datos.data contiene la nueva tarjeta generada (HTTP 201)
    mostrarNotificacion(`Tarjeta ${datos.data.numero} creada.`, 'exito'); 
    
    // Refrescar la lista sin recargar la página
    cargarTarjetas(); 
} 

// Bloquear/desbloquear tarjeta (PUT con tarjeta_id y estado)
async function bloquearTarjeta(tarjetaId, nuevoEstado) { 
    // tarjeta.php espera: { "tarjeta_id": X, "estado": "bloqueada" } 
    const datos = await apiFetch('tarjeta.php', { 
        method: 'PUT', 
        body: JSON.stringify({ tarjeta_id: tarjetaId, estado: nuevoEstado }) 
    }); 
    
    if (!datos) return; 
    
    mostrarNotificacion(datos.message, 'exito'); 
    
    // Actualizar DOM con el nuevo estado llamando a la función de carga
    cargarTarjetas(); 
} 

// ==========================================
// 2. INICIALIZACIÓN Y EVENTOS
// ==========================================

// Ejecutar al cargar la página
document.addEventListener('DOMContentLoaded', cargarTarjetas);

// Asignar el evento al botón de crear tarjeta (suponiendo que su ID es 'btn-nueva-tarjeta')
document.getElementById('btn-nueva-tarjeta').addEventListener('click', () => generarTarjeta(1));