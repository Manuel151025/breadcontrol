<?php
// tests/Integration/CierreModelTest.php
// Pruebas de los agregados del cierre del día y del guardado/upsert del cierre.
// Aislamiento por FECHA FUTURA (2030): los datos reales de la base nunca
// interfieren en las sumas, y todo corre dentro del rollback de la base.

final class CierreModelTest extends BaseDatosTestCase
{
    private CierreModel $model;
    private int $id_usuario = 0;
    private const FECHA = '2030-06-15';

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new CierreModel($this->pdo);

        $this->pdo->prepare("INSERT INTO usuario (nombre_usuario, nombre_completo, contrasena_hash, rol, activo) VALUES (?, 'Usuario Cierre PHPUnit', 'hash', 'propietario', 1)")
            ->execute(['phpunit_cierre_' . uniqid()]);
        $this->id_usuario = (int) $this->pdo->lastInsertId();
    }

    private function venta(string $tipo, float $total, string $fecha_hora): void
    {
        $this->pdo->prepare("INSERT INTO venta (tipo_salida, id_usuario, fecha_hora, unidades_vendidas, precio_unitario, total_venta) VALUES (?, ?, ?, 1, ?, ?)")
            ->execute([$tipo, $this->id_usuario, $fecha_hora, $total, $total]);
    }

    public function testTotalYNumeroDeVentasDelDiaExcluyeBonificaciones(): void
    {
        $this->venta('venta', 5000, self::FECHA . ' 09:00:00');
        $this->venta('venta', 3000, self::FECHA . ' 15:30:00');
        $this->venta('bonificacion', 999, self::FECHA . ' 10:00:00'); // pan regalado: no es ingreso
        $this->venta('venta', 7777, '2030-06-20 09:00:00');           // otro día

        $this->assertSame(8000.0, $this->model->getTotalVentasHoy(self::FECHA));
        $this->assertSame(2, $this->model->getNumVentasHoy(self::FECHA));
    }

    public function testVentasDeAyerParaElComparativo(): void
    {
        $this->venta('venta', 4500, '2030-06-14 12:00:00');
        $this->assertSame(4500.0, $this->model->getVentasAyer(self::FECHA));
    }

    public function testTotalesDeComprasDelDia(): void
    {
        $u = uniqid();
        $this->pdo->prepare("INSERT INTO insumo (nombre, unidad_medida, stock_actual) VALUES (?, 'kg', 0)")
            ->execute(['Insumo Cierre PHPUnit ' . $u]);
        $id_insumo = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO proveedor (nombre) VALUES (?)")->execute(['Proveedor Cierre PHPUnit ' . $u]);
        $id_prov = (int) $this->pdo->lastInsertId();

        $compra = $this->pdo->prepare("INSERT INTO compra (id_insumo, id_proveedor, id_usuario, cantidad, precio_unitario, total_pagado, fecha_compra) VALUES (?, ?, ?, 10, 100, ?, ?)");
        $compra->execute([$id_insumo, $id_prov, $this->id_usuario, 180000, self::FECHA . ' 08:00:00']);
        $compra->execute([$id_insumo, $id_prov, $this->id_usuario, 20000, self::FECHA . ' 11:00:00']);
        $compra->execute([$id_insumo, $id_prov, $this->id_usuario, 55555, '2030-06-16 08:00:00']); // otro día

        $this->assertSame(200000.0, $this->model->getTotalComprasHoy(self::FECHA));
        $this->assertSame(2, $this->model->getNumComprasHoy(self::FECHA));
    }

    public function testTotalDeGastosDelDia(): void
    {
        $gasto = $this->pdo->prepare("INSERT INTO gasto (id_usuario, categoria, descripcion, valor, fecha_gasto) VALUES (?, 'servicio', 'Gasto PHPUnit', ?, ?)");
        $gasto->execute([$this->id_usuario, 12000, self::FECHA . ' 10:00:00']);
        $gasto->execute([$this->id_usuario, 8000, self::FECHA . ' 17:00:00']);
        $gasto->execute([$this->id_usuario, 999, '2030-06-16 10:00:00']); // otro día

        $this->assertSame(20000.0, $this->model->getTotalGastosHoy(self::FECHA));
    }

    public function testCostoDeProduccionDelDiaSaleDelConsumoDeLotes(): void
    {
        $u = uniqid();
        $this->pdo->prepare("INSERT INTO producto (nombre, categoria, precio_venta, unidad_produccion, cantidad_por_tanda) VALUES (?, 'sal', 500, 'carro', 10)")
            ->execute(['Producto Cierre PHPUnit ' . $u]);
        $id_prod = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO receta (id_producto, id_usuario, version, es_vigente) VALUES (?, ?, 1, 1)")
            ->execute([$id_prod, $this->id_usuario]);
        $id_receta = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO insumo (nombre, unidad_medida, stock_actual) VALUES (?, 'kg', 0)")
            ->execute(['Insumo Prod Cierre PHPUnit ' . $u]);
        $id_insumo = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO lote (id_insumo, numero_lote, cantidad_inicial, cantidad_disponible, precio_unitario, fecha_ingreso, estado) VALUES (?, ?, 10, 0, 100, ?, 'agotado')")
            ->execute([$id_insumo, 'ZCT-' . $u, self::FECHA . ' 06:00:00']);
        $id_lote = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO produccion (id_producto, id_receta, id_usuario, cantidad_tandas, fecha_produccion, unidades_producidas, costo_total, costo_unitario) VALUES (?, ?, ?, 1, ?, 100, 0, 0)")
            ->execute([$id_prod, $id_receta, $this->id_usuario, self::FECHA . ' 06:30:00']);
        $id_produccion = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO consumo_lote (id_produccion, id_lote, cantidad_consumida, cantidad_con_merma, costo_consumo) VALUES (?, ?, 10, 10, 1234.50)")
            ->execute([$id_produccion, $id_lote]);

        $this->assertSame(1234.5, $this->model->getCostoProduccionHoy(self::FECHA));
    }

    public function testGuardarCierreYActualizarloElMismoDia(): void
    {
        // Primer cierre del día
        $ok = $this->model->guardarCierre($this->id_usuario, self::FECHA, 100000, 20000, 30000, 70000, 50000, 'Producir más pan de sal');
        $this->assertTrue($ok);

        $c = $this->model->getCierreGuardado(self::FECHA);
        $this->assertNotNull($c);
        $this->assertSame(100000.0, (float) $c['total_ingresos']);
        $this->assertSame(50000.0, (float) $c['utilidad_neta']);
        $this->assertSame('Producir más pan de sal', $c['sugerencia_produccion']);

        // Re-cerrar el mismo día debe ACTUALIZAR, no duplicar (UNIQUE por fecha)
        $this->model->guardarCierre($this->id_usuario, self::FECHA, 120000, 20000, 30000, 90000, 70000, null);

        $c2 = $this->model->getCierreGuardado(self::FECHA);
        $this->assertSame(120000.0, (float) $c2['total_ingresos']);
        $this->assertSame(70000.0, (float) $c2['utilidad_neta']);

        $n = $this->pdo->prepare("SELECT COUNT(*) FROM cierre_dia WHERE fecha = ?");
        $n->execute([self::FECHA]);
        $this->assertSame(1, (int) $n->fetchColumn(), 'Debe existir un solo cierre por fecha');
    }

    public function testSinCierreGuardadoRetornaNull(): void
    {
        // Manejo de error: una fecha sin cierre no debe lanzar excepción
        $this->assertNull($this->model->getCierreGuardado('2030-12-31'));
    }
}
