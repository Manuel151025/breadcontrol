<?php
// helpers/Seguridad.php

/**
 * Reglas de seguridad transversales al back-office y al Portal de Clientes.
 *
 * Fuente única de tres cosas que antes estaban dispersas o resueltas de forma
 * distinta en cada pantalla:
 *
 *  1. La política de contraseña. Había cuatro longitudes mínimas distintas
 *     (4, 4, 6 y 6) copiadas a mano en registro, recuperación, perfil del
 *     portal y cambio de clave del propietario.
 *  2. El tratamiento del código de recuperación por correo, que se guardaba
 *     en texto plano y se comparaba con `!==`, a diferencia del PIN, que sí
 *     usaba `password_hash`/`password_verify`.
 *  3. La ventana y el umbral del control de intentos de inicio de sesión
 *     (ver IntentoLoginModel, que persiste los intentos).
 *
 * Son funciones puras (sin base de datos ni sesión) y se prueban en
 * tests/Unit/SeguridadTest.php.
 */
class Seguridad {

    /** Longitud mínima de cualquier contraseña del sistema. */
    public const CONTRASENA_MIN = 8;

    /** Intentos fallidos por cuenta antes de bloquear temporalmente. */
    public const LOGIN_MAX_INTENTOS = 5;

    /**
     * Intentos fallidos por dirección IP antes de bloquearla, contra un
     * atacante que rota nombres de usuario. Es más alto que el umbral por
     * cuenta a propósito: varias personas legítimas pueden compartir una
     * misma IP de salida.
     */
    public const LOGIN_MAX_INTENTOS_IP = 20;

    /** Ventana en minutos sobre la que se cuentan los intentos fallidos. */
    public const LOGIN_VENTANA_MINUTOS = 15;

    /**
     * Valida una contraseña contra la política única del sistema.
     *
     * @return string|null Mensaje de error listo para mostrar, o null si es válida.
     */
    public static function validarContrasena(string $contrasena): ?string {
        if (strlen($contrasena) < self::CONTRASENA_MIN) {
            return 'La contraseña debe tener al menos ' . self::CONTRASENA_MIN . ' caracteres.';
        }
        if (!preg_match('/[a-zA-Z]/', $contrasena) || !preg_match('/\d/', $contrasena)) {
            return 'La contraseña debe incluir al menos una letra y un número.';
        }
        return null;
    }

    /**
     * Hash del código de recuperación enviado por correo. Se guarda hasheado
     * igual que el PIN: si la base se filtrara, los códigos vigentes no
     * quedarían legibles.
     */
    public static function hashCodigoRecuperacion(string $codigo): string {
        return password_hash($codigo, PASSWORD_BCRYPT);
    }

    /**
     * Verifica un código de recuperación contra el hash guardado.
     *
     * El hash llega directo de una fila de la base (tipo `mixed` para el
     * análisis estático) y puede ser NULL cuando la cuenta no tiene ningún
     * código pendiente: ambos casos devuelven false aquí, para que ningún
     * llamador tenga que recordar comprobarlo por separado.
     */
    public static function verificarCodigoRecuperacion(string $codigo, mixed $hash): bool {
        if (!is_string($hash) || $hash === '' || $codigo === '') {
            return false;
        }
        return password_verify($codigo, $hash);
    }
}
