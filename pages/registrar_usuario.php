<?php
// pages/registrar_usuario.php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auditoria.php';

if (!isset($_SESSION['usuario'])) { header("Location: ../login.php"); exit; }
$rol = strtoupper($_SESSION['usuario']['permisos'] ?? 'GUEST');
if (!in_array($rol, ['ADMIN','DIRECTOR'], true)) { http_response_code(403); exit('No autorizado'); }

// Catálogos auxiliares
$cargos = $conn->query("SELECT Nombre FROM cargos WHERE Activo=1 ORDER BY Nombre")->fetchAll(PDO::FETCH_COLUMN);
$afps   = $conn->query("SELECT Nombre FROM afps   WHERE Activo=1 ORDER BY Nombre")->fetchAll(PDO::FETCH_COLUMN);
$bancos = $conn->query("SELECT Nombre FROM bancos WHERE Activo=1 ORDER BY Nombre")->fetchAll(PDO::FETCH_COLUMN);
$escuelas = $conn->query("SELECT Id_escuela, Nombre_escuela FROM escuelas ORDER BY Nombre_escuela")->fetchAll();

$err = null; $ok = null;

function rolDesdeCargo(string $cargo): string {
  $c = mb_strtolower(trim($cargo));
  if ($c === 'administradora') return 'ADMIN';
  if ($c === 'directora') return 'DIRECTOR';
  return 'PROFESIONAL';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    // Campos obligatorios
    $usuarioLogin = trim($_POST['Nombre_usuario'] ?? '');
    $pwd          = trim($_POST['Contrasena'] ?? '');
    $nombre       = trim($_POST['Nombre_profesional'] ?? '');
    $apellido     = trim($_POST['Apellido_profesional'] ?? '');
    $rut          = trim($_POST['Rut_profesional'] ?? '');
    $cargo        = trim($_POST['Cargo_profesional'] ?? '');
    $escuelaId    = (int)($_POST['Id_escuela_prof'] ?? 0);

    if ($usuarioLogin===''||$pwd===''||$nombre===''||$apellido===''||$rut===''||$cargo===''||$escuelaId<=0) {
      throw new RuntimeException('Completa todos los campos marcados con *.'); // todos * en el form
    }

    // Derivar rol
    $permiso = rolDesdeCargo($cargo); // ADMIN / DIRECTOR / PROFESIONAL

    $conn->beginTransaction();

    // Insert profesional
    $sqlP = "INSERT INTO profesionales
      (Nombre_profesional, Apellido_profesional, Rut_profesional, Cargo_profesional, Id_escuela_prof,
       Banco_profesional, AFP_profesional, Tipo_profesional, Estado_civil_profesional)
      VALUES (?,?,?,?,?,?,?,?,?)";
    $stmtP = $conn->prepare($sqlP);
    $stmtP->execute([
      $nombre, $apellido, $rut, $cargo, $escuelaId,
      trim($_POST['Banco_profesional'] ?? '') ?: null,
      trim($_POST['AFP_profesional'] ?? '') ?: null,
      trim($_POST['Tipo_profesional'] ?? '') ?: null,
      trim($_POST['Estado_civil_profesional'] ?? '') ?: null
    ]);
    $idProf = (int)$conn->lastInsertId();

    registrarAuditoria($conn, (int)$_SESSION['usuario']['id'], 'profesionales', $idProf, 'INSERT', null, [
      'Nombre_profesional'=>$nombre, 'Apellido_profesional'=>$apellido, 'Rut_profesional'=>$rut,
      'Cargo_profesional'=>$cargo, 'Id_escuela_prof'=>$escuelaId
    ]);

    // Insert usuario (sin pedir Permisos al front)
    $hash = password_hash($pwd, PASSWORD_DEFAULT);
    $sqlU = "INSERT INTO usuarios (Nombre_usuario, Contraseña, Estado_usuario, Id_profesional, Permisos)
             VALUES (?,?,?,?,?)";
    $stmtU = $conn->prepare($sqlU);
    $stmtU->execute([$usuarioLogin, $hash, 1, $idProf, $permiso]);
    $idUser = (int)$conn->lastInsertId();

    registrarAuditoria($conn, (int)$_SESSION['usuario']['id'], 'usuarios', $idUser, 'INSERT', null, [
      'Nombre_usuario'=>$usuarioLogin, 'Estado_usuario'=>1, 'Id_profesional'=>$idProf, 'Permisos'=>$permiso
    ]);

    $conn->commit();
    $ok = 'Profesional y usuario creados correctamente. Rol asignado: ' . $permiso;
  } catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    $err = 'Error al registrar: ' . htmlspecialchars($e->getMessage());
  }
}
?>
<h2>Registrar Profesional (Usuario)</h2>
<?php if ($ok): ?><div class="alert alert-success"><?= $ok ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>

<form method="post" data-requires-confirm autocomplete="off">
  <div class="form-grid">
    <div class="form-group">
      <label>Usuario (login) <span class="text-danger">*</span></label>
      <input type="text" name="Nombre_usuario" required>
    </div>
    <div class="form-group">
      <label>Contraseña <span class="text-danger">*</span></label>
      <input type="password" name="Contrasena" minlength="8" required>
    </div>
    <div class="form-group">
      <label>Nombre <span class="text-danger">*</span></label>
      <input type="text" name="Nombre_profesional" required>
    </div>
    <div class="form-group">
      <label>Apellido <span class="text-danger">*</span></label>
      <input type="text" name="Apellido_profesional" required>
    </div>
    <div class="form-group">
      <label>RUT <span class="text-danger">*</span></label>
      <input type="text" name="Rut_profesional" required>
    </div>
    <div class="form-group">
      <label>Cargo <span class="text-danger">*</span></label>
      <select name="Cargo_profesional" required>
        <option value="">-- Selecciona --</option>
        <?php foreach ($cargos as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Escuela <span class="text-danger">*</span></label>
      <select name="Id_escuela_prof" required>
        <option value="">-- Selecciona --</option>
        <?php foreach ($escuelas as $e): ?>
          <option value="<?= (int)$e['Id_escuela'] ?>"><?= htmlspecialchars($e['Nombre_escuela']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Banco</label>
      <select name="Banco_profesional">
        <option value="">-- Selecciona --</option>
        <?php foreach ($bancos as $b): ?>
          <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>AFP</label>
      <select name="AFP_profesional">
        <option value="">-- Selecciona --</option>
        <?php foreach ($afps as $a): ?>
          <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Tipo de profesional</label>
      <input type="text" name="Tipo_profesional">
    </div>
    <div class="form-group">
      <label>Estado civil</label>
      <input type="text" name="Estado_civil_profesional">
    </div>
  </div>

  <button class="btn btn-primary" type="submit">Registrar</button>
</form>
