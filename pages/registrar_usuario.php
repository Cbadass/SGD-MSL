<?php
// pages/registrar_usuario.php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auditoria.php';

if (!isset($_SESSION['usuario'])) { header("Location: ../login.php"); exit; }
$rolActual = strtoupper($_SESSION['usuario']['permisos'] ?? 'GUEST');
if (!in_array($rolActual, ['ADMIN','DIRECTOR'], true)) { http_response_code(403); exit('No autorizado'); }

/** ========================
 *  Utilidades
 *  ====================== */

/** Normaliza: minúsculas, sin acentos/espacios/ñ; permite [a-z0-9 .] */
function norm_slug($s) {
  $s = mb_strtolower(trim($s), 'UTF-8');
  $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n','Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n'];
  $s = strtr($s, $map);
  $s = preg_replace('/[^a-z0-9\.]/u', '', $s);
  return $s;
}

/** Deriva rol desde cargo */
function rolDesdeCargo(string $cargo): string {
  $c = mb_strtolower(trim($cargo));
  if ($c === 'administradora' || $c === 'administrador' || $c === 'administrador(a)') return 'ADMIN';
  if ($c === 'directora' || $c === 'director' || $c === 'director(a)') return 'DIRECTOR';
  return 'PROFESIONAL';
}

/** Username base desde correo: SOLO la parte antes del @ */
function generarUsuarioBaseDesdeCorreo(string $correo): string {
  $correo = trim($correo);
  $local = preg_split('/@/', $correo, 2)[0] ?? '';
  return norm_slug($local);
}

/** Asegura unicidad en usuarios.Nombre_usuario agregando sufijo numérico si hace falta */
function generarUsuarioUnico(PDO $conn, string $base): string {
  $user = $base;
  $i = 1;
  $stmt = $conn->prepare("SELECT 1 FROM usuarios WHERE Nombre_usuario = ?");
  while (true) {
    $stmt->execute([$user]);
    if (!$stmt->fetchColumn()) return $user;
    $i++;
    $user = $base . $i; // ana.perez2, ana.perez3, ...
  }
}

/** Id_escuela del usuario autenticado (si es director) */
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

/** ========================
 *  Catálogos
 *  ====================== */
$cargos = $conn->query("SELECT Nombre FROM cargos WHERE Activo=1 ORDER BY Nombre")->fetchAll(PDO::FETCH_COLUMN);
$afps   = $conn->query("SELECT Nombre FROM afps   WHERE Activo=1 ORDER BY Nombre")->fetchAll(PDO::FETCH_COLUMN);
$bancos = $conn->query("SELECT Nombre FROM bancos WHERE Activo=1 ORDER BY Nombre")->fetchAll(PDO::FETCH_COLUMN);

// Tipos de profesional PERMITIDOS (solo estos 5)
$tiposPermitidos = [
  'Administradora',
  'Directora',
  'Profesor',
  'Asistentes de la educación Especialistas',
  'Otro',
];

// Escuelas: ADMIN todas; DIRECTOR solo la suya
$escuelas = [];
$escuelaDirectorId = null;
$idUsuarioSesion = (int)($_SESSION['usuario']['id'] ?? $_SESSION['usuario']['Id_usuario'] ?? 0);

if ($rolActual === 'ADMIN') {
  $escuelas = $conn->query("SELECT Id_escuela, Nombre_escuela FROM escuelas ORDER BY Nombre_escuela")->fetchAll(PDO::FETCH_ASSOC);
} else { // DIRECTOR
  $escuelaDirectorId = escuelaDeUsuario($conn, $idUsuarioSesion);
  if ($escuelaDirectorId) {
    $stmt = $conn->prepare("SELECT Id_escuela, Nombre_escuela FROM escuelas WHERE Id_escuela = ?");
    $stmt->execute([$escuelaDirectorId]);
    $escuelas = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}

$err = null; $ok = null;

/** ========================
 *  POST: crear profesional + usuario (SIEMPRE contraseña temporal)
 *  ====================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    // ===== Obligatorios (según BD) =====
    $nombres     = trim($_POST['Nombre_profesional'] ?? '');
    $apellidos   = trim($_POST['Apellido_profesional'] ?? '');
    $rut         = trim($_POST['Rut_profesional'] ?? '');
    $correo      = trim($_POST['Correo_profesional'] ?? '');
    $cargo       = trim($_POST['Cargo_profesional'] ?? '');
    $tipoProf    = trim($_POST['Tipo_profesional'] ?? '');
    $banco       = trim($_POST['Banco_profesional'] ?? '');
    $afpSel      = trim($_POST['AFP_profesional'] ?? '');
    // Escuela: admin elige; director forzado a la suya
    $escuelaIdPost = (int)($_POST['Id_escuela_prof'] ?? 0);
    $escuelaId = ($rolActual === 'ADMIN') ? $escuelaIdPost : (int)$escuelaDirectorId;

    if ($nombres===''||$apellidos===''||$rut===''||$correo===''||$cargo===''||$tipoProf===''||$banco===''||$afpSel===''||$escuelaId<=0) {
      throw new RuntimeException('Completa todos los campos obligatorios (*).');
    }

    // Validar tipo_profesional contra la lista de permitidos
    if (!in_array($tipoProf, $tiposPermitidos, true)) {
      throw new RuntimeException('Tipo de profesional inválido.');
    }

    // ===== Adicionales =====
    $nacimiento  = $_POST['Nacimiento_profesional']     ?? null; // date
    $domicilio   = trim($_POST['Domicilio_profesional'] ?? '') ?: null;
    $celular     = trim($_POST['Celular_profesional']   ?? '') ?: null;
    $estadoCivil = trim($_POST['Estado_civil_profesional'] ?? '') ?: null;
    $tipoCuenta  = trim($_POST['Tipo_cuenta_profesional']  ?? '') ?: null;
    $numCuenta   = trim($_POST['Cuenta_B_profesional']     ?? '') ?: null;
    $salud       = trim($_POST['Salud_profesional']        ?? '') ?: null;
    $horas       = $_POST['Horas_profesional'] !== '' ? (int)$_POST['Horas_profesional'] : null;
    $fechaIng    = $_POST['Fecha_ingreso'] ?? null; // date

    // ===== Correo único en profesionales =====
    $q = $conn->prepare("SELECT COUNT(*) FROM profesionales WHERE Correo_profesional = ?");
    $q->execute([$correo]);
    if ($q->fetchColumn() > 0) {
      throw new RuntimeException('Ya existe un profesional con ese correo.');
    }

    // ===== Username desde correo (solo parte antes de @) y único =====
    $baseUser = generarUsuarioBaseDesdeCorreo($correo);
    if ($baseUser === '' || $baseUser === '.') {
      throw new RuntimeException('No fue posible construir el usuario desde el correo.');
    }
    $usuarioLogin = generarUsuarioUnico($conn, $baseUser);

    // ===== Contraseña temporal (siempre) =====
    $tempPwd = substr(bin2hex(random_bytes(10)), 0, 10); // 10 caracteres hex
    $hash = password_hash($tempPwd, PASSWORD_DEFAULT);

    // ===== Rol desde cargo =====
    $permiso = rolDesdeCargo($cargo);

    $conn->beginTransaction();

    // Insert profesional
    $sqlP = "INSERT INTO profesionales
      (Nombre_profesional, Apellido_profesional, Rut_profesional,
       Nacimiento_profesional, Domicilio_profesional, Celular_profesional, Correo_profesional,
       Estado_civil_profesional, Banco_profesional, Tipo_cuenta_profesional, Cuenta_B_profesional,
       AFP_profesional, Salud_profesional, Cargo_profesional, Horas_profesional, Fecha_ingreso,
       Tipo_profesional, Id_escuela_prof)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmtP = $conn->prepare($sqlP);
    $stmtP->execute([
      $nombres, $apellidos, $rut,
      $nacimiento ?: null, $domicilio, $celular, $correo,
      $estadoCivil, $banco, $tipoCuenta, $numCuenta,
      $afpSel, $salud, $cargo, $horas, $fechaIng ?: null,
      $tipoProf, $escuelaId
    ]);
    $idProf = (int)$conn->lastInsertId();

    registrarAuditoria($conn, (int)$idUsuarioSesion, 'profesionales', $idProf, 'INSERT', null, [
      'Nombre_profesional'=>$nombres, 'Apellido_profesional'=>$apellidos, 'Rut_profesional'=>$rut,
      'Correo_profesional'=>$correo, 'Cargo_profesional'=>$cargo, 'Id_escuela_prof'=>$escuelaId,
      'Tipo_profesional'=>$tipoProf
    ]);

    // Insert usuario (Estado 1=activo)
    $sqlU = "INSERT INTO usuarios (Nombre_usuario, Contraseña, Estado_usuario, Id_profesional, Permisos)
             VALUES (?,?,?,?,?)";
    $stmtU = $conn->prepare($sqlU);
    $stmtU->execute([$usuarioLogin, $hash, 1, $idProf, $permiso]);
    $idUser = (int)$conn->lastInsertId();

    // Vincular FK en profesionales si existe la columna
    try {
      $conn->prepare("UPDATE profesionales SET Id_usuario = ? WHERE Id_profesional = ?")
           ->execute([$idUser, $idProf]);
    } catch (Throwable $e) { /* opcional */ }

    registrarAuditoria($conn, (int)$idUsuarioSesion, 'usuarios', $idUser, 'INSERT', null, [
      'Nombre_usuario'=>$usuarioLogin, 'Estado_usuario'=>1, 'Id_profesional'=>$idProf, 'Permisos'=>$permiso
    ]);

    $conn->commit();

    // ===== Mensaje de credenciales + botón copiar =====
    $u = htmlspecialchars($usuarioLogin, ENT_QUOTES, 'UTF-8');
    $p = htmlspecialchars($tempPwd, ENT_QUOTES, 'UTF-8');
    $r = htmlspecialchars($permiso, ENT_QUOTES, 'UTF-8');

    $ok = '
    <div class="alert alert-success" role="alert">
      <div style="font-weight:600; margin-bottom:6px;">✅ Registro exitoso</div>
      <div class="cred-block" style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size:14px; line-height:1.4; padding:10px; border:1px solid rgba(0,0,0,0.1); border-radius:8px; background:rgba(0,0,0,0.03);">
        Usuario: <span class="cred-user">'.$u.'</span><br>
        Contraseña temporal: <span class="cred-pass">'.$p.'</span><br>
        Rol asignado: <span class="cred-role">'.$r.'</span>
      </div>
      <button type="button" class="btn btn-sm" id="copyCredsBtn"
        data-user="'.$u.'" data-pass="'.$p.'"
        style="margin-top:8px;">Copiar credenciales</button>
      <small style="display:block;margin-top:6px;opacity:.8;">Solicita el cambio de contraseña en el primer inicio de sesión.</small>
    </div>';
  } catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    $err = 'Error al registrar: ' . htmlspecialchars($e->getMessage());
  }
}
?>
<h2>Registrar Profesional (Usuario)</h2>
<?php if ($ok): ?><div><?= $ok ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>

<form method="post" data-requires-confirm autocomplete="off">
  <!-- ================= CUENTA ================= -->
  <fieldset>
    <legend>Cuenta</legend>
    <div class="form-grid">
      <div class="form-group">
        <label>Usuario</label>
        <input type="text" value="Se genera desde el correo (parte antes de @)" disabled>
      </div>
      <!-- Contraseña: siempre temporal (no se pide) -->
    </div>
  </fieldset>

  <!-- ================= DATOS PERSONALES ================= -->
  <fieldset>
    <legend>Datos personales</legend>
    <div class="form-grid">
      <div class="form-group">
        <label>Nombres <span class="text-danger">*</span></label>
        <input type="text" name="Nombre_profesional" required>
      </div>
      <div class="form-group">
        <label>Apellidos <span class="text-danger">*</span></label>
        <input type="text" name="Apellido_profesional" required>
      </div>
      <div class="form-group">
        <label>RUT <span class="text-danger">*</span></label>
        <input type="text" name="Rut_profesional" required>
      </div>
      <div class="form-group">
        <label>Fecha de nacimiento</label>
        <input type="date" name="Nacimiento_profesional">
      </div>
      <div class="form-group">
        <label>Estado civil</label>
        <input type="text" name="Estado_civil_profesional" placeholder="Soltera/o, Casada/o, etc.">
      </div>
    </div>
  </fieldset>

  <!-- ================= CONTACTO ================= -->
  <fieldset>
    <legend>Contacto</legend>
    <div class="form-grid">
      <div class="form-group">
        <label>Domicilio</label>
        <input type="text" name="Domicilio_profesional">
      </div>
      <div class="form-group">
        <label>Celular</label>
        <input type="text" name="Celular_profesional" placeholder="+56 9 ...">
      </div>
      <div class="form-group">
        <label>Correo electrónico <span class="text-danger">*</span></label>
        <input type="email" name="Correo_profesional" required>
      </div>
    </div>
  </fieldset>

  <!-- ================= LABORAL ================= -->
  <fieldset>
    <legend>Datos laborales</legend>
    <div class="form-grid">
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
        <label>Tipo de profesional <span class="text-danger">*</span></label>
        <select name="Tipo_profesional" required>
          <option value="">-- Selecciona --</option>
          <?php foreach ($tiposPermitidos as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Horas (semanales)</label>
        <input type="number" name="Horas_profesional" min="0" step="1" placeholder="Ej: 44">
      </div>

      <div class="form-group">
        <label>Fecha de ingreso</label>
        <input type="date" name="Fecha_ingreso">
      </div>

      <div class="form-group">
        <label>Escuela <span class="text-danger">*</span></label>
        <?php if ($rolActual === 'ADMIN'): ?>
          <select name="Id_escuela_prof" required>
            <option value="">-- Selecciona --</option>
            <?php foreach ($escuelas as $e): ?>
              <option value="<?= (int)$e['Id_escuela'] ?>"><?= htmlspecialchars($e['Nombre_escuela']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: // DIRECTOR: fija su escuela ?>
          <?php $e = $escuelas[0] ?? null; ?>
          <input type="text" value="<?= htmlspecialchars($e['Nombre_escuela'] ?? 'Escuela no asignada') ?>" disabled>
          <input type="hidden" name="Id_escuela_prof" value="<?= (int)($e['Id_escuela'] ?? 0) ?>">
        <?php endif; ?>
      </div>
    </div>
  </fieldset>

  <!-- ================= PREVISIÓN Y BANCO ================= -->
  <fieldset>
    <legend>Previsión y Bancos</legend>
    <div class="form-grid">
      <div class="form-group">
        <label>AFP <span class="text-danger">*</span></label>
        <select name="AFP_profesional" required>
          <option value="">-- Selecciona --</option>
          <?php foreach ($afps as $a): ?>
            <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Salud</label>
        <input type="text" name="Salud_profesional" placeholder="Fonasa/Isapre ...">
      </div>

      <div class="form-group">
        <label>Banco <span class="text-danger">*</span></label>
        <select name="Banco_profesional" required>
          <option value="">-- Selecciona --</option>
          <?php foreach ($bancos as $b): ?>
            <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Tipo de cuenta</label>
        <input type="text" name="Tipo_cuenta_profesional" placeholder="Corriente, Vista, RUT, Ahorro...">
      </div>

      <div class="form-group">
        <label>N° Cuenta</label>
        <input type="text" name="Cuenta_B_profesional">
      </div>
    </div>
  </fieldset>

  <div class="mt-2">
    <button class="btn btn-primary" type="submit">Registrar</button>
  </div>
</form>

<script>
  // Copiar credenciales al portapapeles
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
