-- =====================================================
-- Datos de prueba para fintech_db
-- =====================================================

-- Forzar charset UTF-8 en la conexión del cliente
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET CHARACTER SET utf8mb4;

USE fintech_db;

-- Insertar usuarios (las contraseñas serán '123456' hasheadas con password_hash)
-- Para este ejemplo, usamos contraseñas sin hash (después las actualizaremos con PHP)

INSERT INTO usuarios (email, password_hash, dni, nombre, apellidos, telefono, activo, rol) VALUES
('ana@fintech.com', '$2y$10$jeV0r6oXlo2GSvJT/2Wt..EAvuo3a3eHoVCe8oCB.ekLdl3SXOHcy', '12345678A', 'Ana', 'García López', '600111111', TRUE, 'usuario'),
('carlos@fintech.com', '$2y$10$jeV0r6oXlo2GSvJT/2Wt..EAvuo3a3eHoVCe8oCB.ekLdl3SXOHcy', '87654321B', 'Carlos', 'Martín Ruiz', '600222222', TRUE, 'usuario'),
('admin@fintech.com', '$2y$10$jeV0r6oXlo2GSvJT/2Wt..EAvuo3a3eHoVCe8oCB.ekLdl3SXOHcy', '11111111C', 'Admin', 'Sistema', '600333333', TRUE, 'admin');

-- Insertar cuentas (Añadimos un par de cuentas falsas para los "comercios")
INSERT INTO cuentas (id, usuario_id, numero_cuenta, saldo, tipo) VALUES
(1, 1, 'ES0012345678901234567890', 1250.75, 'corriente'),
(2, 1, 'ES0012345678901234567891', 500.00, 'ahorros'),
(3, 2, 'ES0012345678901234567892', 3200.00, 'corriente'),
(4, 3, 'ES0012345678901234567893', 10000.00, 'corriente'),
(99, 3, 'ES9900000000000000991234', 99999.00, 'corriente'); -- Cuenta ficticia para tiendas (ej. Mercadona)

-- Insertar tarjetas virtuales
INSERT INTO tarjetas (cuenta_id, numero, cvv, fecha_expiracion, estado) VALUES
(1, '4532015112830366', '123', '2028-12-31', 'activa'),
(2, '4532015112830367', '456', '2028-12-31', 'activa'),
(3, '4532015112830368', '789', '2028-12-31', 'activa'),
(4, '4532015112830369', '321', '2029-01-31', 'activa');

-- Insertar transacciones de ejemplo (ACTUALIZADAS con los 4 tipos)

INSERT INTO transacciones (cuenta_origen_id, cuenta_destino_id, monto, tipo, descripcion, fecha) VALUES

-- NÓMINAS (ingresos recurrentes — cuenta 3 de Carlos paga a admin)
(3, 4, 2100.00, 'ingreso', 'Nómina Marzo 2026',          DATE_SUB(NOW(), INTERVAL 45 DAY)),
(3, 4, 2100.00, 'ingreso', 'Nómina Abril 2026',          DATE_SUB(NOW(), INTERVAL 15 DAY)),

-- COMPRAS cotidianas (cuenta 4 → cuenta 99 "comercios")
(4, 99, 87.40,  'compra',  'Compra semanal Mercadona',    DATE_SUB(NOW(), INTERVAL 43 DAY)),
(4, 99, 12.50,  'compra',  'Cafetería Central',           DATE_SUB(NOW(), INTERVAL 42 DAY)),
(4, 99, 234.99, 'compra',  'Zara — Ropa temporada',       DATE_SUB(NOW(), INTERVAL 40 DAY)),
(4, 99, 9.99,   'compra',  'Netflix suscripción',         DATE_SUB(NOW(), INTERVAL 39 DAY)),
(4, 99, 54.30,  'compra',  'Gasolinera Repsol',           DATE_SUB(NOW(), INTERVAL 37 DAY)),
(4, 99, 76.20,  'compra',  'Farmacia San Pablo',          DATE_SUB(NOW(), INTERVAL 35 DAY)),
(4, 99, 92.10,  'compra',  'Carrefour — Compra mensual',  DATE_SUB(NOW(), INTERVAL 30 DAY)),
(4, 99, 14.99,  'compra',  'Spotify Premium',             DATE_SUB(NOW(), INTERVAL 29 DAY)),
(4, 99, 320.00, 'compra',  'MediaMarkt — Auriculares',    DATE_SUB(NOW(), INTERVAL 27 DAY)),
(4, 99, 18.75,  'compra',  'Restaurante El Rincón',       DATE_SUB(NOW(), INTERVAL 25 DAY)),
(4, 99, 6.80,   'compra',  'Amazon — Libro técnico',      DATE_SUB(NOW(), INTERVAL 23 DAY)),
(4, 99, 55.00,  'compra',  'Gasolinera BP',               DATE_SUB(NOW(), INTERVAL 20 DAY)),
(4, 99, 9.99,   'compra',  'Disney+ suscripción',         DATE_SUB(NOW(), INTERVAL 18 DAY)),
(4, 99, 143.60, 'compra',  'El Corte Inglés — Hogar',     DATE_SUB(NOW(), INTERVAL 14 DAY)),
(4, 99, 22.40,  'compra',  'Mercadona — Compra rápida',   DATE_SUB(NOW(), INTERVAL 10 DAY)),
(4, 99, 49.00,  'compra',  'Gym Urban Sports Club',       DATE_SUB(NOW(), INTERVAL 8  DAY)),
(4, 99, 11.30,  'compra',  'Bar Deportes — Cena',         DATE_SUB(NOW(), INTERVAL 5  DAY)),
(4, 99, 67.80,  'compra',  'Decathlon — Material deporte',DATE_SUB(NOW(), INTERVAL 3  DAY)),
(4, 99, 8.50,   'compra',  'Glovo — Pedido comida',       DATE_SUB(NOW(), INTERVAL 1  DAY)),

-- TRANSFERENCIAS (admin envía y recibe)
(4, 1,  500.00, 'transferencia', 'Préstamo a Ana',        DATE_SUB(NOW(), INTERVAL 41 DAY)),
(4, 3,  750.00, 'transferencia', 'Pago alquiler trastero',DATE_SUB(NOW(), INTERVAL 32 DAY)),
(1, 4,  500.00, 'transferencia', 'Devolución préstamo Ana',DATE_SUB(NOW(), INTERVAL 22 DAY)),
(4, 1,  200.00, 'transferencia', 'Regalo cumpleaños Ana', DATE_SUB(NOW(), INTERVAL 12 DAY)),
(3, 4,  180.00, 'transferencia', 'Carlos — Deuda cena',   DATE_SUB(NOW(), INTERVAL 6  DAY)),

-- BIZUMS
(4, 1,  15.00,  'bizum', 'Bizum cena viernes',            DATE_SUB(NOW(), INTERVAL 38 DAY)),
(3, 4,  25.00,  'bizum', 'Bizum Carlos — gasolina',       DATE_SUB(NOW(), INTERVAL 33 DAY)),
(4, 1,  10.00,  'bizum', 'Bizum café oficina',            DATE_SUB(NOW(), INTERVAL 28 DAY)),
(1, 4,  40.00,  'bizum', 'Bizum Ana — entradas cine',     DATE_SUB(NOW(), INTERVAL 19 DAY)),
(4, 3,  20.00,  'bizum', 'Bizum Carlos — parking',        DATE_SUB(NOW(), INTERVAL 11 DAY)),
(3, 4,  35.00,  'bizum', 'Bizum Carlos — cena equipo',    DATE_SUB(NOW(), INTERVAL 4  DAY)),
(4, 1,  12.50,  'bizum', 'Bizum Ana — periódico',         DATE_SUB(NOW(), INTERVAL 2  DAY));

-- Insertar notificaciones
INSERT INTO notificaciones (usuario_id, mensaje, leida) VALUES
(1, 'Has recibido un Bizum de 15.00€', FALSE),
(1, 'Tu tarjeta virtual ha sido activada', TRUE),
(2, 'Has enviado una transferencia de 650.00€ a Ana', FALSE),
(3, 'Nueva solicitud de tarjeta pendiente de revisión', FALSE);