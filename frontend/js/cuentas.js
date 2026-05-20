

let cuentasData     = [];
let movimientosData = [];
let nuevaCuenta     = {};
let cuentaCreada    = {};
let ibanCompleto    = '';
let ibanVisible     = false;

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

async function cargarPerfil() {
    const datos = await apiFetch('perfil.php');
    if (!datos || datos.status !== 'success') return;

    const nombreCompleto = datos.nombre + ' ' + datos.apellidos;
    const avatarUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(datos.iniciales)}&background=005bbf&color=fff`;

    document.getElementById('header-avatar').src        = avatarUrl;
    document.getElementById('header-nombre').textContent = datos.nombre + ' ' + datos.apellidos.split(' ')[0] + '.';
    document.getElementById('modal-avatar').src          = `https://ui-avatars.com/api/?name=${encodeURIComponent(nombreCompleto)}&background=005bbf&color=fff`;
    document.getElementById('modal-nombre').textContent  = nombreCompleto;
    document.getElementById('modal-movil').textContent   = datos.telefono || '—';
    document.getElementById('modal-email').textContent   = datos.email;
    document.getElementById('modal-id').textContent      = datos.dni;
}

function toggleModal(show) {
    document.getElementById('modalUser').style.display = show ? 'flex' : 'none';
}

function cerrarSesion() {
    localStorage.removeItem('jwt_token');
    window.location.href = 'inicio_sesion.html';
}

async function cargarCuentas() {
    const data = await apiFetch('cuentas.php');
    if (data && data.status === 'success') cuentasData = data.cuentas;
}

async function cargarMovimientos() {
    const data = await apiFetch('movimientos.php?page=1&limit=100');
    if (data && data.status === 'success') movimientosData = data.movimientos;
}

function pintarCuentas() {
    const grid = document.getElementById('accounts-grid');
    grid.innerHTML = '';

    if (!cuentasData.length) {
        const pVacio = document.createElement('p');
        pVacio.style.cssText = 'color:var(--text-muted);font-size:14px;';
        pVacio.textContent = 'No tienes cuentas aún. ¡Crea la primera!';
        grid.appendChild(pVacio);
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
        
        const headerRow = document.createElement('div');
        headerRow.className = 'card-header-row';
        const headerLeft = document.createElement('div');
        const h3 = document.createElement('h3');
        h3.className = 'card-name';
        h3.textContent = titulo;
        const pNum = document.createElement('p');
        pNum.className = 'card-number';
        pNum.textContent = numMask;
        headerLeft.appendChild(h3);
        headerLeft.appendChild(pNum);
        const iconWrap = document.createElement('div');
        iconWrap.className = `account-icon ${iconClass}`;
        const iconSpan = document.createElement('span');
        iconSpan.className = 'material-symbols-outlined icon-fill';
        iconSpan.textContent = iconName;
        iconWrap.appendChild(iconSpan);
        headerRow.appendChild(headerLeft);
        headerRow.appendChild(iconWrap);
        
        const balanceDiv = document.createElement('div');
        balanceDiv.style.cssText = 'margin-top:24px;';
        const pHint = document.createElement('p');
        pHint.className = 'balance-hint';
        pHint.textContent = label;
        const pValue = document.createElement('p');
        pValue.className = 'balance-value';
        pValue.textContent = saldoFmt;
        balanceDiv.appendChild(pHint);
        balanceDiv.appendChild(pValue);
        card.appendChild(headerRow);
        card.appendChild(balanceDiv);
        card.onclick = () => abrirModal(cuenta);
        grid.appendChild(card);
    });
}

function abrirModal(cuenta) {
    const esHucha = cuenta.tipo === 'ahorros';
    document.getElementById('modal-cuenta-nombre').textContent = esHucha ? 'Hucha (Ahorro)' : 'Cuenta Corriente';

    
    ibanCompleto = cuenta.numero_cuenta || '';
    ibanVisible  = false;
    document.getElementById('modal-cuenta-num').textContent = enmascararNumero(ibanCompleto);
    document.getElementById('icon-toggle-iban').textContent = 'visibility';

    document.getElementById('modal-saldo').textContent = formatearEuros(cuenta.saldo);

    
    const movsCuenta = movimientosData
        .filter(m => m.cuenta_origen_id === cuenta.id || m.cuenta_destino_id === cuenta.id)
        .map(m => {
            if (m.tipo === 'transferencia' || m.tipo === 'bizum') {
                const cantidad = m.cuenta_origen_id === cuenta.id
                    ? -Math.abs(m.cantidad)   // esta cuenta envió → negativo
                    :  Math.abs(m.cantidad);  // esta cuenta recibió → positivo
                return { ...m, cantidad };
            }
            return m;
        });
    pintarMovimientosModal(movsCuenta.slice(0, 5));

    document.getElementById('modal-cuenta').classList.add('open');
}

function pintarMovimientosModal(movimientos) {
    const lista = document.getElementById('modal-mov-list');
    lista.innerHTML = ''; 

    if (!movimientos.length) {
        const divVacio = document.createElement('div');
        divVacio.className = 'modal-mov-empty';
        divVacio.textContent = 'Sin movimientos recientes';
        lista.appendChild(divVacio);
        return;
    }
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

        const spanIco = document.createElement('span');
        spanIco.className = 'material-symbols-outlined';
        spanIco.style.cssText = 'font-size:18px;';
        spanIco.textContent = icono;
        const divIco = document.createElement('div');
        divIco.className = 'modal-mov-icon';
        divIco.appendChild(spanIco);
        const divNombre = document.createElement('div');
        divNombre.className = 'modal-mov-name';
        divNombre.textContent = mov.concepto;
        const divFecha = document.createElement('div');
        divFecha.className = 'modal-mov-date';
        divFecha.textContent = fecha;
        const divMovInfo = document.createElement('div');
        divMovInfo.className = 'modal-mov-info';
        divMovInfo.appendChild(divNombre);
        divMovInfo.appendChild(divFecha);
        const divAmt = document.createElement('div');
        divAmt.className = `modal-mov-amount ${claseAmt}`;
        divAmt.textContent = signo + Math.abs(mov.cantidad).toFixed(2) + '€';
        const divItem = document.createElement('div');
        divItem.className = 'modal-mov-item';
        divItem.appendChild(divIco);
        divItem.appendChild(divMovInfo);
        divItem.appendChild(divAmt);
        lista.appendChild(divItem);
    });
}

function mostrarVista(id) {
    document.querySelectorAll('.page-section').forEach(s => s.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    window.scrollTo(0, 0);
}

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

function _initEventListeners() {
    document.getElementById('modal-close').addEventListener('click', () =>
        document.getElementById('modal-cuenta').classList.remove('open'));

    document.getElementById('modal-cuenta').addEventListener('click', function (e) {
        if (e.target === this) this.classList.remove('open');
    });

    document.getElementById('btn-toggle-iban').addEventListener('click', () => {
        ibanVisible = !ibanVisible;
        const numEl  = document.getElementById('modal-cuenta-num');
        const iconEl = document.getElementById('icon-toggle-iban');
        if (ibanVisible) {
            const limpio  = ibanCompleto.replace(/\s/g, '');
            numEl.textContent  = limpio.match(/.{1,4}/g)?.join(' ') ?? ibanCompleto;
            iconEl.textContent = 'visibility_off';
        } else {
            numEl.textContent  = enmascararNumero(ibanCompleto);
            iconEl.textContent = 'visibility';
        }
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
        const resumenIcon = document.getElementById('resumen-icon');
        resumenIcon.replaceChildren();
        const spanResumen = document.createElement('span');
        spanResumen.className = 'material-symbols-outlined icon-fill';
        spanResumen.textContent = esHucha ? 'savings' : 'account_balance_wallet';
        resumenIcon.appendChild(spanResumen);
        mostrarVista('section-form-step2');
    });

    document.getElementById('form-step2').addEventListener('submit', async function (e) {
        e.preventDefault();
        const chk = document.getElementById('chk-legal');
        if (!chk.checked) { mostrarNotificacion('Debes aceptar los términos y condiciones.', 'advertencia'); return; }

        const btn = document.getElementById('btn-submit-final');
        btn.disabled  = true;
        btn.replaceChildren();
        btn.append('Creando cuenta... ');
        const spinSpan = document.createElement('span');
        spinSpan.className = 'material-symbols-outlined';
        spinSpan.style.cssText = 'font-size:18px;animation:spin 1s linear infinite;';
        spinSpan.textContent = 'sync';
        btn.appendChild(spinSpan);

        const respuesta = await apiFetch('crearCuenta.php', {
            method: 'POST',
            body: JSON.stringify({ tipo: nuevaCuenta.tipo, deposito: nuevaCuenta.deposito })
        });

        btn.disabled  = false;
        btn.replaceChildren();
        btn.append('Completar Apertura ');
        const checkSpan = document.createElement('span');
        checkSpan.className = 'material-symbols-outlined';
        checkSpan.style.cssText = 'font-size:18px;';
        checkSpan.textContent = 'check';
        btn.appendChild(checkSpan);

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
