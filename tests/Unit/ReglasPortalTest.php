<?php
// tests/Unit/ReglasPortalTest.php
// Reglas de negocio del Portal de Clientes (migradas del antiguo test_portal_rules.php):
// crédito/ñapa, límite de gestión de 48 h, bloqueo por pago del instructor,
// visibilidad de pedidos de aprendices, cupo semanal y horario de entrega.
//
// NOTA: estas reglas viven hoy dentro de PortalClienteController; los helpers
// privados de esta clase replican esa lógica 1:1. Al refactorizar el controlador
// (división por responsabilidades) estas pruebas deben apuntar a la clase extraída.

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ReglasPortalTest extends TestCase
{
    // ============ Helpers que replican las reglas del portal ============

    private static function calcularCredito(string $tipo_cliente, float $total_dinero): float
    {
        return ($tipo_cliente === 'tienda')
            ? floor($total_dinero / 5000) * 1000
            : floor($total_dinero / 5000) * 500;
    }

    private static function puedeGestionar(string $estado, string $fecha_entrega_str, string $ahora_str): bool
    {
        $fecha_entrega = new DateTime($fecha_entrega_str);
        $ahora = new DateTime($ahora_str);
        $diff = $ahora->diff($fecha_entrega);
        $horas_restantes = ($diff->days * 24) + $diff->h;
        $esta_vencido = $diff->invert == 1;
        $dentro_limite = (!$esta_vencido && $horas_restantes < 48);
        return ($estado === 'pendiente' && !$esta_vencido && !$dentro_limite);
    }

    private static function bloqueoPorPagoInstructor(bool $es_aprendiz, ?string $estado_pago): bool
    {
        if (!$es_aprendiz) {
            return false;
        }
        return $estado_pago !== null && in_array(strtoupper($estado_pago), ['PENDING', 'PENDIENTE']);
    }

    private static function filtrarPedidosPropietario(array $pedidos): array
    {
        return array_values(array_filter($pedidos, function ($p) {
            return !($p['es_aprendiz'] == 1 && $p['aprobado_instructor'] == 0);
        }));
    }

    private static function carteraInstructor(array $pedidos): float
    {
        $total = 0.0;
        foreach ($pedidos as $p) {
            if ($p['aprobado_instructor'] == 1) {
                $total += (float) $p['monto'];
            }
        }
        return $total;
    }

    private static function excedeCupo(float $acumulado, float $nuevo, float $cupo): bool
    {
        return ($acumulado + $nuevo) > $cupo;
    }

    private static function cupoValido(float $cupo): bool
    {
        if ($cupo < 0 || $cupo > 100000) {
            return false;
        }
        $cupo_int = (int) $cupo;
        return $cupo_int % 500 === 0 && $cupo == $cupo_int;
    }

    private static function horarioValido(string $hora): bool
    {
        return ($hora >= '07:00' && $hora <= '20:00');
    }

    private static function minFechaEntrega(string $hora_pedido): string
    {
        $min_fecha = date('Y-m-d');
        $hora_int = (int) explode(':', $hora_pedido)[0];
        if ($hora_int >= 20) {
            $min_fecha = date('Y-m-d', strtotime('+1 day'));
        }
        return $min_fecha;
    }

    // ============ GRUPO 1: Crédito (bonificación/ñapa) ============

    #[DataProvider('provideCredito')]
    public function testCalculoDeCredito(string $tipo, float $gastado, float $esperado): void
    {
        $this->assertSame($esperado, self::calcularCredito($tipo, $gastado));
    }

    public static function provideCredito(): array
    {
        return [
            // Tienda: $1000 por cada $5000
            'tienda 5000'      => ['tienda', 5000.0, 1000.0],
            'tienda 12000'     => ['tienda', 12000.0, 2000.0],
            'tienda 4999'      => ['tienda', 4999.0, 0.0],
            'tienda 54000'     => ['tienda', 54000.0, 10000.0],
            // Mostrador: $500 por cada $5000
            'mostrador 5000'   => ['mostrador', 5000.0, 500.0],
            'mostrador 12000'  => ['mostrador', 12000.0, 1000.0],
            'mostrador 4999'   => ['mostrador', 4999.0, 0.0],
            'mostrador 54000'  => ['mostrador', 54000.0, 5000.0],
        ];
    }

    // ============ GRUPO 2: Límite de gestión de pedidos (48 h) ============

    public function testPermiteGestionarPedidoPendienteConMasDe48Horas(): void
    {
        $this->assertTrue(self::puedeGestionar('pendiente', '2026-06-15 12:00:00', '2026-06-12 12:00:00'));
    }

    public function testNoPermiteGestionarConMenosDe48Horas(): void
    {
        $this->assertFalse(self::puedeGestionar('pendiente', '2026-06-14 11:00:00', '2026-06-12 12:00:00'));
    }

    public function testNoPermiteGestionarPedidoVencido(): void
    {
        $this->assertFalse(self::puedeGestionar('pendiente', '2026-06-11 12:00:00', '2026-06-12 12:00:00'));
    }

    public function testNoPermiteGestionarPedidoYaConfirmado(): void
    {
        $this->assertFalse(self::puedeGestionar('confirmado', '2026-06-15 12:00:00', '2026-06-12 12:00:00'));
    }

    // ============ GRUPO 3: Bloqueo por pago pendiente del instructor ============

    #[DataProvider('provideBloqueoPago')]
    public function testBloqueoDeEdicionPorPagoDelInstructor(bool $es_aprendiz, ?string $estado, bool $esperado): void
    {
        $this->assertSame($esperado, self::bloqueoPorPagoInstructor($es_aprendiz, $estado));
    }

    public static function provideBloqueoPago(): array
    {
        return [
            'cliente normal con pago pendiente' => [false, 'PENDING', false],
            'aprendiz con PENDING'              => [true, 'PENDING', true],
            'aprendiz con pendiente minúscula'  => [true, 'pendiente', true],
            'aprendiz con pago aprobado'        => [true, 'PAID', false],
            'aprendiz sin pago activo'          => [true, null, false],
        ];
    }

    // ============ GRUPO 4: Visibilidad y cartera de pedidos de aprendices ============

    public function testPropietarioNoVePedidosDeAprendizSinAprobar(): void
    {
        $pedidos = self::pedidosDeMuestra();
        $filtrados = self::filtrarPedidosPropietario($pedidos);

        $this->assertCount(2, $filtrados);
        $this->assertSame(1, $filtrados[0]['id_pedido']);
        $this->assertSame(2, $filtrados[1]['id_pedido']);
    }

    public function testCarteraDelInstructorSoloSumaPedidosAprobados(): void
    {
        $this->assertSame(13000.0, self::carteraInstructor(self::pedidosDeMuestra()));
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
        $this->assertSame($excede, self::excedeCupo($acumulado, $nuevo, $cupo));
    }

    public static function provideCupoConsumo(): array
    {
        return [
            'dentro del cupo por defecto'      => [15000.0, 4000.0, 20000.0, false],
            'excede el cupo por defecto'       => [15000.0, 6000.0, 20000.0, true],
            'dentro de cupo personalizado'     => [30000.0, 15000.0, 50000.0, false],
            'excede cupo personalizado'        => [45000.0, 10000.0, 50000.0, true],
        ];
    }

    #[DataProvider('provideBordesCupo')]
    public function testValidacionDeBordesDelCupo(float $cupo, bool $valido): void
    {
        $this->assertSame($valido, self::cupoValido($cupo));
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

    // ============ GRUPO 7: Horario de entrega ============

    #[DataProvider('provideHorarios')]
    public function testValidacionDeHorarioDeEntrega(string $hora, bool $valido): void
    {
        $this->assertSame($valido, self::horarioValido($hora));
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

    public function testPedidoNocturnoRestringeEntregaAlDiaSiguiente(): void
    {
        $hoy = date('Y-m-d');
        $manana = date('Y-m-d', strtotime('+1 day'));

        $this->assertSame($hoy, self::minFechaEntrega('10:00'));
        $this->assertSame($hoy, self::minFechaEntrega('19:59'));
        $this->assertSame($manana, self::minFechaEntrega('20:00'));
        $this->assertSame($manana, self::minFechaEntrega('22:30'));
    }
}
