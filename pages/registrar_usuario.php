<?php
// pages/registrar_usuario.php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/db.php'; // Debe definir $conn (PDO)

if (!isset($_SESSION['usuario'])) {
  http_response_code(401);
  exit('No autenticado.');
}

try { $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Throwable $e) {}

function nvl($v){ $v = trim((string)$v); return $v === '' ? null : $v; }
function genPwd($len=10){
  $c='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';
  $s=''; for($i=0;$i<$len;$i++) $s.=$c[random_int(0,strlen($c)-1)];
  return $s;
}
function getEscuelas(PDO $db){
  try{
    $st=$db->query("SELECT Id_escuela, Nombre_escuela FROM dbo.escuelas ORDER BY Nombre_escuela");
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }catch(Throwable $e){ return []; }
}

$bancos = [
 'Banco de Chile','BancoEstado','Banco Santander Chile','Scotiabank Chile','Bci',
 'Itaú Chile','Banco Security','Banco Falabella','Banco Ripley','Banco Consorcio',
 'Banco Internacional','Banco BICE','Banco BTG Pactual Chile','HSBC Bank Chile'
];
$afps = ['AFP Habitat','AFP Provida','AFP Capital','AFP Cuprum','AFP Modelo','AFP PlanVital','AFP Uno'];

$escuelas = getEscuelas($conn);

$msg = null;
$ok  = false;
$panelUsuario = $panelCorreo = $panelPass = null;

// === GUARDAR ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Datos del profesional (opcionales varios)
  $Nombre_profesional      = nvl($_POST['Nombre_profesional'] ?? '');
  $Apellido_profesional    = nvl($_POST['Apellido_profesional'] ?? '');
  $Rut_profesional         = nvl($_POST['Rut_profesional'] ?? '');
  $Nacimiento_profesional  = nvl($_POST['Nacimiento_profesional'] ?? '');
  $Domicilio_profesional   = nvl($_POST['Domicilio_profesional'] ?? '');
  $Celular_profesional     = nvl($_POST['Celular_profesional'] ?? '');
  $Correo_profesional      = nvl($_POST['Correo_profesional'] ?? '');
  $Estado_civil_profesional= nvl($_POST['Estado_civil_profesional'] ?? '');
  $Banco_profesional       = nvl($_POST['Banco_profesional'] ?? '');
  $Tipo_cuenta_profesional = nvl($_POST['Tipo_cuenta_profesional'] ?? '');
  $Cuenta_B_profesional    = nvl($_POST['Cuenta_B_profesional'] ?? '');
  $AFP_profesional         = nvl($_POST['AFP_profesional'] ?? '');
  $Salud_profesional       = nvl($_POST['Salud_profesional'] ?? '');
  $Cargo_profesional       = nvl($_POST['Cargo_profesional'] ?? '');
  $Horas_profesional       = nvl($_POST['Horas_profesional'] ?? '');
  $Fecha_ingreso           = nvl($_POST['Fecha_ingreso'] ?? '');
  $Tipo_profesional        = nvl($_POST['Tipo_profesional'] ?? '');
  $Id_escuela_prof         = nvl($_POST['Id_escuela_prof'] ?? '');

  // Usuario
  $Nombre_usuario = trim((string)($_POST['Nombre_usuario'] ?? ''));
  $Permisos = strtoupper(trim((string)($_POST['Permisos'] ?? 'PROFESIONAL')));
  if (!in_array($Permisos, ['ADMIN','DIRECTOR','PROFESIONAL'], true)) $Permisos = 'PROFESIONAL';

  if ($Nombre_usuario === '') {
    $msg = 'Debes ingresar el nombre de usuario.';
  } else {
    try {
      $conn->beginTransaction();

      // Insert profesional
      $sqlP = "INSERT INTO dbo.profesionales
        (Nombre_profesional, Apellido_profesional, Rut_profesional, Nacimiento_profesional,
         Domicilio_profesional, Celular_profesional, Correo_profesional, Estado_civil_profesional,
         Banco_profesional, Tipo_cuenta_profesional, Cuenta_B_profesional, AFP_profesional,
         Salud_profesional, Cargo_profesional, Horas_profesional, Fecha_ingreso, Tipo_profesional,
         Id_escuela_prof)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
      $stP = $conn->prepare($sqlP);
      $stP->execute([
        $Nombre_profesional, $Apellido_profesional, $Rut_profesional, $Nacimiento_profesional,
        $Domicilio_profesional, $Celular_profesional, $Correo_profesional, $Estado_civil_profesional,
        $Banco_profesional, $Tipo_cuenta_profesional, $Cuenta_B_profesional, $AFP_profesional,
        $Salud_profesional, $Cargo_profesional, $Horas_profesional, $Fecha_ingreso, $Tipo_profesional,
        $Id_escuela_prof
      ]);
      $id_prof = (int)$conn->lastInsertId();

      // Generar contraseña y crear usuario
      $pwdPlano = genPwd(10);
      $hash = password_hash($pwdPlano, PASSWORD_DEFAULT);

      $sqlU = "INSERT INTO dbo.usuarios (Nombre_usuario, Contraseña, Estado_usuario, Id_profesional, Permisos)
               VALUES (?,?,?,?,?)";
      $stU = $conn->prepare($sqlU);
      $stU->execute([$Nombre_usuario, $hash, 1, $id_prof, $Permisos]);

      $conn->commit();
      $ok = true;
      $msg = 'Profesional y usuario creados.';

      // Panel de copiado
      $panelUsuario = $Nombre_usuario;
      $panelCorreo  = $Correo_profesional ?? '';
      $panelPass    = $pwdPlano;

    } catch (Throwable $e) {
      if ($conn->inTransaction()) $conn->rollBack();
      $msg = 'Error al registrar: ' . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Registrar profesional y usuario</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body class="<?= ($_COOKIE['modo_oscuro'] ?? 'false') === 'true' ? 'dark-mode' : '' ?>">
<div class="container">

  <h2 class="mb-3">Registrar profesional y usuario</h2>

  <?php if ($msg): ?>
    <div class="alert <?= $ok ? 'alert-success' : 'alert-danger' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <?php if ($ok): ?>
  <div class="alert alert-info" style="max-width:720px;">
    <p class="mb-2"><strong>Credenciales generadas</strong></p>

    <div class="row g-2 align-items-center" style="margin-bottom:8px;">
      <div class="col-auto"><label class="col-form-label">Usuario</label></div>
      <div class="col"><input id="cred-user" class="form-control" value="<?= htmlspecialchars($panelUsuario) ?>" readonly></div>
      <div class="col-auto"><button type="button" class="btn btn-secondary" onclick="copiar('cred-user')">Copiar</button></div>
    </div>

    <div class="row g-2 align-items-center" style="margin-bottom:8px;">
      <div class="col-auto"><label class="col-form-label">Correo</label></div>
      <div class="col"><input id="cred-mail" class="form-control" value="<?= htmlspecialchars($panelCorreo) ?>" readonly></div>
      <div class="col-auto"><button type="button" class="btn btn-secondary" onclick="copiar('cred-mail')">Copiar</button></div>
    </div>

    <div class="row g-2 align-items-center">
      <div class="col-auto"><label class="col-form-label">Contraseña</label></div>
      <div class="col"><input id="cred-pass" class="form-control" value="<?= htmlspecialchars($panelPass) ?>" readonly></div>
      <div class="col-auto"><button type="button" class="btn btn-secondary" onclick="copiar('cred-pass')">Copiar</button></div>
    </div>
  </div>
  <?php endif; ?>

  <form method="post" autocomplete="off">
    <div class="row">
      <div class="col-md-6">
        <label class="form-label">Nombres</label>
        <input name="Nombre_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Nombre_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Apellidos</label>
        <input name="Apellido_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Apellido_profesional'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">RUT</label>
        <input name="Rut_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Rut_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Nacimiento</label>
        <input type="date" name="Nacimiento_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Nacimiento_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Teléfono</label>
        <input name="Celular_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Celular_profesional'] ?? '') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Domicilio</label>
        <input name="Domicilio_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Domicilio_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Correo</label>
        <input type="email" name="Correo_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Correo_profesional'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Estado civil</label>
        <input name="Estado_civil_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Estado_civil_profesional'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Banco</label>
        <input name="Banco_profesional" list="bancosCL" class="form-control" value="<?= htmlspecialchars($_POST['Banco_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Tipo de cuenta</label>
        <input name="Tipo_cuenta_profesional" list="tipocuenta" class="form-control" value="<?= htmlspecialchars($_POST['Tipo_cuenta_profesional'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">N° de cuenta</label>
        <input name="Cuenta_B_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Cuenta_B_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">AFP</label>
        <input name="AFP_profesional" list="afpCL" class="form-control" value="<?= htmlspecialchars($_POST['AFP_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Salud</label>
        <input name="Salud_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Salud_profesional'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Cargo</label>
        <input name="Cargo_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Cargo_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Horas</label>
        <input type="number" name="Horas_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Horas_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Fecha ingreso</label>
        <input type="date" name="Fecha_ingreso" class="form-control" value="<?= htmlspecialchars($_POST['Fecha_ingreso'] ?? '') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Tipo profesional</label>
        <input name="Tipo_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Tipo_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Escuela</label>
        <select name="Id_escuela_prof" class="form-select">
          <option value="">(sin escuela)</option>
          <?php foreach($escuelas as $e): ?>
            <option value="<?= (int)$e['Id_escuela'] ?>" <?= (isset($_POST['Id_escuela_prof']) && $_POST['Id_escuela_prof']==$e['Id_escuela'])?'selected':'' ?>>
              <?= htmlspecialchars($e['Nombre_escuela']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <hr>

    <h5>Usuario del sistema</h5>
    <div class="row">
      <div class="col-md-6">
        <label class="form-label">Nombre de usuario</label>
        <input name="Nombre_usuario" class="form-control" required value="<?= htmlspecialchars($_POST['Nombre_usuario'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Permisos</label>
        <select name="Permisos" class="form-select">
          <?php foreach (['PROFESIONAL','DIRECTOR','ADMIN'] as $p): ?>
            <option value="<?= $p ?>" <?= (($_POST['Permisos']??'PROFESIONAL')===$p)?'selected':'' ?>><?= $p ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="mt-3">
      <button class="btn btn-primary">Guardar</button>
      <a class="btn btn-secondary" href="index.php?seccion=profesionales">Cancelar</a>
    </div>
  </form>

  <!-- datalists (sólo referencia, no afectan estilos) -->
  <datalist id="bancosCL">
    <?php foreach($bancos as $b): ?><option value="<?= htmlspecialchars($b) ?>"></option><?php endforeach; ?>
  </datalist>
  <datalist id="tipocuenta">
    <option value="Cuenta Corriente"></option>
    <option value="Cuenta Vista"></option>
    <option value="Cuenta RUT"></option>
    <option value="Ahorro"></option>
  </datalist>
  <datalist id="afpCL">
    <?php foreach($afps as $a): ?><option value="<?= htmlspecialchars($a) ?>"></option><?php endforeach; ?>
  </datalist>

</div>

<script>
function copiar(id){
  const el=document.getElementById(id);
  el.select(); el.setSelectionRange(0,99999);
  document.execCommand('copy');
}
</script>
</body>
</html>
