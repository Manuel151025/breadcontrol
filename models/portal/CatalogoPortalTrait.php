<?php
// models/portal/CatalogoPortalTrait.php

/**
 * Catálogo y configuración visibles en el portal: variedades de pan,
 * productos, categorías y configuración de pago.
 * Parte de PortalClienteModel (dividido por responsabilidad).
 */
trait CatalogoPortalTrait {

    /**
     * Obtiene los datos de pago configurados en la panadería.
     * @return array<string, mixed>
     */
    public function getConfiguracionPago(): array {
        return $this->pdo->query("SELECT nequi_link_pago, nequi_titular, wompi_habilitado FROM configuracion LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtiene variedades activas.
     * @return array<int, array<string, mixed>>
     */
    public function getVariedadesPanActivas(): array {
        $stmt = $this->pdo->query("SELECT id_variedad, nombre, imagen FROM variedad_pan WHERE activo = 1 ORDER BY nombre ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el catálogo completo de productos con precio.
     * @return array<int, array<string, mixed>>
     */
    public function getProductosActivos(): array {
        return $this->pdo->query("
            SELECT vp.id_variedad, vp.nombre, vp.imagen, vp.id_categoria_precio,
                   cp.nombre AS cat_nombre, cp.precio_unitario
            FROM variedad_pan vp
            INNER JOIN categoria_precio cp ON cp.id_categoria = vp.id_categoria_precio
            WHERE vp.activo = 1 
            ORDER BY cp.precio_unitario, vp.nombre
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el listado de categorías activas para los productos.
     * @return array<int, array<string, mixed>>
     */
    public function getCategoriasActivas(): array {
        return $this->pdo->query("SELECT * FROM categoria_precio WHERE activo = 1 ORDER BY precio_unitario")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene variedades activas por categoría.
     * @return array<int, array<string, mixed>>
     */
    public function getVariedadesPorCategoria(int $id_cat): array {
        $stmt = $this->pdo->prepare("SELECT id_variedad, nombre, imagen FROM variedad_pan WHERE id_categoria_precio = ? AND activo = 1 ORDER BY nombre");
        $stmt->execute([$id_cat]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
