<?php
// tests/test_runner.php
// Ejecutor de pruebas nativas en PHP puro.

class TestRunner {
    private static $passed = 0;
    private static $failed = 0;
    private static $errors = [];

    public static function run() {
        // Cargar entorno mockeado de sesión para pruebas si es necesario
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        echo "\n============================================\n";
        echo "   BREADCONTROL TEST RUNNER (Vanilla PHP)   \n";
        echo "============================================\n\n";

        // Buscar todas las pruebas en la carpeta actual
        $files = glob(__DIR__ . '/test_*.php');
        
        foreach ($files as $file) {
            if (basename($file) === 'test_runner.php') {
                continue;
            }
            
            $testName = basename($file, '.php');
            echo "Corriendo: $testName...\n";
            
            try {
                // Incluir el archivo de test ejecuta las pruebas contenidas en él
                include $file;
                echo "  -> Completado con éxito.\n\n";
            } catch (Throwable $e) {
                self::$failed++;
                self::$errors[] = "Error en archivo $testName: " . $e->getMessage() . "\n" . $e->getTraceAsString();
                echo "  -> \033[31mFALLÓ (Excepción no controlada)\033[0m\n\n";
            }
        }

        self::report();
    }

    public static function assertEquals($expected, $actual, $message = '') {
        if ($expected === $actual) {
            self::$passed++;
        } else {
            self::$failed++;
            $msg = $message ?: "Se esperaba: " . print_r($expected, true) . ", pero se obtuvo: " . print_r($actual, true);
            self::$errors[] = $msg;
            echo "  \033[31m[FALLO]\033[0m $msg\n";
            throw new Exception("Aserción fallida: $msg");
        }
    }

    public static function assertTrue($condition, $message = '') {
        if ($condition === true) {
            self::$passed++;
        } else {
            self::$failed++;
            $msg = $message ?: "Se esperaba que la condición fuera TRUE";
            self::$errors[] = $msg;
            echo "  \033[31m[FALLO]\033[0m $msg\n";
            throw new Exception("Aserción fallida: $msg");
        }
    }

    private static function report() {
        echo "============================================\n";
        echo "              REPORTE DE PRUEBAS            \n";
        echo "============================================\n";
        echo "Aserciones pasadas: " . self::$passed . "\n";
        echo "Aserciones fallidas: " . self::$failed . "\n";
        
        if (self::$failed > 0) {
            echo "\033[31mESTADO: ALGUNAS PRUEBAS FALLARON\033[0m\n\n";
            echo "Detalle de errores:\n";
            foreach (self::$errors as $err) {
                echo "- $err\n";
            }
            exit(1);
        } else {
            echo "\033[32mESTADO: TODAS LAS PRUEBAS PASARON CORRECTAMENTE\033[0m\n\n";
            exit(0);
        }
    }
}

// Permitir que el runner se ejecute directamente si se invoca por CLI
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    TestRunner::run();
}
