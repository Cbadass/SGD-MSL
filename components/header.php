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

      <!-- Corrección mínima: usar un <a> con estilo de botón (no <a> dentro de <button>) -->
      <a href="logout.php" class="btn btn-sm btn-outline-light btn-logout link-text" data-action="logout">
        Cerrar sesión
      </a>
    <?php else: ?>
      <a class="btn btn-sm btn-primary" href="login.php">Iniciar sesión</a>
    <?php endif; ?>
  </div>
</header>
<script>
/**
 * Confirmación global para acciones que guardan/cambian datos.
 * Se activa en:
 *  - submit de formularios con [data-requires-confirm] o con botones .btn-primary/.btn-green
 *  - clicks en botones con [data-confirm]
 */
(function(){
  function needsConfirmSubmit(form){
    if (form.hasAttribute('data-requires-confirm')) return true;
    // heurística: si el botón submit visible es de guardar/crear
    const btn = form.querySelector('button[type="submit"]');
    if (!btn) return false;
    const t = (btn.textContent||'').toLowerCase();
    return /guardar|crear|actualizar|eliminar|registrar/.test(t);
  }
  document.addEventListener('submit', function(e){
    const f = e.target;
    if (!(f instanceof HTMLFormElement)) return;
    if (!needsConfirmSubmit(f)) return;
    const ok = confirm('¿Seguro que desea crear o actualizar?');
    if (!ok) e.preventDefault();
  }, true);

  document.addEventListener('click', function(e){
    const el = e.target.closest('[data-confirm]');
    if (!el) return;
    const msg = el.getAttribute('data-confirm') || '¿Confirmar acción?';
    if (!confirm(msg)) e.preventDefault();
  }, true);
})();

// Handler de logout (respeta tu estructura y añade confirmación)
document.addEventListener('click', function(e){
  const el = e.target.closest('[data-action="logout"], .btn-logout');
  if (!el) return;
  // si es <a>, dejamos que navegue; si no, redirigimos
  if (el.tagName !== 'A') e.preventDefault();
  const ok = confirm('¿Cerrar sesión?');
  if (!ok) {
    e.preventDefault();
    return;
  }
  // Navegación hacia logout.php (el href ya lo hace; esto asegura compatibilidad si es botón sin href)
  window.location.href = 'logout.php';
});
</script>
