<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}
$usuario = $_SESSION['usuario'];
?>
<header class="header">
  <span>SGD Multisenluz</span>
  <div>
    <button class="btn btn-sm btn-outline-light me-2">
      <a class="link-text" href="index.php?seccion=perfil">👤 Mi Perfil</a>
    </button>
    <span>
      Usuario: <strong><?= htmlspecialchars($usuario['nombre']) ?></strong>
      (<?= htmlspecialchars($usuario['permisos']) ?>)
    </span>
    <button id="modoToggle" class="btn btn-sm btn-light me-2 toggle-darkmode">🌙</button>
    <a href="logout.php" class="btn btn-sm btn-outline-light">Cerrar sesión</a>
  </div>
</header>
