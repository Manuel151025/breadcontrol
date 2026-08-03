<?php
// tests/Integration/GeneracionLotesTest.php
// Pruebas de integración de la generación de números de lote
// (migradas del antiguo test_batch_generation.php).
// Formato esperado: PRE-YYYY-MM-DD-NNN

final class GeneracionLotesTest extends BaseDatosTestCase
{
    public function testFormatoDelNumeroDeLote(): void
    {
        $lote = generarNumeroLote('harina');

        $partes = explode('-', $lote);
        $this->assertCount(5, $partes, 'El lote debe tener 5 partes: PRE-YYYY-MM-DD-NNN');

        $this->assertSame('HAR', $partes[0], 'Prefijo: primeras 3 letras en mayúsculas');
        $this->assertSame(date('Y-m-d'), $partes[1] . '-' . $partes[2] . '-' . $partes[3], 'La fecha debe ser hoy');
        $this->assertMatchesRegularExpression('/^\d{3}$/', $partes[4], 'La secuencia debe ser de 3 dígitos');
    }

    public function testPrefijoSinLotesPreviosIniciaEn001(): void
    {
        $lote = generarNumeroLote('xyz_no_existe');
        $partes = explode('-', $lote);

        $this->assertSame('001', end($partes));
    }

    public function testLaSecuenciaIncrementaSobreElUltimoLoteDelDia(): void
    {
        // Necesita un insumo real para respetar la clave foránea de lote
        $id_insumo = $this->pdo->query("SELECT id_insumo FROM insumo ORDER BY id_insumo LIMIT 1")->fetchColumn();
        if ($id_insumo === false) {
            $this->markTestSkipped('No hay insumos en la base para crear un lote de prueba.');
        }

        // Insertar (dentro de la transacción) un lote de hoy con secuencia 007
        $numero = 'ZZT-' . date('Y-m-d') . '-007';
        $stmt = $this->pdo->prepare("
            INSERT INTO lote (id_insumo, numero_lote, cantidad_inicial, cantidad_disponible, precio_unitario)
            VALUES (?, ?, 1, 1, 100)
        ");
        $stmt->execute([(int) $id_insumo, $numero]);

        $siguiente = generarNumeroLote('zztest');

        $this->assertSame('ZZT-' . date('Y-m-d') . '-008', $siguiente);
    }
}
