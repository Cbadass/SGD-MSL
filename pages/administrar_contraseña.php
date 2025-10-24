<?php
// pages/administrar_contraseña.php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auditoria.php';
require_once __DIR__ . '/../includes/roles.php';

if (!isset($_SESSION['usuario'])) { http_response_code(401); exit('No autorizado'); }
$rolActual = strtoupper($_SESSION['usuario']['permisos'] ?? 'GUEST');
$idUsuarioSesion = (int)($_SESSION['usuario']['id'] ?? $_SESSION['usuario']['Id_usuario'] ?? 0);

$isAdmin    = ($rolActual === 'ADMIN');
$isDirector = ($rolActual === 'DIRECTOR');
$isPro      = ($rolActual === 'PROFESIONAL');
$puedeGestionar = $isAdmin || $isDirector;

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

function idProfesionalDeUsuario(PDO $conn, int $idUsuario): ?int {
  $stmt = $conn->prepare("SELECT Id_profesional FROM usuarios WHERE Id_usuario = ?");
  $stmt->execute([$idUsuario]);
  $id = $stmt->fetchColumn();
  return $id ? (int)$id : null;
}

$escuelaDirectorId = $isDirector ? escuelaDeUsuario($conn, $idUsuarioSesion) : null;
$miIdProfesional   = idProfesionalDeUsuario($conn, $idUsuarioSesion);

$err = null; $ok = null; $okCard = '';

// === Acciones POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    // 1) Cambiar mi contraseña
    if (isset($_POST['action']) && $_POST['action'] === 'change_self' && $miIdProfesional) {
      $actual   = (string)($_POST['actual'] ?? '');
      $nueva    = (string)($_POST['nueva'] ?? '');
      $confirm  = (string)($_POST['confirm'] ?? '');

      if ($actual === '' || $nueva === '' || $confirm === '') throw new RuntimeException('Completa todos los campos.');
      if ($nueva !== $confirm) throw new RuntimeException('La nueva contraseña y la confirmación no coinciden.');
      if (strlen($nueva) < 8)  throw new RuntimeException('La nueva contraseña debe tener al menos 8 caracteres.');

      // Buscar usuario actual
      $row = $conn->prepare("SELECT * FROM usuarios WHERE Id_usuario = ?");
      $row->execute([$idUsuarioSesion]);
      $u = $row->fetch(PDO::FETCH_ASSOC);
      if (!$u) throw new RuntimeException('Usuario no encontrado.');

      if (!password_verify($actual, $u['Contraseña'])) throw new RuntimeException('La contraseña actual no es válida.');

      $hash = password_hash($nueva, PASSWORD_DEFAULT);

      $conn->beginTransaction();
      $conn->prepare("UPDATE usuarios SET Contraseña = ? WHERE Id_usuario = ?")->execute([$hash, $idUsuarioSesion]);

      registrarAuditoria($conn, $idUsuarioSesion, 'usuarios', $idUsuarioSesion, 'UPDATE',
        ['Contraseña' => $u['Contraseña']], ['Contraseña' => $hash]);

      $conn->commit();
      $ok = 'Contraseña actualizada correctamente.';
    }

    // 2) Restablecer contraseña (ADMIN / DIRECTOR)
    if (isset($_POST['action']) && $_POST['action'] === 'reset_other') {
      if (!$puedeGestionar) {
        throw new RuntimeException('No tienes permisos para restablecer otras contraseñas.');
      }

      $idTarget = (int)($_POST['Id_usuario'] ?? 0);
      if ($idTarget <= 0) {
        throw new RuntimeException('Usuario objetivo inválido.');
      }

      if ($isDirector && $escuelaDirectorId === null) {
        throw new RuntimeException('No se pudo determinar tu escuela para validar el alcance.');
      }

      $stmtTarget = $conn->prepare("
        SELECT u.Id_usuario, u.Nombre_usuario, u.Permisos, u.Contraseña, p.Id_escuela_prof
          FROM usuarios u
     LEFT JOIN profesionales p ON p.Id_profesional = u.Id_profesional
         WHERE u.Id_usuario = ?
      ");
      $stmtTarget->execute([$idTarget]);
      $target = $stmtTarget->fetch(PDO::FETCH_ASSOC);
      if (!$target) {
        throw new RuntimeException('El usuario solicitado no existe.');
      }

      $targetRol = ensureRole((string)($target['Permisos'] ?? 'PROFESIONAL'));
      $targetSchoolId = isset($target['Id_escuela_prof']) ? (int)$target['Id_escuela_prof'] : null;

      if (!canResetPassword($rolActual, $escuelaDirectorId, $targetSchoolId, $targetRol)) {
        if (in_array($targetRol, ['ADMIN', 'DIRECTOR'], true)) {
          throw new RuntimeException('No puedes restablecer la contraseña de administradores o directores.');
        }
        if ($isDirector && $escuelaDirectorId !== null && $targetSchoolId !== null && (int)$escuelaDirectorId !== (int)$targetSchoolId) {
          throw new RuntimeException('No puedes restablecer contraseñas fuera de tu escuela.');
        }
        throw new RuntimeException('No tienes permisos para restablecer la contraseña de este usuario.');
      }

      $tempPwd = substr(bin2hex(random_bytes(10)), 0, 10);
      $hash = password_hash($tempPwd, PASSWORD_DEFAULT);

      $conn->beginTransaction();
      $conn->prepare("UPDATE usuarios SET Contraseña = ? WHERE Id_usuario = ?")->execute([$hash, $idTarget]);

      registrarAuditoria($conn, $idUsuarioSesion, 'usuarios', $idTarget, 'UPDATE',
        ['Contraseña' => $target['Contraseña']], ['Contraseña' => $hash]);

      $conn->commit();

      $uName = htmlspecialchars($target['Nombre_usuario'] ?? '', ENT_QUOTES, 'UTF-8');
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
if ($puedeGestionar) {
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
    $cond[] = "(u.Permisos IS NULL OR UPPER(u.Permisos) NOT IN ('ADMIN','DIRECTOR'))";
  }
  if ($cond) { $sql .= " WHERE ".implode(' AND ', $cond); }
  $sql .= " ORDER BY p.Apellido_profesional, p.Nombre_profesional";
  $st = $conn->prepare($sql);
  $st->execute($par);
  $list = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>
<h2><?= $puedeGestionar ? 'Gestión de contraseñas' : 'Actualizar mi contraseña' ?></h2>

<?php if ($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>
<?php if ($ok):  ?><div class="alert alert-success"><?= $ok ?></div><?php endif; ?>
<?php if ($okCard): ?><div><?= $okCard ?></div><?php endif; ?>

<?php if ($puedeGestionar): ?>
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
<?php else: ?>
  <div class="alert alert-info">Solo puedes actualizar tu propia contraseña desde esta pantalla.</div>
<?php endif; ?>

<section>
  <h3><?= $puedeGestionar ? 'Cambiar mi contraseña' : 'Actualizar mi contraseña' ?></h3>
  <form method="post" autocomplete="off" data-requires-confirm>
    <input type="hidden" name="action" value="change_self">
    <div class="form-grid">
      <div class="form-group">
        <label>Contraseña actual <span class="text-danger">*</span></label>
        <input type="password" name="actual" required>
      </div>
      <div class="form-group">
        <label>Nueva contraseña <span class="text-danger">*</span></label>
        <input type="password" name="nueva" minlength="8" required>
      </div>
      <div class="form-group">
        <label>Repetir nueva contraseña <span class="text-danger">*</span></label>
        <input type="password" name="confirm" minlength="8" required>
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
</script>
