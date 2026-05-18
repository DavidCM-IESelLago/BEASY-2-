// js/registro.js

// ── Estado ────────────────────────────────────────────────────────────────────
let currentStep = 1;

// ── Toggle visibilidad de contraseña ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.toggle-password').forEach(icon => {
        icon.addEventListener('click', () => {
            const input = icon.previousElementSibling;
            const visible = input.type === 'text';
            input.type       = visible ? 'password' : 'text';
            icon.textContent = visible ? 'visibility' : 'visibility_off';
        });
    });
});

// ── Navegación de pasos ───────────────────────────────────────────────────────
function validateAndNext(modifier) {
    const currentStepEl = document.getElementById(`step${currentStep}`);
    const inputs        = currentStepEl.querySelectorAll('input[required]');
    let allValid        = true;

    if (currentStep === 2) {
        document.getElementById('error-password').style.display       = 'none';
        document.getElementById('confirm_password').style.borderColor = '#d1d5db';
    }

    inputs.forEach(input => {
        if (!input.value.trim()) {
            allValid = false;
            input.style.borderColor = '#ef4444';
        } else {
            input.style.borderColor = '#d1d5db';
        }
    });

    if (!allValid) return;

    if (currentStep === 2) {
        const pass        = document.getElementById('input_password').value;
        const confirmPass = document.getElementById('confirm_password').value;
        const errorSpan   = document.getElementById('error-password');
        if (pass !== confirmPass) {
            errorSpan.style.display = 'block';
            document.getElementById('confirm_password').style.borderColor = '#ef4444';
            return;
        }
    }

    changeStep(modifier);
}

function changeStep(modifier) {
    currentStep += modifier;
    if (currentStep === 3) {
        document.getElementById('resumen_nombre').innerText =
            document.getElementById('input_nombre').value + ' ' + document.getElementById('input_apellidos').value;
        document.getElementById('resumen_dni').innerText   = document.getElementById('input_dni').value;
        document.getElementById('resumen_email').innerText = document.getElementById('input_email').value;
    }
    updateDisplay();
}

function updateDisplay() {
    document.querySelectorAll('.auth-step').forEach((step, index) => {
        step.classList.toggle('active', index === (currentStep - 1));
    });
    document.getElementById('stepCounter').innerText = `PASO ${currentStep} DE 3`;
    document.getElementById('backBtn').style.display = currentStep === 1 ? 'none' : 'block';
}

function validateCheckboxes() {
    const terms   = document.getElementById('terms').checked;
    const privacy = document.getElementById('privacy').checked;
    document.getElementById('btnFinish').disabled = !(terms && privacy);
}

// ── Registro ──────────────────────────────────────────────────────────────────
async function showSuccess() {
    const btn     = document.getElementById('btnFinish');
    btn.disabled  = true;
    btn.innerText = 'Procesando...';

    const datosRegistro = {
        nombre:    document.getElementById('input_nombre').value,
        apellidos: document.getElementById('input_apellidos').value,
        dni:       document.getElementById('input_dni').value,
        email:     document.getElementById('input_email').value,
        password:  document.getElementById('input_password').value,
        telefono:  document.getElementById('input_telefono').value.replace(/\s+/g, '')
    };

    try {
        const respuesta = await apiFetch('../src/Registro.php', {
            method: 'POST',
            body: JSON.stringify(datosRegistro)
        });

        if (respuesta && respuesta.status === 'success') {
            document.getElementById('form-content').style.display  = 'none';
            document.getElementById('success-screen').style.display = 'block';
        } else {
            alert(respuesta.message || 'Error en el registro');
            btn.disabled  = false;
            btn.innerText = 'Finalizar Registro';
        }
    } catch (error) {
        console.error('Error en la petición:', error);
        btn.disabled  = false;
        btn.innerText = 'Finalizar Registro';
    }
}
