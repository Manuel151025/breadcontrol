<?php
// tests/Integration/RecetaModelTest.php
// Guardado de los ingredientes de una receta.
//
// Existe por un fallo real en produccion: el INSERT omitia la columna `unidad`
// (NOT NULL y sin valor por defecto). Con el modo estricto de MySQL —el que corre
// en el servidor— la fila se rechazaba con el error 1364, y como el borrado de los
// ingredientes anteriores no estaba en la misma transaccion, la receta se quedaba
// VACIA. En un XAMPP sin modo estricto no se veia nada.
//
// Por eso estas pruebas fijan el modo estricto en la sesion: sin el, una base laxa
// aceptaria la fila incompleta y la prueba pasaria sin demostrar nada.

final class RecetaModelTest extends BaseDatosTestCase
{
    private RecetaModel $model;
    private int $id_receta;
    private int $id_harina;
    private int $id_sal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        $this->model = new RecetaModel($this->pdo);

        $id_usuario = (int) $this->pdo->query("SELECT id_usuario FROM usuario ORDER BY id_usuario LIMIT 1")->fetchColumn();
        if ($id_usuario === 0) {
            $this->markTestSkipped('La base no tiene ningun usuario.');
        }

        $this->id_harina = $this->crearInsumo('Harina de prueba', 'kg');
        $this->id_sal    = $this->crearInsumo('Sal de prueba', 'g');

        $stmt = $this->pdo->prepare("
            INSERT INTO producto (nombre, categoria, unidad_produccion, cantidad_por_tanda, precio_venta, activo, fecha_creacion)
            VALUES (?, 'sal', 'unidad', 100, 500, 1, NOW())
        ");
        $stmt->execute(['Pan de prueba ' . uniqid()]);
        $this->id_receta = $this->model->crearReceta((int) $this->pdo->lastInsertId(), $id_usuario);
    }

    private function crearInsumo(string $nombre, string $unidad): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO insumo (nombre, unidad_medida, es_harina, stock_actual, punto_reposicion, consumo_promedio_diario, activo, fecha_creacion)
            VALUES (?, ?, 0, 0, 0, 0, 1, NOW())
        ");
        $stmt->execute([$nombre . ' ' . uniqid(), $unidad]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    private function leerIngredientes(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id_insumo, cantidad, unidad, aplica_merma, notas
            FROM receta_ingrediente WHERE id_receta = ? ORDER BY id_receta_ing
        ");
        $stmt->execute([$this->id_receta]);
        return $stmt->fetchAll();
    }

    public function testGuardaLaUnidadDeCadaInsumo(): void
    {
        $this->model->guardarIngredientesReceta($this->id_receta, [
            ['id_insumo' => $this->id_harina, 'cantidad' => 1.5,  'unidad' => 'kg', 'aplica_merma' => 1, 'notas' => null],
            ['id_insumo' => $this->id_sal,    'cantidad' => 30.0, 'unidad' => 'g',  'aplica_merma' => 0, 'notas' => 'fina'],
        ]);

        $filas = $this->leerIngredientes();
        $this->assertCount(2, $filas);
        $this->assertSame('kg', $filas[0]['unidad'], 'La unidad es obligatoria en la base');
        $this->assertSame(1.5, (float) $filas[0]['cantidad']);
        $this->assertSame(1, (int) $filas[0]['aplica_merma']);
        $this->assertSame('g', $filas[1]['unidad']);
        $this->assertSame('fina', $filas[1]['notas']);
    }

    public function testReemplazaLosIngredientesAnteriores(): void
    {
        $this->model->guardarIngredientesReceta($this->id_receta, [
            ['id_insumo' => $this->id_harina, 'cantidad' => 1.0, 'unidad' => 'kg', 'aplica_merma' => 1, 'notas' => null],
        ]);
        $this->model->guardarIngredientesReceta($this->id_receta, [
            ['id_insumo' => $this->id_sal, 'cantidad' => 20.0, 'unidad' => 'g', 'aplica_merma' => 0, 'notas' => null],
        ]);

        $filas = $this->leerIngredientes();
        $this->assertCount(1, $filas, 'Guardar reemplaza, no acumula');
        $this->assertSame($this->id_sal, (int) $filas[0]['id_insumo']);
    }

    public function testSiFallaUnIngredienteLaRecetaConservaLosQueTenia(): void
    {
        // El fallo que dejo una receta vacia en produccion: el borrado se hacia
        // antes de las inserciones y sin transaccion, asi que un INSERT rechazado
        // se llevaba por delante la receta entera.
        $this->model->guardarIngredientesReceta($this->id_receta, [
            ['id_insumo' => $this->id_harina, 'cantidad' => 1.5, 'unidad' => 'kg', 'aplica_merma' => 1, 'notas' => null],
        ]);

        $lanzo = false;
        try {
            $this->model->guardarIngredientesReceta($this->id_receta, [
                ['id_insumo' => $this->id_sal, 'cantidad' => 10.0, 'unidad' => 'g', 'aplica_merma' => 0, 'notas' => null],
                ['id_insumo' => 999999999,     'cantidad' => 10.0, 'unidad' => 'g', 'aplica_merma' => 0, 'notas' => null],
            ]);
        } catch (Throwable $e) {
            $lanzo = true;
        }

        $this->assertTrue($lanzo, 'Un insumo inexistente tiene que fallar');

        $filas = $this->leerIngredientes();
        $this->assertCount(1, $filas, 'La receta no puede quedarse vacia porque falle un ingrediente');
        $this->assertSame($this->id_harina, (int) $filas[0]['id_insumo']);
    }
}
