// js/cuentas.js

// ── Estado ────────────────────────────────────────────────────────────────────
let cuentasData     = [];
let movimientosData = [];
let nuevaCuenta     = {};
let cuentaCreada    = {};

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
        await Promise.all([cargarCuentas(), cargarMovimientos(), cargarPerfil()]);
        pintarCuentas();
        _initEventListeners();
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

// ── Datos ─────────────────────────────────────────────────────────────────────
async function cargarCuentas() {
    const data = await apiFetch('cuentas.php');
    if (data && data.status === 'success') cuentasData = data.cuentas;
}

async function cargarMovimientos() {
    const data = await apiFetch('movimientos.php?page=1&limit=100');
    if (data && data.status === 'success') movimientosData = data.movimientos;
}

// ── Renderizado de cuentas ────────────────────────────────────────────────────
function pintarCuentas() {
    const grid = document.getElementById('accounts-grid');
    grid.innerHTML = '';

    if (!cuentasData.length) {
        grid.innerHTML = '<p style="color:var(--text-muted); font-size:14px;">No tienes cuentas aún. ¡Crea la primera!</p>';
        return;
    }

    cuentasData.forEach(cuenta => {
        const esHucha   = cuenta.tipo === 'ahorros';
        const iconName  = esHucha ? 'savings' : 'account_balance_wallet';
        const iconClass = esHucha ? 'icon-green' : 'icon-blue';
        const label     = esHucha ? 'SALDO HUCHA' : 'SALDO DISPONIBLE';
        const titulo    = esHucha ? 'Hucha (Ahorro)' : 'Cuenta Corriente';
        const numMask   = enmascararNumero(cuenta.numero_cuenta);
        const saldoFmt  = formatearEuros(cuenta.saldo);

        const card = document.createElement('div');
        card.className  = 'account-card';
        card.dataset.id = cuenta.id;
        card.innerHTML  = `
            <div class="card-header-row">
                <div>
                    <h3 class="card-name">${titulo}</h3>
                    <p class="card-number">${numMask}</p>
                </div>
                <div class="account-icon ${iconClass}">
                    <span class="material-symbols-outlined icon-fill">${iconName}</span>
                </div>
            </div>
            <div style="margin-top:24px;">
                <p class="balance-hint">${label}</p>
                <p class="balance-value">${saldoFmt}</p>
            </div>
        `;
        card.addEventListener('click', () => abrirModal(cuenta));
        grid.appendChild(card);
    });
}

// ── Modal de cuenta ───────────────────────────────────────────────────────────
function abrirModal(cuenta) {
    const esHucha = cuenta.tipo === 'ahorros';
    document.getElementById('modal-cuenta-nombre').textContent = esHucha ? 'Hucha (Ahorro)' : 'Cuenta Corriente';
    document.getElementById('modal-cuenta-num').textContent    = enmascararNumero(cuenta.numero_cuenta);
    document.getElementById('modal-saldo').textContent         = formatearEuros(cuenta.saldo);
    document.getElementById('modal-actions-corriente').style.display = esHucha ? 'none' : 'flex';
    document.getElementById('modal-actions-hucha').style.display     = esHucha ? 'flex' : 'none';
    pintarMovimientosModal(movimientosData.slice(0, 3));
    document.getElementById('modal-cuenta').classList.add('open');
}

function pintarMovimientosModal(movimientos) {
    const lista = document.getElementById('modal-mov-list');
    if (!movimientos.length) {
        lista.innerHTML = '<div class="modal-mov-empty">Sin movimientos recientes</div>';
        return;
    }
    lista.innerHTML = '';
    movimientos.forEach(mov => {
        let icono = 'payments';
        if (mov.tipo === 'compra')        icono = 'shopping_cart';
        if (mov.tipo === 'bizum')         icono = 'send_money';
        if (mov.tipo === 'transferencia') icono = 'swap_horiz';
        if (mov.tipo === 'ingreso')       icono = 'savings';

        const esPos    = mov.cantidad > 0;
        const claseAmt = esPos ? 'amount-pos' : 'amount-neg';
        const signo    = esPos ? '+' : '';
        const fecha    = new Date(mov.fecha).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });

        lista.innerHTML += `
            <div class="modal-mov-item">
                <div class="modal-mov-icon"><span class="material-symbols-outlined" style="font-size:18px;">${icono}</span></div>
                <div class="modal-mov-info">
                    <div class="modal-mov-name">${mov.concepto}</div>
                    <div class="modal-mov-date">${fecha}</div>
                </div>
                <div class="modal-mov-amount ${claseAmt}">${signo}${Math.abs(mov.cantidad).toFixed(2)}€</div>
            </div>
        `;
    });
}

// ── Navegación de vistas ──────────────────────────────────────────────────────
function mostrarVista(id) {
    document.querySelectorAll('.page-section').forEach(s => s.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    window.scrollTo(0, 0);
}

// ── Utilidades ────────────────────────────────────────────────────────────────
function enmascararNumero(num) {
    if (!num) return '•••• •••• •••• ••••';
    const limpio = num.replace(/\s/g, '');
    return `${limpio.slice(0, 4)} •••• •••• ${limpio.slice(-4)}`;
}

function formatearEuros(valor) {
    return '€' + parseFloat(valor).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function copiarNumero() {
    const num = document.getElementById('success-numero').textContent;
    navigator.clipboard.writeText(num).then(() => mostrarNotificacion('Número copiado al portapapeles', 'exito'));
}

// ── Event Listeners ───────────────────────────────────────────────────────────
function _initEventListeners() {
    document.getElementById('modal-close').addEventListener('click', () =>
        document.getElementById('modal-cuenta').classList.remove('open'));

    document.getElementById('modal-cuenta').addEventListener('click', function (e) {
        if (e.target === this) this.classList.remove('open');
    });

    document.getElementById('btn-show-form').addEventListener('click', () => mostrarVista('section-form-step1'));
    document.getElementById('btn-cancel-step1').addEventListener('click', () => mostrarVista('section-accounts'));
    document.getElementById('btn-back-step1').addEventListener('click', () => mostrarVista('section-form-step1'));
    document.getElementById('btn-back-step1-bottom').addEventListener('click', () => mostrarVista('section-form-step1'));

    document.getElementById('btn-ir-cuentas').addEventListener('click', async () => {
        await cargarCuentas();
        pintarCuentas();
        mostrarVista('section-accounts');
    });

    document.getElementById('form-step1').addEventListener('submit', function (e) {
        e.preventDefault();
        const tipo     = document.querySelector('input[name="acc_type"]:checked').value;
        const alias    = document.getElementById('input-alias').value.trim();
        const deposito = parseFloat(document.getElementById('input-deposito').value) || 0;
        nuevaCuenta = { tipo, alias, deposito };

        const esHucha = tipo === 'ahorros';
        document.getElementById('resumen-nombre').textContent = esHucha ? 'Hucha (Ahorro)' : 'Cuenta Corriente';
        document.getElementById('resumen-desc').textContent   = esHucha ? 'Meta de ahorro personalizada' : 'Sin comisiones de mantenimiento';
        document.getElementById('resumen-icon').innerHTML     = `<span class="material-symbols-outlined icon-fill">${esHucha ? 'savings' : 'account_balance_wallet'}</span>`;
        mostrarVista('section-form-step2');
    });

    document.getElementById('form-step2').addEventListener('submit', async function (e) {
        e.preventDefault();
        const chk = document.getElementById('chk-legal');
        if (!chk.checked) { mostrarNotificacion('Debes aceptar los términos y condiciones.', 'advertencia'); return; }

        const btn = document.getElementById('btn-submit-final');
        btn.disabled  = true;
        btn.innerHTML = 'Creando cuenta... <span class="material-symbols-outlined" style="font-size:18px;animation:spin 1s linear infinite;">sync</span>';

        const respuesta = await apiFetch('crearCuenta.php', {
            method: 'POST',
            body: JSON.stringify({ tipo: nuevaCuenta.tipo, deposito: nuevaCuenta.deposito })
        });

        btn.disabled  = false;
        btn.innerHTML = 'Completar Apertura <span class="material-symbols-outlined" style="font-size:18px;">check</span>';

        if (!respuesta || respuesta.status !== 'success') return;

        const cuenta = respuesta.cuenta;
        cuentaCreada = {
            tipo:   cuenta.tipo === 'ahorros' ? 'Hucha (Ahorro)' : 'Cuenta Corriente',
            numero: cuenta.numero_cuenta,
            saldo:  cuenta.saldo
        };
        document.getElementById('success-tipo').textContent   = cuentaCreada.tipo;
        document.getElementById('success-numero').textContent = cuentaCreada.numero;
        document.getElementById('success-saldo').textContent  = formatearEuros(cuentaCreada.saldo);
        document.getElementById('form-step1').reset();
        document.getElementById('chk-legal').checked = false;
        mostrarVista('section-success');
    });
}
