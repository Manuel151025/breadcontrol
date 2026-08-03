<?php
// tests/Integration/AuthModelTest.php
// Pruebas de integración de la autenticación del back-office (models/AuthModel.php):
// búsqueda de usuarios, verificación de contraseña bcrypt y flujo de recuperación.
// Todos los datos se crean dentro de la transacción y se revierten al final.

final class AuthModelTest extends BaseDatosTestCase
{
    private AuthModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new AuthModel($this->pdo);
    }

    private function crearUsuario(string $nombre_usuario, string $clave, int $activo = 1): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO usuario (nombre_usuario, nombre_completo, contrasena_hash, rol, activo)
            VALUES (?, 'Usuario PHPUnit', ?, 'empleado', ?)
        ");
        $stmt->execute([$nombre_usuario, password_hash($clave, PASSWORD_DEFAULT), $activo]);
        return (int) $this->pdo->lastInsertId();
    }

    public function testUsuarioInexistenteRetornaNull(): void
    {
        // Manejo de error: no debe lanzar excepción ni retornar false
        $this->assertNull($this->model->getUsuarioPorNombre('no_existe_' . uniqid()));
    }

    public function testUsuarioActivoSeEncuentraYSuContrasenaVerifica(): void
    {
        $nombre = 'phpunit_' . uniqid();
        $this->crearUsuario($nombre, 'ClaveSegura123*');

        $usuario = $this->model->getUsuarioPorNombre($nombre);

        $this->assertNotNull($usuario);
        $this->assertSame($nombre, $usuario['nombre_usuario']);
        $this->assertTrue(password_verify('ClaveSegura123*', $usuario['contrasena_hash']));
        $this->assertFalse(password_verify('otra-clave', $usuario['contrasena_hash']));
    }

    public function testUsuarioInactivoNoPuedeIniciarSesion(): void
    {
        $nombre = 'phpunit_inactivo_' . uniqid();
        $this->crearUsuario($nombre, 'ClaveSegura123*', activo: 0);

        // Regla de seguridad: getUsuarioPorNombre solo retorna usuarios activos
        $this->assertNull($this->model->getUsuarioPorNombre($nombre));
    }

    public function testGetUsuarioPorIdRetornaElUsuario(): void
    {
        $id = $this->crearUsuario('phpunit_id_' . uniqid(), 'ClaveSegura123*');

        $usuario = $this->model->getUsuarioPorId($id);

        $this->assertNotNull($usuario);
        $this->assertSame($id, (int) $usuario['id_usuario']);
    }

    public function testFlujoDeCodigoDeRecuperacion(): void
    {
        $id = $this->crearUsuario('phpunit_rec_' . uniqid(), 'ClaveSegura123*');
        $expira = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Registrar código
        $this->assertTrue($this->model->registrarCodigoRecuperacion($id, '123456', $expira));
        $usuario = $this->model->getUsuarioPorId($id);
        $this->assertSame('123456', $usuario['codigo_recuperacion']);
        $this->assertSame($expira, $usuario['codigo_expira']);

        // Limpiar código
        $this->assertTrue($this->model->limpiarCodigoRecuperacion($id));
        $usuario = $this->model->getUsuarioPorId($id);
        $this->assertNull($usuario['codigo_recuperacion']);
        $this->assertNull($usuario['codigo_expira']);
    }
}
