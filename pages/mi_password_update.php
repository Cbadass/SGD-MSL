<?php
// pages/mi_password_update.php
session_start();

if (empty($_SESSION['usuario'])) {
  header('Location: ../login.php');
  exit;
}

$usuarioSesion = $_SESSION['usuario'];
$nombreUsuario = $usuarioSesion['Nombre_usuario']
  ?? $usuarioSesion['nombre_usuario']
  ?? ($usuarioSesion['usuario'] ?? '');
?>
<h2 class="mb-4">Cambiar mi contraseña</h2>
<p class="mb-3 text-muted">
  <?= $nombreUsuario ? 'Estás conectado como <strong>' . htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8') . '</strong>.' : 'Actualiza tu contraseña.' ?>
  Completa el formulario para actualizar tu contraseña personal.
</p>
<div class="card p-4" style="max-width: 520px;">
  <form id="miPasswordForm" class="form-grid" autocomplete="off">
    <div class="form-group">
      <label for="pwd_actual">Contraseña actual</label>
      <input type="password" name="pwd_actual" id="pwd_actual" required autocomplete="current-password">
    </div>
    <div class="form-group">
      <label for="pwd_nueva">Nueva contraseña</label>
      <input type="password" name="pwd_nueva" id="pwd_nueva" minlength="8" required autocomplete="new-password">
    </div>
    <div class="form-group">
      <label for="pwd_conf">Confirmar nueva contraseña</label>
      <input type="password" name="pwd_conf" id="pwd_conf" minlength="8" required autocomplete="new-password">
    </div>
    <div class="form-group" style="grid-column: 1 / -1;">
      <button type="submit" class="btn btn-primary">Actualizar contraseña</button>
    </div>
  </form>
  <div id="miPasswordFeedback" class="alert mt-3" role="alert" style="display:none;"></div>
</div>

<script>
(function () {
  const form = document.getElementById('miPasswordForm');
  const feedback = document.getElementById('miPasswordFeedback');
  if (!form) return;

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Actualizando…';
    }

    feedback.style.display = 'none';
    feedback.classList.remove('alert-success', 'alert-danger');
    feedback.textContent = '';

    try {
      const response = await fetch('mi_password_update.php', {
        method: 'POST',
        body: new FormData(form),
      });

      if (!response.ok) {
        throw new Error('No se pudo actualizar la contraseña.');
      }

      const data = await response.json();
      if (data.ok) {
        feedback.textContent = 'Contraseña actualizada correctamente.';
        feedback.classList.add('alert-success');
        form.reset();
      } else {
        feedback.textContent = data.msg || 'No se pudo actualizar la contraseña.';
        feedback.classList.add('alert-danger');
      }
    } catch (error) {
      feedback.textContent = error.message || 'No se pudo actualizar la contraseña.';
      feedback.classList.add('alert-danger');
    } finally {
      feedback.style.display = 'block';
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = 'Actualizar contraseña';
      }
    }
  });
})();
</script>
