<?php
// tests/Integration/ValidacionVentasTest.php
// Pruebas de integración de la validación de stock para ventas
// (migradas del antiguo test_sales_validation.php).
// Usan la vista v_stock_productos_hoy a través de getStockProducto/validarStockVenta.

final class ValidacionVentasTest extends BaseDatosTestCase
{
    private int $id_producto = 1;

    protected function setUp(): void
    {
        parent::setUp();

        // Usar el primer producto real de la base; si no hay ninguno,
        // se conserva el ID 1 (getStockProducto retorna 0 para inexistentes).
        $id = $this->pdo->query("SELECT id_producto FROM producto ORDER BY id_producto LIMIT 1")->fetchColumn();
        if ($id !== false) {
            $this->id_producto = (int) $id;
        }
    }

    public function testElStockDelProductoEsNumerico(): void
    {
        $stock = getStockProducto($this->id_producto);
        $this->assertIsFloat($stock);
        $this->assertGreaterThanOrEqual(0, $stock);
    }

    public function testVentaConCantidadCeroDebeFallar(): void
    {
        $res = validarStockVenta($this->id_producto, 0);

        $this->assertFalse($res['ok']);
        $this->assertSame('La cantidad a vender debe ser mayor a 0.', $res['mensaje']);
    }

    public function testVentaConCantidadNegativaDebeFallar(): void
    {
        $res = validarStockVenta($this->id_producto, -10);
        $this->assertFalse($res['ok']);
    }

    public function testVentaQueSuperaElStockDebeFallarConMensajeClaro(): void
    {
        $stock = getStockProducto($this->id_producto);
        $res = validarStockVenta($this->id_producto, (int) $stock + 1000000);

        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('No hay suficiente stock', $res['mensaje']);
        $this->assertArrayHasKey('disponible', $res);
    }

    public function testVentaConCantidadValidaSegunElStockDisponible(): void
    {
        $stock = getStockProducto($this->id_producto);

        if ($stock > 0) {
            $res = validarStockVenta($this->id_producto, (int) floor($stock));
            $this->assertTrue($res['ok'], 'Debe aceptar una venta menor o igual al stock disponible.');
        } else {
            $res = validarStockVenta($this->id_producto, 1);
            $this->assertFalse($res['ok'], 'Con stock 0, vender 1 unidad debe fallar.');
        }
    }

    public function testProductoInexistenteReportaStockCero(): void
    {
        // Manejo de error: un ID inexistente no debe lanzar excepción
        $this->assertSame(0.0, getStockProducto(99999999));
    }
}
