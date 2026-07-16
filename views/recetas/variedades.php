<style>
  :root{--c1:#945b35;--c2:#c8956e;--c3:#c67124;--c4:#e4a565;--c5:#ecc198;--cbg:#faf3ea;--ccard:#fff;--clight:#fdf6ee;--ink:#281508;--ink2:#6b3d1e;--ink3:#b87a4a;--border:rgba(148,91,53,.12);--shadow:0 1px 8px rgba(148,91,53,.09);--shadow2:0 4px 20px rgba(148,91,53,.15);--nav-h:64px;}
  @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
  @keyframes gradAnim{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
  .page{margin-top:var(--nav-h);min-height:calc(100vh - var(--nav-h));padding:1rem;background:var(--cbg);}
  .wc-banner{background:linear-gradient(125deg,#6b3211 0%,#945b35 18%,#c67124 35%,#e4a565 50%,#c67124 65%,#945b35 80%,#6b3211 100%);background-size:300% 300%;animation:gradAnim 8s ease infinite;border-radius:14px;padding:.9rem 1.4rem;display:flex;align-items:center;justify-content:space-between;box-shadow:var(--shadow2);gap:1rem;flex-wrap:wrap;margin-bottom:1rem;}
  .wc-left{display:flex;align-items:center;gap:.9rem;}
  .wc-name{font-family:'Fraunces',serif;font-size:1.35rem;font-weight:800;color:#fff;line-height:1.1;}
  .wc-name em{font-style:italic;color:var(--c5);}
  .wc-sub{font-size:.72rem;color:rgba(255,255,255,.62);margin-top:.15rem;}
  .wc-greeting{font-size:.65rem;text-transform:uppercase;letter-spacing:.2em;color:rgba(255,255,255,.65);margin-bottom:.15rem;}
  .topbar{display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;margin-bottom:1rem;}
  .mod-titulo{font-family:'Fraunces',serif;font-size:1.45rem;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:.5rem;}
  .mod-titulo i{color:var(--c3);}
  .btn-sec{background:var(--ccard);color:var(--ink2);border:1px solid var(--border);border-radius:10px;padding:.5rem 1rem;font-size:.82rem;font-weight:600;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:all .2s;}
  .btn-sec:hover{background:var(--clight);border-color:var(--c3);color:var(--ink);}
  .msg-ok{background:#e8f5e9;border:1px solid #a5d6a7;border-left:4px solid #2e7d32;border-radius:10px;padding:.7rem 1rem;font-size:.8rem;color:#1b5e20;font-weight:600;margin-bottom:1rem;display:flex;align-items:flex-start;gap:.5rem;line-height:1.5;}
  .msg-ok i{flex-shrink:0;margin-top:.12rem;}.msg-ok span{flex:1;}
  .msg-err{background:#ffebee;border:1px solid #ef9a9a;border-left:4px solid #c62828;border-radius:10px;padding:.7rem 1rem;font-size:.8rem;color:#c62828;font-weight:600;margin-bottom:1rem;display:flex;align-items:flex-start;gap:.5rem;line-height:1.5;}
  .msg-err i{flex-shrink:0;margin-top:.12rem;}.msg-err span{flex:1;}

  .cats-grid{display:grid;grid-template-columns:1fr;gap:1.2rem;}
  .cat-card{background:var(--ccard);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);overflow:hidden;animation:fadeUp .45s ease both;}
  .cat-header{display:flex;align-items:center;justify-content:space-between;padding:.8rem 1.1rem;border-bottom:1px solid var(--border);}
  .cat-header-left{display:flex;align-items:center;gap:.5rem;}
  .cat-ico{width:36px;height:36px;border-radius:9px;background:linear-gradient(135deg,var(--c3),var(--c1));display:flex;align-items:center;justify-content:center;color:#fff;font-family:'Fraunces',serif;font-weight:800;font-size:.75rem;}
  .cat-title{font-family:'Fraunces',serif;font-size:1rem;font-weight:700;color:var(--ink);}
  .cat-count{font-size:.62rem;font-weight:700;padding:.15rem .5rem;border-radius:20px;background:var(--clight);color:var(--c1);border:1px solid var(--border);}
  .cat-body{padding:.8rem 1.1rem;}

  /* Cards grid for varieties */
  .var-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.7rem;margin-bottom:.8rem;}
  .var-card{border:1px solid var(--border);border-radius:12px;overflow:hidden;background:var(--clight);transition:all .2s;position:relative;}
  .var-card:hover{box-shadow:var(--shadow);border-color:var(--c3);}
  .var-img{width:100%;height:100px;object-fit:cover;background:var(--border);display:block;}
  .var-img-placeholder{width:100%;height:100px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,rgba(198,113,36,.08),rgba(198,113,36,.03));font-size:2.5rem;}
  .var-info{padding:.5rem .6rem;}
  .var-name{font-size:.78rem;font-weight:700;color:var(--ink);margin-bottom:.3rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  .var-btns{display:flex;gap:.25rem;}
  .btn-act{width:26px;height:26px;border-radius:6px;border:1px solid;display:inline-flex;align-items:center;justify-content:center;font-size:.72rem;text-decoration:none;transition:all .2s;cursor:pointer;background:transparent;}
  .btn-edit{border-color:rgba(25,118,210,.25);color:#1565c0;}.btn-edit:hover{background:rgba(25,118,210,.1);}
  .btn-del{border-color:rgba(198,40,40,.2);color:#c62828;}.btn-del:hover{background:rgba(198,40,40,.1);}

  /* Add form */
  .add-form{background:var(--clight);border:1.5px dashed var(--border);border-radius:12px;padding:.8rem;display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap;}
  .add-form .fl{margin:0;flex:1;min-width:140px;}
  .add-form .fl label{font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--ink3);display:block;margin-bottom:.2rem;}
  .add-form .fl input{width:100%;border:1px solid var(--border);border-radius:8px;padding:.4rem .65rem;font-size:.82rem;color:var(--ink);font-family:inherit;background:#fff;box-sizing:border-box;}
  .add-form .fl input:focus{outline:none;border-color:var(--c3);}
  .add-form .fl input[type=file]{padding:.3rem;font-size:.72rem;}
  .btn-add{background:linear-gradient(135deg,var(--c3),var(--c1));color:#fff;border:none;border-radius:9px;padding:.45rem .8rem;font-size:.8rem;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:.3rem;transition:all .2s;white-space:nowrap;height:34px;}
  .btn-add:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(198,113,36,.3);}

  .empty-cat{text-align:center;padding:1.2rem;font-size:.78rem;color:var(--ink3);}
  .empty-cat i{font-size:1.8rem;opacity:.25;display:block;margin-bottom:.3rem;}

  /* Modal */
  .modal-bg{display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;}
  .modal-box{background:#fff;border-radius:14px;padding:1.4rem;width:90%;max-width:400px;box-shadow:0 12px 40px rgba(0,0,0,.2);}
  .modal-box h4{font-size:.9rem;color:var(--ink);margin:0 0 .8rem;display:flex;align-items:center;gap:.4rem;}
  .modal-box h4 i{color:var(--c3);}
  .modal-box .fl{margin-bottom:.6rem;}
  .modal-box .fl label{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--ink3);display:block;margin-bottom:.2rem;}
  .modal-box .fl input{width:100%;padding:.45rem .65rem;border:1px solid var(--border);border-radius:8px;font-size:.84rem;font-family:inherit;box-sizing:border-box;}
  .modal-box .fl input:focus{outline:none;border-color:var(--c3);}
  .modal-preview{width:80px;height:60px;border-radius:8px;object-fit:cover;border:1px solid var(--border);margin-bottom:.4rem;}
  .modal-btns{display:flex;gap:.4rem;margin-top:.5rem;}
  .modal-btns button{flex:1;padding:.5rem;border-radius:9px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;border:none;}
  .modal-btns .m-save{background:linear-gradient(135deg,var(--c3),var(--c1));color:#fff;}
  .modal-btns .m-cancel{background:var(--clight);color:var(--ink2);border:1px solid var(--border);}

  @media(max-width:768px){.page{margin-top:60px;padding:.6rem;}.var-grid{grid-template-columns:repeat(auto-fill,minmax(110px,1fr));}}
</style>

<div class="page">
  <div class="wc-banner">
    <div class="wc-left">
      <div>
        <div class="wc-greeting">Panadería BreadControl</div>
        <div class="wc-name">Variedades <em>de Pan</em></div>
        <div class="wc-sub">Define los tipos de pan con imagen para detallar pedidos grandes</div>
      </div>
    </div>
  </div>

  <div class="topbar">
    <div class="mod-titulo"><i class="bi bi-list-stars"></i> Variedades de Pan</div>
    <div style="display:flex;gap:.5rem;">
      <a href="index.php" class="btn-sec"><i class="bi bi-arrow-left"></i> Productos</a>
    </div>
  </div>

  <?php if ($msg_ok): ?><div class="msg-ok"><i class="bi bi-check-circle-fill"></i><span><?= $msg_ok ?></span></div><?php endif; ?>
  <?php if ($msg_err): ?><div class="msg-err"><i class="bi bi-exclamation-triangle-fill"></i><span><?= $msg_err ?></span></div><?php endif; ?>

  <div class="cats-grid">
    <?php foreach ($categorias as $cat):
      $vars = $var_por_cat[$cat['id_categoria']] ?? [];
    ?>
    <div class="cat-card">
      <div class="cat-header">
        <div class="cat-header-left">
          <div class="cat-ico">$<?= number_format($cat['precio_unitario'],0,',','.') ?></div>
          <span class="cat-title"><?= htmlspecialchars($cat['nombre']) ?></span>
        </div>
        <span class="cat-count"><?= count($vars) ?> variedad<?= count($vars) != 1 ? 'es' : '' ?></span>
      </div>
      <div class="cat-body">
        <?php if (empty($vars)): ?>
        <div class="empty-cat">
          <i class="bi bi-image"></i>
          Sin variedades · Agrega la primera abajo
        </div>
        <?php else: ?>
        <div class="var-grid">
          <?php foreach ($vars as $v): ?>
          <div class="var-card">
            <?php if (!empty($v['imagen'])): ?>
            <img src="<?= APP_URL ?>/<?= $v['imagen'] ?>" class="var-img" alt="<?= htmlspecialchars($v['nombre']) ?>">
            <?php else: ?>
            <div class="var-img-placeholder">🍞</div>
            <?php endif; ?>
            <div class="var-info">
              <div class="var-name" title="<?= htmlspecialchars($v['nombre']) ?>"><?= htmlspecialchars($v['nombre']) ?></div>
              <div class="var-btns">
                <button class="btn-act btn-edit" title="Editar"
                  data-id="<?= $v['id_variedad'] ?>"
                  data-nombre="<?= htmlspecialchars($v['nombre'], ENT_QUOTES) ?>"
                  data-img="<?= $v['imagen'] ? htmlspecialchars(APP_URL.'/'.$v['imagen'], ENT_QUOTES) : '' ?>"
                  onclick="abrirEdit(this)"><i class="bi bi-pencil"></i></button>
                <form method="POST" style="display:contents;" onsubmit="return confirm('¿Eliminar esta variedad?')">
                  <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                  <input type="hidden" name="del_var" value="<?= $v['id_variedad'] ?>">
                  <button type="submit" class="btn-act btn-del" title="Eliminar"><i class="bi bi-trash3"></i></button>
                </form>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="add-form">
          <input type="hidden" name="id_categoria" value="<?= $cat['id_categoria'] ?>">
          <div class="fl">
            <label>Nombre</label>
            <input type="text" name="nombre_variedad" placeholder="Ej: Pan Galleta" required>
          </div>
          <div class="fl">
            <label>Foto del pan</label>
            <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp">
          </div>
          <button type="submit" name="agregar_variedad" class="btn-add"><i class="bi bi-plus-lg"></i> Agregar</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Modal editar -->
<div class="modal-bg" id="modal-edit" onclick="if(event.target===this)cerrarEdit()">
  <div class="modal-box">
    <h4><i class="bi bi-pencil-square"></i> Editar variedad</h4>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="id_variedad" id="edit-id">
      <img id="edit-preview" class="modal-preview" src="" style="display:none;">
      <div class="fl">
        <label>Nombre</label>
        <input type="text" name="nombre_edit" id="edit-nombre" required>
      </div>
      <div class="fl">
        <label>Cambiar foto (opcional)</label>
        <input type="file" name="imagen_edit" accept="image/jpeg,image/png,image/webp">
      </div>
      <div class="modal-btns">
        <button type="button" class="m-cancel" onclick="cerrarEdit()">Cancelar</button>
        <button type="submit" name="editar_variedad" class="m-save"><i class="bi bi-check-lg"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
function abrirEdit(btn) {
  var id = btn.dataset.id;
  var nombre = btn.dataset.nombre;
  var imgUrl = btn.dataset.img;
  document.getElementById('edit-id').value = id;
  document.getElementById('edit-nombre').value = nombre;
  var preview = document.getElementById('edit-preview');
  if (imgUrl) { preview.src = imgUrl; preview.style.display = 'block'; }
  else { preview.style.display = 'none'; }
  document.getElementById('modal-edit').style.display = 'flex';
  document.getElementById('edit-nombre').focus();
}
function cerrarEdit() { document.getElementById('modal-edit').style.display = 'none'; }
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
