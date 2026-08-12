<?php
// tests/Integration/ReportePortalTest.php
// Regresion del reporte agrupado por aprendiz del portal.
//
// Este reporte fallo en produccion con "SQLSTATE[42000] ... only_full_group_by":
// el SELECT arrastraba columnas del pedido que no estaban en el GROUP BY. MariaDB
// (desarrollo) lo aceptaba y devolvia un valor arbitrario en silencio; MySQL 8
// (produccion) rechaza la consulta entera y la pantalla de detalle del pedido
// respondia con "error inesperado".
//
// La prueba ejecuta la consulta de verdad: en el CI, que corre contra MySQL 8 con
// sql_mode por defecto, cualquier reaparicion del patron vuelve a fallar aqui.

final class ReportePortalTest extends BaseDatosTestCase
{
    private const FECHA = '2031-05-20';

    private PortalClienteModel $model;
    private int $id_tienda;
    private int $id_aprendiz;
    private int $id_variedad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new PortalClienteModel($this->pdo);
        $sufijo = bin2hex(random_bytes(4));

        $stmt = $this->pdo->prepare("INSERT INTO cliente (nombre, tipo, activo) VALUES (?, 'tienda', 1)");
        $stmt->execute(['Tienda reporte ' . $sufijo]);
        $this->id_tienda = (int) $this->pdo->lastInsertId();

        $stmt->execute(['Aprendiz reporte ' . $sufijo]);
        $this->id_aprendiz = (int) $this->pdo->lastInsertId();

        $cat = $this->pdo->query("SELECT id_categoria FROM categoria_precio LIMIT 1")->fetchColumn();
        $stmt = $this->pdo->prepare(
            "INSERT INTO variedad_pan (nombre, id_categoria_precio, activo) VALUES (?, ?, 1)"
        );
        $stmt->execute(['Pan de prueba ' . $sufijo, $cat]);
        $this->id_variedad = (int) $this->pdo->lastInsertId();
    }

    /**
     * Crea un pedido de la tienda, creado por el aprendiz, con una linea de detalle.
     */
    private function crearPedido(int $cantidad, int $napa, int $bonificacion): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO pedido_cliente (id_cliente, id_creador, fecha_entrega, total_estimado, estado)
             VALUES (?, ?, ?, ?, 'pendiente')"
        );
        $stmt->execute([$this->id_tienda, $this->id_aprendiz, self::FECHA, 1000 * $cantidad]);
        $id_pedido = (int) $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare(
            "INSERT INTO pedido_cliente_detalle (id_pedido, id_variedad, cantidad, napa, bonificacion, precio_unitario)
             VALUES (?, ?, ?, ?, ?, 1000)"
        );
        $stmt->execute([$id_pedido, $this->id_variedad, $cantidad, $napa, $bonificacion]);
    }

    public function testLaConsultaSeEjecutaBajoOnlyFullGroupBy(): void
    {
        // Sin filas: lo que se prueba aqui es que el motor ACEPTE la consulta.
        // Con el SELECT anterior, MySQL 8 lanzaba PDOException antes de mirar datos.
        $reporte = $this->model->getReporteAgrupadoTienda($this->id_tienda, self::FECHA);

        $this->assertSame([], $reporte);
    }

    public function testAgrupaPorAprendizYProducto(): void
    {
        $this->crearPedido(cantidad: 10, napa: 1, bonificacion: 0);
        $this->crearPedido(cantidad: 5,  napa: 0, bonificacion: 2);

        $reporte = $this->model->getReporteAgrupadoTienda($this->id_tienda, self::FECHA);

        $nombres = array_keys($reporte);
        $this->assertCount(1, $nombres, 'Los dos pedidos son del mismo aprendiz: una sola clave');

        $filas = $reporte[$nombres[0]];
        $this->assertCount(1, $filas, 'Misma variedad en ambos pedidos: una sola fila');

        // Los dos pedidos se suman: es lo que significa "agrupado por aprendiz y producto".
        $this->assertSame(15, (int) $filas[0]['cantidad']);
        $this->assertSame(1,  (int) $filas[0]['napa']);
        $this->assertSame(2,  (int) $filas[0]['bonificacion']);
    }

    public function testNoDevuelveColumnasDeUnPedidoConcreto(): void
    {
        // id_pedido y total_estimado no pueden viajar en un reporte agrupado por
        // aprendiz: con varios pedidos su valor seria arbitrario. Ningun consumidor
        // los usa, y su presencia era la causa del fallo en produccion.
        $this->crearPedido(cantidad: 3, napa: 0, bonificacion: 0);

        $reporte = $this->model->getReporteAgrupadoTienda($this->id_tienda, self::FECHA);
        $fila    = $reporte[array_key_first($reporte)][0];

        $this->assertArrayNotHasKey('id_pedido', $fila);
        $this->assertArrayNotHasKey('total_estimado', $fila);
    }
}
