<?php
// components/header.php
// La sesión ya está abierta por includes/session.php desde index.php
$usuario = $_SESSION['usuario'] ?? null;
?>
<header class="header">
  <span class="header-title">SGD Multisenluz</span>
  <div>
    <?php if ($usuario): ?>
      <span class="mr-1">
        <?= htmlspecialchars($usuario['nombre'] ?? '') ?>
        (<?= htmlspecialchars($usuario['permisos'] ?? '') ?>)
      </span>
      <button id="modoToggle" class="btn btn-sm btn-light toggle-darkmode mr-1">🌙</button>
      <button class="btn btn-sm btn-outline-light">
        <a class="link-text" href="logout.php">Cerrar sesión</a>
      </button>
    <?php else: ?>
      <a class="btn btn-sm btn-primary" href="login.php">Iniciar sesión</a>
    <?php endif; ?>
  </div>
</header>
