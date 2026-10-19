<?php
// tests/Unit/EntradaTest.php
// Pruebas de includes/entrada.php: la normalización de $_GET/$_POST que impide
// que un campo manipulado (`estado[]=1`, un carrito que no es JSON) termine en
// «Ocurrió un error inesperado», y de los ayudantes que la acompañan.

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class EntradaTest extends TestCase
{
    public function testUnCampoDeTextoQueLlegaComoListaSeDescarta(): void
    {
        $r = entrada_normalizada(['estado' => ['1', 'x' => '2'], 'orden' => 'recientes'], []);

        $this->assertArrayNotHasKey('estado', $r);
        $this->assertSame('recientes', $r['orden']);
    }

    public function testUnaListaDeclaradaConservaSusValoresYClaves(): void
    {
        $r = entrada_normalizada(['dist' => ['13' => '40', '14' => '60']], ['dist']);

        $this->assertSame(['13' => '40', '14' => '60'], $r['dist']);
    }

    public function testUnaListaDeclaradaQueLlegaComoTextoSeConvierteEnLista(): void
    {
        $r = entrada_normalizada(['ids' => '7'], ['ids']);

        $this->assertSame(['7'], $r['ids']);
    }

    public function testDentroDeUnaListaSoloPasanTextos(): void
    {
        $r = entrada_normalizada(['ids' => ['1', ['2'], '3']], ['ids']);

        $this->assertSame([0 => '1', 2 => '3'], $r['ids']);
    }

    public function testLasListasTienenTope(): void
    {
        $r = entrada_normalizada(['ids' => array_map('strval', range(1, 5000))], ['ids']);

        $this->assertCount(1000, $r['ids']);
    }

    public function testSeQuitanLosBytesNulos(): void
    {
        $r = entrada_normalizada(['fecha' => "2026-10-20\0", 'ids' => ["4\0"]], ['ids']);

        $this->assertSame('2026-10-20', $r['fecha']);
        $this->assertSame(['4'], $r['ids']);
    }

    // ------------------------------------------------------------
    // json_lista_productos: el carrito que viaja en un campo oculto
    // ------------------------------------------------------------

    #[DataProvider('provideCarritos')]
    public function testJsonListaProductosSoloDevuelveProductos(string $json, array $esperado): void
    {
        $this->assertSame($esperado, json_lista_productos($json));
    }

    public static function provideCarritos(): array
    {
        $item = ['id_variedad' => 1, 'cantidad' => 2];
        return [
            'carrito normal'  => [json_encode([$item]), [$item]],
            'vacío'           => ['', []],
            'JSON roto'       => ['{"a":', []],
            'número'          => ['5', []],
            'texto'           => ['"hola"', []],
            'mezcla'          => [json_encode([1, 'x', $item, null]), [$item]],
        ];
    }

    // ------------------------------------------------------------
    // mensaje_para_usuario: qué texto de una excepción llega a pantalla
    // ------------------------------------------------------------

    public function testElMensajeDeUnaReglaDeNegocioSeMuestraTalCual(): void
    {
        $this->assertSame('Límite de cupo excedido.', mensaje_para_usuario(new Exception('Límite de cupo excedido.')));
    }

    public function testUnFalloDeBaseDeDatosNoMuestraElSql(): void
    {
        $msg = mensaje_para_usuario(new PDOException("SQLSTATE[22007]: Invalid datetime format: 1292 Incorrect date value: '2026-13-45' for column `fecha_entrega`"));

        $this->assertStringNotContainsString('SQLSTATE', $msg);
        $this->assertStringNotContainsString('fecha_entrega', $msg);
    }

    public function testUnErrorDePhpNoMuestraSuDetalle(): void
    {
        $msg = mensaje_para_usuario(new TypeError('trim(): Argument #1 ($string) must be of type string, array given'));

        $this->assertStringNotContainsString('trim()', $msg);
    }
}
