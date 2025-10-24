<?php
// pages/administrar_contraseña.php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auditoria.php';

if (!isset($_SESSION['usuario'])) { http_response_code(401); exit('No autorizado'); }
$rolActual = strtoupper($_SESSION['usuario']['permisos'] ?? 'GUEST');
$idUsuarioSesion = (int)($_SESSION['usuario']['id'] ?? $_SESSION['usuario']['Id_usuario'] ?? 0);

$isAdmin    = ($rolActual === 'ADMIN');
$isDirector = ($rolActual === 'DIRECTOR');
$isPro      = ($rolActual === 'PROFESIONAL');

// === Helpers ===
function escuelaDeUsuario(PDO $conn, int $idUsuario): ?int {
  $stmt = $conn->prepare("
    SELECT p.Id_escuela_prof
    FROM usuarios u
    JOIN profesionales p ON p.Id_profesional = u.Id_profesional
    WHERE u.Id_usuario = ?
  ");
  $stmt->execute([$idUsuario]);
  $id = $stmt->fetchColumn();
  return $id ? (int)$id : null;
}

$escuelaDirectorId = $isDirector ? escuelaDeUsuario($conn, $idUsuarioSesion) : null;
$err = null; $ok = null; $okCard = '';

// === Acciones POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    // Restablecer contraseña (ADMIN / DIRECTOR)
    if (isset($_POST['action']) && $_POST['action'] === 'reset_other') {
      if (!$isAdmin && !$isDirector) throw new RuntimeException('No autorizado.');
      $idTarget = (int)($_POST['Id_usuario'] ?? 0);
      if ($idTarget <= 0) throw new RuntimeException('Objetivo inválido.');

      // Verificar alcance: si director, el target debe pertenecer a su escuela
      if ($isDirector) {
        $chk = $conn->prepare("
          SELECT 1
          FROM usuarios u
          JOIN profesionales p ON p.Id_profesional = u.Id_profesional
          WHERE u.Id_usuario = ? AND p.Id_escuela_prof = ?
        ");
        $chk->execute([$idTarget, (int)$escuelaDirectorId]);
        if (!$chk->fetchColumn()) {
          throw new RuntimeException('No puedes restablecer contraseñas fuera de tu escuela.');
        }
      }

      // Generar temporal
      $tempPwd = substr(bin2hex(random_bytes(10)), 0, 10);
      $hash = password_hash($tempPwd, PASSWORD_DEFAULT);

      // Estado anterior para auditoría
      $stmtU = $conn->prepare("SELECT * FROM usuarios WHERE Id_usuario = ?");
      $stmtU->execute([$idTarget]);
      $old = $stmtU->fetch(PDO::FETCH_ASSOC);
      if (!$old) throw new RuntimeException('Usuario no encontrado.');

      $conn->beginTransaction();
      $conn->prepare("UPDATE usuarios SET Contraseña = ? WHERE Id_usuario = ?")->execute([$hash, $idTarget]);

      registrarAuditoria($conn, $idUsuarioSesion, 'usuarios', $idTarget, 'UPDATE',
        ['Contraseña' => $old['Contraseña']], ['Contraseña' => $hash]);

      $conn->commit();

      $uName = htmlspecialchars($old['Nombre_usuario'] ?? '', ENT_QUOTES, 'UTF-8');
      $pTmp  = htmlspecialchars($tempPwd, ENT_QUOTES, 'UTF-8');

      $okCard = '
      <div class="alert alert-success" role="alert">
        <div style="font-weight:600; margin-bottom:6px;">✅ Contraseña restablecida</div>
        <div class="cred-block" style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size:14px; line-height:1.4; padding:10px; border:1px solid rgba(0,0,0,0.1); border-radius:8px; background:rgba(0,0,0,0.03);">
          Usuario: <span class="cred-user">'.$uName.'</span><br>
          Contraseña temporal: <span class="cred-pass">'.$pTmp.'</span>
        </div>
        <button type="button" class="btn btn-sm" id="copyCredsBtn"
          data-user="'.$uName.'" data-pass="'.$pTmp.'"
          style="margin-top:8px;">Copiar credenciales</button>
        <small style="display:block;margin-top:6px;opacity:.8;">Solicita cambiar la contraseña al iniciar sesión.</small>
      </div>';
    }

  } catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    $err = 'Error: ' . htmlspecialchars($e->getMessage());
  }
}

// === Listado para reset (solo admin/director) ===
$q = trim($_GET['q'] ?? '');
$list = [];
if ($isAdmin || $isDirector) {
  $sql = "
    SELECT u.Id_usuario, u.Nombre_usuario, u.Permisos, p.Nombre_profesional, p.Apellido_profesional, e.Nombre_escuela
    FROM usuarios u
    LEFT JOIN profesionales p ON p.Id_profesional = u.Id_profesional
    LEFT JOIN escuelas e ON e.Id_escuela = p.Id_escuela_prof
  ";
  $cond = [];
  $par  = [];
  if ($q !== '') {
    $cond[] = "(u.Nombre_usuario LIKE ? OR p.Nombre_profesional LIKE ? OR p.Apellido_profesional LIKE ?)";
    $par[] = "%$q%"; $par[] = "%$q%"; $par[] = "%$q%";
  }
  if ($isDirector) {
    $cond[] = "p.Id_escuela_prof = ?";
    $par[]  = (int)$escuelaDirectorId;
  }
  if ($cond) { $sql .= " WHERE ".implode(' AND ', $cond); }
  $sql .= " ORDER BY p.Apellido_profesional, p.Nombre_profesional";
  $st = $conn->prepare($sql);
  $st->execute($par);
  $list = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>
<h2>Gestión de contraseñas</h2>

<?php if ($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>
<?php if ($ok):  ?><div class="alert alert-success"><?= $ok ?></div><?php endif; ?>
<?php if ($okCard): ?><div><?= $okCard ?></div><?php endif; ?>

<?php if ($isAdmin || $isDirector): ?>
  <section class="mb-3">
    <h3>Restablecer contraseña (temporal)</h3>
    <form method="get" class="mb-2">
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar por usuario o nombre…" />
      <button class="btn btn-sm btn-secondary" type="submit">Buscar</button>
    </form>

    <?php if (empty($list) && $q !== ''): ?>
      <div class="alert alert-info">No se encontraron resultados para “<?= htmlspecialchars($q) ?>”.</div>
    <?php endif; ?>

    <?php if (!empty($list)): ?>
      <table class="table table-striped">
        <thead><tr><th>Usuario</th><th>Nombre</th><th>Escuela</th><th>Rol</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($list as $u): ?>
          <tr>
            <td><?= htmlspecialchars($u['Nombre_usuario']) ?></td>
            <td><?= htmlspecialchars(($u['Apellido_profesional'] ?? '').' '.($u['Nombre_profesional'] ?? '')) ?></td>
            <td><?= htmlspecialchars($u['Nombre_escuela'] ?? '') ?></td>
            <td><?= htmlspecialchars($u['Permisos'] ?? '') ?></td>
            <td>
              <form method="post" data-requires-confirm>
                <input type="hidden" name="action" value="reset_other">
                <input type="hidden" name="Id_usuario" value="<?= (int)$u['Id_usuario'] ?>">
                <button class="btn btn-sm btn-primary" type="submit">Restablecer</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
<?php endif; ?>

<section id="cambiar-mi-password">
  <h3>Cambiar mi contraseña</h3>
  <p class="text-muted" style="max-width: 640px;">
    Actualiza tu contraseña personal sin salir del sistema. La nueva clave debe tener
    al menos 8 caracteres y se aplicará de inmediato.
  </p>
  <div class="alert d-none" role="alert" data-self-password-alert></div>
  <form method="post" action="mi_password_update.php" autocomplete="off" data-self-password-form>
    <div class="form-grid">
      <div class="form-group">
        <label>Contraseña actual <span class="text-danger">*</span></label>
        <input type="password" name="pwd_actual" required autocomplete="current-password">
      </div>
      <div class="form-group">
        <label>Nueva contraseña <span class="text-danger">*</span></label>
        <input type="password" name="pwd_nueva" minlength="8" required autocomplete="new-password">
      </div>
      <div class="form-group">
        <label>Repetir nueva contraseña <span class="text-danger">*</span></label>
        <input type="password" name="pwd_conf" minlength="8" required autocomplete="new-password">
      </div>
    </div>
    <button class="btn btn-primary mt-2" type="submit">Actualizar contraseña</button>
  </form>
</section>

<script>
// Copiar credenciales en restablecimiento
document.addEventListener('click', function (e) {
  const btn = e.target.closest('#copyCredsBtn');
  if (!btn) return;
  const user = btn.getAttribute('data-user') || '';
  const pass = btn.getAttribute('data-pass') || '';
  const text = `Usuario: ${user}\nContraseña temporal: ${pass}`;
  navigator.clipboard.writeText(text).then(() => {
    const original = btn.textContent;
    btn.textContent = '¡Copiado!';
    setTimeout(() => { btn.textContent = original; }, 1500);
  }).catch(() => {
    alert('No se pudieron copiar las credenciales. Copia manualmente.');
  });
});

(function () {
  const forms = document.querySelectorAll('form[data-self-password-form]');
  forms.forEach((form) => {
    if (form.dataset.enhanced === '1') return;
    form.dataset.enhanced = '1';

    const alertBox = form.parentElement.querySelector('[data-self-password-alert]');
    const submitBtn = form.querySelector('[type="submit"]');

    const showAlert = (type, message) => {
      if (!alertBox) return;
      alertBox.className = `alert alert-${type}`;
      alertBox.textContent = message;
      alertBox.classList.remove('d-none');
    };

    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      if (alertBox) {
        alertBox.classList.add('d-none');
        alertBox.textContent = '';
      }

      const formData = new FormData(form);
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.dataset.originalText = submitBtn.textContent;
        submitBtn.textContent = 'Guardando…';
      }

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const data = await response.json().catch(() => null);

        if (!response.ok || !data) {
          const msg = data && data.msg ? data.msg : 'Ocurrió un error inesperado.';
          throw new Error(msg);
        }

        showAlert('success', data.msg || 'Contraseña actualizada correctamente.');
        form.reset();
      } catch (error) {
        const message = error instanceof Error ? error.message : 'No se pudo actualizar la contraseña.';
        showAlert('danger', message);
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = submitBtn.dataset.originalText || 'Actualizar contraseña';
        }
      }
    });
  });
})();
</script>
