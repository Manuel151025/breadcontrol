-- ============================================================
-- Semilla mínima para pruebas de integración (CI)
-- Archivo: sql/init/90_semilla_ci.sql
--
-- NO usar en producción: solo da a la suite de integración un
-- insumo y un producto base sobre los que operar. Los demás datos
-- los crea cada prueba dentro de su propia transacción.
-- ============================================================

INSERT INTO insumo (nombre, unidad_medida, es_harina, stock_actual, punto_reposicion)
VALUES ('Harina de trigo (CI)', 'kg', 1, 100.000, 10.000);

INSERT INTO producto (nombre, categoria, precio_venta, unidad_produccion, cantidad_por_tanda)
VALUES ('Pan de sal (CI)', 'sal', 500.00, 'carro', 100.00);
