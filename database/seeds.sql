-- =====================================================
-- Datos de prueba para fintech_db
-- =====================================================

USE fintech_db;

-- Insertar usuarios (las contraseñas serán '123456' hasheadas con password_hash)
-- Para este ejemplo, usamos contraseñas sin hash (después las actualizaremos con PHP)

INSERT INTO usuarios (email, password_hash, dni, nombre, apellidos, activo, rol) VALUES
('ana@fintech.com', '$2y$10$jeV0r6oXlo2GSvJT/2Wt..EAvuo3a3eHoVCe8oCB.ekLdl3SXOHcy%', '12345678A', 'Ana', 'García López', TRUE, 'usuario'),
('carlos@fintech.com', '$2y$10$jeV0r6oXlo2GSvJT/2Wt..EAvuo3a3eHoVCe8oCB.ekLdl3SXOHcy%', '87654321B', 'Carlos', 'Martín Ruiz', TRUE, 'usuario'),
('admin@fintech.com', '$2y$10$jeV0r6oXlo2GSvJT/2Wt..EAvuo3a3eHoVCe8oCB.ekLdl3SXOHcy%', '11111111C', 'Admin', 'Sistema', TRUE, 'admin');

-- Insertar cuentas (Añadimos un par de cuentas falsas para los "comercios")
INSERT INTO cuentas (id, usuario_id, numero_cuenta, saldo, tipo) VALUES
(1, 1, 'ES001234567890123450', 1250.75, 'corriente'),
(2, 1, 'ES001234567890123451', 500.00, 'ahorros'),
(3, 2, 'ES001234567890123452', 3200.00, 'corriente'),
(4, 3, 'ES001234567890123453', 10000.00, 'corriente'),
(99, 3, 'ES990000000000000099', 99999.00, 'corriente'); -- Cuenta ficticia para tiendas (ej. Mercadona)

-- Insertar tarjetas virtuales
INSERT INTO tarjetas (cuenta_id, numero, cvv, fecha_expiracion, estado) VALUES
(1, '4532015112830366', '123', '2028-12-31', 'activa'),
(2, '4532015112830367', '456', '2028-12-31', 'activa'),
(3, '4532015112830368', '789', '2028-12-31', 'activa'),
(4, '4532015112830369', '321', '2029-01-31', 'activa');

-- Insertar transacciones de ejemplo (ACTUALIZADAS con los 4 tipos)
INSERT INTO transacciones (cuenta_origen_id, cuenta_destino_id, monto, tipo, descripcion, fecha) VALUES
(3, 1, 650.00, 'ingreso', 'Nómina Camarero', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 99, 45.50, 'compra', 'Compra semanal Mercadona', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 4, 250.00, 'transferencia', 'Pago Alquiler mensual', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 2, 15.00, 'bizum', 'Cena post-clases DAW', DATE_SUB(NOW(), INTERVAL 4 HOUR));

-- Insertar notificaciones
INSERT INTO notificaciones (usuario_id, mensaje, leida) VALUES
(1, 'Has recibido un Bizum de 15.00€', FALSE),
(1, 'Tu tarjeta virtual ha sido activada', TRUE),
(2, 'Has enviado una transferencia de 650.00€ a Ana', FALSE),
(3, 'Nueva solicitud de tarjeta pendiente de revisión', FALSE);