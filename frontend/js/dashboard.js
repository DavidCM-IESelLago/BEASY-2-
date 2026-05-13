// js/dashboard.js 

async function cargarDashboard() { 
    const datos = await apiFetch('dashboard.php'); 
    if (!datos) return; 
    
    const dash = datos.dashboard; 
    
    // 1. Actualizar saldo total en el DOM 
    document.getElementById('saldo-total').textContent = dash.saldo_total.toLocaleString('es-ES', { style: 'currency', currency: 'EUR' }); 
    
    // 2. Renderizar tabla de últimos movimientos 
    const tbody = document.querySelector('#tabla-movimientos tbody'); 
    
    // Limpiar de forma segura (sin innerHTML)
    while (tbody.firstChild) {
        tbody.removeChild(tbody.firstChild);
    }
    
    dash.ultimos_movimientos.forEach(mov => { 
        const fila = document.createElement('tr'); 
        fila.className = mov.monto < 0 ? 'gasto' : 'ingreso'; 
        
        const celdaFecha = document.createElement('td');
        celdaFecha.textContent = mov.fecha;
        
        const celdaConcepto = document.createElement('td');
        celdaConcepto.textContent = mov.concepto;
        
        const celdaMonto = document.createElement('td');
        celdaMonto.textContent = mov.monto.toLocaleString('es-ES', { style: 'currency', currency: 'EUR' });
        
        fila.appendChild(celdaFecha);
        fila.appendChild(celdaConcepto);
        fila.appendChild(celdaMonto);
        
        tbody.appendChild(fila); 
    }); 
    
    // 3. Renderizar estadísticas de gasto 
    const contenedor = document.getElementById('stats-gastos'); 
    
    // Limpiar de forma segura (sin innerHTML)
    while (contenedor.firstChild) {
        contenedor.removeChild(contenedor.firstChild);
    }
    
    for (const [categoria, importe] of Object.entries(dash.estadisticas_gastos)) { 
        const cajaStat = document.createElement('div');
        cajaStat.className = 'stat-item';
        
        const textoCategoria = document.createElement('span');
        textoCategoria.textContent = categoria;
        
        const textoImporte = document.createElement('strong');
        textoImporte.textContent = importe.toFixed(2) + ' EUR';
        
        cajaStat.appendChild(textoCategoria);
        cajaStat.appendChild(textoImporte);
        
        contenedor.appendChild(cajaStat);
    } 
} 

document.addEventListener('DOMContentLoaded', cargarDashboard);