<?php
// pages/modificar_profesional.php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/db.php'; // $conn (PDO)

if (!isset($_SESSION['usuario'])) { http_response_code(401); exit('No autenticado.'); }
$me = $_SESSION['usuario'];
$miRol = strtoupper($me['permisos'] ?? 'PROFESIONAL');

try { $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Throwable $e) {}

function nvl($v){ $v = trim((string)$v); return $v === '' ? null : $v; }
function genPwd($len=12){
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

$id_prof = (int)($_GET['Id_profesional'] ?? 0);
if ($id_prof <= 0) { http_response_code(400); exit('Falta Id_profesional'); }

// Cargar profesional + usuario
$st = $conn->prepare("
SELECT p.*,
       u.Id_usuario, u.Nombre_usuario, u.Permisos, u.Estado_usuario
  FROM dbo.profesionales p
  LEFT JOIN dbo.usuarios u ON u.Id_profesional = p.Id_profesional
 WHERE p.Id_profesional = ?");
$st->execute([$id_prof]);
$prof = $st->fetch(PDO::FETCH_ASSOC);
if (!$prof) { http_response_code(404); exit('No encontrado'); }

$msg = null; $ok=false; $newPlain=null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

  try {
    $conn->beginTransaction();

    $sql="UPDATE dbo.profesionales SET
      Nombre_profesional=?, Apellido_profesional=?, Rut_profesional=?, Nacimiento_profesional=?,
      Domicilio_profesional=?, Celular_profesional=?, Correo_profesional=?, Estado_civil_profesional=?,
      Banco_profesional=?, Tipo_cuenta_profesional=?, Cuenta_B_profesional=?, AFP_profesional=?,
      Salud_profesional=?, Cargo_profesional=?, Horas_profesional=?, Fecha_ingreso=?,
      Tipo_profesional=?, Id_escuela_prof=?
      WHERE Id_profesional=?";
    $up=$conn->prepare($sql);
    $up->execute([
      $Nombre_profesional,$Apellido_profesional,$Rut_profesional,$Nacimiento_profesional,
      $Domicilio_profesional,$Celular_profesional,$Correo_profesional,$Estado_civil_profesional,
      $Banco_profesional,$Tipo_cuenta_profesional,$Cuenta_B_profesional,$AFP_profesional,
      $Salud_profesional,$Cargo_profesional,$Horas_profesional,$Fecha_ingreso,
      $Tipo_profesional,$Id_escuela_prof,$id_prof
    ]);

    // Opción mínima para regenerar contraseña (solo ADMIN/DIRECTOR)
    if (!empty($_POST['reset_password']) && in_array($miRol, ['ADMIN','DIRECTOR'], true) && !empty($prof['Id_usuario'])) {
      $newPlain = genPwd(12);
      $hash = password_hash($newPlain, PASSWORD_DEFAULT);
      $sp = $conn->prepare("UPDATE dbo.usuarios SET Contraseña=? WHERE Id_usuario=?");
      $sp->execute([$hash, (int)$prof['Id_usuario']]);
    }

    $conn->commit();
    $ok = true;
    $msg = 'Datos actualizados.';
    // refrescar
    $st->execute([$id_prof]); $prof = $st->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    $msg = 'Error al guardar: ' . $e->getMessage();
  }
}

$escuelas = getEscuelas($conn);
$usuarioSistema = $prof['Nombre_usuario'] ?? '';
$correoProfesional = $prof['Correo_profesional'] ?? '';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Modificar profesional</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body class="<?= ($_COOKIE['modo_oscuro'] ?? 'false') === 'true' ? 'dark-mode' : '' ?>">
<div class="container">

  <h2 class="mb-3">Editar Profesional</h2>

  <?php if ($msg): ?>
    <div class="alert <?= $ok ? 'alert-success':'alert-danger' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- Panel muy simple para copiar usuario/correo y, si corresponde, la contraseña nueva -->
  <?php if ($usuarioSistema || $correoProfesional || $newPlain): ?>
  <div class="alert alert-info" style="max-width:720px;">
    <?php if ($usuarioSistema): ?>
    <div class="row g-2 align-items-center" style="margin-bottom:8px;">
      <div class="col-auto"><label class="col-form-label">Usuario</label></div>
      <div class="col"><input id="m-user" class="form-control" value="<?= htmlspecialchars($usuarioSistema) ?>" readonly></div>
      <div class="col-auto"><button type="button" class="btn btn-secondary" onclick="copiar('m-user')">Copiar</button></div>
    </div>
    <?php endif; ?>
    <?php if ($correoProfesional): ?>
    <div class="row g-2 align-items-center" style="margin-bottom:8px;">
      <div class="col-auto"><label class="col-form-label">Correo</label></div>
      <div class="col"><input id="m-mail" class="form-control" value="<?= htmlspecialchars($correoProfesional) ?>" readonly></div>
      <div class="col-auto"><button type="button" class="btn btn-secondary" onclick="copiar('m-mail')">Copiar</button></div>
    </div>
    <?php endif; ?>
    <?php if ($newPlain): ?>
    <div class="row g-2 align-items-center">
      <div class="col-auto"><label class="col-form-label">Contraseña</label></div>
      <div class="col"><input id="m-pass" class="form-control" value="<?= htmlspecialchars($newPlain) ?>" readonly></div>
      <div class="col-auto"><button type="button" class="btn btn-secondary" onclick="copiar('m-pass')">Copiar</button></div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <form method="post" autocomplete="off">
    <div class="row">
      <div class="col-md-6">
        <label class="form-label">Nombres</label>
        <input name="Nombre_profesional" class="form-control" value="<?= htmlspecialchars($prof['Nombre_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Apellidos</label>
        <input name="Apellido_profesional" class="form-control" value="<?= htmlspecialchars($prof['Apellido_profesional'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">RUT</label>
        <input name="Rut_profesional" class="form-control" value="<?= htmlspecialchars($prof['Rut_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Nacimiento</label>
        <input type="date" name="Nacimiento_profesional" class="form-control" value="<?= htmlspecialchars($prof['Nacimiento_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Teléfono</label>
        <input name="Celular_profesional" class="form-control" value="<?= htmlspecialchars($prof['Celular_profesional'] ?? '') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Domicilio</label>
        <input name="Domicilio_profesional" class="form-control" value="<?= htmlspecialchars($prof['Domicilio_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Correo</label>
        <input type="email" name="Correo_profesional" class="form-control" value="<?= htmlspecialchars($prof['Correo_profesional'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Estado civil</label>
        <input name="Estado_civil_profesional" class="form-control" value="<?= htmlspecialchars($prof['Estado_civil_profesional'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Banco</label>
        <input name="Banco_profesional" list="bancosCL" class="form-control" value="<?= htmlspecialchars($prof['Banco_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Tipo de cuenta</label>
        <input name="Tipo_cuenta_profesional" list="tipocuenta" class="form-control" value="<?= htmlspecialchars($prof['Tipo_cuenta_profesional'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">N° de cuenta</label>
        <input name="Cuenta_B_profesional" class="form-control" value="<?= htmlspecialchars($prof['Cuenta_B_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">AFP</label>
        <input name="AFP_profesional" list="afpCL" class="form-control" value="<?= htmlspecialchars($prof['AFP_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Salud</label>
        <input name="Salud_profesional" class="form-control" value="<?= htmlspecialchars($prof['Salud_profesional'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Cargo</label>
        <input name="Cargo_profesional" class="form-control" value="<?= htmlspecialchars($prof['Cargo_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Horas</label>
        <input type="number" name="Horas_profesional" class="form-control" value="<?= htmlspecialchars($prof['Horas_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Fecha ingreso</label>
        <input type="date" name="Fecha_ingreso" class="form-control" value="<?= htmlspecialchars($prof['Fecha_ingreso'] ?? '') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Tipo profesional</label>
        <input name="Tipo_profesional" class="form-control" value="<?= htmlspecialchars($prof['Tipo_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Escuela</label>
        <select name="Id_escuela_prof" class="form-select">
          <option value="">(sin escuela)</option>
          <?php foreach($escuelas as $e): ?>
            <option value="<?= (int)$e['Id_escuela'] ?>" <?= ((string)$prof['Id_escuela_prof']==(string)$e['Id_escuela'])?'selected':'' ?>>
              <?= htmlspecialchars($e['Nombre_escuela']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <hr>

    <div class="row align-items-center">
      <div class="col-md-4">
        <button class="btn btn-primary">Guardar cambios</button>
      </div>
      <?php if (in_array($miRol, ['ADMIN','DIRECTOR'], true) && !empty($prof['Id_usuario'])): ?>
      <div class="col-md-8">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="reset_password" name="reset_password" value="1">
          <label class="form-check-label" for="reset_password">Generar nueva contraseña (actualiza hash del usuario vinculado)</label>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <div class="mt-3">
      <a class="btn btn-secondary" href="index.php?seccion=perfil&Id_profesional=<?= $id_prof ?>">Volver</a>
    </div>
  </form>

  <!-- datalists -->
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
