<?php
// includes/boton_eliminar.php
// Boton de accion destructiva protegido con CSRF. Reemplaza los antiguos <a href="?del=...">.
// Requiere que generar_token_csrf() (includes/sesion.php) ya este cargado.

if (!function_exists('boton_eliminar')) {
    function boton_eliminar(array $opts): void {
        $accion  = $opts['accion'];
        $campo   = $opts['campo'];
        $valor   = $opts['valor'];
        $confirm = $opts['confirm'];
        $title   = $opts['title'] ?? 'Eliminar';
        $icono   = $opts['icono'] ?? 'bi-trash3';
        $extra   = $opts['extra'] ?? [];
        ?>
        <form method="POST" action="<?= htmlspecialchars($accion) ?>" style="display:contents;"
              onsubmit="return confirm('<?= htmlspecialchars($confirm) ?>')">
            <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
            <input type="hidden" name="<?= htmlspecialchars($campo) ?>" value="<?= htmlspecialchars($valor) ?>">
            <?php foreach ($extra as $nombre_extra => $valor_extra): ?>
            <input type="hidden" name="<?= htmlspecialchars($nombre_extra) ?>" value="<?= htmlspecialchars($valor_extra) ?>">
            <?php endforeach; ?>
            <button type="submit" class="btn-act btn-del" title="<?= htmlspecialchars($title) ?>">
                <i class="bi <?= htmlspecialchars($icono) ?>"></i>
            </button>
        </form>
        <?php
    }
}
