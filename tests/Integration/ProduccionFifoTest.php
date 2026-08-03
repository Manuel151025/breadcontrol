<?php
// tests/Integration/ProduccionFifoTest.php
// Pruebas del cálculo FIFO de lotes y la verificación de stock de ProduccionModel.
// Solo métodos de lectura: corren dentro de la transacción con rollback de la base.

final class ProduccionFifoTest extends BaseDatosTestCase
{
    private ProduccionModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new ProduccionModel($this->pdo);
    }

    /** Crea un insumo con stock y retorna su id. */
    private function crearInsumo(float $stock): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO insumo (nombre, unidad_medida, es_harina, stock_actual, punto_reposicion)
            VALUES (?, 'kg', 1, ?, 1)
        ");
        $stmt->execute(['Insumo FIFO PHPUnit ' . uniqid(), $stock]);
        return (int) $this->pdo->lastInsertId();
    }

    private function crearLote(int $id_insumo, string $fecha, float $cantidad, float $precio): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO lote (id_insumo, numero_lote, cantidad_inicial, cantidad_disponible, precio_unitario, fecha_ingreso, estado)
            VALUES (?, ?, ?, ?, ?, ?, 'activo')
        ");
        $stmt->execute([$id_insumo, 'TST-' . uniqid(), $cantidad, $cantidad, $precio, $fecha]);
        return (int) $this->pdo->lastInsertId();
    }

    /** Fila de ingrediente como la entrega getIngredientesReceta. */
    private function ingrediente(int $id_insumo, float $cant_por_unidad, float $stock): array
    {
        return [
            'id_insumo'       => $id_insumo,
            'nombre'          => 'Insumo FIFO PHPUnit',
            'unidad_medida'   => 'kg',
            'stock_actual'    => $stock,
            'cant_por_unidad' => $cant_por_unidad,
            'aplica_merma'    => 0,
        ];
    }

    public function testFifoConsumeElLoteMasAntiguoPrimero(): void
    {
        $id_insumo = $this->crearInsumo(30);
        $this->crearLote($id_insumo, date('Y-m-d H:i:s', strtotime('-30 days')), 10, 100);
        $this->crearLote($id_insumo, date('Y-m-d H:i:s', strtotime('-10 days')), 20, 200);

        // 2 kg por tanda × 6 tandas = 12 kg necesarios
        $r = $this->model->calcularLotesFIFO($this->ingrediente($id_insumo, 2, 30), 6);

        $this->assertTrue($r['alcanza']);
        $this->assertSame(12.0, $r['cant_necesaria']);
        $this->assertSame(30.0, $r['total_disponible']);
        $this->assertCount(2, $r['lotes_a_usar']);

        // El lote viejo (barato) se agota primero...
        $this->assertSame(10.0, $r['lotes_a_usar'][0]['a_consumir']);
        $this->assertSame(100.0, $r['lotes_a_usar'][0]['precio_unitario']);
        $this->assertTrue($r['lotes_a_usar'][0]['es_mas_antiguo']);
        // ...y el nuevo solo aporta el faltante
        $this->assertSame(2.0, $r['lotes_a_usar'][1]['a_consumir']);
        $this->assertSame(200.0, $r['lotes_a_usar'][1]['precio_unitario']);
    }

    public function testNoAlcanzaCuandoElStockEsInsuficiente(): void
    {
        $id_insumo = $this->crearInsumo(30);
        $this->crearLote($id_insumo, date('Y-m-d H:i:s', strtotime('-30 days')), 10, 100);
        $this->crearLote($id_insumo, date('Y-m-d H:i:s', strtotime('-10 days')), 20, 200);

        // 2 kg × 20 tandas = 40 kg > 30 disponibles
        $r = $this->model->calcularLotesFIFO($this->ingrediente($id_insumo, 2, 30), 20);

        $this->assertFalse($r['alcanza']);
        // Se planifica consumir todo lo que hay (10 + 20)
        $this->assertSame(10.0, $r['lotes_a_usar'][0]['a_consumir']);
        $this->assertSame(20.0, $r['lotes_a_usar'][1]['a_consumir']);
    }

    public function testStockManualSinLoteSeConsumeAlFinal(): void
    {
        // Stock físico 15 pero solo 10 en lotes: 5 kg fueron editados a mano en Inventario
        $id_insumo = $this->crearInsumo(15);
        $this->crearLote($id_insumo, date('Y-m-d H:i:s', strtotime('-30 days')), 10, 100);

        $r = $this->model->calcularLotesFIFO($this->ingrediente($id_insumo, 2, 15), 6); // necesita 12

        $this->assertTrue($r['alcanza']);
        $this->assertTrue($r['hay_stock_manual']);
        $this->assertCount(2, $r['lotes_a_usar']);
        $this->assertSame(10.0, $r['lotes_a_usar'][0]['a_consumir']);

        $manual = $r['lotes_a_usar'][1];
        $this->assertTrue($manual['sin_lote']);
        $this->assertSame('MANUAL', $manual['numero_lote']);
        $this->assertSame(2.0, $manual['a_consumir']);
        $this->assertSame(0.0, (float) $manual['precio_unitario'], 'El stock manual no tiene precio conocido');
    }

    public function testVerificarStockSinProblemasNoReportaNada(): void
    {
        $id_insumo = $this->crearInsumo(30);
        $this->crearLote($id_insumo, date('Y-m-d H:i:s', strtotime('-30 days')), 30, 100);

        $r = $this->model->verificarStockIngredientes([$this->ingrediente($id_insumo, 2, 30)], 6);

        $this->assertSame([], $r['errores']);
        $this->assertSame([], $r['avisos']);
    }

    public function testVerificarStockReportaFaltanteYDescuadreDeLotes(): void
    {
        // stock_actual 15 pero lotes solo 10 → aviso de descuadre;
        // y 2 kg × 10 tandas = 20 kg > 15 → error de faltante
        $id_insumo = $this->crearInsumo(15);
        $this->crearLote($id_insumo, date('Y-m-d H:i:s', strtotime('-30 days')), 10, 100);

        $r = $this->model->verificarStockIngredientes([$this->ingrediente($id_insumo, 2, 15)], 10);

        $this->assertCount(1, $r['errores']);
        $this->assertSame(20.0, (float) $r['errores'][0]['cant_necesaria']);
        $this->assertSame(15.0, (float) $r['errores'][0]['disponible']);

        $this->assertCount(1, $r['avisos']);
        $this->assertSame(15.0, (float) $r['avisos'][0]['stock_actual']);
        $this->assertSame(10.0, (float) $r['avisos'][0]['disponible_lotes']);
    }
}
