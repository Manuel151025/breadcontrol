-- ============================================================
-- Migracion: eliminar la columna huerfana pedido_cliente.id_tienda_destino
-- Fecha: 2026-08-06
--
-- La columna se creo junto con la tabla de pedidos para la funcionalidad
-- "Tiendas Beneficiarias", pero NINGUN punto del codigo la escribio nunca:
-- en la practica siempre valio NULL. Su unico lector era una subconsulta en
-- ConfiguracionModel::getTiendasBeneficiarias() que mostraba un contador de
-- pedidos por tienda beneficiaria; al no escribirse nunca, ese contador
-- marcaba 0 para todas, siempre.
--
-- El destinatario real de un pedido es `id_cliente` (y `id_creador` es quien
-- lo armo); el flujo de pagos y el de aprendiz->instructor se apoyan solo en
-- esas dos columnas. Ver docs/id_tienda_destino.md.
--
-- ORDEN OBLIGATORIO: primero desplegar el codigo que quita la subconsulta y
-- el contador de la vista, y solo despues correr esto. Al reves, la pantalla
-- de Tiendas Beneficiarias fallaria entre el ALTER y el despliegue.
--
-- Es irreversible (la columna no guarda datos: son todos NULL, pero el DROP no
-- se deshace). Backup previo, como con cualquier migracion.
--
-- Portable MariaDB 10.4 / MySQL 8.0. Ejecutar UNA sola vez en una BD existente.
-- ============================================================

SET SQL_SAFE_UPDATES = 0;

ALTER TABLE `pedido_cliente`
  DROP COLUMN `id_tienda_destino`;

SET SQL_SAFE_UPDATES = 1;
