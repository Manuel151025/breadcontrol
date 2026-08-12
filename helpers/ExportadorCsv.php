<?php
// helpers/ExportadorCsv.php

/**
 * Exportación de datos a hoja de cálculo.
 *
 * Antes las exportaciones "a Excel" enviaban HTML con la extensión `.xls`.
 * Eso trae dos problemas reales, no cosméticos:
 *
 *  1. Excel abre el archivo con la advertencia «el formato no coincide con la
 *     extensión» y obliga al usuario a confirmar que confía en él.
 *  2. Los números viajaban ya formateados (`$ 1.500`), así que llegaban como
 *     TEXTO: no se podían sumar, ordenar ni usar en una tabla dinámica. Una
 *     exportación con la que no se puede calcular no sirve como exportación.
 *
 * Se emite CSV con BOM UTF-8 y punto y coma como separador, que es lo que
 * espera Excel en configuración regional de Colombia; los números salen sin
 * separadores de miles y con coma decimal, de modo que la hoja los reconoce
 * como números.
 *
 * Criterio de diseño: la hoja de cálculo es el artefacto de DATOS, no el de
 * presentación. Por eso se emite una tabla plana —una fila por registro, sin
 * títulos, subtotales ni filas de relleno— que el usuario puede filtrar y
 * pivotar. La versión bonita, con secciones y totales, es el PDF.
 */
class ExportadorCsv {

    /**
     * Envía el archivo al navegador y termina la ejecución.
     *
     * @param string                        $nombre      Nombre sin extensión.
     * @param list<string>                  $encabezados Títulos de columna.
     * @param iterable<array<int, mixed>>   $filas       Valores ya ordenados por columna.
     */
    public static function enviar(string $nombre, array $encabezados, iterable $filas): never {
        $archivo = $nombre . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $archivo . '"');
        header('Cache-Control: max-age=0');

        $salida = fopen('php://output', 'w');
        if ($salida === false) {
            exit;
        }

        // BOM: sin él, Excel abre el archivo en la codificación del sistema y
        // los acentos y la eñe se rompen.
        fwrite($salida, "\xEF\xBB\xBF");

        fputcsv($salida, $encabezados, ';', '"', '');
        foreach ($filas as $fila) {
            fputcsv($salida, array_map([self::class, 'formatear'], $fila), ';', '"', '');
        }

        fclose($salida);
        exit;
    }

    /**
     * Deja los números en crudo (coma decimal, sin miles) para que la hoja los
     * trate como números, y el resto como texto.
     */
    private static function formatear(mixed $valor): string {
        if ($valor === null) {
            return '';
        }
        if (is_bool($valor)) {
            return $valor ? 'Sí' : 'No';
        }
        if (is_int($valor)) {
            return (string) $valor;
        }
        if (is_float($valor)) {
            $entero = fmod($valor, 1.0) === 0.0;
            return str_replace('.', ',', $entero ? (string) (int) $valor : number_format($valor, 2, '.', ''));
        }
        if (is_string($valor)) {
            return $valor;
        }
        // Descartados null, bool, int, float y string, lo único que queda es un
        // array u objeto: llegó una fila mal armada. Se emite la celda vacía en
        // vez de romper la exportación entera con un error de conversión.
        return '';
    }
}
