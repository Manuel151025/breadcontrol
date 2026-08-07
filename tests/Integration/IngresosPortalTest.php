<?php
// tests/Integration/IngresosPortalTest.php
// Los pedidos del Portal de Clientes nunca crean una fila en `venta`, asi que
// los reportes financieros (que leen `venta`) dejaban fuera todo lo despachado
// por el portal. FinanzasHelper::ingresosPortalEnRango los suma leyendo el
// estado del pedido.
//
// Se usan fechas de 2031 para que ningun pedido real de la base interfiera con
// las sumas, y todo corre dentro de una transaccion que se revierte.

final class IngresosPortalTest extends BaseDatosTestCase
{
    private const DIA   = '2031-03-10';
    private const OTRO  = '2031-03-11';

    private int $id_cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $stmt = $this->pdo->prepare(
            "INSERT INTO cliente (nombre, tipo, activo) VALUES (?, 'tienda', 1)"
        );
        $stmt->execute(['Tienda de prueba ' . bin2hex(random_bytes(4))]);
        $this->id_cliente = (int) $this->pdo->lastInsertId();
    }

    private function crearPedido(string $fecha_entrega, float $total, string $estado_pago): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO pedido_cliente (id_cliente, fecha_entrega, total_estimado, estado_pago)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$this->id_cliente, $fecha_entrega, $total, $estado_pago]);
    }

    public function testSinPedidosElAporteEsCero(): void
    {
        $this->assertSame(
            0.0,
            FinanzasHelper::ingresosPortalEnRango($this->pdo, self::DIA, self::DIA)
        );
    }

    public function testSoloSumaLosPedidosCobrados(): void
    {
        $this->crearPedido(self::DIA, 10000.0, 'aprobado');
        $this->crearPedido(self::DIA, 25000.0, 'aprobado');
        // Estos NO deben contar: el dinero todavia no entro.
        $this->crearPedido(self::DIA,  9000.0, 'pendiente');
        $this->crearPedido(self::DIA,  7000.0, 'parcial');
        $this->crearPedido(self::DIA,  5000.0, 'no_aplica');

        $this->assertSame(
            35000.0,
            FinanzasHelper::ingresosPortalEnRango($this->pdo, self::DIA, self::DIA)
        );
    }

    public function testElIngresoCaeElDiaDeEntregaNoEnOtro(): void
    {
        // La fecha del ingreso es fecha_entrega: asi cuadra con la produccion
        // que lo costeo, no con el dia en que el propietario confirmo el cobro.
        $this->crearPedido(self::DIA,  12000.0, 'aprobado');
        $this->crearPedido(self::OTRO,  3000.0, 'aprobado');

        $this->assertSame(12000.0, FinanzasHelper::ingresosPortalEnRango($this->pdo, self::DIA, self::DIA));
        $this->assertSame(3000.0,  FinanzasHelper::ingresosPortalEnRango($this->pdo, self::OTRO, self::OTRO));
        $this->assertSame(15000.0, FinanzasHelper::ingresosPortalEnRango($this->pdo, self::DIA, self::OTRO));
    }

    public function testLeerDosVecesNoDuplicaElIngreso(): void
    {
        // Anti-doble-conteo por diseno: se lee un estado, no se crea una fila.
        $this->crearPedido(self::DIA, 8000.0, 'aprobado');

        $primera = FinanzasHelper::ingresosPortalEnRango($this->pdo, self::DIA, self::DIA);
        $segunda = FinanzasHelper::ingresosPortalEnRango($this->pdo, self::DIA, self::DIA);

        $this->assertSame(8000.0, $primera);
        $this->assertSame($primera, $segunda);
    }

    public function testElCierreDelDiaIncluyeElPortal(): void
    {
        // Comprobacion de punta a punta sobre el modelo que alimenta el cierre.
        $modelo = new CierreModel($this->pdo);
        $antes  = $modelo->getTotalVentasHoy(self::DIA);

        $this->crearPedido(self::DIA, 4500.0, 'aprobado');

        $this->assertSame($antes + 4500.0, $modelo->getTotalVentasHoy(self::DIA));
    }
}
