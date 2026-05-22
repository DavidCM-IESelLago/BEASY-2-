

let _cuentasUsuario   = [];
let _ibanValidoCache  = { iban: '', existe: false };
let _timerIban        = null;

document.addEventListener('DOMContentLoaded', async () => {
    const token = localStorage.getItem('jwt_token');
    if (!token) { window.location.href = 'inicio_sesion'; return; }

    try {
        const respuesta = await apiFetch('validate_helper.php');
        if (respuesta && respuesta.status === 'success') {
            document.body.style.display = 'flex';
            await cargarPerfil();
            await cargarDashboard();
        } else {
            localStorage.removeItem('jwt_token');
            window.location.href = 'inicio_sesion';
        }
    } catch (e) {
        window.location.href = 'inicio_sesion';
    }

    _initTransferForm();
    _initBizumForm();
});

function showSection(id) {
    ['dashboard-content', 'transfer-form', 'bizum-form'].forEach(s =>
        document.getElementById(s).classList.add('hidden'));
    document.getElementById(id).classList.remove('hidden');
    if (id === 'transfer-form') cargarCuentasOrigen();
}

async function cargarPerfil() {
    const datos = await apiFetch('perfil.php');
    if (!datos || datos.status !== 'success') return;

    const nombreCompleto = datos.nombre + ' ' + datos.apellidos;
    const avatarUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(datos.iniciales)}&background=005bbf&color=fff`;

    document.getElementById('header-avatar').src   = avatarUrl;
    document.getElementById('header-nombre').textContent = datos.nombre + ' ' + datos.apellidos.split(' ')[0] + '.';
    document.getElementById('modal-avatar').src    = `https://ui-avatars.com/api/?name=${encodeURIComponent(nombreCompleto)}&background=005bbf&color=fff`;
    document.getElementById('modal-nombre').textContent  = nombreCompleto;
    document.getElementById('modal-movil').textContent   = datos.telefono || '—';
    document.getElementById('modal-email').textContent   = datos.email;
    document.getElementById('modal-id').textContent      = datos.dni;
}

function toggleModal(show) {
    const modal = document.getElementById('modalUser');
    modal.style.display = show ? 'flex' : 'none';
    if (show) { setTimeout(() => _trapFocus(modal), 50); } else { _releaseFocus(modal); }
}

function cerrarSesion() {
    localStorage.removeItem('jwt_token');
    window.location.href = 'inicio_sesion';
}

async function cargarDashboard() {
    const datos = await apiFetch('dashboard.php');
    if (!datos) return;

    const dash = datos.dashboard;

    document.getElementById('saldo-total').textContent =
        dash.saldo_total.toLocaleString('es-ES', { style: 'currency', currency: 'EUR' });

    const tbody = document.querySelector('#tabla-movimientos tbody');
    if (!tbody) return;

    tbody.innerHTML = '';
    dash.ultimos_movimientos.forEach(mov => {
        const fila = document.createElement('tr');
        fila.className = mov.monto < 0 ? 'gasto' : 'ingreso';

        const celdaFecha    = document.createElement('td');
        celdaFecha.textContent = mov.fecha;

        const celdaConcepto = document.createElement('td');
        celdaConcepto.textContent = mov.concepto;

        const celdaMonto    = document.createElement('td');
        celdaMonto.textContent = mov.monto.toLocaleString('es-ES', { style: 'currency', currency: 'EUR' });

        fila.append(celdaFecha, celdaConcepto, celdaMonto);
        tbody.appendChild(fila);
    });

    actualizarGrafico(dash.estadisticas_gastos);
}

function _opcionSelect(texto, valor = '') {
    const opt = document.createElement('option');
    opt.value = valor;
    opt.textContent = texto;
    return opt;
}

async function cargarCuentasOrigen() {
    const select = document.getElementById('trans-origen');
    if (!select) return;

    select.replaceChildren(_opcionSelect('Cargando cuentas...'));
    const data = await apiFetch('cuentas.php');

    if (!data || data.status !== 'success' || !Array.isArray(data.cuentas) || data.cuentas.length === 0) {
        select.replaceChildren(_opcionSelect('No tienes cuentas disponibles'));
        return;
    }

    _cuentasUsuario = data.cuentas;
    select.replaceChildren(_opcionSelect('Selecciona una cuenta...'));
    _cuentasUsuario.forEach(c => {
        const saldo = Number(c.saldo).toLocaleString('es-ES', { style: 'currency', currency: 'EUR' });
        const opt   = _opcionSelect(`${c.numero_cuenta} (${saldo})`, c.id);
        select.appendChild(opt);
    });
}

function _initTransferForm() {
    const inputIban = document.getElementById('trans-iban');
    const errorIban = document.getElementById('trans-iban-error');
    const form      = document.getElementById('form-transferencia');

    if (inputIban) {
        inputIban.addEventListener('input', () => {
            const valor = inputIban.value.trim();
            errorIban.style.display = 'none';
            _ibanValidoCache = { iban: '', existe: false };
            clearTimeout(_timerIban);
            if (valor.length < 4) return;

            _timerIban = setTimeout(async () => {
                const ibanLimpio = encodeURIComponent(valor.replace(/\s+/g, ''));
                const data = await apiFetch(`verificarCuenta.php?iban=${ibanLimpio}`);
                if (!data || data.status !== 'success') return;
                _ibanValidoCache = { iban: valor, existe: !!data.existe, cuenta_id: data.cuenta_id || null };
                errorIban.style.display = data.existe ? 'none' : 'block';
            }, 400);
        });
    }

    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const cuentaOrigenId = document.getElementById('trans-origen').value;
            const ibanDestino    = document.getElementById('trans-iban').value.trim();
            const importe        = parseFloat(document.getElementById('trans-importe').value);
            const concepto       = document.getElementById('trans-concepto').value.trim();

            if (!cuentaOrigenId) { mostrarNotificacion('Selecciona una cuenta de origen', 'advertencia'); return; }
            if (!ibanDestino)    { mostrarNotificacion('Introduce el IBAN del destinatario', 'advertencia'); return; }
            if (!importe || importe <= 0) { mostrarNotificacion('Introduce un importe válido', 'advertencia'); return; }
            if (!concepto)       { mostrarNotificacion('El concepto es obligatorio', 'advertencia'); return; }

            if (_ibanValidoCache.iban === ibanDestino && _ibanValidoCache.existe === false) {
                errorIban.style.display = 'block';
                mostrarNotificacion('La cuenta de destino no existe', 'error');
                return;
            }

            // Rellenar resumen del paso 2
            const selectOrigen = document.getElementById('trans-origen');
            const textoOrigen  = selectOrigen.options[selectOrigen.selectedIndex]?.text || cuentaOrigenId;
            document.getElementById('resumen-trans-origen').textContent  = textoOrigen;
            document.getElementById('resumen-trans-iban').textContent    = ibanDestino;
            document.getElementById('resumen-trans-concepto').textContent = concepto;
            document.getElementById('resumen-trans-importe').textContent = importe.toFixed(2) + ' €';

            // Mostrar paso 2 y ocultar paso 1
            document.getElementById('transfer-step1').style.display = 'none';
            document.getElementById('transfer-step2').style.display = 'block';

            // Actualizar stepper
            const c1 = document.getElementById('trans-circle-1');
            c1.style.background = 'var(--tertiary-light)';
            c1.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;">check</span>';
            document.getElementById('trans-ind-2').style.opacity = '1';
            const c2 = document.getElementById('trans-circle-2');
            c2.style.background = 'var(--primary-light)';
            c2.style.color = 'white';
            c2.style.border = 'none';
            c2.textContent = '2';

            // Scroll automático al botón confirmar
            document.getElementById('paso-confirmacion').scrollIntoView({
                behavior: 'smooth', block: 'start'
            });
        });
    }

    // Botón confirmar transferencia (paso 2)
    const btnConfirmar = document.getElementById('btn-confirmar-transferencia');
    if (btnConfirmar) {
        btnConfirmar.addEventListener('click', async () => {
            const cuentaOrigenId = document.getElementById('trans-origen').value;
            const ibanDestino    = document.getElementById('trans-iban').value.trim();
            const importe        = parseFloat(document.getElementById('trans-importe').value);
            const concepto       = document.getElementById('trans-concepto').value.trim();

            btnConfirmar.disabled    = true;
            btnConfirmar.textContent = 'Procesando...';

            const respuesta = await apiFetch('transferencia.php', {
                method: 'POST',
                body: JSON.stringify({
                    cuenta_origen_id: parseInt(cuentaOrigenId, 10),
                    iban_destino: ibanDestino,
                    importe,
                    concepto
                })
            });

            btnConfirmar.disabled    = false;
            btnConfirmar.innerHTML   = '<span class="material-symbols-outlined" style="font-size:18px;">check</span> Confirmar transferencia';

            if (respuesta && respuesta.status === 'success') {
                mostrarNotificacion('Transferencia realizada con éxito', 'exito');
                form.reset();
                document.getElementById('trans-iban-error').style.display = 'none';
                volverPaso1Transfer();
                await cargarDashboard();
                showSection('dashboard-content');
            }
        });
    }
}

function volverPaso1Transfer() {
    document.getElementById('transfer-step2').style.display = 'none';
    document.getElementById('transfer-step1').style.display = 'block';

    // Restaurar stepper
    const c1 = document.getElementById('trans-circle-1');
    c1.style.background = 'var(--primary-light)';
    c1.textContent = '1';
    document.getElementById('trans-ind-2').style.opacity = '0.45';
    const c2 = document.getElementById('trans-circle-2');
    c2.style.background = 'transparent';
    c2.style.color = 'var(--text-muted)';
    c2.style.border = '2px solid var(--border)';
    c2.textContent = '2';
}

function _initBizumForm() {
    const inputTel = document.getElementById('bizum-tel');
    const errorTel = document.getElementById('bizum-tel-error');
    const formBz   = document.getElementById('form-bizum');
    let _telCache  = { tel: '', existe: false };
    let _timerTel  = null;

    if (inputTel) {
        inputTel.addEventListener('input', () => {
            const valor = inputTel.value.trim();
            errorTel.style.display = 'none';
            _telCache = { tel: '', existe: false };
            clearTimeout(_timerTel);
            if (valor.length < 4) return;

            _timerTel = setTimeout(async () => {
                const telLimpio = encodeURIComponent(valor.replace(/\s+/g, ''));
                const data = await apiFetch(`verificarTelefono.php?telefono=${telLimpio}`);
                if (!data || data.status !== 'success') return;
                _telCache = { tel: valor, existe: !!data.existe };
                errorTel.style.display = data.existe ? 'none' : 'block';
            }, 400);
        });
    }

    if (formBz) {
        formBz.addEventListener('submit', async (e) => {
            e.preventDefault();
            const telefono = document.getElementById('bizum-tel').value.trim();
            const importe  = parseFloat(document.getElementById('bizum-importe').value);
            const concepto = document.getElementById('bizum-concepto').value.trim();

            if (!telefono) { mostrarNotificacion('Introduce el número de teléfono', 'advertencia'); return; }
            if (!importe || importe <= 0) { mostrarNotificacion('Introduce un importe válido', 'advertencia'); return; }
            if (!concepto) { mostrarNotificacion('El concepto es obligatorio', 'advertencia'); return; }

            if (_telCache.tel === telefono && _telCache.existe === false) {
                errorTel.style.display = 'block';
                mostrarNotificacion('Ese número no corresponde a ninguna cuenta', 'error');
                return;
            }

            const btnBz = document.getElementById('btn-enviar-bizum');
            btnBz.disabled = true;
            const txtBz = btnBz.textContent;
            btnBz.textContent = 'Enviando...';

            const respuesta = await apiFetch('bizum.php', {
                method: 'POST',
                body: JSON.stringify({ telefono: telefono.replace(/\s+/g, ''), importe, concepto })
            });

            btnBz.disabled = false;
            btnBz.textContent = txtBz;

            if (respuesta && respuesta.status === 'success') {
                mostrarNotificacion('Bizum enviado con éxito', 'exito');
                formBz.reset();
                errorTel.style.display = 'none';
                await cargarDashboard();
                showSection('dashboard-content');
            }
        });
    }
}

function actualizarGrafico(porTipo) {
    const CIRCUNFERENCIA = 251.2;
    const COLORES = {
        compra:        '#1a73e8',
        transferencia: '#005bbf',
        bizum:         '#006d2c',
        ingreso:       '#89fa9b'
    };

    const porTipoNorm = {};
    Object.entries(porTipo || {}).forEach(([tipo, importe]) => {
        porTipoNorm[tipo.toLowerCase()] = importe;
    });

    const totalGastos = Object.values(porTipoNorm).reduce((sum, v) => sum + v, 0);

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

    let acumulado = 0;
    Object.entries(porTipoNorm).forEach(([tipo, importe]) => {
        const longitud = (importe / totalGastos) * CIRCUNFERENCIA;
        const offset   = CIRCUNFERENCIA - acumulado;
        svg.appendChild(crearArco(COLORES[tipo] || '#c1c6d6', longitud, CIRCUNFERENCIA - longitud, offset));
        acumulado += longitud;
    });

    const leyenda = document.getElementById('leyenda-grafico');
    leyenda.innerHTML = '';
    Object.entries(porTipoNorm).forEach(([tipo, importe]) => {
        const porcentaje = ((importe / totalGastos) * 100).toFixed(0);
        const item = document.createElement('div');
        item.style.cssText = 'display:flex;align-items:center;justify-content:space-between;font-size:13px;';

        const left = document.createElement('div');
        left.style.cssText = 'display:flex;align-items:center;gap:8px;';
        const dot = document.createElement('span');
        dot.style.cssText = `width:10px;height:10px;border-radius:50%;background:${COLORES[tipo] || '#c1c6d6'};flex-shrink:0;`;
        const labelTipo = document.createElement('span');
        labelTipo.style.cssText = 'text-transform:capitalize;color:var(--on-surface-variant);';
        labelTipo.textContent = tipo;
        left.appendChild(dot);
        left.appendChild(labelTipo);

        const right = document.createElement('span');
        right.style.cssText = 'font-weight:600;';
        right.textContent = importe.toLocaleString('es-ES', { style: 'currency', currency: 'EUR' }) + ' ';
        const pct = document.createElement('span');
        pct.style.cssText = 'font-weight:400;color:var(--on-surface-variant);';
        pct.textContent = `(${porcentaje}%)`;
        right.appendChild(pct);

        item.appendChild(left);
        item.appendChild(right);
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
