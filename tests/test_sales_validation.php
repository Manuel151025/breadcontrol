<?php
// tests/test_sales_validation.php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funciones.php';

// Validar que las funciones existan
TestRunner::assertTrue(function_exists('getStockProducto'), "La función getStockProducto debe existir.");
TestRunner::assertTrue(function_exists('validarStockVenta'), "La función validarStockVenta debe existir.");

// Obtener el stock actual de un producto (ID 1, usualmente Pan de Sal)
$id_producto = 1;
$stock_inicial = getStockProducto($id_producto);
TestRunner::assertTrue(is_float($stock_inicial) || is_int($stock_inicial), "El stock inicial debe ser un número float o int.");

// Prueba 1: Venta con cantidad igual a 0 (debe fallar)
$res_cero = validarStockVenta($id_producto, 0);
TestRunner::assertEquals(false, $res_cero['ok'], "La validación debe fallar para cantidad = 0.");
TestRunner::assertEquals(
    "La cantidad a vender debe ser mayor a 0.", 
    $res_cero['mensaje'], 
    "El mensaje de error debe indicar que la cantidad debe ser mayor a 0."
);

// Prueba 2: Venta con cantidad negativa (debe fallar)
$res_negativo = validarStockVenta($id_producto, -10);
TestRunner::assertEquals(false, $res_negativo['ok'], "La validación debe fallar para cantidad negativa.");

// Prueba 3: Venta con cantidad excesivamente alta que supera el stock (debe fallar)
$cantidad_excesiva = (int)$stock_inicial + 1000000;
$res_exceso = validarStockVenta($id_producto, $cantidad_excesiva);
TestRunner::assertEquals(false, $res_exceso['ok'], "La validación debe fallar si la cantidad supera el stock.");
TestRunner::assertTrue(
    strpos($res_exceso['mensaje'], "No hay suficiente stock") !== false, 
    "El mensaje debe advertir sobre stock insuficiente."
);

// Prueba 4: Venta con cantidad válida (debe pasar si el stock > 0, o fallar ordenadamente si el stock = 0)
if ($stock_inicial > 0) {
    $res_valido = validarStockVenta($id_producto, (int)floor($stock_inicial));
    TestRunner::assertEquals(true, $res_valido['ok'], "La validación debe ser exitosa si la cantidad es menor o igual al stock disponible.");
} else {
    // Si el stock es 0, intentar vender 1 unidad debe fallar
    $res_valido = validarStockVenta($id_producto, 1);
    TestRunner::assertEquals(false, $res_valido['ok'], "La validación debe fallar para cantidad = 1 si el stock es 0.");
}
