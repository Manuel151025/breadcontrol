<?php
// tests/Integration/BaseDatosTestCase.php
// Clase base para pruebas de integración con MySQL.
//
// - Si la base de datos no está disponible, las pruebas se OMITEN (no fallan):
//   así la suite unitaria puede correr en cualquier máquina sin MySQL.
// - Cada prueba corre dentro de una transacción que se revierte en tearDown,
//   por lo que nunca quedan datos de prueba en la base.

use PHPUnit\Framework\TestCase;

abstract class BaseDatosTestCase extends TestCase
{
    protected ?PDO $pdo = null;

    protected function setUp(): void
    {
        try {
            $this->pdo = getConexion();
        } catch (Throwable $e) {
            $this->markTestSkipped('Base de datos no disponible: ' . $e->getMessage());
        }

        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
