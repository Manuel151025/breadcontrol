<?php
// models/PortalClienteModel.php
//
// Modelo del Portal de Clientes, dividido por responsabilidad en traits
// (models/portal/): una sola clase para los controladores, archivos
// pequeños y cohesivos para mantener.

require_once __DIR__ . '/../includes/estados_pago.php';
require_once __DIR__ . '/../helpers/ReglasPortal.php';

require_once __DIR__ . '/portal/CuentaClienteTrait.php';
require_once __DIR__ . '/portal/CatalogoPortalTrait.php';
require_once __DIR__ . '/portal/PedidosPortalTrait.php';
require_once __DIR__ . '/portal/PagosPortalTrait.php';
require_once __DIR__ . '/portal/InstructorPortalTrait.php';

class PortalClienteModel {
    use CuentaClienteTrait;
    use CatalogoPortalTrait;
    use PedidosPortalTrait;
    use PagosPortalTrait;
    use InstructorPortalTrait;

    private PDO $pdo;


    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
}
