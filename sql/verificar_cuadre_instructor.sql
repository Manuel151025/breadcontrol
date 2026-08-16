-- ============================================================
--  ¿Cuadra el «Resumen por aprendiz» con los KPIs de arriba?
--  Archivo: sql/verificar_cuadre_instructor.sql
--
--  Solo LEE. No modifica nada. Se puede ejecutar en producción.
--
--  Uso:
--    docker exec -i <contenedor_mysql> mysql -u root -p<clave> panaderia_bd \
--      < sql/verificar_cuadre_instructor.sql
--
--  Compara, para cada instructor, la suma de las columnas de la tabla
--  contra el número grande que se muestra arriba en el tablero. Si una
--  fila sale con diferencia distinta de 0, ese número no cuadra.
-- ============================================================

SELECT '===== 1. PEDIDOS: ¿la suma de la columna da el total de arriba? =====' AS informe;

-- Ambos cuentan igual (incluyen los aprobados y luego cancelados), así que
-- solo pueden diferir si hay pedidos de aprendices desactivados o
-- reasignados a otro instructor: cuentan en el KPI y no salen en la tabla.
SELECT
    i.id_cliente                                   AS instructor,
    i.nombre,
    kpi.pedidos_kpi                                AS kpi_pedidos_totales,
    COALESCE(tab.pedidos_tabla, 0)                 AS suma_columna_pedidos,
    kpi.pedidos_kpi - COALESCE(tab.pedidos_tabla, 0) AS diferencia
FROM cliente i
JOIN (
    SELECT id_cliente, COUNT(*) AS pedidos_kpi
    FROM pedido_cliente
    WHERE id_creador IS NOT NULL AND id_creador <> id_cliente AND aprobado_instructor = 1
    GROUP BY id_cliente
) kpi ON kpi.id_cliente = i.id_cliente
LEFT JOIN (
    SELECT p.id_cliente, COUNT(*) AS pedidos_tabla
    FROM pedido_cliente p
    JOIN cliente a ON a.id_cliente = p.id_creador
    WHERE p.id_creador IS NOT NULL AND p.id_creador <> p.id_cliente AND p.aprobado_instructor = 1
      AND a.es_aprendiz = 1 AND a.activo = 1 AND a.id_instructor = p.id_cliente
    GROUP BY p.id_cliente
) tab ON tab.id_cliente = i.id_cliente;


SELECT '===== 2. SALDO PENDIENTE: el KPI y la columna usan reglas distintas =====' AS informe;

-- KPI      : estado_pago NULL, pendiente, no_aplica o parcial, MENOS los abonos.
-- Columna  : solo pendiente y no_aplica, y SIN restar abonos.
-- Cualquier pedido con estado_pago NULL o 'parcial', o cualquier abono
-- registrado, hace que los dos números dejen de coincidir.
SELECT
    p.id_cliente AS instructor,
    COALESCE(SUM(CASE
        WHEN p.estado_pago IS NULL OR p.estado_pago IN ('pendiente','no_aplica','parcial')
        THEN p.total_estimado ELSE 0 END), 0)                       AS base_kpi_sin_restar_abonos,
    COALESCE(SUM(CASE
        WHEN p.estado_pago IN ('pendiente','no_aplica')
        THEN p.total_estimado ELSE 0 END), 0)                       AS suma_columna_tabla,
    COALESCE(SUM(CASE WHEN p.estado_pago IS NULL      THEN p.total_estimado ELSE 0 END), 0) AS solo_en_kpi_estado_null,
    COALESCE(SUM(CASE WHEN p.estado_pago = 'parcial'  THEN p.total_estimado ELSE 0 END), 0) AS solo_en_kpi_parciales
FROM pedido_cliente p
WHERE p.id_creador IS NOT NULL AND p.id_creador <> p.id_cliente
  AND p.estado <> 'rechazado' AND p.aprobado_instructor = 1
GROUP BY p.id_cliente;

-- Los abonos que el KPI resta y la columna no:
SELECT COUNT(*) AS pagos_con_abono, COALESCE(SUM(monto), 0) AS total_abonado FROM pago_abono;


SELECT '===== 3. PEDIDOS vs TOTAL COMPRADO dentro de la misma fila =====' AS informe;

-- Cancelar un pedido pone estado='rechazado' pero NO toca aprobado_instructor
-- (PedidosPortalTrait.php:288). Esos pedidos siguen contando en la columna
-- PEDIDOS pero desaparecen de TOTAL COMPRADO y de SALDO PENDIENTE: la fila
-- muestra más pedidos de los que explica su dinero.
SELECT
    a.id_cliente                AS aprendiz,
    a.nombre,
    COUNT(*)                    AS aparecen_en_columna_pedidos,
    SUM(CASE WHEN p.estado = 'rechazado' THEN 1 ELSE 0 END) AS de_esos_cancelados,
    COALESCE(SUM(CASE WHEN p.estado <> 'rechazado' THEN p.total_estimado ELSE 0 END), 0) AS total_comprado_mostrado
FROM pedido_cliente p
JOIN cliente a ON a.id_cliente = p.id_creador
WHERE p.id_creador IS NOT NULL AND p.id_creador <> p.id_cliente AND p.aprobado_instructor = 1
GROUP BY a.id_cliente, a.nombre
HAVING de_esos_cancelados > 0
ORDER BY de_esos_cancelados DESC;


SELECT '===== 4. APRENDICES ACTIVOS (el 16 de «16 / 21») =====' AS informe;

-- Cuenta creadores distintos con algún pedido aprobado, SIN excluir los
-- cancelados: un aprendiz cuyo único pedido se canceló sigue contando como
-- activo. Y no comprueba que siga vinculado a este instructor.
SELECT
    p.id_cliente AS instructor,
    COUNT(DISTINCT p.id_creador) AS activos_como_los_cuenta_el_kpi,
    COUNT(DISTINCT CASE WHEN p.estado <> 'rechazado' THEN p.id_creador END) AS activos_con_pedidos_vigentes,
    COUNT(DISTINCT CASE WHEN a.es_aprendiz = 1 AND a.activo = 1 AND a.id_instructor = p.id_cliente
                        THEN p.id_creador END) AS activos_que_ademas_salen_en_la_tabla
FROM pedido_cliente p
LEFT JOIN cliente a ON a.id_cliente = p.id_creador
WHERE p.id_creador IS NOT NULL AND p.id_creador <> p.id_cliente AND p.aprobado_instructor = 1
GROUP BY p.id_cliente;
