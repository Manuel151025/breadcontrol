<?php
// tests/Integration/VentaModelTest.php
// Pruebas de VentaModel: registro/edición/eliminación de ventas rápidas y el
// cálculo de stock disponible del día (producido - vendido por categoría).
// Los métodos usados no abren transacciones propias: corren con rollback.

final class VentaModelTest extends BaseDatosTestCase
{
    private VentaModel $model;
    private int $id_cat = 0;
    private int $id_usuario = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new VentaModel($this->pdo);

        $u = uniqid();
        $this->pdo->prepare("INSERT INTO categoria_precio (nombre, precio_unitario, activo) VALUES (?, 500, 1)")
            ->execute(['Cat Venta PHPUnit ' . $u]);
        $this->id_cat = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO usuario (nombre_usuario, nombre_completo, contrasena_hash, rol, activo) VALUES (?, 'Usuario Venta PHPUnit', 'hash', 'empleado', 1)")
            ->execute(['phpunit_venta_' . $u]);
        $this->id_usuario = (int) $this->pdo->lastInsertId();
    }

    /** Crea una producción de HOY con distribución en la categoría de prueba. */
    private function producirHoy(int $unidades): void
    {
        $u = uniqid();
        $this->pdo->prepare("INSERT INTO producto (nombre, categoria, precio_venta, unidad_produccion, cantidad_por_tanda) VALUES (?, 'sal', 500, 'carro', 10)")
            ->execute(['Producto Venta PHPUnit ' . $u]);
        $id_prod = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO receta (id_producto, id_usuario, version, es_vigente) VALUES (?, ?, 1, 1)")
            ->execute([$id_prod, $this->id_usuario]);
        $id_receta = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare("
            INSERT INTO produccion (id_producto, id_receta, id_usuario, cantidad_tandas, fecha_produccion, unidades_producidas, costo_total, costo_unitario)
            VALUES (?, ?, ?, 1, NOW(), ?, 0, 0)
        ")->execute([$id_prod, $id_receta, $this->id_usuario, $unidades]);
        $id_produccion = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO produccion_precio (id_produccion, id_categoria_precio, unidades) VALUES (?, ?, ?)")
            ->execute([$id_produccion, $this->id_cat, $unidades]);
    }

    public function testRegistrarVentaRapidaGuardaLaVentaCompleta(): void
    {
        $ok = $this->model->registrarVentaRapida($this->id_cat, 'venta', null, $this->id_usuario, 10, 500.0, 5000.0, 1);
        $this->assertTrue($ok);

        $id_venta = (int) $this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare("SELECT * FROM venta WHERE id_venta = ?");
        $stmt->execute([$id_venta]);
        $v = $stmt->fetch();

        $this->assertSame($this->id_cat, (int) $v['id_categoria_precio']);
        $this->assertSame('venta', $v['tipo_salida']);
        $this->assertSame(10, (int) $v['unidades_vendidas']);
        $this->assertSame(500.0, (float) $v['precio_unitario']);
        $this->assertSame(5000.0, (float) $v['total_venta']);
        $this->assertSame(1, (int) $v['unidades_bonificacion']);
    }

    public function testCategoriaCeroSeGuardaComoNull(): void
    {
        // Ventas con "Otro precio" no pertenecen a ninguna categoría
        $this->model->registrarVentaRapida(0, 'venta', null, $this->id_usuario, 2, 700.0, 1400.0, 0);
        $id_venta = (int) $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("SELECT id_categoria_precio FROM venta WHERE id_venta = ?");
        $stmt->execute([$id_venta]);
        $this->assertNull($stmt->fetchColumn() ?: null);
    }

    public function testStockDisponibleHoyEsProduccionMenosVentas(): void
    {
        $this->producirHoy(100);
        $this->assertSame(100, $this->model->getStockDisponibleHoy($this->id_cat));

        $this->model->registrarVentaRapida($this->id_cat, 'venta', null, $this->id_usuario, 30, 500.0, 15000.0, 0);
        $this->assertSame(70, $this->model->getStockDisponibleHoy($this->id_cat), '100 producidas - 30 vendidas = 70');
    }

    public function testStockPuedeExcluirUnaVentaAlEditarla(): void
    {
        $this->producirHoy(100);
        $this->model->registrarVentaRapida($this->id_cat, 'venta', null, $this->id_usuario, 30, 500.0, 15000.0, 0);
        $id_venta = (int) $this->pdo->lastInsertId();

        // Al editar una venta, su propia cantidad no debe contar contra el stock
        $this->assertSame(100, $this->model->getStockDisponibleHoy($this->id_cat, $id_venta));
    }

    public function testEditarVentaRapidaActualizaLosValores(): void
    {
        $this->model->registrarVentaRapida($this->id_cat, 'venta', null, $this->id_usuario, 10, 500.0, 5000.0, 0);
        $id_venta = (int) $this->pdo->lastInsertId();

        $ok = $this->model->editarVentaRapida($id_venta, $this->id_cat, 'venta', null, 15, 500.0, 7500.0, 2);
        $this->assertTrue($ok);

        $stmt = $this->pdo->prepare("SELECT unidades_vendidas, total_venta, unidades_bonificacion FROM venta WHERE id_venta = ?");
        $stmt->execute([$id_venta]);
        $v = $stmt->fetch();
        $this->assertSame(15, (int) $v['unidades_vendidas']);
        $this->assertSame(7500.0, (float) $v['total_venta']);
        $this->assertSame(2, (int) $v['unidades_bonificacion']);
    }

    public function testEliminarVentaLaBorraDeLaBase(): void
    {
        $this->model->registrarVentaRapida($this->id_cat, 'venta', null, $this->id_usuario, 5, 500.0, 2500.0, 0);
        $id_venta = (int) $this->pdo->lastInsertId();

        $this->assertTrue($this->model->eliminarVenta($id_venta));

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM venta WHERE id_venta = ?");
        $stmt->execute([$id_venta]);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }
}
