<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}
$usuario = $_SESSION['usuario'];
?>
<header class="header d-flex justify-content-between align-items-center px-3 py-2 bg-dark text-white">
  <span class="fw-bold">SGD Multisenluz</span>
  <div class="d-flex align-items-center">
    <a href="index.php?seccion=perfil" class="btn btn-sm btn-outline-light me-2">👤 Mi Perfil</a>
    <span class="me-3">
      Usuario: <strong><?= htmlspecialchars($usuario['nombre']) ?></strong>
      (<?= htmlspecialchars($usuario['permisos']) ?>)
    </span>
    <button id="modoToggle" class="btn btn-sm btn-light me-2 toggle-darkmode">🌙</button>
    <a href="logout.php" class="btn btn-sm btn-outline-light">Cerrar sesión</a>
  </div>
</header>
