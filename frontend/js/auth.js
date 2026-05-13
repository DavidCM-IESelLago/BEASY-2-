document.getElementById('form-login').addEventListener('submit', async (e) => {
    e.preventDefault(); // Evita recarga de página
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    // Petición AJAX al backend PHP
    // login.php requiere POST + JSON + sin JWT (endpoint público)
    const datos = await apiFetch('login.php', {
        method: 'POST',
        body: JSON.stringify({ email, password })
    });
    // datos es null si apiFetch detectó error HTTP (401, 400, 500)
    if (!datos) return;
    // Almacenar el JWT que devuelve login.php: {"status":"success", "message":"Login correcto", "token":"eyJ..."}
    localStorage.setItem('jwt_token', datos.token);
    // Actualizar DOM con mensaje de bienvenida antes de redirigir
    document.getElementById('msg-login').textContent = datos.message;
    setTimeout(() => window.location.href = 'dashboard.html', 800);
});