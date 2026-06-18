<!-- ESTILOS PERFIL (heredados del tablero) -->
<style>
:root{
  --c1:#945b35; --c2:#c8956e; --c3:#c67124; --c4:#e4a565; --c5:#ecc198;
  --cbg:#faf3ea; --ccard:#ffffff; --clight:#fdf6ee;
  --ink:#281508; --ink2:#6b3d1e; --ink3:#b87a4a;
  --border:rgba(148,91,53,.12);
  --shadow:0 1px 8px rgba(148,91,53,.09);
  --shadow2:0 4px 20px rgba(148,91,53,.15);
  --grad:linear-gradient(135deg,var(--c1),var(--c3) 55%,var(--c4));
  --nav-h:64px;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
html,body{min-height:100%;overflow-x:hidden;font-family:'Plus Jakarta Sans',sans-serif;background:var(--cbg);color:var(--ink);}

@keyframes fadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes gradAnim{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}

/* ── PÁGINA ── */
.pf-page{margin-top:var(--nav-h);min-height:calc(100vh - var(--nav-h));background:var(--cbg);padding:1rem 1.1rem 2rem;}

/* ── HEADER (banner cálido como bienvenida) ── */
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

/* ── MENSAJES ── */
.pf-msg{max-width:980px;margin:0 auto .8rem;}
.msg-ok,.msg-err{
  border-radius:12px;padding:.7rem .95rem;font-size:.82rem;font-weight:600;
  display:flex;align-items:flex-start;gap:.5rem;line-height:1.45;
  animation:fadeUp .35s ease both;
}
.msg-ok{background:rgba(46,125,50,.1);border:1px solid rgba(46,125,50,.25);border-left:3px solid #2e7d32;color:#2e7d32;}
.msg-err{background:rgba(198,40,40,.08);border:1px solid rgba(198,40,40,.22);border-left:3px solid #c62828;color:#c62828;}
.msg-ok i,.msg-err i{flex-shrink:0;margin-top:.12rem;}

/* ── GRID ── */
.pf-grid{max-width:980px;margin:0 auto;display:grid;grid-template-columns:280px 1fr;gap:1rem;animation:fadeUp .45s ease .05s both;align-items:start;}
.pf-sidebar{display:flex;flex-direction:column;gap:1rem;position:sticky;top:calc(var(--nav-h) + 12px);}

/* ── ID CARD ── */
.id-card{background:var(--ccard);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);padding:1.4rem 1.1rem;text-align:center;overflow:hidden;position:relative;}
.id-card::before{content:"";position:absolute;inset:0 0 auto 0;height:70px;background:var(--grad);}
.id-avatar{position:relative;width:74px;height:74px;margin:0 auto .8rem;border-radius:50%;background:#fff;border:3px solid #fff;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(148,91,53,.25);}
.id-avatar span{font-family:'Fraunces',serif;font-size:1.65rem;font-weight:800;color:var(--c1);}
.id-name{position:relative;font-family:'Fraunces',serif;font-size:1.15rem;font-weight:800;color:var(--ink);}
.id-role{position:relative;display:inline-block;margin-top:.45rem;padding:.2rem .7rem;border-radius:20px;background:rgba(198,113,36,.12);font-size:.58rem;text-transform:uppercase;letter-spacing:.18em;font-weight:700;color:var(--c3);}
.id-email{position:relative;margin-top:.85rem;padding-top:.7rem;border-top:1px solid var(--border);font-size:.74rem;color:var(--ink2);display:flex;align-items:center;justify-content:center;gap:.35rem;word-break:break-all;}

/* ── TABS NAV ── */
.tab-nav{background:var(--ccard);border:1px solid var(--border);border-radius:14px;padding:.45rem;display:flex !important;flex-direction:column !important;box-shadow:var(--shadow);}
.tab-btn{width:100% !important;display:flex;align-items:center;gap:.6rem;padding:.7rem .85rem;border-radius:10px;border:none;background:transparent;color:var(--ink2);font-size:.84rem;font-weight:600;font-family:inherit;cursor:pointer;transition:all .2s;text-align:left;margin-bottom:.2rem;}
.tab-btn:last-child{margin-bottom:0;}
.tab-btn:hover{background:var(--clight);color:var(--ink);}
.tab-btn.active{background:var(--grad);color:#fff;box-shadow:var(--shadow2);}
.tab-btn i{font-size:1rem;width:18px;text-align:center;}

/* ── STATUS CARD ── */
.status-card{background:var(--ccard);border:1px solid var(--border);border-radius:14px;padding:1rem 1.1rem;box-shadow:var(--shadow);}
.status-title{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.16em;color:var(--ink3);margin-bottom:.6rem;}
.status-row{display:flex;align-items:center;justify-content:space-between;padding:.35rem 0;font-size:.8rem;border-bottom:1px solid var(--border);}
.status-row:last-child{border-bottom:none;}
.status-row .sr-label{color:var(--ink2);}
.status-row .sr-val{font-size:.72rem;font-weight:700;display:inline-flex;align-items:center;gap:.3rem;}
.sr-ok{color:#2e7d32;}
.sr-warn{color:#c62828;}

/* ── CONTENIDO ── */
.pf-content{background:var(--ccard);border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:var(--shadow);}
.pf-ch{padding:1.1rem 1.4rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.7rem;background:var(--clight);}
.pf-ch-ico{width:40px;height:40px;border-radius:10px;background:rgba(198,113,36,.12);display:flex;align-items:center;justify-content:center;font-size:1.05rem;color:var(--c3);}
.pf-ch h3{font-family:'Fraunces',serif;font-size:1.1rem;font-weight:800;color:var(--ink);margin:0;}
.pf-ch p{font-size:.7rem;color:var(--ink3);margin:.1rem 0 0;}
.pf-body{padding:1.4rem 1.5rem;}
.tab-panel{display:none;animation:fadeUp .3s ease;}
.tab-panel.active{display:block;}

/* ── FORM ── */
.fl{margin-bottom:1rem;}
.fl label{display:block;font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.16em;color:var(--ink3);margin-bottom:.35rem;}
.fl-wrap{position:relative;}
.fl-wrap>i{position:absolute;left:.85rem;top:50%;transform:translateY(-50%);font-size:.9rem;color:var(--ink3);pointer-events:none;}
.fl input{
  width:100%;padding:.72rem .9rem .72rem 2.4rem;
  border:1px solid var(--border);border-radius:10px;
  background:var(--clight);color:var(--ink);font-size:.85rem;font-family:inherit;
  transition:all .2s;box-sizing:border-box;
}
.fl input:focus{outline:none;border-color:var(--c3);background:#fff;box-shadow:0 0 0 3px rgba(198,113,36,.15);}
.fl input::placeholder{color:var(--ink3);opacity:.55;}
.fl input:disabled{opacity:.65;cursor:not-allowed;background:#f3eade;}
.fl .hint{font-size:.66rem;color:var(--ink3);margin-top:.3rem;}
.fl-row{display:grid;grid-template-columns:1fr 1fr;gap:.8rem;}
.eye-btn{position:absolute;right:.85rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--ink3);cursor:pointer;font-size:.9rem;padding:0;}
.eye-btn:hover{color:var(--c3);}

/* ── BOTÓN ── */
.btn-save{
  display:inline-flex;align-items:center;justify-content:center;gap:.45rem;
  padding:.72rem 1.5rem;border-radius:10px;background:var(--grad);color:#fff;border:none;
  font-size:.85rem;font-weight:700;font-family:inherit;cursor:pointer;
  box-shadow:var(--shadow2);transition:all .2s;margin-top:.3rem;
}
.btn-save:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(148,91,53,.25);}
.btn-save:active{transform:scale(.98);}

/* ── PIN BADGES & TIPS ── */
.pin-badge{display:inline-flex;align-items:center;gap:.35rem;padding:.4rem .75rem;border-radius:10px;font-size:.76rem;font-weight:700;margin-bottom:.9rem;}
.pin-ok{background:rgba(46,125,50,.1);border:1px solid rgba(46,125,50,.25);color:#2e7d32;}
.pin-no{background:rgba(198,40,40,.08);border:1px solid rgba(198,40,40,.22);color:#c62828;}
.sec-tip{border-radius:10px;border:1px solid var(--border);background:var(--clight);padding:.85rem .95rem;font-size:.74rem;color:var(--ink2);line-height:1.55;margin-bottom:.9rem;}
.sec-tip strong{color:var(--ink);display:block;margin-bottom:.2rem;font-family:'Fraunces',serif;}

.pf-footer{text-align:center;font-size:.7rem;color:var(--ink3);margin-top:2rem;}

@media(max-width:768px){
  .pf-page{margin-top:60px;padding:.8rem .7rem 2rem;}
  .pf-grid{grid-template-columns:1fr;}
  .pf-sidebar{position:static;}
  .pf-header h1{font-size:1.35rem;}
  .fl-row{grid-template-columns:1fr;}
}
</style>

<div class="pf-page">
  <div class="pf-header">
    <div class="pf-tag">Configuración</div>
    <h1>Mi <em>perfil</em></h1>
    <p>Administra tu información personal, seguridad y PIN de recuperación.</p>
  </div>

  <?php if ($msg_ok): ?><div class="pf-msg"><div class="msg-ok"><i class="bi bi-check-circle-fill"></i><span><?= $msg_ok ?></span></div></div><?php endif; ?>
  <?php if ($msg_err): ?><div class="pf-msg"><div class="msg-err"><i class="bi bi-exclamation-triangle-fill"></i><span><?= $msg_err ?></span></div></div><?php endif; ?>

  <div class="pf-grid">
    <div class="pf-sidebar">
      <div class="id-card">
        <div class="id-avatar"><span><?= $initials ?></span></div>
        <div class="id-name"><?= htmlspecialchars($datos['nombre_completo']) ?></div>
        <div class="id-role"><?= $datos['rol'] ?></div>
        <?php if (!empty($datos['correo_electronico'])): ?>
        <div class="id-email"><i class="bi bi-envelope"></i> <?= htmlspecialchars($datos['correo_electronico']) ?></div>
        <?php endif; ?>
      </div>
      <div class="tab-nav">
        <button class="tab-btn active" data-tab="datos" onclick="switchTab('datos')"><i class="bi bi-person-fill"></i> Datos personales</button>
        <button class="tab-btn" data-tab="seguridad" onclick="switchTab('seguridad')"><i class="bi bi-shield-lock-fill"></i> Seguridad</button>
        <button class="tab-btn" data-tab="pin" onclick="switchTab('pin')"><i class="bi bi-123"></i> PIN de recuperación</button>
      </div>
      <div class="status-card">
        <div class="status-title">Estado de la cuenta</div>
        <div class="status-row"><span class="sr-label">PIN</span><span class="sr-val <?= !empty($datos['pin_recuperacion']) ? 'sr-ok' : 'sr-warn' ?>"><i class="bi bi-<?= !empty($datos['pin_recuperacion']) ? 'check-circle-fill' : 'exclamation-circle-fill' ?>"></i> <?= !empty($datos['pin_recuperacion']) ? 'Configurado' : 'Pendiente' ?></span></div>
        <div class="status-row"><span class="sr-label">Correo</span><span class="sr-val <?= !empty($datos['correo_electronico']) ? 'sr-ok' : 'sr-warn' ?>"><?= !empty($datos['correo_electronico']) ? 'Verificado ✓' : 'Sin configurar' ?></span></div>
        <div class="status-row"><span class="sr-label">Sesión</span><span class="sr-val sr-ok"><?= date('h:i a') ?> · Activa</span></div>
      </div>
    </div>

    <div class="pf-content">
      <div class="pf-ch">
        <div class="pf-ch-ico"><i class="bi bi-person-fill" id="ch-icon"></i></div>
        <div><h3 id="ch-title">Datos personales</h3><p id="ch-desc">Tu información visible en el sistema</p></div>
      </div>
      <div class="pf-body">
        <div class="tab-panel active" id="panel-datos">
          <form method="POST">
            <div class="fl"><label>Usuario</label><div class="fl-wrap"><i class="bi bi-person"></i><input type="text" value="<?= htmlspecialchars($datos['nombre_usuario']) ?>" disabled></div><div class="hint">El nombre de usuario no se puede cambiar</div></div>
            <div class="fl"><label>Nombre completo *</label><div class="fl-wrap"><i class="bi bi-person-fill"></i><input type="text" name="nombre_completo" value="<?= htmlspecialchars($datos['nombre_completo']) ?>" required></div></div>
            <div class="fl-row">
              <div class="fl"><label>Correo electrónico</label><div class="fl-wrap"><i class="bi bi-envelope"></i><input type="email" name="correo_electronico" value="<?= htmlspecialchars($datos['correo_electronico'] ?? '') ?>" placeholder="correo@ejemplo.com"></div><div class="hint">Necesario para recuperar contraseña</div></div>
              <div class="fl"><label>Teléfono</label><div class="fl-wrap"><i class="bi bi-telephone"></i><input type="tel" name="telefono" value="<?= htmlspecialchars($datos['telefono'] ?? '') ?>" placeholder="3001234567"></div></div>
            </div>
            <button type="submit" name="guardar_perfil" class="btn-save"><i class="bi bi-check-lg"></i> Guardar datos</button>
          </form>
        </div>

        <div class="tab-panel" id="panel-seguridad">
          <form method="POST">
            <div class="fl"><label>Contraseña actual</label><div class="fl-wrap"><i class="bi bi-lock"></i><input type="password" name="clave_actual" id="p-actual" required placeholder="••••••••"><button type="button" class="eye-btn" onclick="toggleEye('p-actual',this)"><i class="bi bi-eye"></i></button></div></div>
            <div class="fl-row">
              <div class="fl"><label>Nueva contraseña</label><div class="fl-wrap"><i class="bi bi-shield-lock"></i><input type="password" name="clave_nueva" id="p-nueva" required minlength="6" placeholder="Mínimo 6 caracteres"><button type="button" class="eye-btn" onclick="toggleEye('p-nueva',this)"><i class="bi bi-eye"></i></button></div></div>
              <div class="fl"><label>Confirmar</label><div class="fl-wrap"><i class="bi bi-shield-check"></i><input type="password" name="clave_confirmar" id="p-conf" required minlength="6" placeholder="Repite"><button type="button" class="eye-btn" onclick="toggleEye('p-conf',this)"><i class="bi bi-eye"></i></button></div></div>
            </div>
            <div class="sec-tip"><strong>Recomendaciones de seguridad</strong>Usa una contraseña única. Combina mayúsculas, minúsculas, números y símbolos.</div>
            <button type="submit" name="cambiar_clave" class="btn-save"><i class="bi bi-key-fill"></i> Actualizar contraseña</button>
          </form>
        </div>

        <div class="tab-panel" id="panel-pin">
          <form method="POST">
            <?php if (!empty($datos['pin_recuperacion'])): ?><div class="pin-badge pin-ok"><i class="bi bi-check-circle-fill"></i> PIN configurado correctamente</div>
            <?php else: ?><div class="pin-badge pin-no"><i class="bi bi-exclamation-triangle-fill"></i> PIN no configurado</div><?php endif; ?>
            <div class="fl"><label>Tu contraseña</label><div class="fl-wrap"><i class="bi bi-lock"></i><input type="password" name="clave_pin" required placeholder="Confirma tu contraseña"></div></div>
            <div class="fl"><label>Nuevo PIN de 6 dígitos</label><div class="fl-wrap"><i class="bi bi-123"></i><input type="text" name="pin" maxlength="6" pattern="\d{6}" inputmode="numeric" required placeholder="••••••" style="text-align:center;letter-spacing:.7em;font-size:1rem;font-weight:700;font-family:'Fraunces',serif;color:var(--ink);" oninput="this.value=this.value.replace(/\D/g,'').slice(0,6)"></div><div class="hint">Solo dígitos. Lo necesitarás para recuperar tu contraseña.</div></div>
            <button type="submit" name="guardar_pin" class="btn-save"><i class="bi bi-save"></i> Guardar PIN</button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <div class="pf-footer">BreadControl · Sistema de gestión de panadería</div>
</div>

<script>
var tabData={datos:{icon:'bi-person-fill',title:'Datos personales',desc:'Tu información visible en el sistema'},seguridad:{icon:'bi-shield-lock-fill',title:'Seguridad',desc:'Protege el acceso a tu cuenta'},pin:{icon:'bi-123',title:'PIN de recuperación',desc:'Código de 6 dígitos para recuperar contraseña'}};
var currentTab='<?= $tab_activo ?>';
if(currentTab!=='datos')switchTab(currentTab);
function switchTab(tab){document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active')});document.querySelectorAll('.tab-panel').forEach(function(p){p.classList.remove('active')});document.querySelector('[data-tab="'+tab+'"]').classList.add('active');document.getElementById('panel-'+tab).classList.add('active');var d=tabData[tab];document.getElementById('ch-icon').className='bi '+d.icon;document.getElementById('ch-title').textContent=d.title;document.getElementById('ch-desc').textContent=d.desc;}
function toggleEye(id,btn){var inp=document.getElementById(id);var ico=btn.querySelector('i');if(inp.type==='password'){inp.type='text';ico.className='bi bi-eye-off';}else{inp.type='password';ico.className='bi bi-eye';}}
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
