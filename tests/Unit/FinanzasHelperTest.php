<?php
// tests/Unit/FinanzasHelperTest.php
// Pruebas unitarias del helper real helpers/FinanzasHelper.php:
// utilidad bruta y neta calculadas con costo real de producción (no compras).

use PHPUnit\Framework\TestCase;

final class FinanzasHelperTest extends TestCase
{
    public function testUtilidadBrutaYNetaConValoresPositivos(): void
    {
        $resultado = FinanzasHelper::calcularUtilidad(100000.0, 40000.0, 25000.0);

        $this->assertSame(60000.0, $resultado['bruta']);
        $this->assertSame(35000.0, $resultado['neta']);
    }

    public function testUtilidadPuedeSerNegativaSiHayPerdidas(): void
    {
        // Ventas bajas + costos altos: el sistema debe reportar la pérdida, no ocultarla
        $resultado = FinanzasHelper::calcularUtilidad(10000.0, 15000.0, 5000.0);

        $this->assertSame(-5000.0, $resultado['bruta']);
        $this->assertSame(-10000.0, $resultado['neta']);
    }

    public function testUtilidadConTodoEnCero(): void
    {
        $resultado = FinanzasHelper::calcularUtilidad(0.0, 0.0, 0.0);

        $this->assertSame(0.0, $resultado['bruta']);
        $this->assertSame(0.0, $resultado['neta']);
    }
}
