<?php
// tests/Unit/FormularioCompraTest.php
// Validación de los formularios de compras (helpers/FormularioCompra.php): qué
// se acepta al registrar una compra o guardar un proveedor, y con qué mensaje
// se rechaza lo demás. Sin base de datos ni navegador.

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class FormularioCompraTest extends TestCase
{
    /** Sábado: las compras no se fechan en domingo. */
    private const HOY = '2026-10-17';

    /** @return array<string, string> */
    private static function compraValida(): array
    {
        return [
            'id_insumo' => '3', 'id_proveedor' => '2', 'fecha_compra' => '2026-10-16',
            'cantidad' => '25,5', 'num_bultos' => '2', 'precio_bulto' => '90000',
        ];
    }

    public function testUnaCompraValidaQuedaListaParaElModelo(): void
    {
        $c = FormularioCompra::compra(self::compraValida(), self::HOY);

        $this->assertSame('', $c['error']);
        $this->assertSame(3, $c['id_insumo']);
        $this->assertSame(2, $c['id_proveedor']);
        $this->assertSame('2026-10-16', $c['fecha']);
        $this->assertSame(25.5, $c['cantidad'], 'acepta la coma decimal');
        $this->assertSame(2, $c['num_bultos']);
        $this->assertSame(90000.0, $c['precio_bulto']);
    }

    public function testSinFechaSeUsaHoy(): void
    {
        $c = FormularioCompra::compra(['fecha_compra' => ''] + self::compraValida(), self::HOY);

        $this->assertSame(self::HOY, $c['fecha']);
        $this->assertSame('', $c['error']);
    }

    public function testSinBultosCuentaUno(): void
    {
        $c = FormularioCompra::compra(['num_bultos' => ''] + self::compraValida(), self::HOY);

        $this->assertSame(1, $c['num_bultos']);
    }

    /** @param array<string, string> $cambio */
    #[DataProvider('provideComprasRechazadas')]
    public function testUnaCompraInvalidaSeRechazaConSuMotivo(array $cambio, string $motivo): void
    {
        $c = FormularioCompra::compra($cambio + self::compraValida(), self::HOY);

        $this->assertStringContainsString($motivo, $c['error']);
    }

    public static function provideComprasRechazadas(): array
    {
        return [
            'fecha imposible'      => [['fecha_compra' => '2026-13-45'], 'no es válida'],
            'fecha futura'         => [['fecha_compra' => '2026-10-18'], 'posterior a hoy'],
            'domingo'              => [['fecha_compra' => '2026-10-11'], 'domingo'],
            'sin insumo'           => [['id_insumo' => ''], 'Elige el insumo'],
            'sin proveedor'        => [['id_proveedor' => '0'], 'Elige el insumo'],
            'cantidad cero'        => [['cantidad' => '0'], 'La cantidad'],
            'cantidad con letras'  => [['cantidad' => 'abc'], 'La cantidad'],
            'cantidad enorme'      => [['cantidad' => '1000001'], 'La cantidad'],
            'cantidad infinita'    => [['cantidad' => '1e309'], 'La cantidad'],
            'precio cero'          => [['precio_bulto' => '0'], 'El precio'],
            'precio enorme'        => [['precio_bulto' => '100000001'], 'El precio'],
            'demasiados bultos'    => [['num_bultos' => '10001'], 'bultos'],
            'total desmedido'      => [['precio_bulto' => '100000000', 'num_bultos' => '10000'], 'total de la compra'],
        ];
    }

    public function testLosLimitesExactosSeAceptan(): void
    {
        $c = FormularioCompra::compra([
            'fecha_compra' => self::HOY, 'cantidad' => '1000000', 'precio_bulto' => '99999', 'num_bultos' => '10000',
        ] + self::compraValida(), self::HOY);

        $this->assertSame('', $c['error']);
    }

    public function testSeInformaSoloElPrimerProblema(): void
    {
        $c = FormularioCompra::compra(['fecha_compra' => 'abc', 'cantidad' => 'abc'] + self::compraValida(), self::HOY);

        $this->assertSame('La fecha de compra no es válida. Elígela en el calendario.', $c['error']);
    }

    // ------------------------------------------------------------
    // Proveedor
    // ------------------------------------------------------------

    public function testUnProveedorValido(): void
    {
        $p = FormularioCompra::proveedor([
            'nombre' => '  DistriHuevos  ', 'telefono' => '300 123-4567', 'tipo_entrega' => 'domicilio',
            'dias_entrega_promedio' => '2,5',
        ]);

        $this->assertSame([], $p['errores']);
        $this->assertSame('DistriHuevos', $p['nombre']);
        $this->assertSame('3001234567', $p['telefono'], 'solo dígitos');
        $this->assertSame(2.5, $p['dias']);
        $this->assertNull($p['dias_visita'], 'sin visita no hay días');
    }

    public function testElTelefonoSeRecortaAQuinceDigitos(): void
    {
        $p = FormularioCompra::proveedor(['nombre' => 'X', 'telefono' => '12345678901234567890']);

        $this->assertSame('123456789012345', $p['telefono']);
    }

    public function testConVisitaSoloSeGuardanDiasDeLaLista(): void
    {
        $p = FormularioCompra::proveedor([
            'nombre' => 'Molino', 'tipo_entrega' => 'visita', 'dias_visita' => ['lunes', 'domingo', ' jueves ', 'x'],
        ]);

        $this->assertSame([], $p['errores']);
        $this->assertSame('lunes,jueves', $p['dias_visita']);
    }

    /** @param array<string, mixed> $post */
    #[DataProvider('provideProveedoresRechazados')]
    public function testUnProveedorInvalidoSeRechaza(array $post, string $motivo): void
    {
        $p = FormularioCompra::proveedor($post);

        $this->assertCount(1, $p['errores']);
        $this->assertStringContainsString($motivo, $p['errores'][0]);
    }

    public static function provideProveedoresRechazados(): array
    {
        return [
            'sin nombre'              => [['nombre' => '   '], 'obligatorio'],
            'nombre largo'            => [['nombre' => str_repeat('a', 101)], '100 caracteres'],
            'entrega inventada'       => [['nombre' => 'A', 'tipo_entrega' => 'dron'], 'tipo de entrega'],
            'días negativos'          => [['nombre' => 'A', 'dias_entrega_promedio' => '-1'], 'entre 0 y 365'],
            'días con letras'         => [['nombre' => 'A', 'dias_entrega_promedio' => 'abc'], 'entre 0 y 365'],
            'visita sin días'         => [['nombre' => 'A', 'tipo_entrega' => 'visita'], 'día de visita'],
            'visita con días ajenos'  => [['nombre' => 'A', 'tipo_entrega' => 'visita', 'dias_visita' => ['domingo']], 'día de visita'],
        ];
    }
}
