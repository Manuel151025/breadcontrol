<?php
// tests/Integration/AjusteInventarioTest.php
// Ajuste manual de inventario (InventarioModel::registrarAjusteInventario).
//
// El caso que motivó la prueba: cuando un insumo no tiene lotes activos, el
// ajuste crea un lote AJU-<prefijo>-<fecha>-NNN, y el consecutivo era siempre
// 001. El segundo ajuste del día sobre ese insumo —o sobre otro con las mismas
// tres letras— chocaba con el índice único de numero_lote y no se guardaba.
//
// El método abre y confirma SU PROPIA transacción, así que no puede correr
// dentro del rollback de BaseDatosTestCase: los datos se crean de verdad y se
// eliminan en tearDown (siempre, incluso si la prueba falla).

use PHPUnit\Framework\TestCase;

final class AjusteInventarioTest extends TestCase
{
    private ?PDO $pdo = null;
    private InventarioModel $model;

    private int $id_usuario = 0;
    /** @var list<int> */
    private array $ids_insumo = [];

    protected function setUp(): void
    {
        try {
            $this->pdo = getConexion();
        } catch (Throwable $e) {
            $this->markTestSkipped('Base de datos no disponible: ' . $e->getMessage());
        }
        $this->model = new InventarioModel($this->pdo);

        $this->pdo->prepare("INSERT INTO usuario (nombre_usuario, nombre_completo, contrasena_hash, rol, activo) VALUES (?, 'Usuario Ajuste PHPUnit', 'hash', 'empleado', 1)")
            ->execute(['phpunit_aju_' . uniqid()]);
        $this->id_usuario = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        if ($this->pdo === null) {
            return;
        }
        foreach ($this->ids_insumo as $id) {
            foreach ([
                "DELETE FROM alerta WHERE modulo_origen = 'inventario' AND mensaje LIKE CONCAT('%', (SELECT nombre FROM insumo WHERE id_insumo = ?), '%')",
                "DELETE FROM ajuste_inventario WHERE id_insumo = ?",
                "DELETE FROM lote WHERE id_insumo = ?",
                "DELETE FROM insumo WHERE id_insumo = ?",
            ] as $sql) {
                try { $this->pdo->prepare($sql)->execute([$id]); } catch (Throwable $e) {}
            }
        }
        try {
            $this->pdo->prepare("DELETE FROM usuario WHERE id_usuario = ?")->execute([$this->id_usuario]);
        } catch (Throwable $e) {}
    }

    /** Insumo sin lotes, con un nombre cuyas tres primeras letras se controlan. */
    private function crearInsumo(string $nombre): int
    {
        $this->pdo->prepare("INSERT INTO insumo (nombre, unidad_medida, es_harina, stock_actual, punto_reposicion) VALUES (?, 'kg', 0, 0, 0)")
            ->execute([$nombre]);
        $id = (int) $this->pdo->lastInsertId();
        $this->ids_insumo[] = $id;
        return $id;
    }

    /** @return list<string> */
    private function lotesDe(int $id_insumo): array
    {
        $stmt = $this->pdo->prepare("SELECT numero_lote FROM lote WHERE id_insumo = ? ORDER BY numero_lote");
        $stmt->execute([$id_insumo]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function testDosAjustesDelMismoDiaSobreInsumosConElMismoPrefijoNoChocan(): void
    {
        // «Qzx…»: prefijo QZX, que ningún insumo real de la base comparte.
        $a = $this->crearInsumo('Qzxúcar PHPUnit ' . uniqid());
        $b = $this->crearInsumo('Qzxúcar morena PHPUnit ' . uniqid());

        $this->model->registrarAjusteInventario($a, $this->id_usuario, 5, 'Conteo físico');
        $this->model->registrarAjusteInventario($b, $this->id_usuario, 3, 'Conteo físico');

        $hoy = date('Y-m-d');
        $this->assertSame(['AJU-QZX-' . $hoy . '-001'], $this->lotesDe($a));
        $this->assertSame(['AJU-QZX-' . $hoy . '-002'], $this->lotesDe($b));
    }

    public function testUnSegundoAjusteTrasAgotarElLoteCreaOtroConElSiguienteNumero(): void
    {
        $id  = $this->crearInsumo('Qzxharina PHPUnit ' . uniqid());
        $hoy = date('Y-m-d');

        $this->model->registrarAjusteInventario($id, $this->id_usuario, 4, 'Llegó sin factura');
        // A cero: el lote se agota y el insumo se queda sin lotes activos.
        $this->model->registrarAjusteInventario($id, $this->id_usuario, 0, 'Se dañó');
        // Otra vez hay existencias: antes este paso fallaba con «Duplicate entry».
        $this->model->registrarAjusteInventario($id, $this->id_usuario, 2, 'Apareció en bodega');

        $this->assertSame(['AJU-QZX-' . $hoy . '-001', 'AJU-QZX-' . $hoy . '-002'], $this->lotesDe($id));
    }
}
