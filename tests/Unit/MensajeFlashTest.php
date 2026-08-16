<?php
// tests/Unit/MensajeFlashTest.php
// El aviso que deja redirigir() debe llegar a la pantalla, y una sola vez.
//
// Durante mucho tiempo mostrarMensaje() existía pero ninguna vista la llamaba:
// los avisos ("Insumo creado", "Proveedor desactivado") se guardaban en la
// sesión y se descartaban sin mostrarse. Ahora la invoca el layout, así que
// estas pruebas fijan el contrato del que depende.

use PHPUnit\Framework\TestCase;

final class MensajeFlashTest extends TestCase
{
    protected function setUp(): void
    {
        unset($_SESSION['mensaje_tipo'], $_SESSION['mensaje_texto']);
    }

    public function testSinAvisoPendienteNoSeImprimeNada(): void
    {
        $this->assertSame('', mostrarMensaje());
    }

    public function testElAvisoSeMuestraYSeConsume(): void
    {
        $_SESSION['mensaje_tipo']  = 'exito';
        $_SESSION['mensaje_texto'] = 'Insumo <strong>Harina</strong> creado.';

        $salida = mostrarMensaje();

        $this->assertStringContainsString('Insumo <strong>Harina</strong> creado.', $salida);
        $this->assertStringContainsString('role="status"', $salida, 'Los lectores de pantalla deben anunciarlo');

        // Al recargar la pantalla el aviso ya no debe repetirse.
        $this->assertSame('', mostrarMensaje(), 'El aviso debe consumirse al leerlo');
        $this->assertArrayNotHasKey('mensaje_texto', $_SESSION);
    }

    public function testCadaTipoSeDistingueVisualmente(): void
    {
        $colores = [];
        foreach (['exito', 'error', 'alerta'] as $tipo) {
            $_SESSION['mensaje_tipo']  = $tipo;
            $_SESSION['mensaje_texto'] = 'Mensaje de prueba';
            $colores[$tipo] = mostrarMensaje();
        }

        $this->assertNotSame($colores['exito'], $colores['error'], 'Un error no puede verse igual que un éxito');
        $this->assertNotSame($colores['exito'], $colores['alerta']);
    }

    public function testUnTipoDesconocidoNoRompeLaPantalla(): void
    {
        $_SESSION['mensaje_tipo']  = 'inventado';
        $_SESSION['mensaje_texto'] = 'Mensaje de prueba';

        $this->assertStringContainsString('Mensaje de prueba', mostrarMensaje());
    }

    public function testUnAvisoVacioNoDejaUnRecuadroHuerfano(): void
    {
        $_SESSION['mensaje_tipo']  = 'exito';
        $_SESSION['mensaje_texto'] = '   ';

        $this->assertSame('', mostrarMensaje());
    }
}
