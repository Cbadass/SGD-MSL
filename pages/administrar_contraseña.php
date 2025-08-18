<?php
// pages/administrar_contraseña.php
declare(strict_types=1);
session_start();

// ====== INCLUDES / DB (PDO) ======
$root = dirname(__DIR__);
require_once $root . '/includes/db.php'; // Debe definir $conn (PDO)

// Asegurar manejo de errores con excepciones
try {
  if (!($conn instanceof PDO)) {
    throw new RuntimeException('La conexión $conn no es PDO.');
  }
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
  http_response_code(500);
  exit('Error de conexión: ' . $e->getMessage());
}

// ====== SESIÓN / ROLES ======
if (!isset($_SESSION['usuario'])) {
  http_response_code(401);
  exit('No autenticado.');
}
$ME       = $_SESSION['usuario'];
$ME_ID    = (int)($ME['id'] ?? 0);
$ME_ROLE  = strtoupper((string)($ME['permisos'] ?? 'PROFESIONAL'));
$ALLOWED  = ['ADMIN','DIRECTOR','PROFESIONAL'];
if (!$ME_ID || !in_array($ME_ROLE, $ALLOWED, true)) {
  http_response_code(403);
  exit('Sesión inválida o rol no permitido.');
}

// ====== CONSTANTES DE TABLAS/COLUMNAS ======
const TBL_USERS = '[dbo].[usuarios]';
const COL_ID    = '[Id_usuario]';
const COL_USER  = '[Nombre_usuario]';
const COL_PASS  = '[Contraseña]';
const COL_ROLE  = '[Permisos]';
const COL_PROF  = '[Id_profesional]';

const TBL_AUD   = '[dbo].[Auditoria]';
const A_COL_UID = '[Usuario_id]';
const A_COL_TAB = '[Tabla]';
const A_COL_RID = '[Registro_id]';
const A_COL_ACC = '[Accion]';
const A_COL_OLD = '[Datos_anteriores]';
const A_COL_NEW = '[Datos_nuevos]';
const A_COL_FE  = '[Fecha]';

// ====== HELPERS ======
function csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
  }
  return $_SESSION['csrf_token'];
}
function check_csrf(): void {
  if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(400);
    exit('CSRF inválido.');
  }
}

function fetchUser(PDO $db, int $id): ?array {
  $sql = "SELECT "
       . COL_ID   . " AS Id, "
       . COL_USER . " AS Usuario, "
       . COL_PASS . " AS Hash, "
       . COL_ROLE . " AS Rol, "
       . COL_PROF . " AS IdProf
          FROM " . TBL_USERS . " WHERE " . COL_ID . " = ?";
  $st = $db->prepare($sql);
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

function updatePasswordHash(PDO $db, int $id, string $newHash): bool {
  $sql = "UPDATE " . TBL_USERS . " SET " . COL_PASS . " = :hash WHERE " . COL_ID . " = :id";
  $st  = $db->prepare($sql);
  $ok  = $st->execute([':hash' => $newHash, ':id' => $id]);

  if ($ok && $st->rowCount() === 0) {
    // Si no afectó filas, verificar si el hash ya estaba igual
    $chk = $db->prepare("SELECT " . COL_PASS . " FROM " . TBL_USERS . " WHERE " . COL_ID . " = :id");
    $chk->execute([':id' => $id]);
    $curr = $chk->fetchColumn();
    return $curr === $newHash;
  }
  return $ok && $st->rowCount() > 0;
}

function audit(PDO $db, int $actorId, string $tabla, int $registroId, string $accion, array $old, array $new): void {
  // Truncar 'Accion' a 10 chars para evitar error por tamaño de columna (NVARCHAR(10) común)
  $accionShort = mb_substr($accion, 0, 10, 'UTF-8');
  $sql = "INSERT INTO " . TBL_AUD . " ("
       . A_COL_UID . ", " . A_COL_TAB . ", " . A_COL_RID . ", " . A_COL_ACC . ", "
       . A_COL_OLD . ", " . A_COL_NEW . ", " . A_COL_FE . ")
       VALUES (?, ?, ?, ?, ?, ?, ?)";
  $st = $db->prepare($sql);
  $st->execute([
    $actorId,
    $tabla,
    $registroId,
    $accionShort,
    json_encode($old, JSON_UNESCAPED_UNICODE),
    json_encode($new, JSON_UNESCAPED_UNICODE),
    date('Y-m-d H:i:s')
  ]);
}

function genTempPassword(int $len = 12): string {
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';
  $pwd = '';
  for ($i = 0; $i < $len; $i++) {
    $pwd .= $chars[random_int(0, strlen($chars) - 1)];
  }
  return $pwd;
}
function isAdminOrDirector(string $role): bool {
  return in_array($role, ['ADMIN','DIRECTOR'], true);
}

// ====== TARGET ======
$targetId  = isset($_GET['id']) ? (int)$_GET['id'] : $ME_ID;
$target    = fetchUser($conn, $targetId);
if (!$target) { http_response_code(404); exit('Usuario no encontrado.'); }

$defaultReturn = $target['IdProf'] ? ('index.php?seccion=perfil&Id_profesional=' . (int)$target['IdProf'])
                                   : ('index.php?seccion=perfil&uid=' . $targetId);
$returnUrl = $_GET['return'] ?? $defaultReturn;

$isOwn    = ($targetId === $ME_ID);
$copyUser = $target['Usuario']; // Para UI copiar
$copyPass = null;

// ====== ACCIONES ======
$successMsg = null;
$errorMsg   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  $op = $_POST['op'] ?? '';

  if ($op === 'change_own') {
    if (!$isOwn) {
      $errorMsg = 'Solo puedes cambiar tu propia contraseña.';
    } else {
      $current = trim((string)($_POST['current'] ?? ''));
      $new1    = trim((string)($_POST['new1'] ?? ''));
      $new2    = trim((string)($_POST['new2'] ?? ''));

      if ($new1 === '' || $new2 === '') {
        $errorMsg = 'Ingresa y confirma la nueva contraseña.';
      } elseif ($new1 !== $new2) {
        $errorMsg = 'La confirmación no coincide.';
      } elseif (strlen($new1) < 8) {
        $errorMsg = 'La nueva contraseña debe tener al menos 8 caracteres.';
      } elseif (!password_verify($current, $target['Hash'])) {
        $errorMsg = 'Tu contraseña actual no es correcta.';
      } else {
        $oldHash = $target['Hash'];
        $newHash = password_hash($new1, PASSWORD_DEFAULT);

        try {
          $conn->beginTransaction();
          $ok = updatePasswordHash($conn, $targetId, $newHash);
          if ($ok) {
            // Accion <= 10 chars
            audit($conn, $ME_ID, 'usuarios', $targetId, 'CHG_SELF',
                  ['Contraseña' => $oldHash],
                  ['Contraseña' => $newHash]);
            $conn->commit();
            $successMsg = 'Contraseña actualizada correctamente.';
            $copyPass   = $new1;        // para copiar en UI (no se guarda en texto plano)
            $target     = fetchUser($conn, $targetId); // refrescar
          } else {
            $conn->rollBack();
            $errorMsg = 'No se pudo actualizar la contraseña (sin filas afectadas). Revisa el tamaño de la columna.';
          }
        } catch (Throwable $ex) {
          if ($conn->inTransaction()) { $conn->rollBack(); }
          $errorMsg = 'Error al actualizar: ' . $ex->getMessage();
        }
      }
    }
  }

  if ($op === 'reset_other') {
    if ($isOwn) {
      $errorMsg = 'Esta acción es para restablecer contraseñas de otros usuarios.';
    } elseif (!isAdminOrDirector($ME_ROLE)) {
      $errorMsg = 'No tienes permisos para restablecer contraseñas ajenas.';
    } else {
      $temp     = genTempPassword(12);
      $newHash  = password_hash($temp, PASSWORD_DEFAULT);
      $oldHash  = $target['Hash'];

      try {
        $conn->beginTransaction();
        $ok = updatePasswordHash($conn, $targetId, $newHash);
        if ($ok) {
          // Accion <= 10 chars
          audit($conn, $ME_ID, 'usuarios', $targetId, 'RST_OTHER',
                ['Contraseña' => $oldHash],
                ['Contraseña' => $newHash]);
          $conn->commit();
          $successMsg = 'Se generó una contraseña temporal.';
          $copyPass   = $temp;      // mostrar para copiar
          $target     = fetchUser($conn, $targetId); // refrescar
        } else {
          $conn->rollBack();
          $errorMsg = 'No se pudo restablecer la contraseña (sin filas afectadas). Revisa el tamaño de la columna.';
        }
      } catch (Throwable $ex) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        $errorMsg = 'Error al restablecer: ' . $ex->getMessage();
      }
    }
  }
}

// ====== HTML / UI ======
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Administrar contraseña</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root { --bg:#0b1220; --card:#111a2b; --text:#e8eefc; --muted:#9fb0d6; --ok:#2ecc71; --err:#ff6b6b; --accent:#6ea8fe; }
body{margin:0;font-family:system-ui,Segoe UI,Roboto,Arial;background:var(--bg);color:var(--text)}
.wrap{max-width:880px;margin:28px auto;padding:0 16px}
.card{background:var(--card);border:1px solid #1f2a44;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.25);padding:22px}
h1{margin:0 0 12px;font-size:22px}
.sub{color:var(--muted);margin:0 0 18px}
.row{display:flex;gap:14px;flex-wrap:wrap}
.grow{flex:1}
label{display:block;margin:12px 0 6px;color:var(--muted);font-size:14px}
input[type="password"],input[type="text"]{width:100%;padding:12px 14px;border-radius:12px;border:1px solid #2a3860;background:#0c1528;color:var(--text);outline:none}
.btn{display:inline-flex;align-items:center;gap:8px;border:1px solid #2a3860;background:#122040;color:var(--text);padding:10px 14px;border-radius:12px;cursor:pointer;text-decoration:none}
.btn:hover{filter:brightness(1.1)}
.btn.ok{background:#14321f;border-color:#245b3b}
.btn.warn{background:#321616;border-color:#5b2424}
.btn.accent{background:#102344;border-color:#2f4f8f}
.spacer{height:14px}
.msg{padding:12px 14px;border-radius:12px;margin:10px 0}
.msg.ok{background:#12351f;border:1px solid #1f6d3b}
.msg.err{background:#3a1313;border:1px solid #7c2323}
.copybox{display:flex;gap:8px;margin-top:8px}
.copybox input{flex:1}
.muted{color:var(--muted);font-size:13px}
.hr{height:1px;background:#1f2a44;margin:18px 0}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="row" style="justify-content:space-between;align-items:center;">
      <div>
        <h1>Administrar contraseña</h1>
        <p class="sub">Usuario objetivo: <strong><?= htmlspecialchars($target['Usuario']) ?></strong> <?= $isOwn ? '(tu perfil)' : '' ?></p>
      </div>
      <div class="row">
        <a class="btn" href="<?= htmlspecialchars($returnUrl) ?>" onclick="return confirmarCancel();">← Volver</a>
        <button class="btn warn" onclick="if(confirm('¿Cancelar? Los cambios no se guardarán.')){ location.href='<?= htmlspecialchars($returnUrl) ?>'; }">Cancelar</button>
      </div>
    </div>

    <?php if ($successMsg): ?><div class="msg ok"><?= htmlspecialchars($successMsg) ?></div><?php endif; ?>
    <?php if ($errorMsg):   ?><div class="msg err"><?= htmlspecialchars($errorMsg)   ?></div><?php endif; ?>

    <?php if ($isOwn): ?>
      <!-- Cambiar mi contraseña -->
      <form method="post" autocomplete="off" spellcheck="false">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="op" value="change_own">
        <div class="row">
          <div class="grow">
            <label>Contraseña actual</label>
            <input type="password" name="current" required>
          </div>
        </div>
        <div class="row">
          <div class="grow">
            <label>Nueva contraseña (mín. 8)</label>
            <input type="password" name="new1" minlength="8" required>
          </div>
          <div class="grow">
            <label>Confirmar nueva contraseña</label>
            <input type="password" name="new2" minlength="8" required>
          </div>
        </div>
        <div class="spacer"></div>
        <div class="row">
          <button class="btn ok" type="submit">Guardar</button>
          <button class="btn" type="button" onclick="togglePw()">Mostrar/Ocultar</button>
        </div>
      </form>

      <div class="hr"></div>
      <div>
        <p class="muted">Puedes copiar tu usuario y, si acabas de cambiarla, tu nueva contraseña (solo visible ahora).</p>
        <label>Usuario</label>
        <div class="copybox">
          <input type="text" id="u-copy" value="<?= htmlspecialchars($copyUser) ?>" readonly>
          <button class="btn accent" type="button" onclick="copyFrom('u-copy')">Copiar usuario</button>
        </div>
        <?php if ($copyPass): ?>
          <label>Nueva contraseña</label>
          <div class="copybox">
            <input type="text" id="p-copy" value="<?= htmlspecialchars($copyPass) ?>" readonly>
            <button class="btn accent" type="button" onclick="copyFrom('p-copy')">Copiar contraseña</button>
          </div>
        <?php endif; ?>
      </div>

    <?php else: ?>
      <!-- Restablecer contraseña de otro (ADMIN / DIRECTOR) -->
      <?php if (isAdminOrDirector($ME_ROLE)): ?>
        <form method="post" onsubmit="return confirm('¿Generar una contraseña temporal para este usuario?');">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="op" value="reset_other">
          <button class="btn ok" type="submit">Generar nueva contraseña</button>
        </form>

        <div class="hr"></div>
        <div>
          <p class="muted">Comparte las credenciales temporalmente con el profesional.</p>
          <label>Usuario</label>
          <div class="copybox">
            <input type="text" id="u-copy" value="<?= htmlspecialchars($copyUser) ?>" readonly>
            <button class="btn accent" type="button" onclick="copyFrom('u-copy')">Copiar usuario</button>
          </div>

          <?php if ($copyPass): ?>
            <label>Contraseña generada (solo visible ahora)</label>
            <div class="copybox">
              <input type="text" id="p-copy" value="<?= htmlspecialchars($copyPass) ?>" readonly>
              <button class="btn accent" type="button" onclick="copyFrom('p-copy')">Copiar contraseña</button>
            </div>
          <?php else: ?>
            <p class="muted">Aún no has generado una nueva contraseña.</p>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="msg err">No tienes permisos para administrar la contraseña de este usuario.</div>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<script>
function copyFrom(id){
  const el = document.getElementById(id);
  el.select(); el.setSelectionRange(0,99999);
  document.execCommand('copy');
  const btn = event.target.closest('button');
  if (btn){ const old = btn.textContent; btn.textContent='Copiado ✓'; setTimeout(()=>btn.textContent=old, 1200); }
}
function confirmarCancel(){ return confirm('¿Cancelar? Los cambios no se guardarán.'); }
function togglePw(){
  document.querySelectorAll('input[type="password"]').forEach(i=>{
    i.type = (i.type==='password'?'text':'password');
  });
}
</script>
</body>
</html>
