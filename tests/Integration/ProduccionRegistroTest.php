<?php
// tests/Integration/ProduccionRegistroTest.php
// Prueba de registrarProduccionConConsumos: el corazón del costeo FIFO.
//
// Este método abre y confirma SU PROPIA transacción, así que no puede correr
// dentro del rollback de BaseDatosTestCase. Los datos se crean de verdad y
// se eliminan en tearDown (siempre, incluso si la prueba falla).

use PHPUnit\Framework\TestCase;

final class ProduccionRegistroTest extends TestCase
{
    private ?PDO $pdo = null;
    private ProduccionModel $model;

    private int $id_usuario = 0;
    private int $id_producto = 0;
    private int $id_receta = 0;
    private int $id_insumo = 0;
    private int $id_cat = 0;
    private array $ids_produccion = [];

    protected function setUp(): void
    {
        try {
            $this->pdo = getConexion();
        } catch (Throwable $e) {
            $this->markTestSkipped('Base de datos no disponible: ' . $e->getMessage());
        }
        $this->model = new ProduccionModel($this->pdo);

        $u = uniqid();
        $this->pdo->prepare("INSERT INTO usuario (nombre_usuario, nombre_completo, contrasena_hash, rol, activo) VALUES (?, 'Usuario Produccion PHPUnit', 'hash', 'empleado', 1)")
            ->execute(['phpunit_prod_' . $u]);
        $this->id_usuario = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO producto (nombre, categoria, precio_venta, unidad_produccion, cantidad_por_tanda) VALUES (?, 'sal', 500, 'carro', 10)")
            ->execute(['Producto PHPUnit ' . $u]);
        $this->id_producto = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO receta (id_producto, id_usuario, version, es_vigente) VALUES (?, ?, 1, 1)")
            ->execute([$this->id_producto, $this->id_usuario]);
        $this->id_receta = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO insumo (nombre, unidad_medida, es_harina, stock_actual, punto_reposicion) VALUES (?, 'kg', 1, 30, 1)")
            ->execute(['Insumo PHPUnit ' . $u]);
        $this->id_insumo = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO receta_ingrediente (id_receta, id_insumo, cantidad, unidad, aplica_merma) VALUES (?, ?, 2, 'kg', 0)")
            ->execute([$this->id_receta, $this->id_insumo]);

        // Lote viejo barato (10 kg a $100) y lote nuevo caro (20 kg a $200)
        $lote = $this->pdo->prepare("INSERT INTO lote (id_insumo, numero_lote, cantidad_inicial, cantidad_disponible, precio_unitario, fecha_ingreso, estado) VALUES (?, ?, ?, ?, ?, ?, 'activo')");
        $lote->execute([$this->id_insumo, 'TSTA-' . $u, 10, 10, 100, date('Y-m-d H:i:s', strtotime('-30 days'))]);
        $lote->execute([$this->id_insumo, 'TSTB-' . $u, 20, 20, 200, date('Y-m-d H:i:s', strtotime('-10 days'))]);

        $this->pdo->prepare("INSERT INTO categoria_precio (nombre, precio_unitario, activo) VALUES (?, 500, 1)")
            ->execute(['Cat PHPUnit ' . $u]);
        $this->id_cat = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        if ($this->pdo === null) {
            return;
        }
        // Orden inverso a las dependencias; cada paso es tolerante a fallos
        $pasos = [
            ["DELETE FROM consumo_lote WHERE id_produccion = ?", $this->ids_produccion],
            ["DELETE FROM produccion_precio WHERE id_produccion = ?", $this->ids_produccion],
            ["DELETE FROM produccion WHERE id_produccion = ?", $this->ids_produccion],
        ];
        foreach ($pasos as [$sql, $ids]) {
            foreach ($ids as $id) {
                try { $this->pdo->prepare($sql)->execute([$id]); } catch (Throwable $e) {}
            }
        }
        $limpieza = [
            ["DELETE FROM lote WHERE id_insumo = ?", $this->id_insumo], // incluye lotes sintéticos EST-
            ["DELETE FROM receta_ingrediente WHERE id_receta = ?", $this->id_receta],
            ["DELETE FROM receta WHERE id_receta = ?", $this->id_receta],
            ["DELETE FROM insumo WHERE id_insumo = ?", $this->id_insumo],
            ["DELETE FROM producto WHERE id_producto = ?", $this->id_producto],
            ["DELETE FROM categoria_precio WHERE id_categoria = ?", $this->id_cat],
            ["DELETE FROM usuario WHERE id_usuario = ?", $this->id_usuario],
        ];
        foreach ($limpieza as [$sql, $id]) {
            if ($id > 0) {
                try { $this->pdo->prepare($sql)->execute([$id]); } catch (Throwable $e) {}
            }
        }
    }

    private function ingredientes(): array
    {
        return $this->model->getIngredientesReceta($this->id_receta);
    }

    public function testProduccionConsumeFifoYCalculaElCostoReal(): void
    {
        // 2 kg × 6 tandas = 12 kg: 10 del lote viejo ($100) + 2 del nuevo ($200)
        $r = $this->model->registrarProduccionConConsumos(
            $this->id_producto, $this->id_receta, $this->id_usuario,
            6, 60, date('Y-m-d H:i:s'), 'PHPUnit',
            $this->ingredientes(), [$this->id_cat => 60]
        );
        $this->ids_produccion[] = (int) $r['id_produccion'];

        $this->assertTrue($r['ok']);
        $this->assertSame(1400.0, $r['costo_total'], '10×$100 + 2×$200 = $1.400');
        $this->assertSame(round(1400 / 60, 4), $r['costo_unitario']);
        $this->assertSame(60, $r['unidades']);

        // Lote viejo agotado, lote nuevo con 18 kg
        $lotes = $this->pdo->prepare("SELECT cantidad_disponible, estado FROM lote WHERE id_insumo = ? ORDER BY fecha_ingreso");
        $lotes->execute([$this->id_insumo]);
        $filas = $lotes->fetchAll();
        $this->assertSame(0.0, (float) $filas[0]['cantidad_disponible']);
        $this->assertSame('agotado', $filas[0]['estado']);
        $this->assertSame(18.0, (float) $filas[1]['cantidad_disponible']);
        $this->assertSame('activo', $filas[1]['estado']);

        // Consumos trazados con su costo por lote
        $consumos = $this->pdo->prepare("SELECT costo_consumo FROM consumo_lote WHERE id_produccion = ? ORDER BY costo_consumo DESC");
        $consumos->execute([$r['id_produccion']]);
        $this->assertSame([1000.0, 400.0], array_map('floatval', $consumos->fetchAll(PDO::FETCH_COLUMN)));

        // El stock físico del insumo bajó 12 kg
        $this->assertSame(18.0, $this->model->getInsumoStockActual($this->id_insumo));
    }

    public function testRemanenteSinLoteGeneraLoteSinteticoConUltimoPrecio(): void
    {
        // 2 kg × 20 tandas = 40 kg, pero solo hay 30 en lotes:
        // el remanente de 10 kg se costea al último precio conocido ($200)
        $r = $this->model->registrarProduccionConConsumos(
            $this->id_producto, $this->id_receta, $this->id_usuario,
            20, 200, date('Y-m-d H:i:s'), 'PHPUnit',
            $this->ingredientes(), [$this->id_cat => 200], true
        );
        $this->ids_produccion[] = (int) $r['id_produccion'];

        $this->assertTrue($r['ok']);
        $this->assertSame(7000.0, $r['costo_total'], '10×$100 + 20×$200 + 10×$200 = $7.000');

        // Lote sintético EST- creado, agotado y con el precio estimado
        $est = $this->pdo->prepare("SELECT cantidad_inicial, precio_unitario, estado FROM lote WHERE id_insumo = ? AND numero_lote LIKE 'EST-%'");
        $est->execute([$this->id_insumo]);
        $sintetico = $est->fetch();
        $this->assertNotFalse($sintetico, 'Debe existir el lote sintético EST-');
        $this->assertSame(10.0, (float) $sintetico['cantidad_inicial']);
        $this->assertSame(200.0, (float) $sintetico['precio_unitario']);
        $this->assertSame('agotado', $sintetico['estado']);

        // 3 consumos trazados y stock físico en 0 (nunca negativo)
        $n = $this->pdo->prepare("SELECT COUNT(*) FROM consumo_lote WHERE id_produccion = ?");
        $n->execute([$r['id_produccion']]);
        $this->assertSame(3, (int) $n->fetchColumn());
        $this->assertSame(0.0, $this->model->getInsumoStockActual($this->id_insumo));
    }
}
