<?php
// pages/modificar_profesional.php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auditoria.php';
require_once __DIR__ . '/../includes/roles.php';

if (!isset($_SESSION['usuario'])) { http_response_code(401); exit('No autorizado'); }

$rolActual        = strtoupper($_SESSION['usuario']['permisos'] ?? 'GUEST');
$idUsuarioSesion  = (int)($_SESSION['usuario']['id'] ?? $_SESSION['usuario']['Id_usuario'] ?? 0);

// --- Helpers de alcance
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
function profesionalDeUsuario(PDO $conn, int $idUsuario): ?int {
  $stmt = $conn->prepare("SELECT Id_profesional FROM usuarios WHERE Id_usuario = ?");
  $stmt->execute([$idUsuario]);
  $id = $stmt->fetchColumn();
  return $id ? (int)$id : null;
}

// --- Catálogos
$cargos = $conn->query("SELECT Nombre FROM cargos WHERE Activo=1 ORDER BY Nombre")->fetchAll(PDO::FETCH_COLUMN);
$afps   = $conn->query("SELECT Nombre FROM afps   WHERE Activo=1 ORDER BY Nombre")->fetchAll(PDO::FETCH_COLUMN);
$bancos = $conn->query("SELECT Nombre FROM bancos WHERE Activo=1 ORDER BY Nombre")->fetchAll(PDO::FETCH_COLUMN);
$escuelasAll = $conn->query("SELECT Id_escuela, Nombre_escuela FROM escuelas ORDER BY Nombre_escuela")->fetchAll(PDO::FETCH_ASSOC);

// Tipos de profesional permitidos (como en RegistrarUsuario)
$tiposPermitidos = [
  'Administradora',
  'Directora',
  'Profesor',
  'Asistentes de la educación Especialistas',
  'Otro',
];

// --- Obtener el objetivo a editar (RESPETA TU ENLACE ANTIGUO)
$idProf = (int)($_GET['Id_profesional'] ?? $_POST['Id_profesional'] ?? 0);
if ($idProf <= 0) {
  http_response_code(400);
  echo '<div class="alert alert-warning">Falta el parámetro <code>Id_profesional</code> en la URL.</div>';
  exit;
}

// --- Cargar el profesional
$stmt = $conn->prepare("
  SELECT p.*, u.Id_usuario AS U_Id_usuario, u.Permisos AS U_Permisos
  FROM profesionales p
  LEFT JOIN usuarios u ON u.Id_profesional = p.Id_profesional
  WHERE p.Id_profesional = ?
");
$stmt->execute([$idProf]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); exit('Profesional no encontrado'); }

// --- Alcance por rol
$actorEscuelaId  = escuelaDeUsuario($conn, $idUsuarioSesion);
$actorProfId     = profesionalDeUsuario($conn, $idUsuarioSesion);
$targetEscuelaId = (int)($row['Id_escuela_prof'] ?? 0);
$targetUserId    = (int)($row['U_Id_usuario'] ?? 0);

$puedeEditarPerfil = canEditProfile(
  $rolActual,
  $idUsuarioSesion,            // actorUserId
  $targetUserId ?: null,       // targetUserId
  $actorEscuelaId,             // actorSchoolId
  $targetEscuelaId ?: null     // targetSchoolId
);

if (!$puedeEditarPerfil) { http_response_code(403); exit('Sin permisos para editar este perfil'); }

$soloAdminLabor = !canEditLabor($rolActual); // si no es admin, bloquea campos laborales

$err = null; $ok = null;

// Campos laborales (solo ADMIN puede cambiarlos)
$laborFields = [
  'Cargo_profesional','Tipo_profesional','Id_escuela_prof',
  'Horas_profesional','Fecha_ingreso',
  // Previsión/Banco los tratamos como laborales también:
  'AFP_profesional','Banco_profesional','Tipo_cuenta_profesional','Cuenta_B_profesional',
];

// --- POST: actualizar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    // estado anterior para auditoría
    $stmtOld = $conn->prepare("SELECT * FROM profesionales WHERE Id_profesional = ?");
    $stmtOld->execute([$idProf]);
    $old = $stmtOld->fetch(PDO::FETCH_ASSOC);
    if (!$old) throw new RuntimeException('Profesional no encontrado.');

    // Inputs
    $input = [
      'Nombre_profesional'      => trim($_POST['Nombre_profesional'] ?? ''),
      'Apellido_profesional'    => trim($_POST['Apellido_profesional'] ?? ''),
      'Rut_profesional'         => trim($_POST['Rut_profesional'] ?? ''),
      'Nacimiento_profesional'  => $_POST['Nacimiento_profesional'] ?? null,
      'Domicilio_profesional'   => trim($_POST['Domicilio_profesional'] ?? ''),
      'Celular_profesional'     => trim($_POST['Celular_profesional'] ?? ''),
      'Correo_profesional'      => trim($_POST['Correo_profesional'] ?? ''),
      'Estado_civil_profesional'=> trim($_POST['Estado_civil_profesional'] ?? ''),
      // laborales (serán filtrados si no es admin)
      'Cargo_profesional'       => trim($_POST['Cargo_profesional'] ?? ''),
      'Tipo_profesional'        => trim($_POST['Tipo_profesional'] ?? ''),
      'Id_escuela_prof'         => isset($_POST['Id_escuela_prof']) ? (int)$_POST['Id_escuela_prof'] : null,
      'Horas_profesional'       => ($_POST['Horas_profesional'] ?? '') === '' ? null : (int)$_POST['Horas_profesional'],
      'Fecha_ingreso'           => $_POST['Fecha_ingreso'] ?? null,
      'AFP_profesional'         => trim($_POST['AFP_profesional'] ?? ''),
      'Banco_profesional'       => trim($_POST['Banco_profesional'] ?? ''),
      'Tipo_cuenta_profesional' => trim($_POST['Tipo_cuenta_profesional'] ?? ''),
      'Cuenta_B_profesional'    => trim($_POST['Cuenta_B_profesional'] ?? ''),
      'Salud_profesional'       => trim($_POST['Salud_profesional'] ?? ''),
    ];

    // Obligatorios básicos
    foreach (['Nombre_profesional','Apellido_profesional','Rut_profesional','Correo_profesional'] as $k) {
      if ($input[$k] === '' || $input[$k] === null) throw new RuntimeException("Campo obligatorio: $k");
    }

    // Si no es admin → eliminar de la actualización los campos laborales
    if ($soloAdminLabor) {
      foreach ($laborFields as $lf) unset($input[$lf]);
    } else {
      // ADMIN: validaciones mínimas
      if (isset($input['Id_escuela_prof']) && (int)$input['Id_escuela_prof'] <= 0) {
        throw new RuntimeException('Escuela inválida.');
      }
      // Tipo_profesional debe estar en la lista
      if ($input['Tipo_profesional'] !== '' && !in_array($input['Tipo_profesional'], $tiposPermitidos, true)) {
        throw new RuntimeException('Tipo de profesional inválido.');
      }
    }

    // Construir SET dinámico solo con cambios
    $set = []; $vals = [];
    foreach ($input as $campo => $val) {
      if (!array_key_exists($campo, $old)) continue;
      // normaliza '' a NULL
      $valNorm = ($val === '') ? null : $val;
      // compara con anterior
      if (($old[$campo] ?? null) != $valNorm) {
        $set[]  = "$campo = ?";
        $vals[] = $valNorm;
      }
    }

    if (!empty($set)) {
      $vals[] = $idProf;
      $conn->beginTransaction();

      $sqlUpd = "UPDATE profesionales SET ".implode(', ', $set)." WHERE Id_profesional = ?";
      $upd = $conn->prepare($sqlUpd);
      $upd->execute($vals);

      // === FIX AUDITORÍA: tomar permiso anterior ANTES del UPDATE a usuarios
      // Si ADMIN cambió el cargo -> recalcular Permisos del usuario vinculado
      if (!$soloAdminLabor && isset($_POST['Cargo_profesional']) && $_POST['Cargo_profesional'] !== '' && $_POST['Cargo_profesional'] !== $old['Cargo_profesional']) {
        $nuevoRol   = rolDesdeCargo($_POST['Cargo_profesional']);
        if (!rolValido($nuevoRol)) { $nuevoRol = 'PROFESIONAL'; }

        // Tomar el permiso ANTERIOR y el Id_usuario ANTES de actualizar
        $oldPerm    = $row['U_Permisos'] ?? null;
        $targetUid  = (int)($row['U_Id_usuario'] ?? 0);

        // Actualizar rol en usuarios
        $conn->prepare("UPDATE usuarios SET Permisos = ? WHERE Id_profesional = ?")->execute([$nuevoRol, $idProf]);

        // Auditoría correcta: old -> new
        if ($targetUid > 0) {
          registrarAuditoria(
            $conn,
            $idUsuarioSesion,
            'usuarios',
            $targetUid,
            'UPDATE',
            ['Permisos' => $oldPerm],
            ['Permisos' => $nuevoRol]
          );
        }
      }

      // Auditoría de profesionales (antes / después)
      $stmtNew = $conn->prepare("SELECT * FROM profesionales WHERE Id_profesional = ?");
      $stmtNew->execute([$idProf]);
      $new = $stmtNew->fetch(PDO::FETCH_ASSOC);

      // Solo registrar diferencias
      $oldDiff = []; $newDiff = [];
      foreach ($input as $k => $_) {
        if (($old[$k] ?? null) != ($new[$k] ?? null)) {
          $oldDiff[$k] = $old[$k] ?? null;
          $newDiff[$k] = $new[$k] ?? null;
        }
      }
      registrarAuditoria($conn, $idUsuarioSesion, 'profesionales', $idProf, 'UPDATE', $oldDiff, $newDiff);

      $conn->commit();
      $ok = 'Perfil actualizado correctamente.';
      $row = $new; // refrescar
    } else {
      $ok = 'No hubo cambios para guardar.';
    }

  } catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    $err = 'Error al actualizar: ' . htmlspecialchars($e->getMessage());
  }
}
?>
<h2>Modificar Profesional</h2>

<?php if ($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>
<?php if ($ok):  ?><div class="alert alert-success"><?= $ok ?></div><?php endif; ?>

<form method="post" data-requires-confirm autocomplete="off">
  <input type="hidden" name="Id_profesional" value="<?= (int)$idProf ?>">

  <!-- DATOS PERSONALES -->
  <fieldset>
    <legend>Datos personales</legend>
    <div class="form-grid">
      <div class="form-group">
        <label>Nombres <span class="text-danger">*</span></label>
        <input type="text" name="Nombre_profesional" value="<?= htmlspecialchars($row['Nombre_profesional'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Apellidos <span class="text-danger">*</span></label>
        <input type="text" name="Apellido_profesional" value="<?= htmlspecialchars($row['Apellido_profesional'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>RUT <span class="text-danger">*</span></label>
        <input type="text" name="Rut_profesional" value="<?= htmlspecialchars($row['Rut_profesional'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Fecha de nacimiento</label>
        <input type="date" name="Nacimiento_profesional" value="<?= htmlspecialchars($row['Nacimiento_profesional'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Estado civil</label>
        <input type="text" name="Estado_civil_profesional" value="<?= htmlspecialchars($row['Estado_civil_profesional'] ?? '') ?>">
      </div>
    </div>
  </fieldset>

  <!-- CONTACTO -->
  <fieldset>
    <legend>Contacto</legend>
    <div class="form-grid">
      <div class="form-group">
        <label>Domicilio</label>
        <input type="text" name="Domicilio_profesional" value="<?= htmlspecialchars($row['Domicilio_profesional'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Celular</label>
        <input type="text" name="Celular_profesional" value="<?= htmlspecialchars($row['Celular_profesional'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Correo electrónico <span class="text-danger">*</span></label>
        <input type="email" name="Correo_profesional" value="<?= htmlspecialchars($row['Correo_profesional'] ?? '') ?>" required>
      </div>
    </div>
  </fieldset>

  <!-- DATOS LABORALES (solo ADMIN editable) -->
  <?php $canEditLabor = !$soloAdminLabor; ?>
  <fieldset>
    <legend>Datos laborales</legend>
    <div class="form-grid">
      <div class="form-group">
        <label>Cargo <?= $canEditLabor?'<span class="text-danger">*</span>':'' ?></label>
        <?php if ($canEditLabor): ?>
          <select name="Cargo_profesional" required title="Solo editable por Administrador">
            <option value="">-- Selecciona --</option>
            <?php foreach ($cargos as $c): ?>
              <option value="<?= htmlspecialchars($c) ?>" <?= ($row['Cargo_profesional']??'')===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input type="text" value="<?= htmlspecialchars($row['Cargo_profesional'] ?? '') ?>" disabled title="Solo editable por Administrador">
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label>Tipo de profesional <?= $canEditLabor?'<span class="text-danger">*</span>':'' ?></label>
        <?php if ($canEditLabor): ?>
          <select name="Tipo_profesional" required title="Solo editable por Administrador">
            <option value="">-- Selecciona --</option>
            <?php foreach ($tiposPermitidos as $t): ?>
              <option value="<?= htmlspecialchars($t) ?>" <?= ($row['Tipo_profesional']??'')===$t?'selected':'' ?>><?= htmlspecialchars($t) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input type="text" value="<?= htmlspecialchars($row['Tipo_profesional'] ?? '') ?>" disabled title="Solo editable por Administrador">
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label>Horas (semanales)</label>
        <input type="number" name="Horas_profesional" min="0" step="1"
               value="<?= htmlspecialchars($row['Horas_profesional'] ?? '') ?>" <?= $canEditLabor?'':'disabled' ?> title="<?= $canEditLabor?'':'Solo editable por Administrador' ?>">
      </div>

      <div class="form-group">
        <label>Fecha de ingreso</label>
        <input type="date" name="Fecha_ingreso"
               value="<?= htmlspecialchars($row['Fecha_ingreso'] ?? '') ?>" <?= $canEditLabor?'':'disabled' ?> title="<?= $canEditLabor?'':'Solo editable por Administrador' ?>">
      </div>

      <div class="form-group">
        <label>Escuela <?= $canEditLabor?'<span class="text-danger">*</span>':'' ?></label>
        <?php if ($canEditLabor): ?>
          <select name="Id_escuela_prof" required title="Solo editable por Administrador">
            <option value="">-- Selecciona --</option>
            <?php foreach ($escuelasAll as $e): ?>
              <option value="<?= (int)$e['Id_escuela'] ?>" <?= ((int)($row['Id_escuela_prof']??0)===(int)$e['Id_escuela'])?'selected':'' ?>>
                <?= htmlspecialchars($e['Nombre_escuela']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <?php
            $nombreEsc = '';
            foreach ($escuelasAll as $e) if ((int)$e['Id_escuela'] === (int)($row['Id_escuela_prof']??0)) { $nombreEsc=$e['Nombre_escuela']; break; }
          ?>
          <input type="text" value="<?= htmlspecialchars($nombreEsc) ?>" disabled title="Solo editable por Administrador">
        <?php endif; ?>
      </div>
    </div>
  </fieldset>

  <!-- PREVISIÓN Y BANCO (considerados laborales → solo ADMIN) -->
  <fieldset>
    <legend>Previsión y Bancos</legend>
    <div class="form-grid">
      <div class="form-group">
        <label>AFP <?= $canEditLabor?'<span class="text-danger">*</span>':'' ?></label>
        <?php if ($canEditLabor): ?>
          <select name="AFP_profesional" required title="Solo editable por Administrador">
            <option value="">-- Selecciona --</option>
            <?php foreach ($afps as $a): ?>
              <option value="<?= htmlspecialchars($a) ?>" <?= ($row['AFP_profesional']??'')===$a?'selected':'' ?>><?= htmlspecialchars($a) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input type="text" value="<?= htmlspecialchars($row['AFP_profesional'] ?? '') ?>" disabled title="Solo editable por Administrador">
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label>Salud</label>
        <input type="text" name="Salud_profesional" value="<?= htmlspecialchars($row['Salud_profesional'] ?? '') ?>" <?= $canEditLabor?'':'disabled' ?> title="<?= $canEditLabor?'':'Solo editable por Administrador' ?>">
      </div>

      <div class="form-group">
        <label>Banco <?= $canEditLabor?'<span class="text-danger">*</span>':'' ?></label>
        <?php if ($canEditLabor): ?>
          <select name="Banco_profesional" required title="Solo editable por Administrador">
            <option value="">-- Selecciona --</option>
            <?php foreach ($bancos as $b): ?>
              <option value="<?= htmlspecialchars($b) ?>" <?= ($row['Banco_profesional']??'')===$b?'selected':'' ?>><?= htmlspecialchars($b) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input type="text" value="<?= htmlspecialchars($row['Banco_profesional'] ?? '') ?>" disabled title="Solo editable por Administrador">
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label>Tipo de cuenta</label>
        <input type="text" name="Tipo_cuenta_profesional" value="<?= htmlspecialchars($row['Tipo_cuenta_profesional'] ?? '') ?>" <?= $canEditLabor?'':'disabled' ?> title="<?= $canEditLabor?'':'Solo editable por Administrador' ?>">
      </div>

      <div class="form-group">
        <label>N° Cuenta</label>
        <input type="text" name="Cuenta_B_profesional" value="<?= htmlspecialchars($row['Cuenta_B_profesional'] ?? '') ?>" <?= $canEditLabor?'':'disabled' ?> title="<?= $canEditLabor?'':'Solo editable por Administrador' ?>">
      </div>
    </div>
  </fieldset>

  <div class="mt-2">
    <button class="btn btn-primary" type="submit">Guardar cambios</button>
  </div>
</form>
