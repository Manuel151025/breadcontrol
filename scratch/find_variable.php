<?php
$lines = file(__DIR__ . '/../controllers/PortalClienteController.php');
foreach ($lines as $num => $line) {
    if (strpos($line, 'total_comprado') !== false) {
        echo ($num + 1) . ": " . trim($line) . "\n";
    }
}
