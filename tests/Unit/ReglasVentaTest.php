<?php
// tests/Unit/ReglasVentaTest.php
// Crédito de bonificación (tiendas) y ñapa (mostrador) del punto de venta
// (helpers/ReglasVenta.php).

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ReglasVentaTest extends TestCase
{
    #[DataProvider('provideCredito')]
    public function testCalculoDeCredito(bool $es_tienda, float $gastado, float $esperado): void
    {
        $this->assertSame($esperado, ReglasVenta::calcularCredito($es_tienda, $gastado));
    }

    public static function provideCredito(): array
    {
        return [
            // Tienda: $1000 por cada $5000
            'tienda 5000'      => [true, 5000.0, 1000.0],
            'tienda 12000'     => [true, 12000.0, 2000.0],
            'tienda 4999'      => [true, 4999.0, 0.0],
            'tienda 54000'     => [true, 54000.0, 10000.0],
            // Mostrador: $500 por cada $5000
            'mostrador 5000'   => [false, 5000.0, 500.0],
            'mostrador 12000'  => [false, 12000.0, 1000.0],
            'mostrador 4999'   => [false, 4999.0, 0.0],
            'mostrador 54000'  => [false, 54000.0, 5000.0],
            'sin venta'        => [true, 0.0, 0.0],
        ];
    }
}
