-- ============================================================
-- Migracion: DEFAULT de pedido_cliente.aprobado_instructor = 0 (red de seguridad)
-- Fecha: 2026-07-23
--
-- El DEFAULT era 1: si algun INSERT omitiera la columna, el pedido de un aprendiz
-- se aprobaria solo y el paso del instructor no existiria en la practica.
--
-- El codigo (PortalClienteModel::crearPedido) YA fija aprobado_instructor de forma
-- explicita en cada INSERT/UPDATE (0 si id_cliente != id_creador; 1 si son iguales),
-- asi que este cambio es solo una red de seguridad para futuros INSERT.
--
-- NO afecta a los clientes normales: sus pedidos propios (id_cliente = id_creador) se
-- insertan con aprobado_instructor = 1 explicito. NO se reescriben filas existentes.
--
-- Portable MariaDB 10.4 / MySQL 8.0.
-- ============================================================

SET SQL_SAFE_UPDATES = 0;

ALTER TABLE `pedido_cliente`
  MODIFY `aprobado_instructor` tinyint(1) NOT NULL DEFAULT 0;

SET SQL_SAFE_UPDATES = 1;
