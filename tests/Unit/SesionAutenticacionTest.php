<?php
// tests/Unit/SesionAutenticacionTest.php
// Pruebas de las funciones reales de autenticación y seguridad de includes/sesion.php:
// tokens CSRF, estado de sesión del usuario y verificación de contraseñas (bcrypt).

use PHPUnit\Framework\TestCase;

final class SesionAutenticacionTest extends TestCase
{
    protected function setUp(): void
    {
        // Cada prueba parte de una sesión limpia
        $_SESSION = [];
    }

    // ------------------------------------------------------------
    // CSRF
    // ------------------------------------------------------------

    public function testValidarTokenSinSesionNiTokenRetornaFalse(): void
    {
        // Manejo de error: null y vacío no deben lanzar excepción
        $this->assertFalse(validar_token_csrf(null));
        $this->assertFalse(validar_token_csrf(''));
    }

    public function testGenerarTokenProduce64CaracteresHexadecimales(): void
    {
        $token = generar_token_csrf();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testGenerarTokenEsIdempotenteDentroDeLaMismaSesion(): void
    {
        $this->assertSame(generar_token_csrf(), generar_token_csrf());
    }

    public function testValidarConElTokenCorrectoRetornaTrue(): void
    {
        $token = generar_token_csrf();
        $this->assertTrue(validar_token_csrf($token));
    }

    public function testValidarConTokenIncorrectoRetornaFalse(): void
    {
        generar_token_csrf();
        $this->assertFalse(validar_token_csrf('token_incorrecto'));
    }

    // ------------------------------------------------------------
    // Estado de sesión del usuario
    // ------------------------------------------------------------

    public function testUsuarioActualSinSesionRetornaValoresPorDefecto(): void
    {
        $usuario = usuarioActual();

        $this->assertNull($usuario['id_usuario']);
        $this->assertSame('', $usuario['nombre']);
        $this->assertSame('', $usuario['rol']);
    }

    public function testUsuarioActualConSesionActivaRetornaSusDatos(): void
    {
        $_SESSION['id_usuario'] = 7;
        $_SESSION['nombre_completo'] = 'Manuel Cárdenas';
        $_SESSION['rol'] = 'propietario';

        $usuario = usuarioActual();

        $this->assertSame(7, $usuario['id_usuario']);
        $this->assertSame('Manuel Cárdenas', $usuario['nombre']);
        $this->assertSame('propietario', $usuario['rol']);
    }

    public function testEsPropietarioSoloConRolPropietario(): void
    {
        $this->assertFalse(esPropietario(), 'Sin sesión no debe ser propietario');

        $_SESSION['rol'] = 'empleado';
        $this->assertFalse(esPropietario(), 'Un empleado no es propietario');

        $_SESSION['rol'] = 'propietario';
        $this->assertTrue(esPropietario());
    }

    // ------------------------------------------------------------
    // Contraseñas (esquema usado por iniciarSesion / AuthModel)
    // ------------------------------------------------------------

    public function testHashYVerificacionDeContrasena(): void
    {
        $hash = password_hash('MiClaveSegura123*', PASSWORD_DEFAULT);

        $this->assertTrue(password_verify('MiClaveSegura123*', $hash));
        $this->assertFalse(password_verify('clave-equivocada', $hash));
        $this->assertFalse(password_verify('', $hash), 'Contraseña vacía nunca debe verificar');
    }
}
