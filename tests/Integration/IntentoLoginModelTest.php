<?php
// tests/Integration/IntentoLoginModelTest.php
// Pruebas del freno a la fuerza bruta en el inicio de sesion (models/IntentoLoginModel.php).
//
// Se prueba contra la tabla real `intento_login` dentro de una transaccion que
// se revierte, con un identificador unico por ejecucion para no chocar con
// intentos reales que pudiera haber en la base.

final class IntentoLoginModelTest extends BaseDatosTestCase
{
    private IntentoLoginModel $model;
    private string $usuario;
    private string $ip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model   = new IntentoLoginModel($this->pdo);
        $sufijo        = bin2hex(random_bytes(6));
        $this->usuario = 'test_' . $sufijo;
        // IP de documentacion (RFC 5737), nunca usada por un cliente real.
        $this->ip      = '192.0.2.' . random_int(1, 254);
    }

    public function testCuentaLimpiaNoEstaBloqueada(): void
    {
        $this->assertFalse(
            $this->model->estaBloqueado(IntentoLoginModel::AMBITO_ADMIN, $this->usuario, $this->ip)
        );
    }

    public function testBloqueaAlAlcanzarElUmbralDeLaCuenta(): void
    {
        for ($i = 1; $i < Seguridad::LOGIN_MAX_INTENTOS; $i++) {
            $this->model->registrarFallo(IntentoLoginModel::AMBITO_ADMIN, $this->usuario, $this->ip);
            $this->assertFalse(
                $this->model->estaBloqueado(IntentoLoginModel::AMBITO_ADMIN, $this->usuario, $this->ip),
                "No debe bloquear con $i intento(s), por debajo del umbral"
            );
        }

        $this->model->registrarFallo(IntentoLoginModel::AMBITO_ADMIN, $this->usuario, $this->ip);
        $this->assertTrue(
            $this->model->estaBloqueado(IntentoLoginModel::AMBITO_ADMIN, $this->usuario, $this->ip)
        );
    }

    public function testElBloqueoNoCruzaAmbitos(): void
    {
        // Un mismo nombre de usuario puede existir en el back-office y en el
        // portal: agotar los intentos en uno no debe cerrar el otro.
        for ($i = 0; $i < Seguridad::LOGIN_MAX_INTENTOS; $i++) {
            $this->model->registrarFallo(IntentoLoginModel::AMBITO_ADMIN, $this->usuario, null);
        }

        $this->assertTrue($this->model->estaBloqueado(IntentoLoginModel::AMBITO_ADMIN, $this->usuario, null));
        $this->assertFalse($this->model->estaBloqueado(IntentoLoginModel::AMBITO_PORTAL, $this->usuario, null));
    }

    public function testUnInicioCorrectoLimpiaLosIntentosPrevios(): void
    {
        for ($i = 0; $i < Seguridad::LOGIN_MAX_INTENTOS; $i++) {
            $this->model->registrarFallo(IntentoLoginModel::AMBITO_PORTAL, $this->usuario, null);
        }
        $this->assertTrue($this->model->estaBloqueado(IntentoLoginModel::AMBITO_PORTAL, $this->usuario, null));

        $this->model->limpiar(IntentoLoginModel::AMBITO_PORTAL, $this->usuario);
        $this->assertFalse($this->model->estaBloqueado(IntentoLoginModel::AMBITO_PORTAL, $this->usuario, null));
    }

    public function testLosIntentosFueraDeLaVentanaNoCuentan(): void
    {
        $viejo = date('Y-m-d H:i:s', time() - ((Seguridad::LOGIN_VENTANA_MINUTOS + 5) * 60));
        $stmt  = $this->pdo->prepare(
            "INSERT INTO intento_login (ambito, identificador, ip, fecha) VALUES (?, ?, ?, ?)"
        );
        for ($i = 0; $i < Seguridad::LOGIN_MAX_INTENTOS + 3; $i++) {
            $stmt->execute([IntentoLoginModel::AMBITO_ADMIN, $this->usuario, $this->ip, $viejo]);
        }

        $this->assertFalse(
            $this->model->estaBloqueado(IntentoLoginModel::AMBITO_ADMIN, $this->usuario, $this->ip)
        );
    }

    public function testBloqueaPorIpAunqueRoteElNombreDeUsuario(): void
    {
        // Umbral por IP mas alto que el de cuenta: frena al atacante que prueba
        // un usuario distinto en cada intento para no disparar el bloqueo por cuenta.
        for ($i = 0; $i < Seguridad::LOGIN_MAX_INTENTOS_IP; $i++) {
            $this->model->registrarFallo(
                IntentoLoginModel::AMBITO_ADMIN,
                $this->usuario . '_' . $i,
                $this->ip
            );
        }

        $this->assertTrue(
            $this->model->estaBloqueado(IntentoLoginModel::AMBITO_ADMIN, 'otro_usuario_cualquiera', $this->ip)
        );
    }
}
