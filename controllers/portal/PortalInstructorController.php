<?php
// controllers/portal/PortalInstructorController.php

require_once __DIR__ . '/PortalControllerBase.php';

/**
 * Gestión del grupo de aprendices por parte del instructor:
 * código de invitación, cupos semanales y retiro de aprendices.
 */
class PortalInstructorController extends PortalControllerBase {

    /**
     * Pantalla "Mis aprendices": el instructor genera/rota su código de invitación,
     * ve su grupo y ajusta cupos o retira aprendices. Solo cuentas instructor-capaces
     * (tienda que no es aprendiz); un instructor solo gestiona SUS aprendices.
     */
    public function misAprendices(): void {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];

        $cliente = $this->model->getClienteById($cliente_id);
        if (!$cliente) {
            header('Location: logout.php');
            exit;
        }

        // Solo una cuenta instructor-capaz entra aquí (seguridad).
        if (!$this->model->esInstructorCapaz($cliente)) {
            header('Location: dashboard.php');
            exit;
        }

        // Mensajes vía POST-Redirect-GET.
        $msg_ok  = $_SESSION['flash_ok']  ?? '';
        $msg_err = $_SESSION['flash_err'] ?? '';
        unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                $msg_err = 'Token de seguridad inválido o expirado. Recarga la página e intenta de nuevo.';
            } elseif (isset($_POST['generar_codigo'])) {
                $dias = (int)($_POST['dias_vigencia'] ?? 0);
                $dias = max(0, min(365, $dias));
                $sin_limite = isset($_POST['sin_limite_usos']);
                $usos = $sin_limite ? null : max(1, min(1000, (int)($_POST['usos_maximos'] ?? 1)));
                try {
                    $codigo = $this->model->generarCodigoAprendiz($cliente_id, $dias, $usos);
                    $msg_ok = 'Código generado: ' . $codigo . '. Compártelo con tus aprendices.';
                } catch (Exception $e) {
                    log_error($e);
                    $msg_err = 'No se pudo generar el código. Intenta de nuevo.';
                }
            } elseif (isset($_POST['desactivar_codigo'])) {
                $n = $this->model->desactivarCodigosInstructor($cliente_id);
                $msg_ok = $n > 0 ? 'Código desactivado. Puedes generar uno nuevo cuando quieras.' : 'No había un código activo.';
            } elseif (isset($_POST['quitar_aprendiz'])) {
                $aid = (int)($_POST['aprendiz_id'] ?? 0);
                if ($this->model->quitarAprendiz($cliente_id, $aid)) {
                    $msg_ok = 'Aprendiz retirado de tu grupo. Sus pedidos anteriores se conservan.';
                } else {
                    $msg_err = 'No se pudo retirar: esa persona no es un aprendiz de tu grupo.';
                }
            } elseif (isset($_POST['actualizar_cupo'])) {
                $aid  = (int)($_POST['aprendiz_id'] ?? 0);
                $cupo = (float)($_POST['cupo_semanal'] ?? 0);
                $error_cupo = ReglasPortal::validarCupoSemanal($cupo);
                if ($error_cupo !== null) {
                    $msg_err = $error_cupo;
                } elseif ($this->model->actualizarCupoAprendizInstructor($cliente_id, $aid, $cupo)) {
                    $msg_ok = 'Cupo semanal actualizado.';
                } else {
                    $msg_err = 'No se pudo actualizar: esa persona no es un aprendiz de tu grupo.';
                }
            }

            $_SESSION['flash_ok']  = $msg_ok;
            $_SESSION['flash_err'] = $msg_err;
            redirigir(APP_URL . '/portal/mis_aprendices.php');
        }

        $codigo_activo = $this->model->getCodigoActivoInstructor($cliente_id);
        $aprendices    = $this->model->getAprendicesGestion($cliente_id);
        $es_instructor = true;

        require_once __DIR__ . '/../../views/portal/mis_aprendices.php';
    }

}
