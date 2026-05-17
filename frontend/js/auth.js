document.getElementById('form-login').addEventListener('submit', async (e) => {
    e.preventDefault();
    const email    = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;

    const datos = await apiFetch('login.php', {
        method: 'POST',
        body: JSON.stringify({ email, password })
    });

    if (!datos) return;

    localStorage.setItem('jwt_token', datos.token);
    console.log('ROL RECIBIDO:', datos.rol);
    document.getElementById('msg-login').textContent = datos.message;

    // Redirigir según rol
    setTimeout(() => {
        if (datos.rol === 'admin') {
            window.location.href = 'admin.html';
        } else {
            window.location.href = 'dashboard.html';
        }
    }, 800);
});