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

    // ── esDomingo(): el proveedor no entrega los domingos ──
    //
    // La regla es una propiedad de la fecha de la compra, no del día en que se
    // registra. Se comprueba con fechas fijas para que la prueba dé lo mismo
    // cualquier día de la semana.

    public function testReconoceUnDomingo(): void
    {
        $this->assertTrue(esDomingo('2026-08-16'), '16/08/2026 fue domingo');
    }

    public function testUnDiaHabilNoEsDomingo(): void
    {
        $this->assertFalse(esDomingo('2026-08-15'), '15/08/2026 fue sábado');
        $this->assertFalse(esDomingo('2026-08-17'), '17/08/2026 fue lunes');
    }

    public function testFuncionaConFechaYHora(): void
    {
        $this->assertTrue(esDomingo('2026-08-16 14:30:00'));
    }

    public function testUnaFechaIlegibleNoBloqueaLaCompra(): void
    {
        // Validar el formato es tarea del formulario. Aquí, ante la duda, no se
        // rechaza una compra por un motivo que no es el suyo.
        $this->assertFalse(esDomingo('no soy una fecha'));
        $this->assertFalse(esDomingo(''));
    }

    // ── prefijoLote(): tres letras ASCII para el código del lote ──

    public function testPrefijoDeUnNombreCorriente(): void
    {
        $this->assertSame('HAR', prefijoLote('Harina de trigo'));
        $this->assertSame('SAL', prefijoLote('Sal'));
    }

    /**
     * El caso que rompía el sistema: «Azúcar» tiene la ú como tercer carácter.
     * Cortar por bytes se llevaba medio carácter y dejaba un prefijo inválido
     * que no encontraba sus propios lotes, así que la secuencia reiniciaba y la
     * segunda compra del insumo chocaba con la primera para siempre.
     */
    public function testUnNombreConTildeNoProduceUnPrefijoRoto(): void
    {
        $prefijo = prefijoLote('Azúcar');

        $this->assertSame('AZU', $prefijo);
        $this->assertSame(3, strlen($prefijo), 'Tres bytes: una letra ASCII cada uno');
        $this->assertMatchesRegularExpression('/^[A-Z0-9]+$/', $prefijo, 'Va impreso en la etiqueta: solo ASCII');
    }

    public function testOtrosNombresAcentuadosYLaEnie(): void
    {
        $this->assertSame('ANI', prefijoLote('Anís estrellado'));
        $this->assertSame('PIN', prefijoLote('Piña en almíbar'));
        $this->assertSame('MAN', prefijoLote('Mantequilla'));
    }

    public function testSeIgnoranEspaciosYSignos(): void
    {
        $this->assertSame('ACE', prefijoLote('  ¡Aceite! vegetal'));
    }

    public function testUnNombreSinLetrasIgualObtieneCodigo(): void
    {
        // Un insumo raro no puede quedarse sin poder comprarse.
        $this->assertSame('INS', prefijoLote('...'));
        $this->assertSame('INS', prefijoLote(''));
    }

    public function testUnNombreCortoNoSeRellena(): void
    {
        // «Ají» sin tilde ya son tres letras; el caso corto de verdad es de dos.
        $this->assertSame('AJI', prefijoLote('Ají'));
        $this->assertSame('TE', prefijoLote('Té'));
    }
}
