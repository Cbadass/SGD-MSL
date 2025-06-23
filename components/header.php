<?php
session_start();
if (!isset($_SESSION['usuario'])) {
  header("Location: login.php");
  exit;
}
$usuario = $_SESSION['usuario'];
?>
<header class="header">
  <span class="header-title">SGD Multisenluz</span>
  <div>
    <span class="mr-1">
      <?= htmlspecialchars($usuario['nombre']) ?>
      (<?= htmlspecialchars($usuario['permisos']) ?>)
    </span>
    <button class="btn btn-sm btn-outline-light mr-1">
      <a class="link-text" href="index.php?seccion=perfil">👤 Mi Perfil</a>
    </button>
    <button id="modoToggle" class="btn btn-sm btn-light toggle-darkmode mr-1">🌙</button>
    <button  class="btn btn-sm btn-outline-light">
      <a class="link-text" href="logout.php">Cerrar sesión</a>
    </button>
  </div>
</header>