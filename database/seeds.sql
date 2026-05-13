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

-- Insertar cuentas
INSERT INTO cuentas (usuario_id, numero_cuenta, saldo, tipo) VALUES
(1, 'ES001234567890123450', 1250.75, 'corriente'),
(1, 'ES001234567890123451', 500.00, 'ahorros'),
(2, 'ES001234567890123452', 3200.00, 'corriente'),
(3, 'ES001234567890123453', 10000.00, 'corriente');

-- Insertar tarjetas virtuales
INSERT INTO tarjetas (cuenta_id, numero, cvv, fecha_expiracion, estado) VALUES
(1, '4532015112830366', '123', '2028-12-31', 'activa'),
(2, '4532015112830367', '456', '2028-12-31', 'activa'),
(3, '4532015112830368', '789', '2028-12-31', 'activa'),
(4, '4532015112830369', '321', '2029-01-31', 'activa');

-- Insertar transacciones de ejemplo
INSERT INTO transacciones (cuenta_origen_id, cuenta_destino_id, monto, tipo, descripcion, estado, fecha) VALUES
(1, 3, 50.00, 'transferencia', 'Pago a Carlos', 'completada', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 1, 25.00, 'transferencia', 'Devolución', 'completada', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 2, 100.00, 'transferencia', 'Ahorro', 'completada', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(4, 1, 200.00, 'transferencia', 'Recarga', 'completada', DATE_SUB(NOW(), INTERVAL 4 HOUR));

-- Insertar notificaciones
INSERT INTO notificaciones (usuario_id, mensaje, leida) VALUES
(1, 'Has recibido una transferencia de 25.00€ de Carlos', FALSE),
(1, 'Tu tarjeta virtual ha sido activada', TRUE),
(2, 'Has enviado una transferencia de 50.00€ a Ana', FALSE),
(3, 'Nueva solicitud de tarjeta pendiente de revisión', FALSE);