<?php
// tests/Unit/ReglasPortalTest.php
// Pruebas de las reglas de negocio del Portal de Clientes contra la clase REAL
// de producción helpers/ReglasPortal.php (usada por los controladores de
// controllers/portal/, VentaController y PortalClienteModel): crédito/ñapa,
// límite de gestión de 48 h, bloqueo por pago del instructor, cupo semanal
// y horario de entrega.
//
// Los grupos de visibilidad/cartera y fecha mínima nocturna replican reglas
// que viven en SQL y en JS respectivamente; se documentan aquí como espejo.

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ReglasPortalTest extends TestCase
{
    // ============ GRUPO 1: Crédito (bonificación/ñapa) ============

    #[DataProvider('provideCredito')]
    public function testCalculoDeCredito(bool $es_tienda, float $gastado, float $esperado): void
    {
        $this->assertSame($esperado, ReglasPortal::calcularCredito($es_tienda, $gastado));
    }

    public static function provideCredito(): array
    {
        return [
            // Tienda: $1000 por cada $5000
            'tienda 5000'      => [true, 5000.0, 1000.0],
            'tienda 12000'     => [true, 12000.0, 2000.0],
            'tienda 4999'      => [true, 4999.0, 0.0],
            'tienda 54000'     => [true, 54000.0, 10000.0],
            // Mostrador: $500 por cada $5000
            'mostrador 5000'   => [false, 5000.0, 500.0],
            'mostrador 12000'  => [false, 12000.0, 1000.0],
            'mostrador 4999'   => [false, 4999.0, 0.0],
            'mostrador 54000'  => [false, 54000.0, 5000.0],
        ];
    }

    // ============ GRUPO 2: Límite de gestión de pedidos (48 h) ============

    public function testPermiteGestionarPedidoPendienteConMasDe48Horas(): void
    {
        $ahora = new DateTimeImmutable('2026-06-12 12:00:00');
        $this->assertTrue(ReglasPortal::puedeGestionarPedido('pendiente', '2026-06-15 12:00:00', $ahora));
    }

    public function testNoPermiteGestionarConMenosDe48Horas(): void
    {
        $ahora = new DateTimeImmutable('2026-06-12 12:00:00');
        $this->assertFalse(ReglasPortal::puedeGestionarPedido('pendiente', '2026-06-14 11:00:00', $ahora));
    }

    public function testNoPermiteGestionarPedidoVencido(): void
    {
        $ahora = new DateTimeImmutable('2026-06-12 12:00:00');
        $this->assertFalse(ReglasPortal::puedeGestionarPedido('pendiente', '2026-06-11 12:00:00', $ahora));
    }

    public function testNoPermiteGestionarPedidoYaConfirmado(): void
    {
        $ahora = new DateTimeImmutable('2026-06-12 12:00:00');
        $this->assertFalse(ReglasPortal::puedeGestionarPedido('confirmado', '2026-06-15 12:00:00', $ahora));
    }

    public function testDentroDeLimite48hSoloSiNoEstaVencido(): void
    {
        $ahora = new DateTimeImmutable('2026-06-12 12:00:00');

        // A 47 horas: dentro del límite (bloqueado para el cliente, la vista lo explica)
        $this->assertTrue(ReglasPortal::dentroDeLimite48h('2026-06-14 11:00:00', $ahora));
        // A 72 horas: fuera del límite (aún gestionable)
        $this->assertFalse(ReglasPortal::dentroDeLimite48h('2026-06-15 12:00:00', $ahora));
        // Vencido: NO cuenta como "dentro del límite" (es otro estado)
        $this->assertFalse(ReglasPortal::dentroDeLimite48h('2026-06-11 12:00:00', $ahora));
    }

    public function testFueraDeLimiteGestionIncluyeVencidosYMenosDe48h(): void
    {
        $ahora = new DateTimeImmutable('2026-06-12 12:00:00');

        $this->assertTrue(ReglasPortal::fueraDeLimiteGestion('2026-06-11 12:00:00', $ahora), 'Vencido');
        $this->assertTrue(ReglasPortal::fueraDeLimiteGestion('2026-06-14 11:00:00', $ahora), 'A 47 horas');
        $this->assertFalse(ReglasPortal::fueraDeLimiteGestion('2026-06-15 12:00:00', $ahora), 'A 72 horas');
    }

    // ============ GRUPO 2b: Pedidos sin fecha de entrega asignada ============
    //
    // Un aprendiz que pide para la cuenta ADSO no elige la fecha: la fija el
    // instructor al aprobar. Hasta entonces el pedido guarda la fecha centinela
    // 1000-01-01, que la regla de las 48 horas leia como "entrega vencida" y
    // bloqueaba la edicion justo cuando el aprendiz aun podia querer cambiarla.

    public function testReconoceLaEntregaSinDefinir(): void
    {
        $this->assertTrue(ReglasPortal::entregaSinDefinir('1000-01-01 00:00:00'));
        $this->assertFalse(ReglasPortal::entregaSinDefinir('2026-06-15 12:00:00'));
    }

    public function testPermiteGestionarUnPedidoQueAunNoTieneFecha(): void
    {
        $this->assertTrue(
            ReglasPortal::puedeGestionarPedido('pendiente', '1000-01-01 00:00:00'),
            'Mientras espera al instructor, el aprendiz debe poder editar o cancelar'
        );
    }

    public function testUnPedidoSinFechaNoCuentaComoProximoNiVencido(): void
    {
        $this->assertFalse(ReglasPortal::dentroDeLimite48h('1000-01-01 00:00:00'));
        $this->assertFalse(ReglasPortal::fueraDeLimiteGestion('1000-01-01 00:00:00'));
    }

    public function testUnPedidoSinFechaYaConfirmadoTampocoSeGestiona(): void
    {
        // La falta de fecha no debe saltarse la otra mitad de la regla: una vez
        // confirmado, el pedido deja de estar en manos del cliente.
        $this->assertFalse(ReglasPortal::puedeGestionarPedido('confirmado', '1000-01-01 00:00:00'));
    }

    // ============ GRUPO 3: Bloqueo por pago pendiente del instructor ============

    #[DataProvider('provideBloqueoPago')]
    public function testBloqueoDeEdicionPorPagoDelInstructor(bool $es_aprendiz, ?string $estado, bool $esperado): void
    {
        $this->assertSame($esperado, ReglasPortal::bloqueoPorPagoInstructor($es_aprendiz, $estado));
    }

    public static function provideBloqueoPago(): array
    {
        return [
            'cliente normal con pago pendiente' => [false, 'PENDING', false],
            'aprendiz con PENDING'              => [true, 'PENDING', true],
            'aprendiz con pendiente minúscula'  => [true, 'pendiente', true],
            'aprendiz con pago aprobado'        => [true, 'PAID', false],
            'aprendiz sin pago activo'          => [true, null, false],
            'aprendiz con estado vacío'         => [true, '', false],
        ];
    }

    // ============ GRUPO 4: Visibilidad y cartera (espejo de la regla en SQL) ============

    public function testPropietarioNoVePedidosDeAprendizSinAprobar(): void
    {
        $filtrados = array_values(array_filter(self::pedidosDeMuestra(), function ($p) {
            return !($p['es_aprendiz'] == 1 && $p['aprobado_instructor'] == 0);
        }));

        $this->assertCount(2, $filtrados);
        $this->assertSame(1, $filtrados[0]['id_pedido']);
        $this->assertSame(2, $filtrados[1]['id_pedido']);
    }

    public function testCarteraDelInstructorSoloSumaPedidosAprobados(): void
    {
        $cartera = 0.0;
        foreach (self::pedidosDeMuestra() as $p) {
            if ($p['aprobado_instructor'] == 1) {
                $cartera += (float) $p['monto'];
            }
        }

        $this->assertSame(13000.0, $cartera);
    }

    private static function pedidosDeMuestra(): array
    {
        return [
            ['id_pedido' => 1, 'es_aprendiz' => 0, 'aprobado_instructor' => 1, 'monto' => 5000.0],
            ['id_pedido' => 2, 'es_aprendiz' => 1, 'aprobado_instructor' => 1, 'monto' => 8000.0],
            ['id_pedido' => 3, 'es_aprendiz' => 1, 'aprobado_instructor' => 0, 'monto' => 6000.0],
        ];
    }

    // ============ GRUPO 5: Cupo de consumo semanal ============

    #[DataProvider('provideCupoConsumo')]
    public function testVerificacionDeExcesoDeCupo(float $acumulado, float $nuevo, float $cupo, bool $excede): void
    {
        $this->assertSame($excede, ReglasPortal::excedeCupoSemanal($acumulado, $nuevo, $cupo));
    }

    public static function provideCupoConsumo(): array
    {
        return [
            'dentro del cupo por defecto'      => [15000.0, 4000.0, 20000.0, false],
            'excede el cupo por defecto'       => [15000.0, 6000.0, 20000.0, true],
            'exactamente el cupo no excede'    => [15000.0, 5000.0, 20000.0, false],
            'dentro de cupo personalizado'     => [30000.0, 15000.0, 50000.0, false],
            'excede cupo personalizado'        => [45000.0, 10000.0, 50000.0, true],
        ];
    }

    #[DataProvider('provideBordesCupo')]
    public function testValidacionDeBordesDelCupo(float $cupo, bool $valido): void
    {
        $error = ReglasPortal::validarCupoSemanal($cupo);
        if ($valido) {
            $this->assertNull($error, "Cupo $cupo debería ser válido");
        } else {
            $this->assertNotNull($error, "Cupo $cupo debería ser rechazado");
        }
    }

    public static function provideBordesCupo(): array
    {
        return [
            'cero'                    => [0.0, true],
            'mínimo múltiplo'         => [500.0, true],
            'intermedio'              => [50000.0, true],
            'máximo permitido'        => [100000.0, true],
            'mayor al máximo'         => [100500.0, false],
            'negativo'                => [-500.0, false],
            'no múltiplo de 500'      => [450.0, false],
            'con decimales'           => [100000.5, false],
        ];
    }

    public function testMensajesDeErrorDelCupo(): void
    {
        $this->assertSame(
            'El cupo semanal debe estar entre $0 y $100.000 COP.',
            ReglasPortal::validarCupoSemanal(150000.0)
        );
        $this->assertSame(
            'El cupo semanal debe ser múltiplo de $500 COP.',
            ReglasPortal::validarCupoSemanal(450.0)
        );
    }

    // ============ GRUPO 7: Horario de entrega ============

    #[DataProvider('provideHorarios')]
    public function testValidacionDeHorarioDeEntrega(string $hora, bool $valido): void
    {
        $this->assertSame($valido, ReglasPortal::horarioEntregaValido($hora));
    }

    public static function provideHorarios(): array
    {
        return [
            'apertura 7:00'    => ['07:00', true],
            'medio día'        => ['12:30', true],
            'cierre 20:00'     => ['20:00', true],
            'antes de abrir'   => ['06:59', false],
            'después de cerrar'=> ['20:01', false],
            'noche'            => ['22:00', false],
        ];
    }

    // ============ GRUPO 8: Fecha mínima nocturna (espejo de la regla en JS) ============

    public function testPedidoNocturnoRestringeEntregaAlDiaSiguiente(): void
    {
        // Regla implementada en las vistas JS (nuevo_pedido.php): pedir después
        // de las 20:00 obliga a entregar a partir de mañana.
        $minFecha = function (string $hora_pedido): string {
            $hora_int = (int) explode(':', $hora_pedido)[0];
            return $hora_int >= 20 ? date('Y-m-d', strtotime('+1 day')) : date('Y-m-d');
        };

        $hoy = date('Y-m-d');
        $manana = date('Y-m-d', strtotime('+1 day'));

        $this->assertSame($hoy, $minFecha('10:00'));
        $this->assertSame($hoy, $minFecha('19:59'));
        $this->assertSame($manana, $minFecha('20:00'));
        $this->assertSame($manana, $minFecha('22:30'));
    }
}
