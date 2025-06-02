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
    <span class="me-3">
      Usuario conectado: <strong><?= htmlspecialchars($usuario['nombre']) ?></strong> (<?= htmlspecialchars($usuario['permisos']) ?>)
    </span>
    <button id="modoToggle" class="btn btn-sm btn-light me-2 toggle-darkmode">🌙</button>
    <a href="logout.php" class="btn btn-sm btn-outline-light">Cerrar sesión</a>
  </div>
</header>

<!-- Script para alternar modo oscuro (si no lo tienes ya en otro lado) -->
<script>
document.getElementById('modoToggle').addEventListener('click', () => {
  document.body.classList.toggle('dark-mode');
  const dark = document.body.classList.contains('dark-mode');
  document.cookie = `modo_oscuro=${dark}; path=/; max-age=31536000`;
});
</script>
