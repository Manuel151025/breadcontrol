<?php
// helpers/FormularioCompra.php

/**
 * Lectura y validación de los formularios de compras: registrar una compra y
 * guardar un proveedor.
 *
 * Son funciones puras: reciben los campos del formulario (ya normalizados por
 * includes/entrada.php) y devuelven los valores listos para el modelo, o el
 * motivo por el que se rechazan. Así se prueban sin navegador ni base de datos
 * y el controlador se queda con la orquestación. Antes todo vivía dentro de
 * CompraController, y cada validación nueva lo hacía más difícil de leer.
 */
class FormularioCompra {
    /** Límites de una compra: frenan una tecla de más sin estorbar a ninguna compra real. */
    public const CANTIDAD_MAXIMA = 1000000;
    public const PRECIO_MAXIMO   = 100000000;
    public const BULTOS_MAXIMOS  = 10000;
    /** compra.total_pagado es DECIMAL(12,2). */
    public const TOTAL_MAXIMO    = 9999999999;

    public const TIPOS_ENTREGA = ['domicilio', 'recogida', 'visita'];
    public const DIAS_VISITA   = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];

    /**
     * @param array<mixed> $post
     * @return array{error: string, id_insumo: int, id_proveedor: int, fecha: string, cantidad: float, num_bultos: int, precio_bulto: float}
     */
    public static function compra(array $post, string $hoy): array {
        $fecha        = self::texto($post, 'fecha_compra') ?: $hoy;
        $cantidad     = numero_en_rango(self::texto($post, 'cantidad'), 0.001, self::CANTIDAD_MAXIMA);
        $precio_bulto = numero_en_rango(self::texto($post, 'precio_bulto'), 1, self::PRECIO_MAXIMO);
        $datos = [
            'error'        => '',
            'id_insumo'    => (int) self::texto($post, 'id_insumo'),
            'id_proveedor' => (int) self::texto($post, 'id_proveedor'),
            'fecha'        => $fecha,
            'cantidad'     => (float) $cantidad,
            'num_bultos'   => max(1, (int) (self::texto($post, 'num_bultos') ?: '1')),
            'precio_bulto' => (float) $precio_bulto,
        ];
        $datos['error'] = self::motivoRechazoCompra($datos, $cantidad !== null, $precio_bulto !== null, $hoy);
        return $datos;
    }

    /**
     * Primer problema de una compra, o '' si se puede registrar.
     *
     * Se comprueba la fecha elegida, no el día de hoy: un domingo se puede
     * registrar la compra del sábado, pero no fecharla en domingo.
     *
     * @param array{id_insumo: int, id_proveedor: int, fecha: string, num_bultos: int, precio_bulto: float} $c
     */
    private static function motivoRechazoCompra(array $c, bool $cantidad_ok, bool $precio_ok, string $hoy): string {
        $miles = fn(int $n): string => number_format($n, 0, ',', '.');
        $reglas = [
            [fn() => !fecha_valida($c['fecha']), 'La fecha de compra no es válida. Elígela en el calendario.'],
            [fn() => $c['fecha'] > $hoy, 'La fecha de compra no puede ser posterior a hoy.'],
            [fn() => esDomingo($c['fecha']), 'No se pueden registrar compras con fecha de domingo.'],
            [fn() => !$c['id_insumo'] || !$c['id_proveedor'], 'Elige el insumo y el proveedor.'],
            [fn() => !$cantidad_ok, 'La cantidad debe ser un número mayor que 0 y de máximo ' . $miles(self::CANTIDAD_MAXIMA) . '.'],
            [fn() => !$precio_ok, 'El precio debe ser un número mayor que 0 y de máximo $' . $miles(self::PRECIO_MAXIMO) . '.'],
            [fn() => $c['num_bultos'] > self::BULTOS_MAXIMOS, 'El número de bultos no puede pasar de ' . $miles(self::BULTOS_MAXIMOS) . '.'],
            [fn() => $c['precio_bulto'] * $c['num_bultos'] > self::TOTAL_MAXIMO, 'El total de la compra es demasiado grande. Revisa el precio y el número de bultos.'],
        ];
        return self::primerFallo($reglas);
    }

    /**
     * Campos de un proveedor. El nombre se devuelve ya pasado por limpiar(),
     * que es como se guarda; los errores no incluyen la regla de nombre único,
     * que necesita la base de datos y la resuelve el controlador.
     *
     * @param array<mixed> $post
     * @return array{errores: list<string>, nombre: string, telefono: string, entrega: string, dias: float, dias_visita: string|null}
     */
    public static function proveedor(array $post): array {
        $nombre   = limpiar(self::texto($post, 'nombre'));
        $telefono = substr(preg_replace('/\D/', '', self::texto($post, 'telefono')) ?? '', 0, 15);
        $entrega  = self::texto($post, 'tipo_entrega') ?: 'domicilio';
        $dias     = numero_en_rango(self::texto($post, 'dias_entrega_promedio') ?: '0', 0, 365);

        // Solo días de la lista: cualquier otra cosa no se guarda.
        $marcados    = is_array($post['dias_visita'] ?? null) ? array_filter($post['dias_visita'], 'is_string') : [];
        $elegidos    = array_values(array_intersect(self::DIAS_VISITA, array_map('trim', $marcados)));
        $dias_visita = $entrega === 'visita' ? implode(',', $elegidos) : null;

        $reglas = [
            [$nombre === '', 'El nombre es obligatorio.'],
            [!texto_cabe($nombre, 100), 'El nombre no puede superar los 100 caracteres.'],
            [!in_array($entrega, self::TIPOS_ENTREGA, true), 'Elige un tipo de entrega de la lista.'],
            [$dias === null, 'Los días de entrega deben ser un número entre 0 y 365.'],
            [$entrega === 'visita' && $dias_visita === '', 'Selecciona al menos un día de visita.'],
        ];
        $errores = array_values(array_map(fn($r) => $r[1], array_filter($reglas, fn($r) => $r[0])));

        return [
            'errores'     => $errores,
            'nombre'      => $nombre,
            'telefono'    => $telefono,
            'entrega'     => $entrega,
            'dias'        => (float) $dias,
            'dias_visita' => $dias_visita,
        ];
    }

    /**
     * Mensaje de la primera regla que falla, o ''. Las condiciones van en
     * funciones para no evaluar las siguientes: esDomingo() sobre una fecha
     * que ya se sabe inválida no tiene sentido.
     *
     * @param list<array{0: callable(): bool, 1: string}> $reglas
     */
    private static function primerFallo(array $reglas): string {
        foreach ($reglas as [$falla, $mensaje]) {
            if ($falla()) {
                return $mensaje;
            }
        }
        return '';
    }

    /** @param array<mixed> $post */
    private static function texto(array $post, string $clave): string {
        $valor = $post[$clave] ?? '';
        return is_string($valor) ? $valor : '';
    }
}
