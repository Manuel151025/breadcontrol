<?php
// tests/Unit/AutorizacionControladoresTest.php
//
// Inventario ejecutable de la autorizacion de TODOS los controladores.
//
// Por que es una prueba estructural y no funcional: las guardas
// (requerirPropietario, requerirLogin, requireCliente) terminan en `exit`, asi
// que no se pueden invocar desde PHPUnit sin matar el proceso de pruebas. Lo
// que si se puede fijar —y es lo que de verdad falla en la practica— es que
// ningun endpoint nuevo nazca sin guarda. El riesgo real no es que una guarda
// existente deje de funcionar, es que alguien anada un metodo publico y se
// olvide de ponerla; eso es exactamente lo que esta prueba impide.
//
// Fija ADEMAS que conjunto alcanza un empleado. Hoy son tres endpoints de 33;
// el dia que eso cambie, el cambio se vera en el diff de este archivo y no
// pasara inadvertido.

use PHPUnit\Framework\TestCase;

final class AutorizacionControladoresTest extends TestCase
{
    /**
     * Llamadas que cierran el acceso. `startSession()` NO esta aqui a
     * proposito: solo arranca la sesion, no comprueba quien la tiene.
     */
    private const GUARDAS = ['requerirPropietario', 'requerirLogin', 'requireCliente'];

    /**
     * Endpoints deliberadamente accesibles sin sesion, con el motivo de cada
     * uno. Anadir algo aqui debe costar tanto como escribir por que.
     *
     * @var array<string, string>
     */
    private const PUBLICOS_A_PROPOSITO = [
        'AuthController::landing'       => 'Portada publica: presenta el producto a quien no ha entrado.',
        'AuthController::login'         => 'Formulario de acceso; exigir sesion para entrar seria circular.',
        'AuthController::recuperarPin'  => 'Recuperacion de acceso: por definicion, sin sesion.',
        'AuthController::logout'        => 'Cerrar sesion no puede exigir tenerla; sin ella no hace nada.',

        'PortalAuthController::login'          => 'Acceso del portal de clientes.',
        'PortalAuthController::googleCallback' => 'Retorno de Google OAuth: llega antes de haber sesion.',
        'PortalAuthController::registro'       => 'Alta de cliente nuevo.',
        'PortalAuthController::recuperarPass'  => 'Recuperacion de contrasena del portal.',
        'PortalAuthController::completarEmail' => 'Comprueba $_SESSION[cliente_id] EN LINEA en vez de con '
            . 'requireCliente(): esa guarda redirige a completar_email.php cuando falta el correo, '
            . 'asi que usarla aqui crearia un bucle de redireccion infinito.',
    ];

    /**
     * Back-office que un empleado alcanza: lo guardado solo con
     * requerirLogin(). Todo lo demas exige propietario.
     *
     * `CompraController::proveedores` esta aqui por diseno mixto: se ve con
     * requerirLogin(), pero la rama POST llama a requerirPropietario(), de modo
     * que un empleado consulta proveedores y solo el propietario los modifica.
     *
     * @var list<string>
     */
    private const ALCANZABLE_POR_EMPLEADO = [
        'CompraController::proveedores',
        'InventarioController::ajuste',
        'ProduccionController::detalle',
    ];

    public function testTodoMetodoPublicoTieneGuardaOEstaDeclaradoPublico(): void
    {
        $desprotegidos = [];
        foreach (self::metodosPublicos() as $clave => $datos) {
            if ($datos['guardas'] === [] && !isset(self::PUBLICOS_A_PROPOSITO[$clave])) {
                $desprotegidos[] = $clave . '  (' . $datos['archivo'] . ')';
            }
        }

        $this->assertSame(
            [],
            $desprotegidos,
            "Estos metodos publicos de controlador no llaman a ninguna guarda ni estan declarados\n"
            . "como publicos a proposito. Si el endpoint debe ser publico, anadelo a\n"
            . "PUBLICOS_A_PROPOSITO con el motivo; si no, ponle su guarda:\n  "
            . implode("\n  ", $desprotegidos)
        );
    }

    public function testElConjuntoAlcanzablePorUnEmpleadoNoCambia(): void
    {
        $solo_login = [];
        foreach (self::metodosPublicos() as $clave => $datos) {
            if (in_array('requerirLogin', $datos['guardas'], true)
                && !in_array('requireCliente', $datos['guardas'], true)) {
                $solo_login[] = $clave;
            }
        }
        sort($solo_login);

        $esperado = self::ALCANZABLE_POR_EMPLEADO;
        sort($esperado);

        $this->assertSame(
            $esperado,
            $solo_login,
            "Cambio que puede alcanzar un usuario con rol 'empleado'. No es necesariamente un\n"
            . "error —el rol esta a medio implementar y algun dia habra que decidirlo—, pero\n"
            . "tiene que ser una decision consciente: actualiza ALCANZABLE_POR_EMPLEADO."
        );
    }

    public function testElRestoDelBackOfficeExigePropietario(): void
    {
        $solo_propietario = 0;
        foreach (self::metodosPublicos() as $datos) {
            if (in_array('requerirPropietario', $datos['guardas'], true)) {
                $solo_propietario++;
            }
        }

        // 30 metodos exigen propietario. El numero exacto importa menos que el
        // hecho de que no baje sin querer: cada uno que salga de aqui es una
        // pantalla de administracion que se abre a mas gente.
        $this->assertGreaterThanOrEqual(
            30,
            $solo_propietario,
            'Bajo el numero de endpoints que exigen propietario. Comprueba que sea intencionado.'
        );
    }

    public function testLaListaDeEndpointsPublicosNoTieneEntradasMuertas(): void
    {
        $existentes = array_keys(self::metodosPublicos());
        $muertas = array_diff(array_keys(self::PUBLICOS_A_PROPOSITO), $existentes);

        $this->assertSame(
            [],
            array_values($muertas),
            "PUBLICOS_A_PROPOSITO menciona metodos que ya no existen. Una lista de excepciones\n"
            . "que no se limpia acaba tapando un endpoint real: retirar estas entradas.\n  "
            . implode("\n  ", $muertas)
        );
    }

    public function testElInventarioEncuentraControladores(): void
    {
        // Red de seguridad de la propia prueba: si el analizador dejara de
        // encontrar metodos —por un cambio de rutas o de sintaxis—, las otras
        // comprobaciones pasarian recorriendo una lista vacia y no verificarian
        // nada. Este es el fallo que las haria mentir en silencio.
        $this->assertGreaterThan(40, count(self::metodosPublicos()));
    }

    /**
     * Metodos publicos de los controladores y las guardas que invoca cada uno.
     * Se analiza con token_get_all y no con expresiones regulares porque hay
     * que distinguir la visibilidad y acotar el cuerpo de cada metodo.
     *
     * @return array<string, array{archivo: string, guardas: list<string>}>
     */
    private static function metodosPublicos(): array
    {
        $raiz = dirname(__DIR__, 2);
        $rutas = array_merge(
            glob($raiz . '/controllers/*.php') ?: [],
            glob($raiz . '/controllers/portal/*.php') ?: []
        );

        $resultado = [];
        foreach ($rutas as $ruta) {
            $tokens = token_get_all((string) file_get_contents($ruta));
            $clase  = self::nombreDeClase($tokens) ?? basename($ruta, '.php');
            $total  = count($tokens);

            for ($i = 0; $i < $total; $i++) {
                if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
                    continue;
                }
                if (self::visibilidad($tokens, $i) !== 'public') {
                    continue;
                }
                $nombre = self::nombreDeFuncion($tokens, $i);
                if ($nombre === null || $nombre === '__construct') {
                    continue;
                }

                $cuerpo  = self::cuerpo($tokens, $i);
                $guardas = [];
                foreach (self::GUARDAS as $g) {
                    if (str_contains($cuerpo, $g . '(')) {
                        $guardas[] = $g;
                    }
                }

                // Ruta relativa y con barras normales: en Windows glob() mezcla
                // separadores y el mensaje de error sale ilegible.
                $relativa = str_replace('\\', '/', $ruta);
                $relativa = str_replace(str_replace('\\', '/', $raiz) . '/', '', $relativa);

                $resultado[$clase . '::' . $nombre] = [
                    'archivo' => $relativa,
                    'guardas' => $guardas,
                ];
            }
        }

        return $resultado;
    }

    /** @param array<int, array{0: int, 1: string}|string> $tokens */
    private static function nombreDeClase(array $tokens): ?string
    {
        $total = count($tokens);
        for ($i = 0; $i < $total; $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_CLASS) {
                for ($j = $i + 1; $j < $total; $j++) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        return $tokens[$j][1];
                    }
                }
            }
        }
        return null;
    }

    /** @param array<int, array{0: int, 1: string}|string> $tokens */
    private static function visibilidad(array $tokens, int $i): string
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            if (!is_array($tokens[$j])) {
                break;
            }
            $t = $tokens[$j][0];
            if ($t === T_PRIVATE)   { return 'private'; }
            if ($t === T_PROTECTED) { return 'protected'; }
            if ($t === T_PUBLIC)    { return 'public'; }
            // Modificadores y ruido que pueden ir delante sin cambiar la visibilidad.
            if (in_array($t, [T_WHITESPACE, T_ABSTRACT, T_FINAL, T_STATIC, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            break;
        }
        // Sin modificador explicito, PHP la considera publica.
        return 'public';
    }

    /** @param array<int, array{0: int, 1: string}|string> $tokens */
    private static function nombreDeFuncion(array $tokens, int $i): ?string
    {
        $total = count($tokens);
        for ($j = $i + 1; $j < $total; $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                return $tokens[$j][1];
            }
            if ($tokens[$j] === '(') {
                return null;   // funcion anonima
            }
        }
        return null;
    }

    /**
     * Cuerpo del metodo, desde su primera llave hasta la que la cierra.
     * @param array<int, array{0: int, 1: string}|string> $tokens
     */
    private static function cuerpo(array $tokens, int $i): string
    {
        $total  = count($tokens);
        $prof   = 0;
        $dentro = false;
        $cuerpo = '';

        for ($j = $i; $j < $total; $j++) {
            $texto = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            if ($texto === '{') {
                $prof++;
                $dentro = true;
            }
            if ($dentro) {
                $cuerpo .= $texto;
            }
            if ($texto === '}') {
                $prof--;
                if ($prof === 0 && $dentro) {
                    break;
                }
            }
            if ($texto === ';' && !$dentro) {
                break;   // metodo abstracto o de interfaz: no tiene cuerpo
            }
        }

        return $cuerpo;
    }
}
