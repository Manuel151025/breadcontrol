<?php
// tests/Unit/SeguridadTest.php
// Pruebas de la clase REAL helpers/Seguridad.php, fuente unica de la politica
// de contrasena y del tratamiento del codigo de recuperacion por correo.
//
// Antes de centralizarla habia cuatro longitudes minimas distintas (4, 4, 6, 6)
// copiadas a mano en registro, recuperacion, perfil del portal y cambio de
// clave del propietario; estas pruebas fijan la regla unica.

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SeguridadTest extends TestCase
{
    // ============ GRUPO 1: politica de contrasena ============

    #[DataProvider('provideContrasenasInvalidas')]
    public function testContrasenaInvalidaDevuelveMensaje(string $contrasena): void
    {
        $this->assertNotNull(Seguridad::validarContrasena($contrasena));
    }

    /** @return array<string, array{string}> */
    public static function provideContrasenasInvalidas(): array
    {
        return [
            'vacia'                => [''],
            'los 4 de antes'       => ['abcd'],
            'los 6 de antes'       => ['abc123'],
            'un caracter de menos' => ['abc1234'],
            'solo letras'          => ['abcdefghij'],
            'solo numeros'         => ['1234567890'],
        ];
    }

    #[DataProvider('provideContrasenasValidas')]
    public function testContrasenaValidaNoDevuelveMensaje(string $contrasena): void
    {
        $this->assertNull(Seguridad::validarContrasena($contrasena));
    }

    /** @return array<string, array{string}> */
    public static function provideContrasenasValidas(): array
    {
        return [
            'minimo exacto'   => ['abcdefg1'],
            'con mayusculas'  => ['PanDeSal2026'],
            'con simbolos'    => ['pan-de-sal-2026!'],
            'numero al medio' => ['abc1defgh'],
        ];
    }

    public function testElMinimoDeclaradoEsElQueSeAplica(): void
    {
        $justo = str_repeat('a', Seguridad::CONTRASENA_MIN - 1) . '1';
        $corta = str_repeat('a', Seguridad::CONTRASENA_MIN - 2) . '1';

        $this->assertNull(Seguridad::validarContrasena($justo));
        $this->assertNotNull(Seguridad::validarContrasena($corta));
    }

    // ============ GRUPO 2: codigo de recuperacion ============

    public function testElCodigoSeGuardaHasheadoNoEnClaro(): void
    {
        $hash = Seguridad::hashCodigoRecuperacion('123456');

        $this->assertNotSame('123456', $hash);
        $this->assertStringStartsWith('$2y$', $hash);
    }

    public function testElCodigoCorrectoVerifica(): void
    {
        $hash = Seguridad::hashCodigoRecuperacion('654321');

        $this->assertTrue(Seguridad::verificarCodigoRecuperacion('654321', $hash));
        $this->assertFalse(Seguridad::verificarCodigoRecuperacion('654322', $hash));
    }

    public function testDosHashesDelMismoCodigoSonDistintos(): void
    {
        // bcrypt usa sal aleatoria: dos cuentas con el mismo codigo no comparten hash.
        $a = Seguridad::hashCodigoRecuperacion('000111');
        $b = Seguridad::hashCodigoRecuperacion('000111');

        $this->assertNotSame($a, $b);
        $this->assertTrue(Seguridad::verificarCodigoRecuperacion('000111', $a));
        $this->assertTrue(Seguridad::verificarCodigoRecuperacion('000111', $b));
    }

    #[DataProvider('provideCodigosSinHashValido')]
    public function testCuentaSinCodigoPendienteNuncaVerifica(string $codigo, ?string $hash): void
    {
        // Sin esta guarda, una cuenta con codigo_recuperacion NULL podria pasar
        // la verificacion si el llamador olvidara comprobarlo por separado.
        $this->assertFalse(Seguridad::verificarCodigoRecuperacion($codigo, $hash));
    }

    /** @return array<string, array{string, string|null}> */
    public static function provideCodigosSinHashValido(): array
    {
        return [
            'hash nulo'          => ['123456', null],
            'hash vacio'         => ['123456', ''],
            'codigo vacio'       => ['', '$2y$10$abcdefghijklmnopqrstuv'],
            'ambos vacios'       => ['', ''],
        ];
    }
}
