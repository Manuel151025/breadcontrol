<?php
// tests/Integration/CompraRegistroTest.php
// Pruebas de registrarCompra: creación de compra + lote FIFO + merma de harina
// + historial de variación de precio.
//
// El método abre SU PROPIA transacción, así que no puede usar el rollback de
// BaseDatosTestCase: los datos se crean de verdad y se limpian en tearDown.

use PHPUnit\Framework\TestCase;

final class CompraRegistroTest extends TestCase
{
    private ?PDO $pdo = null;
    private CompraModel $model;

    private int $id_usuario = 0;
    private int $id_proveedor = 0;
    private int $id_harina = 0;
    private int $id_normal = 0;

    protected function setUp(): void
    {
        try {
            $this->pdo = getConexion();
        } catch (Throwable $e) {
            $this->markTestSkipped('Base de datos no disponible: ' . $e->getMessage());
        }
        $this->model = new CompraModel($this->pdo);

        $u = uniqid();
        $this->pdo->prepare("INSERT INTO usuario (nombre_usuario, nombre_completo, contrasena_hash, rol, activo) VALUES (?, 'Usuario Compra PHPUnit', 'hash', 'empleado', 1)")
            ->execute(['phpunit_compra_' . $u]);
        $this->id_usuario = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO proveedor (nombre, activo) VALUES (?, 1)")
            ->execute(['Proveedor Compra PHPUnit ' . $u]);
        $this->id_proveedor = (int) $this->pdo->lastInsertId();

        // Prefijos de lote improbables (ZQH/ZQN) para no chocar con insumos reales
        $this->pdo->prepare("INSERT INTO insumo (nombre, unidad_medida, es_harina, stock_actual, punto_reposicion) VALUES (?, 'kg', 1, 0, 1)")
            ->execute(['Zqh Harina PHPUnit ' . $u]);
        $this->id_harina = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO insumo (nombre, unidad_medida, es_harina, stock_actual, punto_reposicion) VALUES (?, 'unidad', 0, 0, 1)")
            ->execute(['Zqn Huevos PHPUnit ' . $u]);
        $this->id_normal = (int) $this->pdo->lastInsertId();
    }

    /**
     * Una fecha reciente que no caiga en domingo.
     *
     * La suite tiene que dar el mismo resultado cualquier día de la semana: si
     * usara `date()` a secas, los domingos toparía con la regla de negocio y
     * antes se resolvía saltándose las pruebas justo ese día.
     */
    private function fechaHabil(): string
    {
        $fecha = new DateTimeImmutable('today');
        while ($fecha->format('w') === '0') {
            $fecha = $fecha->modify('-1 day');
        }

        return $fecha->format('Y-m-d H:i:s');
    }

    protected function tearDown(): void
    {
        if ($this->pdo === null) {
            return;
        }
        foreach ([$this->id_harina, $this->id_normal] as $id_insumo) {
            if ($id_insumo > 0) {
                foreach ([
                    "DELETE FROM historial_precio WHERE id_insumo = ?",
                    "DELETE FROM lote WHERE id_insumo = ?",
                    "DELETE FROM compra WHERE id_insumo = ?",
                    "DELETE FROM insumo WHERE id_insumo = ?",
                ] as $sql) {
                    try { $this->pdo->prepare($sql)->execute([$id_insumo]); } catch (Throwable $e) {}
                }
            }
        }
        try { $this->pdo->prepare("DELETE FROM proveedor WHERE id_proveedor = ?")->execute([$this->id_proveedor]); } catch (Throwable $e) {}
        try { $this->pdo->prepare("DELETE FROM usuario WHERE id_usuario = ?")->execute([$this->id_usuario]); } catch (Throwable $e) {}
    }

    public function testCompraDeHarinaAplicaMermaDel6PorCiento(): void
    {
        // 100 kg en 2 bultos a $90.000 el bulto → $1.800/kg, $180.000 total
        $r = $this->model->registrarCompra($this->id_harina, $this->id_proveedor, $this->fechaHabil(), 100, 2, 90000, $this->id_usuario);

        $this->assertTrue($r['es_harina']);
        $this->assertSame(94.0, (float) $r['cantidad_disponible'], 'Harina: 100 kg - 6% de merma = 94 kg');
        $this->assertSame(0.0, (float) $r['variacion'], 'Primera compra: sin precio anterior, sin variación');
        $this->assertStringStartsWith('ZQH-' . date('Y-m-d'), $r['numero_lote']);

        // La compra quedó con el precio unitario y total correctos
        $stmt = $this->pdo->prepare("SELECT precio_unitario, total_pagado FROM compra WHERE id_compra = ?");
        $stmt->execute([$r['id_compra']]);
        $c = $stmt->fetch();
        $this->assertSame(1800.0, (float) $c['precio_unitario']);
        $this->assertSame(180000.0, (float) $c['total_pagado']);

        // El lote FIFO nace activo: entra completo pero disponible con merma
        $stmt = $this->pdo->prepare("SELECT cantidad_inicial, cantidad_disponible, precio_unitario, estado FROM lote WHERE id_compra = ?");
        $stmt->execute([$r['id_compra']]);
        $l = $stmt->fetch();
        $this->assertSame(100.0, (float) $l['cantidad_inicial']);
        $this->assertSame(94.0, (float) $l['cantidad_disponible']);
        $this->assertSame(1800.0, (float) $l['precio_unitario']);
        $this->assertSame('activo', $l['estado']);

        // El stock físico del insumo subió con la merma aplicada
        $stmt = $this->pdo->prepare("SELECT stock_actual FROM insumo WHERE id_insumo = ?");
        $stmt->execute([$this->id_harina]);
        $this->assertSame(94.0, (float) $stmt->fetchColumn());
    }

    public function testSegundaCompraMasCaraRegistraLaVariacionDePrecio(): void
    {
        $this->model->registrarCompra($this->id_harina, $this->id_proveedor, $this->fechaHabil(), 100, 2, 90000, $this->id_usuario);
        // Mismo volumen pero 10% más caro: $1.980/kg
        $r = $this->model->registrarCompra($this->id_harina, $this->id_proveedor, $this->fechaHabil(), 100, 2, 99000, $this->id_usuario);

        $this->assertSame(10.0, (float) $r['variacion'], '(1980-1800)/1800 = +10%');

        $stmt = $this->pdo->prepare("SELECT precio, variacion_pct FROM historial_precio WHERE id_compra = ?");
        $stmt->execute([$r['id_compra']]);
        $h = $stmt->fetch();
        $this->assertNotFalse($h, 'La variación debe quedar en el historial de precios');
        $this->assertSame(1980.0, (float) $h['precio']);
        $this->assertSame(10.0, (float) $h['variacion_pct']);
    }

    public function testInsumoSinMermaConservaLaCantidadCompleta(): void
    {
        // 50 unidades en 1 bulto a $40.000 → $800/unidad, sin merma
        $r = $this->model->registrarCompra($this->id_normal, $this->id_proveedor, $this->fechaHabil(), 50, 1, 40000, $this->id_usuario);

        $this->assertFalse($r['es_harina']);
        $this->assertSame(50.0, (float) $r['cantidad_disponible']);

        $stmt = $this->pdo->prepare("SELECT stock_actual FROM insumo WHERE id_insumo = ?");
        $stmt->execute([$this->id_normal]);
        $this->assertSame(50.0, (float) $stmt->fetchColumn());
    }

    public function testInsumoInexistenteLanzaExcepcion(): void
    {
        // Manejo de error: no debe crear nada ni fallar en silencio
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('El insumo seleccionado no existe.');
        $this->model->registrarCompra(99999999, $this->id_proveedor, $this->fechaHabil(), 10, 1, 1000, $this->id_usuario);
    }

    public function testUnaCompraFechadaEnDomingoSeRechaza(): void
    {
        // El proveedor no entrega los domingos.
        $domingo = (new DateTimeImmutable('last sunday'))->format('Y-m-d H:i:s');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No se pueden registrar compras con fecha de domingo.');
        $this->model->registrarCompra($this->id_normal, $this->id_proveedor, $domingo, 10, 1, 1000, $this->id_usuario);
    }

    /**
     * La regla mira la fecha de la compra, no el día en que se teclea.
     *
     * Antes preguntaba «¿hoy es domingo?», así que el domingo el sistema
     * rechazaba incluso la compra del sábado: había que esperar al lunes para
     * poder anotarla.
     */
    public function testUnDiaHabilPasadoSeRegistraAunqueHoySeaDomingo(): void
    {
        $sabado = (new DateTimeImmutable('last saturday'))->format('Y-m-d H:i:s');

        $r = $this->model->registrarCompra($this->id_normal, $this->id_proveedor, $sabado, 20, 1, 16000, $this->id_usuario);

        $this->assertGreaterThan(0, $r['id_compra']);

        $stmt = $this->pdo->prepare("SELECT DATE(fecha_compra) FROM compra WHERE id_compra = ?");
        $stmt->execute([$r['id_compra']]);
        $this->assertSame(
            (new DateTimeImmutable('last saturday'))->format('Y-m-d'),
            $stmt->fetchColumn(),
            'La compra debe quedar guardada con la fecha del sábado'
        );
    }
}
