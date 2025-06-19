<?php
// create_profesional.php
require_once 'includes/db.php';
session_start();

// /* Descomenta en producción
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}
// */

// — Funciones de validación y formateo —
function cleanRut($rut) {
    return preg_replace('/[^0-9kK]/', '', $rut);
}

function dvRut($rut) {
    $RUT = cleanRut($rut);
    $digits = substr($RUT, 0, -1);
    $dv      = strtoupper(substr($RUT, -1));
    $sum = 0; $mult = 2;
    for ($i = strlen($digits) - 1; $i >= 0; $i--) {
        $sum += $digits[$i] * $mult;
        $mult = $mult < 7 ? $mult + 1 : 2;
    }
    $res = 11 - ($sum % 11);
    if ($res == 11) $expected = '0';
    elseif ($res == 10) $expected = 'K';
    else $expected = (string)$res;
    return $dv === $expected;
}

function formatRut($rut) {
    $R = cleanRut($rut);
    $number = substr($R, 0, -1);
    $dv     = strtoupper(substr($R, -1));
    return number_format($number, 0, ',', '.') . "-$dv";
}

function validPhone($phone) {
    return preg_match('/^\+?56[\s\-]?9[\s\-]?\d{4}[\s\-]?\d{4}$/', $phone);
}

// — Listados para selects —
$escuelas = ['Multiverso'=>2,'Sendero'=>1,'Luz de luna'=>3];
$tipos_prof = ['Docente','Administrativo','Asistente'];
$cargos     = [
    'Administradora','Directora',
    'Profesor(a) Diferencial','Profesor(a)',
    'Asistentes de la educación','Especialistas','Docente',
    'Psicologa','Fonoaudiologo','Kinesiologo','Terapeuta Ocupacional'
];
$bancos      = ['Banco Estado','Santander','Banco Falabella'];
$tipos_cta   = ['Corriente','Vista','Ahorro'];
$afps        = ['AFP Modelo','Habitat'];
$saludes     = ['FONASA','ISAPRE'];
$permisos    = ['Usuario','Administrador'];
$estados_civ = [
    'Soltero/a'                =>'Persona que no ha contraído matrimonio.',
    'Casado/a'                 =>'Vínculo matrimonial legalmente reconocido.',
    'Conviviente civil'        =>'Unión Civil legalmente reconocida en Chile.',
    'Separado/a judicialmente' =>'Sentencia judicial de separación.',
    'Divorciado/a'             =>'Divorcio legalizado.',
    'Viudo/a'                  =>'Cónyuge fallecido.'
];

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // — Captura y trim de inputs —
    $nombre      = trim($_POST['nombre']            ?? '');
    $apellido    = trim($_POST['apellido']          ?? '');
    $correo      = trim($_POST['correo']            ?? '');
    $telefono    = trim($_POST['telefono']          ?? '');
    $rut         = trim($_POST['rut']               ?? '');
    $nacimiento  = trim($_POST['fecha_nacimiento']  ?? '');
    $tipo_profes = $_POST['tipo_profesional']       ?? '';
    $cargo       = $_POST['cargo']                  ?? '';
    $horas       = intval($_POST['horas']           ?? 0);
    $fecha_ing   = trim($_POST['fecha_ingreso']     ?? '');
    $domicilio   = trim($_POST['domicilio']         ?? '');
    $estado_civ  = $_POST['estado_civil']           ?? '';
    $banco       = $_POST['banco']                  ?? '';
    $tipo_cta_sel= $_POST['tipo_cuenta']            ?? '';
    $cuenta      = trim($_POST['cuenta']            ?? '');
    $afp_sel     = $_POST['afp']                    ?? '';
    $salud_sel   = $_POST['salud']                  ?? '';
    $permiso     = $_POST['permiso']                ?? '';
    $escuela_sel = $_POST['escuela']                ?? '';

    // — Validaciones preliminares —
    if (!isset($escuelas[$escuela_sel])) {
        $message = "<p class='text-danger'>Escuela inválida.</p>";
    }
    elseif (!$nombre || !$apellido || !$correo || !$telefono || !$rut) {
        $message = "<p class='text-danger'>Complete todos los campos obligatorios.</p>";
    }
    elseif (!dvRut($rut)) {
        $message = "<p class='text-danger'>RUT inválido.</p>";
    }
    elseif (!validPhone($telefono)) {
        $message = "<p class='text-danger'>Teléfono debe ser '+56 9 XXXX XXXX'.</p>";
    }
    elseif (!in_array($tipo_profes, $tipos_prof, true)) {
        $message = "<p class='text-danger'>Tipo profesional inválido.</p>";
    }
    elseif (!in_array($cargo, $cargos, true)) {
        $message = "<p class='text-danger'>Cargo inválido.</p>";
    }
    elseif (!array_key_exists($estado_civ, $estados_civ)) {
        $message = "<p class='text-danger'>Estado civil inválido.</p>";
    }
    elseif (!in_array($banco, $bancos, true)
         || !in_array($tipo_cta_sel, $tipos_cta, true)
         || !in_array($afp_sel, $afps, true)
         || !in_array($salud_sel, $saludes, true)
         || !in_array($permiso, $permisos, true)
    ) {
        $message = "<p class='text-danger'>Datos de cuenta/banco/permisos inválidos.</p>";
    }
    else {
        // — Unicidad de RUT —
        $rut_fmt = formatRut($rut);
        $stmtRut = $conn->prepare("SELECT COUNT(*) FROM profesionales WHERE Rut_profesional = ?");
        $stmtRut->execute([$rut_fmt]);
        if ($stmtRut->fetchColumn() > 0) {
            $message = "<p class='text-danger'>Ya existe un profesional con RUT $rut_fmt.</p>";
        } else {
            // — Generar usuario único —
            list($n1) = explode(' ', $nombre);
            list($a1) = explode(' ', $apellido);
            $baseUser = strtolower("$n1.$a1");
            $usr = $baseUser;
            $i = 1;
            $stmtUsrCheck = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE Nombre_usuario = ?");
            while (true) {
                $stmtUsrCheck->execute([$usr]);
                if ($stmtUsrCheck->fetchColumn() == 0) break;
                $usr = $baseUser . $i++;
            }

            // — Contraseña y hash —
            $plainPwd = bin2hex(random_bytes(4)); // 8 hex chars
            $hashPwd  = password_hash($plainPwd, PASSWORD_DEFAULT);

            try {
                $conn->beginTransaction();

                // 1) Insertar en profesionales
                $stmt1 = $conn->prepare("
                    INSERT INTO profesionales (
                      Nombre_profesional, Apellido_profesional, Rut_profesional,
                      Nacimiento_profesional, Domicilio_profesional, Celular_profesional,
                      Correo_profesional, Estado_civil_profesional, Banco_profesional,
                      Tipo_cuenta_profesional, Cuenta_B_profesional, AFP_profesional,
                      Salud_profesional, Cargo_profesional, Horas_profesional,
                      Fecha_ingreso, Tipo_profesional, Id_escuela_prof
                    ) VALUES (
                      :nom, :ape, :rut, :nac,
                      :dom, :tel, :mail, :ec, :bco,
                      :tcta, :cta, :afp,
                      :sal, :car, :hrs,
                      :fing, :tprof, :idesc
                    )
                ");
                $stmt1->execute([
                    ':nom'   => $nombre,
                    ':ape'   => $apellido,
                    ':rut'   => $rut_fmt,
                    ':nac'   => $nacimiento,
                    ':dom'   => $domicilio,
                    ':tel'   => $telefono,
                    ':mail'  => $correo,
                    ':ec'    => $estado_civ,
                    ':bco'   => $banco,
                    ':tcta'  => $tipo_cta_sel,
                    ':cta'   => $cuenta,
                    ':afp'   => $afp_sel,
                    ':sal'   => $salud_sel,
                    ':car'   => $cargo,
                    ':hrs'   => $horas,
                    ':fing'  => $fecha_ing,
                    ':tprof' => $tipo_profes,
                    ':idesc' => $escuelas[$escuela_sel]
                ]);
                $id_prof = $conn->lastInsertId();

                // 2) Insertar en usuarios
                $stmt2 = $conn->prepare("
                    INSERT INTO usuarios (
                      Nombre_usuario, Contraseña, Estado_usuario,
                      Permisos, Id_profesional
                    ) VALUES (
                      :usr, :pwd, 1, :perm, :idp
                    )
                ");
                $stmt2->execute([
                    ':usr'  => $usr,
                    ':pwd'  => $hashPwd,
                    ':perm' => strtolower($permiso),
                    ':idp'  => $id_prof
                ]);
                $id_user = $conn->lastInsertId();

                // 3) Actualizar profesionales con su Id_usuario
                $stmt3 = $conn->prepare("
                    UPDATE profesionales
                    SET Id_usuario = ?
                    WHERE Id_profesional = ?
                ");
                $stmt3->execute([$id_user, $id_prof]);

                $conn->commit();

                $message = "<p class='text-success'>
                    Profesional y usuario creados.<br>
                    <strong>Usuario:</strong> $usr<br>
                    <strong>Contraseña:</strong> $plainPwd
                </p>";
            } catch (PDOException $e) {
                $conn->rollBack();
                $message = "<p class='text-danger'>Error al registrar: " 
                           . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registrar Profesional</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <h2>Registrar nuevo profesional</h2>
  <?= $message ?>

  <form method="POST" class="row g-3 needs-validation" novalidate>
    <div class="col-md-6"><label class="form-label">Nombres</label>
      <input name="nombre" class="form-control" required>
    </div>
    <div class="col-md-6"><label class="form-label">Apellidos</label>
      <input name="apellido" class="form-control" required>
    </div>
    <div class="col-md-4"><label class="form-label">Correo</label>
      <input name="correo" type="email" class="form-control" required>
    </div>
    <div class="col-md-4"><label class="form-label">Teléfono</label>
      <input name="telefono" class="form-control" placeholder="+56 9 1234 5678" required>
    </div>
    <div class="col-md-4"><label class="form-label">RUT</label>
      <input name="rut" class="form-control" placeholder="20.384.593-4" required>
    </div>
    <div class="col-md-4"><label class="form-label">Fecha nacimiento</label>
      <input name="fecha_nacimiento" type="date" class="form-control" required>
    </div>
    <div class="col-md-4"><label class="form-label">Tipo profesional</label>
      <select name="tipo_profesional" class="form-select" required>
        <option value="">Seleccione...</option>
        <?php foreach ($tipos_prof as $t): ?>
          <option><?= htmlspecialchars($t) ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <div class="col-md-4"><label class="form-label">Cargo</label>
      <select name="cargo" class="form-select" required>
        <option value="">Seleccione...</option>
        <?php foreach ($cargos as $c): ?>
          <option><?= htmlspecialchars($c) ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <div class="col-md-2"><label class="form-label">Horas</label>
      <input name="horas" type="number" class="form-control" min="0">
    </div>
    <div class="col-md-2"><label class="form-label">Ingreso</label>
      <input name="fecha_ingreso" type="date" class="form-control">
    </div>
    <div class="col-md-6"><label class="form-label">Domicilio</label>
      <input name="domicilio" class="form-control">
    </div>
    <div class="col-md-6"><label class="form-label">Estado civil</label>
      <select name="estado_civil" class="form-select" required>
        <option value="">Seleccione...</option>
        <?php foreach ($estados_civ as $ec => $desc): ?>
          <option value="<?= htmlspecialchars($ec) ?>" title="<?= htmlspecialchars($desc) ?>">
            <?= htmlspecialchars($ec) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>
    <div class="col-md-4"><label class="form-label">Banco</label>
      <select name="banco" class="form-select" required>
        <option value="">Seleccione...</option>
        <?php foreach ($bancos as $b): ?>
          <option><?= htmlspecialchars($b) ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <div class="col-md-4"><label class="form-label">Tipo de cuenta</label>
      <select name="tipo_cuenta" class="form-select" required>
        <option value="">Seleccione...</option>
        <?php foreach ($tipos_cta as $tc): ?>
          <option><?= htmlspecialchars($tc) ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <div class="col-md-4"><label class="form-label">N° de cuenta</label>
      <input name="cuenta" class="form-control" required>
    </div>
    <div class="col-md-4"><label class="form-label">AFP</label>
      <select name="afp" class="form-select" required>
        <option value="">Seleccione...</option>
        <?php foreach ($afps as $a): ?>
          <option><?= htmlspecialchars($a) ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <div class="col-md-4"><label class="form-label">Salud</label>
      <select name="salud" class="form-select" required>
        <option value="">Seleccione...</option>
        <?php foreach ($saludes as $s): ?>
          <option><?= htmlspecialchars($s) ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <div class="col-md-4"><label class="form-label">Permisos</label>
      <select name="permiso" class="form-select" required>
        <?php foreach ($permisos as $p): ?>
          <option><?= htmlspecialchars($p) ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <div class="col-md-4"><label class="form-label">Escuela</label>
      <select name="escuela" class="form-select" required>
        <option value="">Seleccione...</option>
        <?php foreach ($escuelas as $e => $id): ?>
          <option><?= htmlspecialchars($e) ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <div class="col-12"><button type="submit" class="btn btn-success">Guardar Datos</button></div>
  </form>
</body>
</html>
