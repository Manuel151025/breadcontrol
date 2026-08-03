<?php
// tests/Unit/FuncionesTest.php
// Pruebas unitarias de las funciones auxiliares reales de includes/funciones.php:
// formato de moneda/números, sanitización de entradas y manejo de fechas de entrega.

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class FuncionesTest extends TestCase
{
    // ------------------------------------------------------------
    // formatoPeso: moneda colombiana sin decimales
    // ------------------------------------------------------------

    #[DataProvider('provideFormatoPeso')]
    public function testFormatoPeso(float $valor, string $esperado): void
    {
        $this->assertSame($esperado, formatoPeso($valor));
    }

    public static function provideFormatoPeso(): array
    {
        return [
            'cero'            => [0.0, '$ 0'],
            'miles'           => [12000.0, '$ 12.000'],
            'millones'        => [1234567.0, '$ 1.234.567'],
            'redondeo arriba' => [999.5, '$ 1.000'],
        ];
    }

    // ------------------------------------------------------------
    // formatoDecimal y formatoInteligente
    // ------------------------------------------------------------

    public function testFormatoDecimalUsaComaDecimalYPuntoDeMiles(): void
    {
        $this->assertSame('1.234,50', formatoDecimal(1234.5));
        $this->assertSame('1.234,6', formatoDecimal(1234.56, 1));
    }

    #[DataProvider('provideFormatoInteligente')]
    public function testFormatoInteligenteEliminaCerosInnecesarios(float $valor, string $esperado): void
    {
        $this->assertSame($esperado, formatoInteligente($valor));
    }

    public static function provideFormatoInteligente(): array
    {
        return [
            'entero grande'   => [12000.0, '12.000'],
            'entero simple'   => [2.0, '2'],
            'medio'           => [2.5, '2,5'],
            'tres decimales'  => [0.125, '0,125'],
        ];
    }

    // ------------------------------------------------------------
    // limpiar: sanitización de entrada del usuario (seguridad XSS)
    // ------------------------------------------------------------

    public function testLimpiarRecortaEspaciosYEliminaEtiquetas(): void
    {
        $this->assertSame('Pan', limpiar('  <b>Pan</b>  '));
    }

    public function testLimpiarEscapaComillasYCaracteresHtml(): void
    {
        $this->assertSame('alert(&quot;x&quot;)', limpiar('<script>alert("x")</script>'));
        $this->assertSame('O&#039;Brien &amp; Cía', limpiar("O'Brien & Cía"));
    }

    // ------------------------------------------------------------
    // calcularVariacion: variación porcentual de precios
    // ------------------------------------------------------------

    #[DataProvider('provideVariacion')]
    public function testCalcularVariacion(float $anterior, float $nuevo, float $esperado): void
    {
        $this->assertSame($esperado, calcularVariacion($anterior, $nuevo));
    }

    public static function provideVariacion(): array
    {
        return [
            'sube 10%'              => [1000.0, 1100.0, 10.0],
            'baja 10%'              => [1000.0, 900.0, -10.0],
            'sin cambio'            => [1000.0, 1000.0, 0.0],
            'redondeo a 2 cifras'   => [300.0, 400.0, 33.33],
            // Manejo de error: división por cero no debe lanzar excepción
            'precio anterior cero'  => [0.0, 500.0, 0.0],
        ];
    }

    // ------------------------------------------------------------
    // formatearFechaEntrega: fechas "Por definir" y formatos amigables
    // ------------------------------------------------------------

    public function testFechaNulaOVaciaSeMuestraComoPorDefinir(): void
    {
        // Manejo de error: null y cadena vacía no deben romper la función
        $this->assertSame('Por definir (Tienda ADSO)', formatearFechaEntrega(null, false));
        $this->assertSame('Por definir (Tienda ADSO)', formatearFechaEntrega('', false));
    }

    public function testFechaDummySeMuestraComoPorDefinirConHtml(): void
    {
        $html = formatearFechaEntrega('1000-01-01 00:00:00', true);
        $this->assertStringContainsString('Por definir (Tienda ADSO)', $html);
        $this->assertStringContainsString('<span', $html);
    }

    public function testFechaConHoraSeFormateaConHora(): void
    {
        $this->assertSame('15/06/2026 12:30 PM', formatearFechaEntrega('2026-06-15 12:30:00', false));
    }

    public function testFechaSinHoraSeFormateaSoloFecha(): void
    {
        $this->assertSame('15/06/2026', formatearFechaEntrega('2026-06-15 00:00:00', false));
    }
}
