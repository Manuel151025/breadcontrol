<?php
// tests/Unit/PurgaLogsTest.php
// La retención de registros: se conservan los recientes y se borran los viejos.
//
// Hay un archivo por día, así que ninguno crece sin control; lo que crecía sin
// control era su número. Y lo que no puede pasar bajo ningún concepto es que la
// limpieza se lleve por delante el .htaccess que impide leer los registros
// desde la web, ni un archivo reciente que haga falta para diagnosticar.
//
// Todo ocurre en un directorio temporal: las pruebas no tocan logs/ del proyecto.

use PHPUnit\Framework\TestCase;

final class PurgaLogsTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/purga_logs_' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    /** Crea un archivo con una antigüedad concreta en días. */
    private function archivo(string $nombre, int $diasDeAntiguedad): string
    {
        $ruta = $this->dir . '/' . $nombre;
        file_put_contents($ruta, 'contenido de prueba');
        touch($ruta, time() - ($diasDeAntiguedad * 86400));

        return $ruta;
    }

    public function testBorraLosRegistrosMasViejosQueElLimite(): void
    {
        $viejo = $this->archivo('app-2026-01-01.log', 90);

        $borrados = purgarLogsAntiguos($this->dir, 30);

        $this->assertSame(1, $borrados);
        $this->assertFileDoesNotExist($viejo);
    }

    public function testConservaLosRecientes(): void
    {
        $ayer = $this->archivo('app-2026-08-19.log', 1);
        $hace29 = $this->archivo('app-2026-07-22.log', 29);

        $borrados = purgarLogsAntiguos($this->dir, 30);

        $this->assertSame(0, $borrados, 'Nada de menos de 30 días debe desaparecer');
        $this->assertFileExists($ayer);
        $this->assertFileExists($hace29);
    }

    public function testAlcanzaTambienALosDePhp(): void
    {
        $this->archivo('php-error-2026-01-01.log', 90);
        $this->archivo('app-2026-01-02.log', 90);

        $this->assertSame(2, purgarLogsAntiguos($this->dir, 30));
    }

    /**
     * El .htaccess es lo único que impide leer los registros desde el navegador.
     * Borrarlo dejaría al descubierto trazas con rutas internas y datos del
     * sistema, así que la limpieza no debe tocarlo ni aunque sea antiquísimo.
     */
    public function testNoSeLlevaPorDelanteElHtaccess(): void
    {
        $guardian = $this->archivo('.htaccess', 400);

        purgarLogsAntiguos($this->dir, 30);

        $this->assertFileExists($guardian, 'El .htaccess protege los registros: es intocable');
    }

    public function testIgnoraArchivosQueNoSonRegistros(): void
    {
        $otro = $this->archivo('respaldo_importante.sql', 400);

        purgarLogsAntiguos($this->dir, 30);

        $this->assertFileExists($otro, 'Solo se borran app-*.log y php-error-*.log');
    }

    public function testUnDirectorioVacioNoRompeNada(): void
    {
        $this->assertSame(0, purgarLogsAntiguos($this->dir, 30));
    }

    public function testUnDirectorioInexistenteNoRompeNada(): void
    {
        // Si la carpeta no existe, registrar un error no puede provocar otro.
        $this->assertSame(0, purgarLogsAntiguos($this->dir . '/no_existe', 30));
    }
}
