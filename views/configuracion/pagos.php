<style>
:root{
  --c1:#945b35; --c2:#c8956e; --c3:#c67124; --c4:#e4a565; --c5:#ecc198;
  --cbg:#faf3ea; --ccard:#fff; --clight:#fdf6ee;
  --ink:#281508; --ink2:#6b3d1e; --ink3:#b87a4a;
  --border:rgba(148,91,53,.12);
  --shadow:0 1px 8px rgba(148,91,53,.09);
  --shadow2:0 4px 20px rgba(148,91,53,.15);
  --nav-h:64px;
  --pago-green:#2e7d32; --pago-green-dk:#1b5e20; --pago-green-bg:#e8f5e9; --pago-green-bd:#a5d6a7;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
html,body{min-height:100%;overflow-x:hidden;font-family:'Plus Jakarta Sans',sans-serif;background:var(--cbg);color:var(--ink);}

@keyframes fadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes gradAnim{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}

.pf-page{margin-top:var(--nav-h);min-height:calc(100vh - var(--nav-h));padding:1rem 1.1rem 2rem;}

.pf-header{
  max-width:980px;margin:0 auto 1rem;
  background:linear-gradient(125deg,#6b3211 0%,#945b35 18%,#c67124 35%,#e4a565 50%,#c67124 65%,#945b35 80%,#6b3211 100%);
  background-size:300% 300%;animation:gradAnim 8s ease infinite;
  border-radius:14px;padding:1rem 1.4rem;color:#fff;box-shadow:var(--shadow2);
}
.pf-header .pf-tag{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.2em;color:rgba(255,255,255,.7);margin-bottom:.25rem;}
.pf-header h1{font-family:'Fraunces',serif;font-size:1.6rem;font-weight:800;line-height:1.1;margin:0;color:#fff;}
.pf-header h1 em{font-style:italic;color:var(--c5);}
.pf-header p{font-size:.78rem;color:rgba(255,255,255,.78);margin-top:.3rem;}

.pf-container{max-width:980px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;animation:fadeUp .4s ease both;}
@media(max-width:800px){.pf-container{grid-template-columns:1fr;}}

.pf-card{background:var(--ccard);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);padding:1.4rem;}
.pf-card h2{font-family:'Fraunces',serif;font-size:1.15rem;color:var(--c1);margin-bottom:.3rem;display:flex;align-items:center;gap:.5rem;}
.pf-card .pf-subtitle{font-size:.82rem;color:var(--ink3);margin-bottom:1.2rem;}

.pf-form-group{margin-bottom:1rem;}
.pf-form-group label{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--ink3);margin-bottom:.4rem;}
.pf-form-group input[type=url],.pf-form-group input[type=text]{width:100%;padding:.75rem;border:1px solid var(--border);border-radius:10px;font-family:inherit;font-size:.92rem;color:var(--ink);transition:border-color .2s;}
.pf-form-group input:focus{outline:none;border-color:var(--c3);box-shadow:0 0 0 3px rgba(198,113,36,.1);}
.pf-form-group .hint{font-size:.72rem;color:var(--ink3);margin-top:.35rem;line-height:1.4;}

.pf-toggle{display:flex;align-items:center;gap:.7rem;padding:.85rem 1rem;background:var(--clight);border:1px solid var(--border);border-radius:10px;cursor:pointer;transition:all .2s;margin-bottom:.7rem;}
.pf-toggle:hover{border-color:var(--c3);}
.pf-toggle input{width:18px;height:18px;accent-color:var(--c3);cursor:pointer;flex-shrink:0;}
.pf-toggle .pf-toggle-text{flex:1;}
.pf-toggle .pf-toggle-title{font-weight:700;font-size:.88rem;color:var(--ink);}
.pf-toggle .pf-toggle-desc{font-size:.72rem;color:var(--ink3);margin-top:.15rem;line-height:1.4;}

.pf-btn-save{display:flex;align-items:center;justify-content:center;gap:.5rem;width:100%;background:linear-gradient(135deg,var(--c3),var(--c1));color:#fff;border:none;border-radius:10px;padding:.85rem;font-size:.92rem;font-weight:700;cursor:pointer;transition:all .2s;margin-top:.5rem;}
.pf-btn-save:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(198,113,36,.3);}

.pf-msg-ok{background:#e8f5e9;border:1px solid #a5d6a7;border-left:3px solid #2e7d32;border-radius:10px;padding:.7rem 1rem;font-size:.85rem;color:#1b5e20;font-weight:600;margin-bottom:1rem;}
.pf-msg-err{background:#ffebee;border:1px solid #ef9a9a;border-left:3px solid #c62828;border-radius:10px;padding:.7rem 1rem;font-size:.85rem;color:#c62828;margin-bottom:1rem;}

.pf-status{display:inline-flex;align-items:center;gap:.4rem;font-size:.72rem;font-weight:700;padding:.3rem .7rem;border-radius:20px;text-transform:uppercase;letter-spacing:.05em;}
.pf-status-on{background:var(--pago-green-bg);color:var(--pago-green-dk);border:1px solid var(--pago-green-bd);}
.pf-status-off{background:#f5f5f5;color:#757575;border:1px solid #e0e0e0;}

.pf-help{background:#fff8e1;border-left:3px solid #ffb300;padding:1rem;border-radius:10px;font-size:.82rem;color:#856404;line-height:1.6;}
.pf-help h3{font-family:'Fraunces',serif;font-size:1rem;color:#5d4d10;margin-bottom:.5rem;}
.pf-help ol{margin-left:1.2rem;margin-top:.4rem;}
.pf-help ol li{margin-bottom:.4rem;}
.pf-help code{background:#fff;border:1px solid rgba(0,0,0,.1);padding:.1rem .35rem;border-radius:4px;font-size:.78rem;}

.pf-back{display:inline-flex;align-items:center;gap:.4rem;color:var(--ink2);text-decoration:none;font-size:.82rem;font-weight:600;padding:.5rem .9rem;border-radius:10px;border:1px solid var(--border);background:var(--ccard);margin-bottom:1rem;transition:all .2s;}
.pf-back:hover{background:var(--clight);border-color:var(--c3);color:var(--c1);}

.pf-current-link{background:var(--clight);border:1px solid var(--border);border-radius:10px;padding:.85rem 1rem;font-family:monospace;font-size:.78rem;word-break:break-all;color:var(--c1);margin-top:.5rem;}
.pf-current-link a{color:var(--c3);text-decoration:none;}
.pf-current-link a:hover{text-decoration:underline;}
</style>

<div class="pf-page">
    <div class="pf-header">
        <div class="pf-tag">Configuración</div>
        <h1>Pagos <em>Digitales</em></h1>
        <p>Configura tu link único de Nequi Negocios para recibir pagos en tus pedidos digitales.</p>
    </div>

    <div style="max-width:980px;margin:0 auto;">
        <a href="<?= APP_URL ?>/modules/configuracion/perfil.php" class="pf-back">
            <i class="bi bi-arrow-left"></i> Volver a mi perfil
        </a>
    </div>

    <?php if ($msg_ok): ?>
        <div style="max-width:980px;margin:0 auto;"><div class="pf-msg-ok"><i class="bi bi-check-circle-fill"></i> <?= $msg_ok ?></div></div>
    <?php endif; ?>
    <?php if ($msg_err): ?>
        <div style="max-width:980px;margin:0 auto;"><div class="pf-msg-err"><i class="bi bi-exclamation-triangle-fill"></i> <?= $msg_err ?></div></div>
    <?php endif; ?>

    <div class="pf-container">
        <!-- Tarjeta principal: configuracion del link -->
        <form method="post" class="pf-card">
            <h2>
                <i class="bi bi-credit-card-2-front" style="color:var(--c3);"></i>
                Tu link de Nequi Negocios
                <?php if (!empty($config['wompi_habilitado'])): ?>
                    <span class="pf-status pf-status-on" style="margin-left:auto;">
                        <i class="bi bi-check-circle-fill"></i> Activo
                    </span>
                <?php else: ?>
                    <span class="pf-status pf-status-off" style="margin-left:auto;">
                        <i class="bi bi-pause-circle"></i> Inactivo
                    </span>
                <?php endif; ?>
            </h2>
            <p class="pf-subtitle">Pega aquí el link único que aparece en tu app Nequi Negocios. Es el mismo link para todos los pedidos.</p>

            <div class="pf-form-group">
                <label for="nequi_link_pago">URL del link de pago</label>
                <input type="url" id="nequi_link_pago" name="nequi_link_pago"
                       value="<?= htmlspecialchars($config['nequi_link_pago'] ?? '') ?>"
                       placeholder="https://checkout.wompi.co/l/VPOS_xxxxxxxx">
                <div class="hint">Lo encuentras en tu app Nequi Negocios o en <code>comercios.wompi.co</code>. Es el link permanente de tu comercio.</div>

                <?php if (!empty($config['nequi_link_pago'])): ?>
                    <div class="pf-current-link">
                        <i class="bi bi-link-45deg"></i>
                        Link actual: <a href="<?= htmlspecialchars($config['nequi_link_pago']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($config['nequi_link_pago']) ?></a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="pf-form-group">
                <label for="nequi_titular">Nombre del titular <span style="color:var(--ink3);font-weight:400;">(opcional)</span></label>
                <input type="text" id="nequi_titular" name="nequi_titular"
                       value="<?= htmlspecialchars($config['nequi_titular'] ?? '') ?>"
                       placeholder="Ej: Dulce Sabor - Manuel Cardenas">
                <div class="hint">Nombre que verá el cliente como referencia (lo mostramos en el portal junto al botón de pagar).</div>
            </div>

            <label class="pf-toggle">
                <input type="checkbox" name="wompi_habilitado" value="1" <?= !empty($config['wompi_habilitado']) ? 'checked' : '' ?>>
                <div class="pf-toggle-text">
                    <div class="pf-toggle-title">Habilitar pagos digitales</div>
                    <div class="pf-toggle-desc">Permite que los clientes vean el botón "Pagar ahora" en sus pedidos confirmados.</div>
                </div>
            </label>

            <label class="pf-toggle">
                <input type="checkbox" name="wompi_confirmar_auto" value="1" <?= !empty($config['wompi_confirmar_auto']) ? 'checked' : '' ?>>
                <div class="pf-toggle-text">
                    <div class="pf-toggle-title">Confirmar pedido al recibir el pago</div>
                    <div class="pf-toggle-desc">Cuando marques un pago como recibido, el pedido pasa automáticamente a "Confirmado".</div>
                </div>
            </label>

            <button type="submit" name="guardar_link" class="pf-btn-save">
                <i class="bi bi-save-fill"></i> Guardar configuración
            </button>
        </form>

        <!-- Tarjeta de ayuda: como obtener el link -->
        <div class="pf-card">
            <h2>
                <i class="bi bi-question-circle" style="color:var(--c3);"></i>
                ¿Cómo obtener tu link?
            </h2>
            <p class="pf-subtitle">Solo necesitas configurarlo una vez. El mismo link sirve para todos los pedidos.</p>

            <div class="pf-help">
                <h3><i class="bi bi-phone"></i> Desde tu app Nequi Negocios</h3>
                <ol>
                    <li>Abre la app <strong>Nequi Negocios</strong> en tu celular.</li>
                    <li>Entra a la sección <strong>"Recibir pagos"</strong> o <strong>"Tu link de pago"</strong>.</li>
                    <li>Vas a ver una URL tipo <code>checkout.wompi.co/l/VPOS_xxxxxxxx</code>.</li>
                    <li>Toca el botón <strong>"Copiar"</strong> al lado del link.</li>
                    <li>Vuelve acá y pégalo en el campo de arriba.</li>
                    <li>Activa el toggle "Habilitar pagos digitales" y guarda.</li>
                </ol>
            </div>

            <div style="margin-top:1.2rem;background:var(--clight);border-left:3px solid var(--c3);padding:.9rem 1rem;border-radius:10px;font-size:.82rem;color:var(--ink2);line-height:1.5;">
                <strong style="color:var(--c1);">¿Cómo funciona?</strong><br>
                Cuando confirmes un pedido del portal, el cliente verá un botón "Pagar ahora" que lo lleva a tu link. Él escribe el monto exacto del pedido y paga con Nequi, Bancolombia, PSE o tarjeta. Cuando recibas el pago en tu app, vuelves al pedido en BreadControl y lo marcas como pagado.
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
