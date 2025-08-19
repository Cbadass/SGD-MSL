<?php
// pages/modificar_profesional.php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/db.php'; // $conn (PDO)
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Seguridad
if (!isset($_SESSION['usuario'])) { http_response_code(401); exit('No autenticado'); }
$yo = $_SESSION['usuario'];
$miRol = strtoupper($yo['permisos'] ?? 'PROFESIONAL');

// Utilidades
function toNull($v){ $v = trim((string)$v); return $v===''?null:$v; }
function genPwd($len=10){
  $c='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';
  $s=''; for($i=0;$i<$len;$i++) $s.=$c[random_int(0,strlen($c)-1)];
  return $s;
}
function getEscuelas(PDO $db){
  try {
    $st=$db->query("SELECT Id_escuela, Nombre_escuela FROM dbo.escuelas ORDER BY Nombre_escuela");
    return $st->fetchAll(PDO::FETCH_ASSOC);
  } catch(Throwable $e){ return []; }
}

// catálogos
$bancos = [
 'Banco de Chile','BancoEstado','Banco Santander Chile','Scotiabank Chile','Bci',
 'Itaú Chile','Banco Security','Banco Falabella','Banco Ripley','Banco Consorcio',
 'Banco Internacional','Banco BICE','Banco BTG Pactual Chile','HSBC Bank Chile'
];
$afps = ['AFP Habitat','AFP Provida','AFP Capital','AFP Cuprum','AFP Modelo','AFP PlanVital','AFP Uno'];

// Id profesional
$id_prof = (int)($_GET['Id_profesional'] ?? 0);
if ($id_prof<=0){ http_response_code(400); exit('Falta Id_profesional'); }

// Cargar profesional + usuario
$st=$conn->prepare("
  SELECT p.*, u.Id_usuario, u.Nombre_usuario, u.Permisos, u.Estado_usuario
    FROM dbo.profesionales p
    LEFT JOIN dbo.usuarios u ON u.Id_profesional = p.Id_profesional
   WHERE p.Id_profesional = ?");
$st->execute([$id_prof]);
$prof = $st->fetch(PDO::FETCH_ASSOC);
if (!$prof){ http_response_code(404); exit('Profesional no encontrado'); }

// Estado UI
$exito=false; $msg=null; $copiable=['usuario'=>null,'pwd'=>null,'correo'=>null];

// Guardar cambios
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $Nombre_profesional     = toNull($_POST['Nombre_profesional'] ?? '');
  $Apellido_profesional   = toNull($_POST['Apellido_profesional'] ?? '');
  $Rut_profesional        = toNull($_POST['Rut_profesional'] ?? '');
  $Nacimiento_profesional = toNull($_POST['Nacimiento_profesional'] ?? '');
  $Domicilio_profesional  = toNull($_POST['Domicilio_profesional'] ?? '');
  $Celular_profesional    = toNull($_POST['Celular_profesional'] ?? '');
  $Correo_profesional     = toNull($_POST['Correo_profesional'] ?? '');
  $Estado_civil_profesional = toNull($_POST['Estado_civil_profesional'] ?? '');
  $Banco_profesional      = toNull($_POST['Banco_profesional'] ?? '');
  $Tipo_cuenta_profesional= toNull($_POST['Tipo_cuenta_profesional'] ?? '');
  $Cuenta_B_profesional   = toNull($_POST['Cuenta_B_profesional'] ?? '');
  $AFP_profesional        = toNull($_POST['AFP_profesional'] ?? '');
  $Salud_profesional      = toNull($_POST['Salud_profesional'] ?? '');
  $Cargo_profesional      = toNull($_POST['Cargo_profesional'] ?? '');
  $Horas_profesional      = toNull($_POST['Horas_profesional'] ?? '');
  $Fecha_ingreso          = toNull($_POST['Fecha_ingreso'] ?? '');
  $Tipo_profesional       = toNull($_POST['Tipo_profesional'] ?? '');
  $Id_escuela_prof        = toNull($_POST['Id_escuela_prof'] ?? '');

  try{
    $conn->beginTransaction();

    $sql="UPDATE dbo.profesionales SET
      Nombre_profesional=?, Apellido_profesional=?, Rut_profesional=?, Nacimiento_profesional=?,
      Domicilio_profesional=?, Celular_profesional=?, Correo_profesional=?, Estado_civil_profesional=?,
      Banco_profesional=?, Tipo_cuenta_profesional=?, Cuenta_B_profesional=?, AFP_profesional=?,
      Salud_profesional=?, Cargo_profesional=?, Horas_profesional=?, Fecha_ingreso=?,
      Tipo_profesional=?, Id_escuela_prof=?
      WHERE Id_profesional=?";
    $stU=$conn->prepare($sql);
    $stU->execute([
      $Nombre_profesional,$Apellido_profesional,$Rut_profesional,$Nacimiento_profesional,
      $Domicilio_profesional,$Celular_profesional,$Correo_profesional,$Estado_civil_profesional,
      $Banco_profesional,$Tipo_cuenta_profesional,$Cuenta_B_profesional,$AFP_profesional,
      $Salud_profesional,$Cargo_profesional,$Horas_profesional,$Fecha_ingreso,
      $Tipo_profesional,$Id_escuela_prof,$id_prof
    ]);

    // Reset de contraseña opcional (sólo ADMIN/DIRECTOR)
    if (!empty($_POST['reset_password']) && in_array($miRol, ['ADMIN','DIRECTOR'], true) && !empty($prof['Id_usuario'])) {
      $pwd = genPwd(12);
      $hash= password_hash($pwd, PASSWORD_DEFAULT);
      $stP=$conn->prepare("UPDATE dbo.usuarios SET Contraseña=? WHERE Id_usuario=?");
      $stP->execute([$hash, (int)$prof['Id_usuario']]);
      $copiable['pwd'] = $pwd;
    }

    $conn->commit();
    $exito=true; $msg='Datos actualizados correctamente.';
    // Para mostrar en copiable
    $copiable['usuario'] = $prof['Nombre_usuario'];
    $copiable['correo']  = $Correo_profesional ?? $prof['Correo_profesional'];
    // refrescar
    $st->execute([$id_prof]); $prof = $st->fetch(PDO::FETCH_ASSOC);
  }catch(Throwable $e){
    if ($conn->inTransaction()) $conn->rollBack();
    $msg='Error al guardar: '.$e->getMessage();
  }
}

// Escuelas
$escuelas = getEscuelas($conn);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Modificar profesional</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
.copy-wrap{background:#111a2b;border:1px solid #1f2a44;border-radius:12px;padding:14px;margin-top:16px}
.copy-row{display:flex;gap:8px;margin:.35rem 0}
.copy-row input{flex:1} .copy-btn{white-space:nowrap}
</style>
</head>
<body class="<?= ($_COOKIE['modo_oscuro'] ?? 'false') === 'true' ? 'dark-mode' : '' ?>">
<div class="container">
  <h2 class="mb-3">Modificar Profesional</h2>

  <?php if ($msg): ?>
    <div class="alert <?= $exito?'alert-success':'alert-danger' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <?php if ($exito && ($copiable['usuario'] || $copiable['pwd'] || $copiable['correo'])): ?>
    <div class="copy-wrap">
      <h5 class="mb-2">Datos para copiar</h5>
      <?php if ($copiable['usuario']): ?>
      <div class="copy-row">
        <input class="form-control" id="m_user" value="<?= htmlspecialchars($copiable['usuario']) ?>" readonly>
        <button class="btn btn-secondary copy-btn" onclick="copy('m_user')">Copiar usuario</button>
      </div>
      <?php endif; ?>
      <?php if ($copiable['pwd']): ?>
      <div class="copy-row">
        <input class="form-control" id="m_pwd" value="<?= htmlspecialchars($copiable['pwd']) ?>" readonly>
        <button class="btn btn-secondary copy-btn" onclick="copy('m_pwd')">Copiar contraseña</button>
      </div>
      <?php endif; ?>
      <?php if ($copiable['correo']): ?>
      <div class="copy-row">
        <input class="form-control" id="m_mail" value="<?= htmlspecialchars($copiable['correo']) ?>" readonly>
        <button class="btn btn-secondary copy-btn" onclick="copy('m_mail')">Copiar correo</button>
      </div>
      <?php endif; ?>
    </div>
    <hr>
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
        <input list="bancosCL" name="Banco_profesional" class="form-control" value="<?= htmlspecialchars($prof['Banco_profesional'] ?? '') ?>">
        <datalist id="bancosCL">
          <?php foreach($bancos as $b): ?><option value="<?= htmlspecialchars($b) ?>"></option><?php endforeach; ?>
        </datalist>
      </div>
      <div class="col-md-4">
        <label class="form-label">Tipo de cuenta</label>
        <input list="tipocuenta" name="Tipo_cuenta_profesional" class="form-control" value="<?= htmlspecialchars($prof['Tipo_cuenta_profesional'] ?? '') ?>">
        <datalist id="tipocuenta">
          <option value="Cuenta Corriente"></option>
          <option value="Cuenta Vista"></option>
          <option value="Cuenta RUT"></option>
          <option value="Ahorro"></option>
        </datalist>
      </div>

      <div class="col-md-4">
        <label class="form-label">N° de cuenta</label>
        <input name="Cuenta_B_profesional" class="form-control" value="<?= htmlspecialchars($prof['Cuenta_B_profesional'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">AFP</label>
        <input list="afpCL" name="AFP_profesional" class="form-control" value="<?= htmlspecialchars($prof['AFP_profesional'] ?? '') ?>">
        <datalist id="afpCL">
          <?php foreach($afps as $a): ?><option value="<?= htmlspecialchars($a) ?>"></option><?php endforeach; ?>
        </datalist>
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
          <label class="form-check-label" for="reset_password">Generar nueva contraseña (se actualizará el hash del usuario vinculado)</label>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <div class="mt-3">
      <a class="btn btn-secondary" href="index.php?seccion=perfil&Id_profesional=<?= $id_prof ?>">Volver</a>
    </div>
  </form>
</div>

<script>
function copy(id){
  const el=document.getElementById(id);
  el.select(); el.setSelectionRange(0,99999);
  document.execCommand('copy');
  const btn=event.target.closest('button'); if(btn){ const o=btn.textContent; btn.textContent='Copiado ✓'; setTimeout(()=>btn.textContent=o,1100); }
}
</script>
</body>
</html>
