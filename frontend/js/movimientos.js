// js/movimientos.js

// ── Estado ────────────────────────────────────────────────────────────────────
const LIMITE     = 3;
let paginaActual = 1;

// ── Inicialización ────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    const token = localStorage.getItem('jwt_token');
    if (!token) { window.location.href = 'inicio_sesion.html'; return; }

    try {
        const resp = await apiFetch('validate_helper.php');
        if (!resp || resp.status !== 'success') {
            localStorage.removeItem('jwt_token');
            window.location.href = 'inicio_sesion.html';
            return;
        }
        document.body.style.display = 'flex';
        await cargarPerfil();
        await cargarDatosMes();
        await cargarPagina(paginaActual);

        document.getElementById('btn-ver-mas').addEventListener('click', () => {
            paginaActual++;
            cargarPagina(paginaActual);
        });
    } catch (e) {
        window.location.href = 'inicio_sesion.html';
    }
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

// ── Datos del mes (gráfico) ───────────────────────────────────────────────────
async function cargarDatosMes() {
    const data = await apiFetch('movimientos.php?page=1&limit=1000');
    if (!data || data.status !== 'success') return;

    const ahora  = new Date();
    const delMes = data.movimientos.filter(m => {
        const f = new Date(m.fecha);
        return f.getMonth() === ahora.getMonth() && f.getFullYear() === ahora.getFullYear();
    });
    actualizarGrafico(delMes);
}

// ── Paginación ────────────────────────────────────────────────────────────────
async function cargarPagina(pagina) {
    const tbody = document.getElementById('contenedor-transacciones');
    const boton = document.getElementById('btn-ver-mas');
    boton.innerText = 'Cargando...';
    boton.disabled  = true;

    const data = await apiFetch(`movimientos.php?page=${pagina}&limit=${LIMITE}`);
    if (!data) {
        if (pagina === 1) tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:40px;">No se pudo conectar con el servidor</td></tr>';
        boton.innerText = 'Reintentar';
        boton.disabled  = false;
        return;
    }

    if (data.status === 'success') {
        const nuevos = data.movimientos;
        if (pagina === 1 && nuevos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:40px;color:var(--text-muted);">Sin movimientos registrados</td></tr>';
            boton.style.display = 'none';
            return;
        }
        pintarMovimientos(nuevos);
        if (nuevos.length < LIMITE) {
            boton.innerText    = 'No hay más transacciones';
            boton.disabled     = true;
            boton.style.cursor = 'default';
        } else {
            boton.innerText = 'Cargar más';
            boton.disabled  = false;
        }
    } else {
        boton.innerText = 'Reintentar';
        boton.disabled  = false;
    }
}

// ── Renderizado de filas ──────────────────────────────────────────────────────
function pintarMovimientos(movimientos) {
    const tbody = document.getElementById('contenedor-transacciones');
    movimientos.forEach(mov => {
        let icono = 'payments';
        if (mov.tipo === 'compra')        icono = 'shopping_cart';
        if (mov.tipo === 'bizum')         icono = 'send_money';
        if (mov.tipo === 'transferencia') icono = 'swap_horiz';
        if (mov.tipo === 'ingreso')       icono = 'savings';

        const esPositivo   = mov.cantidad > 0;
        const claseImporte = esPositivo ? 'amount-pos' : 'amount-neg';
        const importe      = (esPositivo ? '+' : '') + mov.cantidad.toFixed(2) + '€';

        tbody.insertAdjacentHTML('beforeend', `
            <tr>
                <td>
                    <div class="tx-info">
                        <div class="tx-icon"><span class="material-symbols-outlined">${icono}</span></div>
                        <div>
                            <p class="tx-name">${mov.concepto}</p>
                            <p class="tx-method">${mov.tipo.toUpperCase()}</p>
                        </div>
                    </div>
                </td>
                <td><span class="badge badge-${mov.tipo}">${mov.tipo}</span></td>
                <td>
                    <p class="tx-date">${new Date(mov.fecha).toLocaleDateString('es-ES')}</p>
                    <p class="tx-time">${new Date(mov.fecha).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</p>
                </td>
                <td class="tx-amount ${claseImporte}">${importe}</td>
            </tr>
        `);
    });
}

// ── Donut Chart ───────────────────────────────────────────────────────────────
function actualizarGrafico(movimientos) {
    const CIRCUNFERENCIA = 251.2;
    const COLORES = { compra: '#1a73e8', transferencia: '#005bbf', bizum: '#006d2c', ingreso: '#89fa9b' };

    const gastos      = movimientos.filter(m => m.cantidad < 0);
    const totalGastos = gastos.reduce((sum, m) => sum + Math.abs(m.cantidad), 0);

    document.getElementById('total-gastos').textContent =
        totalGastos > 0 ? totalGastos.toFixed(2) + '€' : '0.00€';

    const porTipo = {};
    gastos.forEach(m => { porTipo[m.tipo] = (porTipo[m.tipo] || 0) + Math.abs(m.cantidad); });

    const svg = document.querySelector('.chart-svg');
    svg.querySelectorAll('.chart-circle').forEach(c => c.remove());

    if (totalGastos === 0) {
        svg.appendChild(crearArco('#c1c6d6', CIRCUNFERENCIA, 0, CIRCUNFERENCIA));
        return;
    }

    let acumulado = 0;
    Object.entries(porTipo).forEach(([tipo, importe]) => {
        const longitud = (importe / totalGastos) * CIRCUNFERENCIA;
        const offset   = CIRCUNFERENCIA - acumulado;
        svg.appendChild(crearArco(COLORES[tipo] || '#c1c6d6', longitud, CIRCUNFERENCIA - longitud, offset));
        acumulado += longitud;
    });

    let leyenda = document.getElementById('leyenda-grafico');
    if (!leyenda) {
        leyenda = document.createElement('div');
        leyenda.id = 'leyenda-grafico';
        leyenda.style.cssText = 'display:flex;flex-direction:column;gap:8px;margin-top:16px;';
        document.querySelector('.chart-container').closest('.card').appendChild(leyenda);
    }
    leyenda.innerHTML = '';

    Object.entries(porTipo).forEach(([tipo, importe]) => {
        const porcentaje = ((importe / totalGastos) * 100).toFixed(0);
        const item = document.createElement('div');
        item.style.cssText = 'display:flex;align-items:center;justify-content:space-between;font-size:13px;';
        item.innerHTML = `
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="width:10px;height:10px;border-radius:50%;background:${COLORES[tipo] || '#c1c6d6'};flex-shrink:0;"></span>
                <span style="text-transform:capitalize;color:var(--text-muted);">${tipo}</span>
            </div>
            <span style="font-weight:600;">${importe.toFixed(2)}€ <span style="font-weight:400;color:var(--text-muted);">(${porcentaje}%)</span></span>
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
