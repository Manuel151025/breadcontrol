<?php
// tests/Integration/PortalClienteModelTest.php
// Pruebas de integración de la aprobación/rechazo en lote de pedidos de aprendices
// por parte del instructor (migradas del grupo 8 del antiguo test_portal_rules.php).
// Todos los datos se crean dentro de la transacción y se revierten al final.

final class PortalClienteModelTest extends BaseDatosTestCase
{
    private PortalClienteModel $model;
    private int $id_instructor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new PortalClienteModel($this->pdo);

        // Instructor temporal
        $stmt = $this->pdo->prepare("
            INSERT INTO cliente (nombre, tipo, telefono, usuario, contrasena_hash, es_aprendiz, activo, fecha_creacion)
            VALUES ('Instructor Test PHPUnit', 'tienda', '1234567', ?, 'hash', 0, 1, NOW())
        ");
        $stmt->execute(['inst_phpunit_' . uniqid()]);
        $this->id_instructor = (int) $this->pdo->lastInsertId();
    }

    private function crearPedidoPendiente(float $total): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO pedido_cliente (id_cliente, id_creador, fecha_entrega, total_estimado, aprobado_instructor, estado)
            VALUES (?, ?, '1000-01-01', ?, 0, 'pendiente')
        ");
        $stmt->execute([$this->id_instructor, $this->id_instructor, $total]);
        return (int) $this->pdo->lastInsertId();
    }

    private function leerPedido(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT aprobado_instructor, fecha_entrega, estado FROM pedido_cliente WHERE id_pedido = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function testAprobacionEnLoteActualizaPedidosYFechaDeEntrega(): void
    {
        $id_ped1 = $this->crearPedidoPendiente(5000.0);
        $id_ped2 = $this->crearPedidoPendiente(10000.0);

        $afectados = $this->model->aprobarPedidosInstructorLote(
            [$id_ped1, $id_ped2],
            $this->id_instructor,
            '2026-06-20 08:30:00'
        );

        $this->assertSame(2, $afectados, 'Debe reportar 2 pedidos aprobados');

        $p1 = $this->leerPedido($id_ped1);
        $this->assertSame(1, (int) $p1['aprobado_instructor']);
        // fecha_entrega es columna DATE: se conserva solo la fecha
        $this->assertSame('2026-06-20', substr($p1['fecha_entrega'], 0, 10));
        $this->assertSame('pendiente', $p1['estado'], 'Ante la panadería el pedido sigue pendiente');

        $p2 = $this->leerPedido($id_ped2);
        $this->assertSame(1, (int) $p2['aprobado_instructor']);
        $this->assertSame('2026-06-20', substr($p2['fecha_entrega'], 0, 10));
    }

    public function testRechazoEnLoteMarcaElPedidoComoRechazado(): void
    {
        $id_ped = $this->crearPedidoPendiente(3000.0);

        $afectados = $this->model->rechazarPedidosInstructorLote([$id_ped], $this->id_instructor);
        $this->assertSame(1, $afectados);

        $p = $this->leerPedido($id_ped);
        $this->assertSame(0, (int) $p['aprobado_instructor']);
        $this->assertSame('rechazado', $p['estado']);
    }

    public function testAprobarConListaVaciaRetornaCero(): void
    {
        $this->assertSame(0, $this->model->aprobarPedidosInstructorLote([], $this->id_instructor, '2026-06-20 08:30:00'));
    }

    public function testAprobarPedidosDeOtroInstructorLanzaExcepcion(): void
    {
        // Manejo de error: un instructor no puede aprobar pedidos ajenos
        $id_ped = $this->crearPedidoPendiente(5000.0);
        $id_otro_instructor = $this->id_instructor + 1000000; // no existe / no es dueño

        $this->expectException(Exception::class);
        $this->model->aprobarPedidosInstructorLote([$id_ped], $id_otro_instructor, '2026-06-20 08:30:00');
    }

    public function testRechazarPedidoYaProcesadoLanzaExcepcion(): void
    {
        // Manejo de error: un pedido ya aprobado no puede rechazarse en lote
        $id_ped = $this->crearPedidoPendiente(5000.0);
        $this->model->aprobarPedidosInstructorLote([$id_ped], $this->id_instructor, '2026-06-20 08:30:00');

        $this->expectException(Exception::class);
        $this->model->rechazarPedidosInstructorLote([$id_ped], $this->id_instructor);
    }
}
