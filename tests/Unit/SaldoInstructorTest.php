<?php
// tests/Unit/SaldoInstructorTest.php
// El desglose por aprendiz debe sumar exactamente el total del tablero.
//
// Un pago consolidado cubre varios pedidos a la vez y el abono se registra
// contra el pago, no contra cada pedido. Para saber cuánto sigue debiendo cada
// aprendiz hay que repartir lo pendiente a prorrata, y ese reparto tiene que
// cuadrar al peso: si las partes suman un peso de más o de menos, la columna
// «Saldo pendiente» deja de dar el KPI «Saldo pendiente total», que es el
// descuadre que este cálculo existe para evitar.

use PHPUnit\Framework\TestCase;

final class SaldoInstructorTest extends TestCase
{
    /**
     * @param array<int, float> $montos
     * @return array<int, float>
     */
    private function repartir(array $montos, float $deficit): array
    {
        $metodo = new ReflectionMethod(PortalClienteModel::class, 'repartirDeficit');
        $metodo->setAccessible(true);

        /** @var array<int, float> $partes */
        $partes = $metodo->invoke(null, $montos, $deficit);

        return $partes;
    }

    public function testSinAbonosCadaPedidoDebeSuImporteCompleto(): void
    {
        $partes = $this->repartir([10000.0, 20000.0], 30000.0);

        $this->assertSame([10000.0, 20000.0], $partes);
    }

    public function testUnAbonoParcialSeRepartePorloQuePusoCadaPedido(): void
    {
        // Debían 30.000 entre los dos y abonaron 15.000: queda la mitad de cada uno.
        $partes = $this->repartir([10000.0, 20000.0], 15000.0);

        $this->assertSame([5000.0, 10000.0], $partes);
    }

    public function testElResiduoDelRedondeoNoDescuadraElTotal(): void
    {
        // 10 entre tres partes iguales no es divisible: 3,33 + 3,33 + 3,34.
        $deficit = 10.0;
        $partes  = $this->repartir([1.0, 1.0, 1.0], $deficit);

        $this->assertSame($deficit, array_sum($partes), 'Las partes deben sumar el déficit exacto');
        $this->assertCount(3, $partes);
    }

    public function testUnSoloPedidoSeLlevaTodoLoPendiente(): void
    {
        $this->assertSame([7500.0], $this->repartir([12000.0], 7500.0));
    }

    public function testImportesEnCeroNoProvocanDivisionPorCero(): void
    {
        $partes = $this->repartir([0.0, 0.0], 5000.0);

        $this->assertSame(5000.0, array_sum($partes), 'Lo pendiente no puede evaporarse');
    }

    public function testUnPagoSinPedidosDevuelveUnRepartoVacio(): void
    {
        $this->assertSame([], $this->repartir([], 1000.0));
    }

    /**
     * El caso que motivó todo esto: varios pedidos desiguales con un abono
     * cualquiera. Importa menos cuánto le toca a cada uno que el hecho de que
     * la suma cierre con el total que se muestra arriba.
     */
    public function testLasPartesSiempreCierranConElTotal(): void
    {
        $casos = [
            [[5000.0, 7000.0, 13000.0], 9333.0],
            [[1500.0, 1500.0, 1500.0, 1500.0], 4000.0],
            [[20000.0, 333.0], 11111.11],
        ];

        foreach ($casos as [$montos, $deficit]) {
            $suma = array_sum($this->repartir($montos, $deficit));
            $this->assertEqualsWithDelta(
                $deficit,
                $suma,
                0.001,
                'Reparto de ' . $deficit . ' entre ' . count($montos) . ' pedidos'
            );
        }
    }
}
