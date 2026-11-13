-- ============================================================
-- Retirar el Portal de Clientes
-- Archivo: sql/migraciones/2026-11-13_01_retirar_portal_clientes.sql
--
-- BreadControl deja de ser un proyecto del SENA: el portal donde los aprendices
-- pedían pan a cuenta del instructor se retira por completo, con sus pedidos,
-- pagos, códigos de aprendiz y cuentas de acceso. El back-office (inventario,
-- compras, producción, recetas, ventas, gastos, cierre y finanzas) no cambia.
--
-- ORDEN OBLIGATORIO
--   1. Respaldo completo de la base (mysqldump). Esta migración BORRA datos y
--      no hay vuelta atrás sin ese respaldo.
--   2. Desplegar primero el código sin portal. El código anterior consulta
--      estas tablas y columnas; si la migración va antes, el sitio publicado
--      se rompe hasta que llegue el despliegue.
--   3. Aplicar esta migración.
--   4. php scripts/migraciones.php --marcar=2026-11-13_01_retirar_portal_clientes.sql
--
-- Las sentencias DDL hacen commit implícito: si algo falla a mitad, revisar en
-- qué paso quedó antes de volver a ejecutar (cada paso usa IF EXISTS donde
-- MySQL lo permite).
-- ============================================================

-- ── 1. Tablas del flujo de pedidos del portal ───────────────
-- De hijas a padres, por las claves foráneas que las encadenan.
DROP TABLE IF EXISTS `pago_abono`;
DROP TABLE IF EXISTS `pago_pedido`;
DROP TABLE IF EXISTS `pedido_cliente_detalle`;
DROP TABLE IF EXISTS `pedido_cliente`;
DROP TABLE IF EXISTS `codigo_aprendiz`;

-- ── 2. Cuentas que solo existían para el portal ─────────────
-- Una cuenta del portal se reconoce por tener credenciales, Google, correo o
-- vínculo de aprendiz. Se borran SOLO si no tienen ventas registradas: una
-- tienda que también compró por el punto de venta se conserva, porque
-- `venta.id_cliente` la nombra en los reportes (y no tiene clave foránea que
-- impida dejarla huérfana).
DELETE c FROM `cliente` c
 WHERE (c.`usuario` IS NOT NULL
        OR c.`google_id` IS NOT NULL
        OR c.`email` IS NOT NULL
        OR c.`es_aprendiz` = 1
        OR c.`id_instructor` IS NOT NULL)
   AND NOT EXISTS (SELECT 1 FROM `venta` v WHERE v.`id_cliente` = c.`id_cliente`);

-- ── 3. Columnas del portal en `cliente` ─────────────────────
-- Queda lo que usa el punto de venta: nombre, tipo, teléfono, notas, activo.
ALTER TABLE `cliente`
  DROP INDEX `google_id`,
  DROP INDEX `usuario`,
  DROP INDEX `uq_cliente_email`,
  DROP COLUMN `usuario`,
  DROP COLUMN `contrasena_hash`,
  DROP COLUMN `es_aprendiz`,
  DROP COLUMN `cupo_semanal`,
  DROP COLUMN `id_instructor`,
  DROP COLUMN `email`,
  DROP COLUMN `foto_url`,
  DROP COLUMN `google_id`,
  DROP COLUMN `es_beneficiaria`,
  DROP COLUMN `pin_recuperacion`,
  DROP COLUMN `codigo_recuperacion`,
  DROP COLUMN `codigo_expira`,
  DROP COLUMN `fecha_aprendiz`;

-- ── 4. Pagos digitales (Nequi) y cuenta del instructor ──────
ALTER TABLE `configuracion`
  DROP COLUMN `nequi_link_pago`,
  DROP COLUMN `nequi_titular`,
  DROP COLUMN `wompi_habilitado`,
  DROP COLUMN `wompi_confirmar_auto`,
  DROP COLUMN `id_cliente_adso`;

-- ── 5. Intentos de acceso del portal ────────────────────────
DELETE FROM `intento_login` WHERE `ambito` = 'portal';
ALTER TABLE `intento_login` MODIFY `ambito` enum('admin') NOT NULL;
