// js/dashboard.js

async function cargarDashboard() {
    const datos = await apiFetch('dashboard.php');
    if (!datos) return;

    const dash = datos.dashboard;

    // 1. Actualizar saldo total en el DOM
    document.getElementById('saldo-total').textContent = dash.saldo_total.toLocaleString('es-ES', { style: 'currency', currency: 'EUR' });

    // 2. Renderizar tabla de últimos movimientos
    const tbody = document.querySelector('#tabla-movimientos tbody');

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

    // 3. Cargar gráfico de gastos del mes
    cargarGastosMes();
}

// ── GASTOS DEL MES: carga movimientos y actualiza el donut ────────────────
async function cargarGastosMes() {
    const data = await apiFetch('movimientos.php?page=1&limit=1000');
    if (!data || data.status !== 'success') return;

    const ahora  = new Date();
    const delMes = data.movimientos.filter(m => {
        const f = new Date(m.fecha);
        return f.getMonth()    === ahora.getMonth() &&
               f.getFullYear() === ahora.getFullYear();
    });

    actualizarGrafico(delMes);
}

// ── DONUT CHART ───────────────────────────────────────────────────────────
function actualizarGrafico(movimientos) {
    const CIRCUNFERENCIA = 251.2;
    const COLORES = {
        compra:        '#1a73e8',
        transferencia: '#005bbf',
        bizum:         '#006d2c',
        ingreso:       '#89fa9b'
    };

    const gastos      = movimientos.filter(m => m.cantidad < 0);
    const totalGastos = gastos.reduce((sum, m) => sum + Math.abs(m.cantidad), 0);

    document.getElementById('total-gastos').textContent =
        totalGastos > 0
            ? totalGastos.toLocaleString('es-ES', { style: 'currency', currency: 'EUR' })
            : '0,00 €';

    const svg = document.querySelector('.chart-svg');
    svg.querySelectorAll('.chart-circle').forEach(c => c.remove());

    if (totalGastos === 0) {
        svg.appendChild(crearArco('#c1c6d6', CIRCUNFERENCIA, 0, CIRCUNFERENCIA));
        return;
    }

    const porTipo = {};
    gastos.forEach(m => {
        porTipo[m.tipo] = (porTipo[m.tipo] || 0) + Math.abs(m.cantidad);
    });

    let acumulado = 0;
    Object.entries(porTipo).forEach(([tipo, importe]) => {
        const longitud = (importe / totalGastos) * CIRCUNFERENCIA;
        const offset   = CIRCUNFERENCIA - acumulado;
        svg.appendChild(crearArco(COLORES[tipo] || '#c1c6d6', longitud, CIRCUNFERENCIA - longitud, offset));
        acumulado += longitud;
    });

    // Leyenda
    const leyenda = document.getElementById('leyenda-grafico');
    while (leyenda.firstChild) leyenda.removeChild(leyenda.firstChild);

    Object.entries(porTipo).forEach(([tipo, importe]) => {
        const porcentaje = ((importe / totalGastos) * 100).toFixed(0);
        const item = document.createElement('div');
        item.style.cssText = 'display:flex;align-items:center;justify-content:space-between;font-size:13px;';
        item.innerHTML = `
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="width:10px;height:10px;border-radius:50%;background:${COLORES[tipo] || '#c1c6d6'};flex-shrink:0;"></span>
                <span style="text-transform:capitalize;color:var(--on-surface-variant);">${tipo}</span>
            </div>
            <span style="font-weight:600;">${importe.toLocaleString('es-ES', { style: 'currency', currency: 'EUR' })} <span style="font-weight:400;color:var(--on-surface-variant);">(${porcentaje}%)</span></span>
        `;
        leyenda.appendChild(item);
    });
}

function crearArco(color, dasharray, dashGap, dashoffset) {
    const c = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    c.setAttribute('class', 'chart-circle');
    c.setAttribute('cx', '50');
    c.setAttribute('cy', '50');
    c.setAttribute('fill', 'transparent');
    c.setAttribute('r', '40');
    c.setAttribute('stroke', color);
    c.setAttribute('stroke-width', '14');
    c.setAttribute('stroke-dasharray', `${dasharray} ${dashGap}`);
    c.setAttribute('stroke-dashoffset', dashoffset);
    return c;
}
// ── FIN DONUT CHART ───────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', cargarDashboard);