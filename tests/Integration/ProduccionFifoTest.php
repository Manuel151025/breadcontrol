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

    // ------------------------------------------------------------
    // Aviso de descuadre entre el stock del insumo y sus lotes
    //
    // Es el punto 7 del anexo de limitaciones: insumo.stock_actual puede
    // desincronizarse de la suma real de lote.cantidad_disponible. El modelo ya
    // detecta ese descuadre y lo reporta en 'avisos', pero ninguna prueba lo
    // ejercia: las dos existentes solo miraban 'errores'. Las pruebas de
    // mutacion lo destaparon —ocho mutantes escapaban en esa comparacion— y sin
    // esto el detector de un problema conocido podia dejar de funcionar sin que
    // nadie se enterara.
    // ------------------------------------------------------------

    public function testAvisaCuandoElStockDelInsumoNoCuadraConSusLotes(): void
    {
        // El insumo dice 30 kg pero sus lotes solo suman 25: hay 5 kg que el
        // inventario cree tener y que ningun lote respalda.
        $id_insumo = $this->crearInsumo(30);
        $this->crearLote($id_insumo, date('Y-m-d H:i:s', strtotime('-10 days')), 25, 100);

        $r = $this->model->verificarStockIngredientes([$this->ingrediente($id_insumo, 1, 30)], 1);

        $this->assertCount(1, $r['avisos'], 'El descuadre debe reportarse');
        $this->assertSame(30.0, (float) $r['avisos'][0]['stock_actual']);
        $this->assertSame(25.0, (float) $r['avisos'][0]['disponible_lotes']);
    }

    public function testNoAvisaCuandoElStockCuadraConSusLotes(): void
    {
        // Mismo escenario, ya cuadrado: sin aviso. Sin esta comprobacion, un
        // detector que avisara SIEMPRE pasaria la prueba de arriba igual.
        $id_insumo = $this->crearInsumo(25);
        $this->crearLote($id_insumo, date('Y-m-d H:i:s', strtotime('-10 days')), 25, 100);

        $r = $this->model->verificarStockIngredientes([$this->ingrediente($id_insumo, 1, 25)], 1);

        $this->assertSame([], $r['avisos']);
    }

    public function testUnDescuadreMenorAlaMilesimaNoSeReporta(): void
    {
        // La comparacion redondea a tres decimales a proposito: los decimales
        // de coma flotante de MySQL no deben producir avisos falsos. Un descuadre
        // de una diezmilesima no es un descuadre, es ruido del tipo de dato.
        $id_insumo = $this->crearInsumo(25.0001);
        $this->crearLote($id_insumo, date('Y-m-d H:i:s', strtotime('-10 days')), 25, 100);

        $r = $this->model->verificarStockIngredientes([$this->ingrediente($id_insumo, 1, 25.0001)], 1);

        $this->assertSame([], $r['avisos'], 'Una diferencia por debajo de la milesima es ruido, no descuadre');
    }

    public function testElPlanFifoRedondeaLasCantidadesACuatroDecimales(): void
    {
        // Las otras pruebas usan cantidades enteras (10, 2, 20), de modo que el
        // round(..., 4) de 'a_consumir' nunca llegaba a hacer nada. Con una
        // receta de 1/3 por unidad la cantidad es periodica y el redondeo si
        // decide el valor.
        $id_insumo = $this->crearInsumo(10);
        $this->crearLote($id_insumo, date('Y-m-d H:i:s', strtotime('-10 days')), 10, 100);

        // 0,33333 x 1 tanda = 0,33333 -> 0,3333 con cuatro decimales
        $r = $this->model->calcularLotesFIFO($this->ingrediente($id_insumo, 0.33333, 10), 1);

        $this->assertSame(0.3333, $r['lotes_a_usar'][0]['a_consumir']);
    }
}
