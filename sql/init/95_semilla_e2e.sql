-- ============================================================
-- Semilla para las pruebas de extremo a extremo (Playwright)
-- Archivo: sql/init/95_semilla_e2e.sql
--
-- NO usar en produccion. Crea cuentas con contrasena conocida para que el
-- navegador pueda entrar; en un sistema real eso es exactamente lo que no se
-- quiere. Solo se carga en el contenedor efimero del CI y en un entorno local
-- de desarrollo.
--
-- Se carga DESPUES de 90_semilla_ci.sql, que aporta un insumo y un producto.
-- Aqui se anade unicamente lo que necesita un navegador para completar un
-- recorrido: cuentas, una tienda y un catalogo minimo de variedades.
--
-- La contrasena de las dos cuentas es:  PruebaE2E2026
-- Cumple la politica del proyecto (8+ caracteres, con letras y digitos, ver
-- Seguridad::validarContrasena). El hash es bcrypt y va escrito tal cual: es
-- deterministe y no depende de quien ejecute la semilla.
-- ============================================================

-- ── Zona horaria: la MISMA que usa la aplicacion ────────────
--
-- config/db.php ejecuta `SET time_zone = '-05:00'` en cada conexion, asi que
-- para la aplicacion CURDATE() es la fecha de Colombia. Esta semilla, en cambio,
-- la carga el cliente `mysql`, que usa la zona del servidor: en el runner de
-- GitHub eso es UTC.
--
-- Sin esta linea, entre las 00:00 y las 05:00 UTC —es decir, entre las 19:00 y
-- la medianoche en Colombia— el NOW() de mas abajo escribe la produccion con la
-- fecha del dia SIGUIENTE, y la consulta de stock del punto de venta
-- (`DATE(p.fecha_produccion) = CURDATE()`) no la encuentra: la categoria sale
-- con "0 disp." y el recorrido de venta falla por falta de datos, no por un
-- defecto del codigo.
--
-- Paso exactamente eso la primera vez que este flujo corrio en CI, a las 03:17
-- UTC. En local no se veia porque el servidor de desarrollo ya esta en la zona
-- de Colombia y las dos fechas coincidian.
SET time_zone = '-05:00';

-- ── Cuenta del back-office (rol propietario) ────────────────
INSERT INTO usuario (nombre_usuario, nombre_completo, contrasena_hash, rol, activo)
VALUES (
    'e2e_propietario',
    'Propietario de pruebas E2E',
    '$2y$10$ehvMGOKmMTZycXhcH7fXmuWFmoAqJ1Rc2u0uL7SCUKq3IzrMiQ3ti',
    'propietario',
    1
);

-- ── Cuenta del back-office con rol empleado ─────────────────
-- No se usa todavia en ningun recorrido, pero se siembra aqui para que anadir
-- las pruebas de permisos por rol no exija tocar la semilla despues.
INSERT INTO usuario (nombre_usuario, nombre_completo, contrasena_hash, rol, activo)
VALUES (
    'e2e_empleado',
    'Empleado de pruebas E2E',
    '$2y$10$ehvMGOKmMTZycXhcH7fXmuWFmoAqJ1Rc2u0uL7SCUKq3IzrMiQ3ti',
    'empleado',
    1
);

-- ── Cliente del portal, con credenciales de acceso ──────────
INSERT INTO cliente (nombre, tipo, telefono, activo, usuario, contrasena_hash, email)
VALUES (
    'Tienda de pruebas E2E',
    'tienda',
    '3000000000',
    1,
    'e2e_cliente',
    '$2y$10$ehvMGOKmMTZycXhcH7fXmuWFmoAqJ1Rc2u0uL7SCUKq3IzrMiQ3ti',
    'e2e_cliente@ejemplo.test'
);

-- ── Catalogo minimo para poder crear un pedido ──────────────
-- Sin al menos una categoria de precio y una variedad, la pantalla de nuevo
-- pedido no tiene nada que ofrecer y el recorrido no se puede completar.
INSERT INTO categoria_precio (nombre, precio_unitario, activo)
VALUES ('Categoria E2E', 1000.00, 1);

INSERT INTO variedad_pan (nombre, id_categoria_precio, activo)
VALUES (
    'Pan de pruebas E2E',
    (SELECT id_categoria FROM categoria_precio WHERE nombre = 'Categoria E2E'),
    1
);

-- ── Produccion del dia, para que el punto de venta tenga stock ──
--
-- El POS calcula las unidades disponibles sobre la produccion registrada HOY
-- (VentaModel: produccion_precio unido a produccion con fecha_produccion del
-- dia). Sin esto, la categoria aparece con "0 disp." y no se puede completar
-- una venta: el recorrido fallaria por falta de datos, no por un defecto.
--
-- Por eso la fecha se escribe con NOW() y no con una constante: una semilla con
-- la fecha fijada funcionaria el dia que se escribio y dejaria de funcionar al
-- siguiente, que es la peor clase de prueba fragil —la que se rompe sola y
-- ensena a ignorar el CI.

INSERT INTO receta (id_producto, id_usuario, version, es_vigente)
VALUES (
    (SELECT id_producto FROM producto LIMIT 1),
    (SELECT id_usuario FROM usuario WHERE nombre_usuario = 'e2e_propietario'),
    1,
    1
);

INSERT INTO produccion (
    id_producto, id_receta, id_usuario, cantidad_tandas,
    unidades_producidas, costo_total, costo_unitario, fecha_produccion
)
VALUES (
    (SELECT id_producto FROM producto LIMIT 1),
    (SELECT id_receta FROM receta ORDER BY id_receta DESC LIMIT 1),
    (SELECT id_usuario FROM usuario WHERE nombre_usuario = 'e2e_propietario'),
    1.0,
    100,
    50000.00,
    500.0000,
    NOW()
);

INSERT INTO produccion_precio (id_produccion, id_categoria_precio, unidades)
VALUES (
    (SELECT id_produccion FROM produccion ORDER BY id_produccion DESC LIMIT 1),
    (SELECT id_categoria FROM categoria_precio WHERE nombre = 'Categoria E2E'),
    100
);
