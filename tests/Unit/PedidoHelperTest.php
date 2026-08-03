<?php
// tests/Unit/PedidoHelperTest.php
// Pruebas unitarias del helper real helpers/PedidoHelper.php:
// cálculo del total esperado de un pedido (individual o consolidado) y deuda restante.

use PHPUnit\Framework\TestCase;

final class PedidoHelperTest extends TestCase
{
    public function testTotalEsperadoDePedidoIndividual(): void
    {
        $pedido = ['total_estimado' => '12500.50'];
        $this->assertSame(12500.50, PedidoHelper::calcularTotalEsperado($pedido, []));
    }

    public function testTotalEsperadoDePagoConsolidadoSumaTodosLosPedidos(): void
    {
        $pedido = ['total_estimado' => '5000'];
        $consolidados = [
            ['total_estimado' => '5000'],
            ['total_estimado' => '8000.25'],
            ['total_estimado' => '2000'],
        ];
        // Cuando hay pago consolidado, el total del pedido individual se ignora
        $this->assertSame(15000.25, PedidoHelper::calcularTotalEsperado($pedido, $consolidados));
    }

    public function testDeudaRestanteNormal(): void
    {
        $this->assertSame(3000.0, PedidoHelper::calcularDeudaRestante(10000.0, 7000.0));
    }

    public function testDeudaRestanteNuncaEsNegativa(): void
    {
        // Manejo de error: un sobrepago no debe producir deuda negativa
        $this->assertSame(0.0, PedidoHelper::calcularDeudaRestante(10000.0, 15000.0));
        $this->assertSame(0.0, PedidoHelper::calcularDeudaRestante(10000.0, 10000.0));
    }
}
