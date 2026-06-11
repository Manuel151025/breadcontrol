<?php
// tests/test_batch_generation.php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funciones.php';

// Validar que la función generarNumeroLote exista
TestRunner::assertTrue(function_exists('generarNumeroLote'), "La función generarNumeroLote debe existir.");

// Prueba 1: Formato básico del lote
$prefijo = 'harina';
$lote = generarNumeroLote($prefijo);

TestRunner::assertTrue(is_string($lote), "El lote generado debe ser un string.");
TestRunner::assertEquals(
    3, 
    strpos($lote, '-'), 
    "El primer guión debe estar en la posición index 3 (tres letras de prefijo)."
);

// Extraer componentes
$partes = explode('-', $lote);
TestRunner::assertEquals(5, count($partes), "El lote debe dividirse en 5 partes separadas por guión (PRE-YYYY-MM-DD-NNN).");

$prefijo_resultado = $partes[0];
$fecha_resultado = $partes[1] . '-' . $partes[2] . '-' . $partes[3];
$secuencia_resultado = $partes[4];

// Verificar prefijo en mayúsculas de 3 letras
TestRunner::assertEquals('HAR', $prefijo_resultado, "El prefijo extraído debe ser 'HAR' (las primeras 3 letras del prefijo en mayúsculas).");

// Verificar fecha
$fecha_esperada = date('Y-m-d');
TestRunner::assertEquals($fecha_esperada, $fecha_resultado, "La fecha del lote debe ser la fecha actual: $fecha_esperada.");

// Verificar secuencia (debe ser numérico de 3 dígitos con ceros)
TestRunner::assertEquals(3, strlen($secuencia_resultado), "La secuencia debe tener exactamente 3 dígitos.");
TestRunner::assertTrue(is_numeric($secuencia_resultado), "La secuencia debe ser un valor numérico.");

// Prueba 2: Lote inexistente (secuencia inicial)
$prefijo_raro = 'xyz_no_existe';
$lote_inicial = generarNumeroLote($prefijo_raro);
$partes_inicial = explode('-', $lote_inicial);
$secuencia_inicial = end($partes_inicial);

TestRunner::assertEquals('001', $secuencia_inicial, "Si no existen lotes para el prefijo de hoy, debe iniciar con '001'.");
