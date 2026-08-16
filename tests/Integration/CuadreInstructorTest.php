<?php
// tests/Integration/CuadreInstructorTest.php
//
// El tablero del instructor muestra un total grande («Saldo pendiente total»)
// y, debajo, una tabla con el saldo de cada aprendiz. Durante mucho tiempo los
// dos se calculaban por separado con reglas distintas y no cuadraban: la tabla
// ignoraba los pedidos con estado_pago nulo o parcial, y tampoco restaba los
// abonos. Estas pruebas montan el escenario completo en la base y comprueban
// el invariante que debe cumplirse siempre:
//
//     la suma de la columna == el total de arriba
//
// Todo ocurre dentro de la transacción de BaseDatosTestCase, que se revierte.

final class CuadreInstructorTest extends BaseDatosTestCase
{
    private PortalClienteModel $modelo;
    private int $instructor;
    private int $aprendiz_a;
    private int $aprendiz_b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->modelo = new PortalClienteModel($this->pdo);

        $this->instructor = $this->crearCliente('ZZ Instructor Prueba', false, null);
        $this->aprendiz_a = $this->crearCliente('ZZ Aprendiz A', true, $this->instructor);
        $this->aprendiz_b = $this->crearCliente('ZZ Aprendiz B', true, $this->instructor);
    }

    private function crearCliente(string $nombre, bool $es_aprendiz, ?int $instructor): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO cliente (nombre, es_aprendiz, activo, id_instructor) VALUES (?, ?, 1, ?)"
        );
        $stmt->execute([$nombre, $es_aprendiz ? 1 : 0, $instructor]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Un pedido del aprendiz a la tienda del instructor.
     *
     * estado_pago es NOT NULL DEFAULT 'no_aplica' en el esquema, así que
     * 'no_aplica' es lo que lleva un pedido sin proceso de pago abierto.
     */
    private function crearPedido(
        int $creador,
        float $total,
        string $estado_pago = 'no_aplica',
        ?int $id_pago = null,
        string $estado = 'confirmado'
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO pedido_cliente
                (id_cliente, id_creador, fecha_entrega, fecha_solicitud, total_estimado,
                 aprobado_instructor, estado, estado_pago, id_pago_activo)
            VALUES (?, ?, CURDATE(), NOW(), ?, 1, ?, ?, ?)
        ");
        $stmt->execute([$this->instructor, $creador, $total, $estado, $estado_pago, $id_pago]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Un pago consolidado que cubre varios pedidos a la vez. */
    private function crearPago(float $monto): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO pago_pedido (monto, monto_centavos, estado) VALUES (?, ?, 'PENDING')"
        );
        $stmt->execute([$monto, (int) round($monto * 100)]);

        return (int) $this->pdo->lastInsertId();
    }

    private function abonar(int $id_pago, float $monto): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO pago_abono (id_pago, monto, metodo_pago) VALUES (?, ?, 'nequi')"
        );
        $stmt->execute([$id_pago, $monto]);
    }

    /**
     * Escenario: un pedido suelto sin pagar, y un pago consolidado que cubre
     * pedidos de DOS aprendices distintos con un abono parcial encima.
     */
    private function montarEscenario(): void
    {
        // A debe 10.000 por su cuenta, sin pago abierto.
        $this->crearPedido($this->aprendiz_a, 10000);

        // Un pago de 50.000 cubre un pedido de A (20.000) y uno de B (30.000).
        // Abonaron 20.000, así que el grupo sigue debiendo 30.000, que se
        // reparte a prorrata: 12.000 de A y 18.000 de B.
        $pago = $this->crearPago(50000);
        $this->crearPedido($this->aprendiz_a, 20000, 'parcial', $pago);
        $this->crearPedido($this->aprendiz_b, 30000, 'parcial', $pago);
        $this->abonar($pago, 20000);

        // Un pedido cancelado: sigue con aprobado_instructor = 1, así que no
        // debe contar ni como dinero ni como pedido.
        $this->crearPedido($this->aprendiz_a, 5000, 'pendiente', null, 'rechazado');
    }

    /** @return array<int, array<string, mixed>> */
    private function filasPorAprendiz(): array
    {
        $porId = [];
        foreach ($this->modelo->getAprendicesResumen($this->instructor) as $fila) {
            $porId[(int) $fila['id_cliente']] = $fila;
        }

        return $porId;
    }

    public function testLaSumaDeLaColumnaDaElTotalDelTablero(): void
    {
        $this->montarEscenario();

        $kpi = $this->modelo->getInstructorStats($this->instructor);
        $suma = 0.0;
        foreach ($this->modelo->getAprendicesResumen($this->instructor) as $fila) {
            $suma += (float) $fila['saldo_pendiente'];
        }

        $this->assertEqualsWithDelta(40000.0, (float) $kpi['pendiente_total'], 0.01, 'Total del tablero');
        $this->assertEqualsWithDelta((float) $kpi['pendiente_total'], $suma, 0.01, 'La tabla debe explicar el total');
    }

    public function testElAbonoDeUnPagoCompartidoSeRepartePorloQueDebeCadaUno(): void
    {
        $this->montarEscenario();
        $filas = $this->filasPorAprendiz();

        // A: 10.000 del pedido suelto + 12.000 de su parte del consolidado.
        $this->assertEqualsWithDelta(22000.0, (float) $filas[$this->aprendiz_a]['saldo_pendiente'], 0.01);
        $this->assertEqualsWithDelta(18000.0, (float) $filas[$this->aprendiz_b]['saldo_pendiente'], 0.01);
    }

    public function testUnPedidoCanceladoNoCuentaComoPedidoNiComoDinero(): void
    {
        $this->montarEscenario();

        $kpi   = $this->modelo->getInstructorStats($this->instructor);
        $filas = $this->filasPorAprendiz();

        $this->assertSame(3, (int) $kpi['total_pedidos'], 'Los 3 vigentes, no los 4 aprobados');
        $this->assertSame(2, (int) $filas[$this->aprendiz_a]['total_pedidos'], 'A tiene 2 vigentes');
        $this->assertEqualsWithDelta(30000.0, (float) $filas[$this->aprendiz_a]['total_comprado'], 0.01);
    }

    public function testUnAprendizCuyoUnicoPedidoSeCancelaNoCuentaComoActivo(): void
    {
        $this->crearPedido($this->aprendiz_a, 8000, 'pendiente');
        $this->crearPedido($this->aprendiz_b, 5000, 'pendiente', null, 'rechazado');

        $kpi = $this->modelo->getInstructorStats($this->instructor);

        $this->assertSame(1, (int) $kpi['aprendices_activos'], 'Solo A tiene un pedido vigente');
    }

    public function testAlDesactivarUnAprendizSuDeudaSigueVisibleEnLaTabla(): void
    {
        $this->montarEscenario();
        $this->pdo->prepare("UPDATE cliente SET activo = 0 WHERE id_cliente = ?")
                  ->execute([$this->aprendiz_b]);

        $kpi   = $this->modelo->getInstructorStats($this->instructor);
        $filas = $this->filasPorAprendiz();

        $this->assertArrayHasKey($this->aprendiz_b, $filas, 'Su deuda no puede desaparecer de la tabla');
        $this->assertFalse((bool) $filas[$this->aprendiz_b]['en_mi_grupo'], 'Debe salir marcado como fuera del grupo');

        $suma = 0.0;
        foreach ($filas as $fila) {
            $suma += (float) $fila['saldo_pendiente'];
        }
        $this->assertEqualsWithDelta((float) $kpi['pendiente_total'], $suma, 0.01, 'El total sigue explicado');
    }

    public function testUnAprendizSinPedidosSaleConSaldoCero(): void
    {
        $this->crearPedido($this->aprendiz_a, 10000, 'pendiente');
        $filas = $this->filasPorAprendiz();

        $this->assertSame(0.0, (float) $filas[$this->aprendiz_b]['saldo_pendiente']);
        $this->assertSame(0, (int) $filas[$this->aprendiz_b]['total_pedidos']);
        $this->assertTrue((bool) $filas[$this->aprendiz_b]['en_mi_grupo']);
    }
}
