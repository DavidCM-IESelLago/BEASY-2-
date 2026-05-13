// js/movimientos.js 

let paginaActual = 1; 
const LIMITE = 10; 

async function cargarMovimientos(pagina = 1) { 
    // Petición con parámetros de paginación en la URL (GET) 
    const datos = await apiFetch(`movimientos.php?page=${pagina}&limit=${LIMITE}`); 
    
    if (!datos) return; 
    
    // Renderizar filas en la tabla 
    const tbody = document.querySelector('#tabla-movimientos tbody'); 
    
    // Limpiar tabla de forma segura (sin innerHTML)
    while (tbody.firstChild) {
        tbody.removeChild(tbody.firstChild);
    }
    
    datos.movimientos.forEach(mov => { 
        const icono = mov.tipo === 'ingreso' ? '↑' : '↓'; 
        const clase = mov.tipo === 'ingreso' ? 'ingreso' : 'gasto'; 
        
        // Creamos la fila
        const fila = document.createElement('tr');
        fila.className = clase;
        
        // Creamos las celdas (columnas)
        const celdaFecha = document.createElement('td');
        celdaFecha.textContent = mov.fecha;
        
        const celdaConcepto = document.createElement('td');
        celdaConcepto.textContent = icono + ' ' + mov.concepto;
        
        const celdaMonto = document.createElement('td');
        celdaMonto.textContent = Math.abs(mov.cantidad).toFixed(2) + ' EUR';
        
        // Añadimos las celdas a la fila
        fila.appendChild(celdaFecha);
        fila.appendChild(celdaConcepto);
        fila.appendChild(celdaMonto);
        
        // Añadimos la fila a la tabla
        tbody.appendChild(fila); 
    }); 
    
    // Actualizar info de paginación en el DOM 
    const pag = datos.paginacion; 
    document.getElementById('info-pagina').textContent = `Página ${pag.pagina_actual}`; 
    paginaActual = pag.pagina_actual; 
} 

// Botones de navegación 
document.getElementById('btn-anterior').addEventListener('click', () => { 
    if (paginaActual > 1) cargarMovimientos(paginaActual - 1); 
}); 

document.getElementById('btn-siguiente').addEventListener('click', () => { 
    cargarMovimientos(paginaActual + 1); 
}); 

document.addEventListener('DOMContentLoaded', () => cargarMovimientos(1));