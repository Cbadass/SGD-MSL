<?php
// pages/modificar_profesional.php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auditoria.php';
require_once __DIR__ . '/../includes/roles.php';

if (!isset($_SESSION['usuario'])) { http_response_code(401); exit('No autorizado'); }

$rolActual = strtoupper($_SESSION['usuario']['permisos'] ?? 'GUEST');
$idUsuarioSesion = (int)($_SESSION['usuario']['id'] ?? $_SESSION['usuario']['Id_usuario'] ?? 0);

$isAdmin      = ($rolActual === 'ADMIN');
$isDirector   = ($rolActual === 'DIRECTOR');
$isProfesional= ($rolActual === 'PROFESIONAL');

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

function profesionalDeUsuario(PDO $conn, int $idUsuario): ?int {
  $stmt = $conn->prepare("SELECT Id_profesional FROM usuarios WHERE Id_usuario = ?");
  $stmt->execute([$idUsuario]);
  $id = $stmt->fetchColumn();
  return $id ? (int)$id : null;
}

$escuelaDirectorId = $isDirector ? escuelaDeUsuario($conn, $idUsuarioSesion) : null;
$miProfesionalId   = profesionalDeUsuario($conn, $idUsuarioSesion);

// === Cargar catálogos ===
$cargos = $conn->query("SELECT Nombre FROM cargos WHERE Activo=1 ORDER BY Nombre")->fetchAll(PDO::FETCH_COLUMN);
$afps   = $conn->query("SELECT Nombre FROM afps   WHERE Activo=1 ORDER BY Nombre")->fetchAll(PDO::FETCH_COLUMN);
$bancos = $conn->query("SELECT Nombre FROM bancos WHERE Activo=1 ORDER BY Nombre")->fetchAll(PDO::FETCH_COLUMN);
$escuelasAll = $conn->query("SELECT Id_escuela, Nombre_escuela FROM escuelas ORDER BY Nombre_escuela")->fetchAll(PDO::FETCH_ASSOC);

// === Ámbito de selección (listado) ===
$where = '1=1';
$params = [];
if ($isDirector) { $where = 'p.Id_escuela_prof = ?'; $params[] = (int)$escuelaDirectorId; }
if ($isProfesional) { $where = 'p.Id_profesional = ?'; $params[] = (int)$miProfesionalId; }

$lista = $conn->prepare("
  SELECT p.Id_profesional, p.Nombre_profesional, p.Apellido_profesional, p.Correo_profesional, e.Nombre_escuela
  FROM profesionales p
  LEFT JOIN escuelas e ON e.Id_escuela = p.Id_escuela_prof
  WHERE $where
  ORDER BY p.Apellido_profesional, p.Nombre_profesional
");
$lista->execute($params);
$profesionales = $lista->fetchAll(PDO::FETCH_ASSOC);

// === Target a editar ===
$idProf = isset($_GET['id']) ? (int)$_GET['id'] : null;

// seguridad: solo puede abrir lo permitido en su scope
if ($idProf !== null) {
  $chk = $conn->prepare("
    SELECT p.*, u.Id_usuario AS U_Id_usuario, u.Permisos AS U_Permisos
    FROM profesionales p
    LEFT JOIN usuarios u ON u.Id_profesional = p.Id_profesional
    WHERE p.Id_profesional = ?
  ");
  $chk->execute([$idProf]);
  $row = $chk->fetch(PDO::FETCH_ASSOC);

  if (!$row) { http_response_code(404); exit('Profesional no encontrado'); }

  $okScope = $isAdmin
    || ($isDirector && (int)$row['Id_escuela_prof'] === (int)$escuelaDirectorId)
    || ($isProfesional && (int)$row['Id_profesional'] === (int)$miProfesionalId);

  if (!$okScope) { http_response_code(403); exit('Sin permisos para editar este perfil'); }
}

$err = null; $ok = null;

// Campos laborales: solo ADMIN puede cambiarlos
$laborFields = [
  'Cargo_profesional','Tipo_profesional','Id_escuela_prof',
  'Horas_profesional','Fecha_ingreso'
];
// Puedes decidir si Previsión/Banco son laborales. Aquí los tratamos como laborales también:
$laborFieldsExtra = ['AFP_profesional','Banco_profesional','Tipo_cuenta_profesional','Cuenta_B_profesional'];

// POST actualizar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $idProf !== null) {
  try {
    // Cargar estado anterior para auditoría
    $stmtOld = $conn->prepare("SELECT * FROM profesionales WHERE Id_profesional = ?");
    $stmtOld->execute([$idProf]);
    $old = $stmtOld->fetch(PDO::FETCH_ASSOC);
    if (!$old) { throw new RuntimeException('Profesional no encontrado'); }

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
      // laborales (se validan según rol)
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

    // Validaciones mínimas (obligatorios) – coherentes con registrar
    $oblig = ['Nombre_profesional','Apellido_profesional','Rut_profesional','Correo_profesional'];
    foreach ($oblig as $k) { if ($input[$k] === '' || $input[$k] === null) throw new RuntimeException("Campo obligatorio: $k"); }

    // Filtrar campos según rol
    $updates = $input;

    if (!$isAdmin) {
      // Quitar campos laborales si no es admin (no se actualizan)
      foreach (array_merge($laborFields, $laborFieldsExtra) as $lf) {
        unset($updates[$lf]);
      }
    } else {
      // ADMIN: si cambia Id_escuela_prof y es null o 0, error
      if (isset($updates['Id_escuela_prof']) && (int)$updates['Id_escuela_prof'] <= 0) {
        throw new RuntimeException('Escuela inválida.');
      }
    }

    // Si Director: forzar que no cambie de escuela (por seguridad)
    if ($isDirector) {
      // aunque venga el campo, lo ignoramos y lo pisamos por la suya
      // (pero arriba ya lo removimos del $updates)
      // Solo validar alcance de edición
      if ((int)$old['Id_escuela_prof'] !== (int)$escuelaDirectorId) {
        throw new RuntimeException('No puede mover este profesional de escuela.');
      }
    }

    // Construir UPDATE dinámico
    $set = [];
    $vals = [];
    foreach ($updates as $campo => $val) {
      if (!array_key_exists($campo, $old)) continue; // por seguridad
      if ($val === $old[$campo]) continue;           // sin cambios
      $set[] = "$campo = ?";
      $vals[] = $val === '' ? null : $val; // normaliza vacíos a NULL
    }

    if (!empty($set)) {
      $vals[] = $idProf;
      $conn->beginTransaction();

      $sqlUpd = "UPDATE profesionales SET ".implode(', ', $set)." WHERE Id_profesional = ?";
      $upd = $conn->prepare($sqlUpd);
      $upd->execute($vals);

      // Si ADMIN cambió el cargo -> recalcular Permisos
      if ($isAdmin && isset($input['Cargo_profesional']) && $input['Cargo_profesional'] !== '' && $input['Cargo_profesional'] !== $old['Cargo_profesional']) {
        $nuevoRol = rolDesdeCargo($input['Cargo_profesional']);
        $conn->prepare("UPDATE usuarios SET Permisos = ? WHERE Id_profesional = ?")->execute([$nuevoRol, $idProf]);

        // auditoría rol
        registrarAuditoria($conn, $idUsuarioSesion, 'usuarios', (int)$row['U_Id_usuario'], 'UPDATE',
          ['Permisos' => $row['U_Permisos']], ['Permisos' => $nuevoRol]);
      }

      // Auditoría de profesionales (solo campos cambiados)
      $newNow = $conn->prepare("SELECT * FROM profesionales WHERE Id_profesional = ?");
      $newNow->execute([$idProf]);
      $new = $newNow->fetch(PDO::FETCH_ASSOC);

      // Construir arrays old/new reducidos a cambios
      $oldDiff = []; $newDiff = [];
      foreach ($updates as $k => $_) {
        if (($old[$k] ?? null) != ($new[$k] ?? null)) {
          $oldDiff[$k] = $old[$k] ?? null;
          $newDiff[$k] = $new[$k] ?? null;
        }
      }
      registrarAuditoria($conn, $idUsuarioSesion, 'profesionales', $idProf, 'UPDATE', $oldDiff, $newDiff);

      $conn->commit();
      $ok = 'Perfil actualizado correctamente.';
      // refrescar $row
      $row = $new;
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

<?php if ($idProf === null): ?>
  <form class="mb-2">
    <label>Selecciona un profesional</label>
    <select name="id" class="form-select" onchange="this.form.submit()">
      <option value="">-- elegir --</option>
      <?php foreach ($profesionales as $p): ?>
        <option value="<?= (int)$p['Id_profesional'] ?>">
          <?= htmlspecialchars($p['Apellido_profesional'].' '.$p['Nombre_profesional'].' — '.$p['Correo_profesional'].' — '.($p['Nombre_escuela']??'')) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php if (empty($profesionales)): ?>
    <div class="alert alert-info">No hay profesionales en tu alcance.</div>
  <?php endif; ?>
<?php else: ?>
  <?php
    // Mostrar formulario del profesional seleccionado
    // $row cargado arriba
    $canEditLabor = $isAdmin; // solo admin
  ?>
  <form method="post" data-requires-confirm autocomplete="off">
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
    <fieldset>
      <legend>Datos laborales</legend>
      <div class="form-grid">
        <div class="form-group">
          <label>Cargo <?= $canEditLabor?'<span class="text-danger">*</span>':'' ?></label>
          <select name="Cargo_profesional" <?= $canEditLabor?'':'disabled' ?> <?= $canEditLabor?'required':'' ?>>
            <?php if (!$canEditLabor): ?>
              <option><?= htmlspecialchars($row['Cargo_profesional'] ?? '') ?></option>
            <?php else: ?>
              <option value="">-- Selecciona --</option>
              <?php foreach ($cargos as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= ($row['Cargo_profesional']??'')===$c?'selected':'' ?>>
                  <?= htmlspecialchars($c) ?>
                </option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Tipo de profesional <?= $canEditLabor?'<span class="text-danger">*</span>':'' ?></label>
          <?php
            // Si tienes la misma lista fija que en registrar, podrías reutilizarla
            $tiposPermitidos = [
              'Administradora','Directora','Profesor','Asistentes de la educación Especialistas','Otro'
            ];
          ?>
          <?php if ($canEditLabor): ?>
            <select name="Tipo_profesional" required>
              <option value="">-- Selecciona --</option>
              <?php foreach ($tiposPermitidos as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>" <?= ($row['Tipo_profesional']??'')===$t?'selected':'' ?>>
                  <?= htmlspecialchars($t) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input type="text" value="<?= htmlspecialchars($row['Tipo_profesional'] ?? '') ?>" disabled>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label>Horas (semanales)</label>
          <input type="number" name="Horas_profesional" min="0" step="1"
                 value="<?= htmlspecialchars($row['Horas_profesional'] ?? '') ?>" <?= $canEditLabor?'':'disabled' ?>>
        </div>

        <div class="form-group">
          <label>Fecha de ingreso</label>
          <input type="date" name="Fecha_ingreso"
                 value="<?= htmlspecialchars($row['Fecha_ingreso'] ?? '') ?>" <?= $canEditLabor?'':'disabled' ?>>
        </div>

        <div class="form-group">
          <label>Escuela <?= $canEditLabor?'<span class="text-danger">*</span>':'' ?></label>
          <?php if ($canEditLabor): ?>
            <select name="Id_escuela_prof" required>
              <option value="">-- Selecciona --</option>
              <?php foreach ($escuelasAll as $e): ?>
                <option value="<?= (int)$e['Id_escuela'] ?>" <?= ((int)($row['Id_escuela_prof']??0) === (int)$e['Id_escuela'])?'selected':'' ?>>
                  <?= htmlspecialchars($e['Nombre_escuela']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <?php
              $nombreEsc = '';
              foreach ($escuelasAll as $e) if ((int)$e['Id_escuela'] === (int)($row['Id_escuela_prof']??0)) { $nombreEsc=$e['Nombre_escuela']; break; }
            ?>
            <input type="text" value="<?= htmlspecialchars($nombreEsc) ?>" disabled>
          <?php endif; ?>
        </div>
      </div>
    </fieldset>

    <!-- PREVISIÓN Y BANCO (solo ADMIN editable, ver decisión arriba) -->
    <fieldset>
      <legend>Previsión y Bancos</legend>
      <div class="form-grid">
        <div class="form-group">
          <label>AFP <?= $canEditLabor?'<span class="text-danger">*</span>':'' ?></label>
          <?php if ($canEditLabor): ?>
            <select name="AFP_profesional" required>
              <option value="">-- Selecciona --</option>
              <?php foreach ($afps as $a): ?>
                <option value="<?= htmlspecialchars($a) ?>" <?= ($row['AFP_profesional']??'')===$a?'selected':'' ?>>
                  <?= htmlspecialchars($a) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input type="text" value="<?= htmlspecialchars($row['AFP_profesional'] ?? '') ?>" disabled>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label>Salud</label>
          <input type="text" name="Salud_profesional" value="<?= htmlspecialchars($row['Salud_profesional'] ?? '') ?>" <?= $canEditLabor?'':'disabled' ?>>
        </div>

        <div class="form-group">
          <label>Banco <?= $canEditLabor?'<span class="text-danger">*</span>':'' ?></label>
          <?php if ($canEditLabor): ?>
            <select name="Banco_profesional" required>
              <option value="">-- Selecciona --</option>
              <?php foreach ($bancos as $b): ?>
                <option value="<?= htmlspecialchars($b) ?>" <?= ($row['Banco_profesional']??'')===$b?'selected':'' ?>>
                  <?= htmlspecialchars($b) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input type="text" value="<?= htmlspecialchars($row['Banco_profesional'] ?? '') ?>" disabled>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label>Tipo de cuenta</label>
          <input type="text" name="Tipo_cuenta_profesional" value="<?= htmlspecialchars($row['Tipo_cuenta_profesional'] ?? '') ?>" <?= $canEditLabor?'':'disabled' ?>>
        </div>

        <div class="form-group">
          <label>N° Cuenta</label>
          <input type="text" name="Cuenta_B_profesional" value="<?= htmlspecialchars($row['Cuenta_B_profesional'] ?? '') ?>" <?= $canEditLabor?'':'disabled' ?>>
        </div>
      </div>
    </fieldset>

    <div class="mt-2">
      <button class="btn btn-primary" type="submit">Guardar cambios</button>
    </div>
  </form>
<?php endif; ?>
