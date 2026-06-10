<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
session_start();

if (!isset($_SESSION['cliente_id'])) {
    header('Location: index.php');
    exit;
}

$cliente_id = $_SESSION['cliente_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre      = trim($_POST['nombre'] ?? '');
    $es_aprendiz = isset($_POST['es_aprendiz']) ? 1 : 0;

    if (empty($nombre)) {
        $error = 'El nombre no puede estar vacío.';
    } else {
        $pdo = getConexion();
        $pdo->prepare("UPDATE cliente SET nombre = ?, es_aprendiz = ? WHERE id_cliente = ?")
            ->execute([$nombre, $es_aprendiz, $cliente_id]);

        $_SESSION['cliente_nombre'] = $nombre;
        header('Location: dashboard.php');
        exit;
    }
}

// Pre-fill with current name
$pdo     = getConexion();
$stmt    = $pdo->prepare("SELECT nombre, foto_url FROM cliente WHERE id_cliente = ?");
$stmt->execute([$cliente_id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);
$nombre_actual = $cliente['nombre'] ?? '';
$foto_url      = $cliente['foto_url'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <title>Completa tu perfil — BreadControl</title>
  <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/img/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    *{margin:0;padding:0;box-sizing:border-box;}
    :root{
      --bg:hsl(24,60%,6%); --fg:hsl(30,30%,90%); --card:hsla(24,40%,10%,.6);
      --orange:hsl(27,72%,47%); --honey:hsl(30,67%,65%);
      --muted:hsl(30,20%,45%); --border:rgba(255,255,255,.1); --input-bg:hsla(24,40%,8%,.6);
    }
    body{font-family:'DM Sans',sans-serif;color:var(--fg);background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;}

    .glass-card{
      background:var(--card);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
      border:1px solid var(--border);border-radius:1.5rem;
      width:100%;max-width:440px;padding:2.4rem 2.2rem 2rem;
      display:flex;flex-direction:column;align-items:center;gap:1.4rem;
      animation:fadeUp .5s ease-out forwards;
    }

    .avatar{
      width:72px;height:72px;border-radius:50%;
      border:3px solid var(--orange);object-fit:cover;
    }
    .avatar-placeholder{
      width:72px;height:72px;border-radius:50%;
      background:linear-gradient(135deg,var(--orange),var(--honey));
      display:flex;align-items:center;justify-content:center;
      font-size:2rem;color:#fff;
    }

    .card-title{text-align:center;}
    .card-title h1{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;margin-bottom:.3rem;}
    .card-title p{font-size:.82rem;color:var(--muted);line-height:1.5;}

    .msg-error{
      width:100%;background:rgba(220,38,38,.12);border:1px solid rgba(220,38,38,.25);
      border-left:4px solid #dc2626;border-radius:.75rem;
      padding:.65rem .9rem;font-size:.78rem;color:#fca5a5;
      display:flex;align-items:center;gap:.45rem;
    }

    .field{width:100%;}
    .field label{display:block;font-size:.78rem;font-weight:500;color:rgba(255,255,255,.6);margin-bottom:.4rem;}
    .field input{
      width:100%;height:2.85rem;padding:0 1rem;
      background:var(--input-bg);border:1px solid var(--border);border-radius:.75rem;
      color:var(--fg);font-family:inherit;font-size:.85rem;outline:none;
      transition:border-color .3s,box-shadow .3s;
    }
    .field input:focus{border-color:var(--orange);box-shadow:0 0 0 3px hsla(27,72%,47%,.15);}

    .aprendiz-box{
      width:100%;display:flex;align-items:flex-start;gap:.75rem;
      background:rgba(255,167,38,.08);
      border:1px solid rgba(255,167,38,.25);
      border-radius:.75rem;padding:1rem;cursor:pointer;
    }
    .aprendiz-box input[type="checkbox"]{
      width:18px;height:18px;flex-shrink:0;margin-top:.1rem;
      accent-color:var(--orange);cursor:pointer;
    }
    .aprendiz-box .aprendiz-text{display:flex;flex-direction:column;gap:.2rem;}
    .aprendiz-box .aprendiz-label{font-size:.85rem;font-weight:600;color:var(--honey);}
    .aprendiz-box .aprendiz-desc{font-size:.75rem;color:var(--muted);line-height:1.4;}

    .btn-submit{
      width:100%;height:2.85rem;border:none;border-radius:2rem;
      background:linear-gradient(135deg,var(--orange),var(--honey));
      color:#fff;font-family:inherit;font-size:.88rem;font-weight:700;
      cursor:pointer;transition:all .3s;
    }
    .btn-submit:hover{transform:translateY(-2px);box-shadow:0 8px 30px hsla(27,72%,47%,.35);}

    @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
  </style>
</head>
<body>

<div class="glass-card">

  <?php if ($foto_url): ?>
    <img src="<?= htmlspecialchars($foto_url) ?>" alt="Foto" class="avatar">
  <?php else: ?>
    <div class="avatar-placeholder"><i class="bi bi-person-fill"></i></div>
  <?php endif; ?>

  <div class="card-title">
    <h1>¡Casi listo!</h1>
    <p>Confirma tu nombre y dinos si eres aprendiz SENA para que tus pedidos se registren correctamente.</p>
  </div>

  <?php if ($error): ?>
  <div class="msg-error"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" style="width:100%;display:flex;flex-direction:column;gap:1.3rem;">

    <div class="field">
      <label>Nombre de Tienda o Persona</label>
      <input type="text" name="nombre" value="<?= htmlspecialchars($nombre_actual) ?>" placeholder="Ej: Tienda El Buen Sabor" required maxlength="100">
    </div>

    <label class="aprendiz-box">
      <input type="checkbox" name="es_aprendiz" value="1">
      <div class="aprendiz-text">
        <span class="aprendiz-label">Soy aprendiz SENA</span>
        <span class="aprendiz-desc">Mis pedidos se cobrarán a la cuenta de Tienda ADSO, no a mí directamente.</span>
      </div>
    </label>

    <button type="submit" class="btn-submit">Entrar al portal</button>
  </form>

</div>

</body>
</html>
