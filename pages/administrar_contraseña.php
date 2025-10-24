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
$soloGestionPropia = !empty($soloGestionPropia);
if ($soloGestionPropia) {
  $puedeGestionar = false;
}

$tituloPrincipal = $puedeGestionar
  ? 'Gestión de contraseñas'
  : ($soloGestionPropia ? 'Cambiar mi contraseña' : 'Actualizar mi contraseña');

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

$err = null; $ok = null; $resetDialogData = null;

// === Acciones POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    // 1) Restablecer contraseña (ADMIN / DIRECTOR)
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
        SELECT u.Id_usuario, u.Nombre_usuario, u.Permisos, u.Contraseña,
               p.Id_escuela_prof, p.Nombre_profesional, p.Apellido_profesional,
               p.Rut_profesional, p.Correo_profesional, p.Cargo_profesional,
               e.Nombre_escuela
          FROM usuarios u
     LEFT JOIN profesionales p ON p.Id_profesional = u.Id_profesional
     LEFT JOIN escuelas e ON e.Id_escuela = p.Id_escuela_prof
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

      $nombre = trim(($target['Nombre_profesional'] ?? '') . ' ' . ($target['Apellido_profesional'] ?? ''));
      $nombreVisible = $nombre !== '' ? $nombre : ($target['Nombre_usuario'] ?? 'usuario');

      $ok = sprintf(
        'Contraseña temporal generada para %s.',
        htmlspecialchars($nombreVisible, ENT_QUOTES, 'UTF-8')
      );

      $resetDialogData = [
        'usuario' => $target['Nombre_usuario'] ?? '',
        'nombres' => $target['Nombre_profesional'] ?? '',
        'apellidos' => $target['Apellido_profesional'] ?? '',
        'nombreCompleto' => $nombre,
        'correo'  => (string)($target['Correo_profesional'] ?? ''),
        'rut'     => (string)($target['Rut_profesional'] ?? ''),
        'escuela' => $target['Nombre_escuela'] ?? '',
        'cargo'   => $target['Cargo_profesional'] ?? '',
        'password'=> $tempPwd,
      ];
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
    SELECT u.Id_usuario, u.Nombre_usuario, u.Permisos,
           p.Nombre_profesional, p.Apellido_profesional, p.Rut_profesional,
           p.Cargo_profesional, p.Correo_profesional, e.Nombre_escuela
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
<h2><?= htmlspecialchars($tituloPrincipal) ?></h2>

<?php if ($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>
<?php if ($ok):  ?><div class="alert alert-success"><?= $ok ?></div><?php endif; ?>
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
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Apellido</th>
            <th>RUT</th>
            <th>Escuela</th>
            <th>Cargo</th>
            <th>Usuario</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($list as $u): ?>
          <tr>
            <td><?= htmlspecialchars($u['Nombre_profesional'] ?? '') ?></td>
            <td><?= htmlspecialchars($u['Apellido_profesional'] ?? '') ?></td>
            <td><?= htmlspecialchars($u['Rut_profesional'] ?? '') ?></td>
            <td><?= htmlspecialchars($u['Nombre_escuela'] ?? '') ?></td>
            <td><?= htmlspecialchars($u['Cargo_profesional'] ?? ($u['Permisos'] ?? '')) ?></td>
            <td><?= htmlspecialchars($u['Nombre_usuario']) ?></td>
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
<?php elseif (!$soloGestionPropia): ?>
  <div class="alert alert-info">Solo puedes actualizar tu propia contraseña desde esta pantalla.</div>
<?php endif; ?>

<style>
.reset-modal {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
}

.reset-modal[hidden] {
  display: none;
}

.reset-modal__backdrop {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
}

.reset-modal__content {
  position: relative;
  background: #fff;
  border-radius: 10px;
  padding: 20px;
  width: min(420px, calc(100% - 32px));
  box-shadow: 0 18px 45px rgba(0, 0, 0, 0.2);
}

.reset-modal__content h4 {
  margin-top: 0;
  margin-bottom: 12px;
}

.reset-modal__content p {
  margin-top: 0;
}

.reset-modal__content dl {
  margin: 12px 0 18px;
}

.reset-modal__content dt {
  font-weight: 600;
  font-size: 0.9rem;
}

.reset-modal__content dd {
  margin: 0 0 8px 0;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  word-break: break-word;
}

.reset-modal__actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
</style>

<div id="resetModal" class="reset-modal" hidden>
  <div class="reset-modal__backdrop" role="presentation"></div>
  <div class="reset-modal__content" role="dialog" aria-modal="true" aria-labelledby="resetModalTitle">
    <h4 id="resetModalTitle">Credenciales temporales generadas</h4>
    <p>Entrega estas credenciales al usuario y solicita el cambio al iniciar sesión.</p>
    <dl>
      <dt>Nombre y apellidos</dt>
      <dd data-field="nombreCompleto"></dd>
      <dt>Correo electrónico</dt>
      <dd data-field="correo"></dd>
      <dt>Usuario</dt>
      <dd data-field="usuario"></dd>
      <dt>RUT</dt>
      <dd data-field="rut"></dd>
      <dt>Contraseña temporal</dt>
      <dd data-field="password"></dd>
    </dl>
    <div class="reset-modal__actions">
      <button type="button" class="btn btn-sm btn-secondary" id="copyDialogCreds">Copiar credenciales</button>
      <button type="button" class="btn btn-sm btn-primary" id="closeResetModal">Cerrar</button>
    </div>
  </div>
</div>

<?php if ($resetDialogData): ?>
  <script>
    window.resetDialogData = <?= json_encode(
      $resetDialogData,
      JSON_UNESCAPED_UNICODE
      | JSON_HEX_TAG
      | JSON_HEX_APOS
      | JSON_HEX_AMP
      | JSON_HEX_QUOT
    ) ?>;
  </script>
<?php endif; ?>

<script>
(function () {
  const modal = document.getElementById('resetModal');
  if (!modal) { return; }

  const backdrop = modal.querySelector('.reset-modal__backdrop');
  const fieldMap = {
    nombreCompleto: modal.querySelector('[data-field="nombreCompleto"]'),
    correo: modal.querySelector('[data-field="correo"]'),
    usuario: modal.querySelector('[data-field="usuario"]'),
    rut: modal.querySelector('[data-field="rut"]'),
    password: modal.querySelector('[data-field="password"]')
  };

  const closeBtn = document.getElementById('closeResetModal');
  const copyBtn = document.getElementById('copyDialogCreds');
  let currentData = window.resetDialogData || null;

  function fillFields(data) {
    if (!data) { return; }
    const nombres = data.nombres || '';
    const apellidos = data.apellidos || '';
    const nombreCompleto = data.nombreCompleto || [nombres, apellidos].filter(Boolean).join(' ').trim();
    fieldMap.nombreCompleto.textContent = nombreCompleto || data.usuario || '—';
    fieldMap.correo.textContent = data.correo || '—';
    fieldMap.usuario.textContent = data.usuario || '—';
    fieldMap.rut.textContent = data.rut || '—';
    fieldMap.password.textContent = data.password || '—';
  }

  function showModal(data) {
    currentData = data;
    fillFields(data);
    modal.removeAttribute('hidden');
  }

  function hideModal() {
    modal.setAttribute('hidden', 'hidden');
  }

  if (currentData) {
    showModal(currentData);
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', hideModal);
  }

  if (backdrop) {
    backdrop.addEventListener('click', hideModal);
  }

  if (copyBtn) {
    copyBtn.addEventListener('click', function () {
      if (!currentData) { return; }
      const nombre = currentData.nombreCompleto || [currentData.nombres, currentData.apellidos].filter(Boolean).join(' ').trim();
      const lines = [
        nombre ? `Nombre: ${nombre}` : null,
        currentData.correo ? `Correo: ${currentData.correo}` : null,
        currentData.usuario ? `Usuario: ${currentData.usuario}` : null,
        currentData.rut ? `RUT: ${currentData.rut}` : null,
        currentData.password ? `Contraseña temporal: ${currentData.password}` : null
      ].filter(Boolean);

      navigator.clipboard.writeText(lines.join('\n')).then(function () {
        const original = copyBtn.textContent;
        copyBtn.textContent = '¡Copiado!';
        setTimeout(function () { copyBtn.textContent = original; }, 1500);
      }).catch(function () {
        alert('No se pudieron copiar las credenciales. Copia manualmente.');
      });
    });
  }
})();
</script>
