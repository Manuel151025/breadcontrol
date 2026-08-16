<?php
// tests/Unit/CompraModelLoteTest.php
// Comprueba cuándo registrarCompra() reintenta con otro número de lote.
//
// Dos compras simultáneas pueden calcular el mismo consecutivo del día. La
// UNIQUE KEY de numero_lote impide guardar datos corruptos, y el modelo
// reintenta con el siguiente número en vez de mostrarle al usuario un error de
// duplicado. Lo delicado es NO reintentar ante otros errores del mismo
// SQLSTATE: una llave foránea rota (insumo o proveedor inexistente) volvería a
// fallar las cinco veces y ocultaría el problema real.

use PHPUnit\Framework\TestCase;

final class CompraModelLoteTest extends TestCase
{
    /** Invoca el método privado que discrimina el tipo de error. */
    private function esLoteDuplicado(PDOException $e): bool
    {
        $modelo = (new ReflectionClass(CompraModel::class))->newInstanceWithoutConstructor();
        $metodo = new ReflectionMethod(CompraModel::class, 'esLoteDuplicado');
        $metodo->setAccessible(true);

        return (bool) $metodo->invoke($modelo, $e);
    }

    /**
     * Fabrica una PDOException con su SQLSTATE.
     *
     * PDO guarda el SQLSTATE como texto ('23000') en la propiedad heredada
     * $code, que Exception declara protegida y sin setter, así que la única
     * forma de reproducir el error real en una prueba es escribirla por
     * reflexión.
     */
    private function excepcion(string $mensaje, string $sqlstate): PDOException
    {
        $e = new PDOException($mensaje);

        $code = new ReflectionProperty(Exception::class, 'code');
        $code->setAccessible(true);
        $code->setValue($e, $sqlstate);

        return $e;
    }

    public function testElChoqueDeDosComprasPorElMismoLoteSeReintenta(): void
    {
        $e = $this->excepcion(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry "
            . "'HAR-2026-08-15-003' for key 'lote.numero_lote'",
            '23000'
        );

        $this->assertTrue($this->esLoteDuplicado($e));
    }

    public function testUnaLlaveForaneaRotaNoSeReintenta(): void
    {
        // Mismo SQLSTATE 23000, causa distinta: el insumo no existe. Reintentar
        // solo repetiría el fallo y escondería el error de verdad.
        $e = $this->excepcion(
            "SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update "
            . "a child row: a foreign key constraint fails (`panaderia_bd`.`lote`, "
            . "CONSTRAINT `fk_lote_insumo` FOREIGN KEY (`id_insumo`) REFERENCES `insumo`)",
            '23000'
        );

        $this->assertFalse($this->esLoteDuplicado($e));
    }

    public function testOtroDuplicadoQueNoEsElDelLoteNoSeReintenta(): void
    {
        $e = $this->excepcion(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry "
            . "'sofia' for key 'usuario.correo'",
            '23000'
        );

        $this->assertFalse($this->esLoteDuplicado($e));
    }

    public function testUnErrorDeConexionNoSeConfundeConUnDuplicado(): void
    {
        $e = $this->excepcion('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away', 'HY000');

        $this->assertFalse($this->esLoteDuplicado($e));
    }
}
