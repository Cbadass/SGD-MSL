<?php
// pages/registrar_usuario.php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/db.php'; // Debe definir $conn (PDO)

// Seguridad mínima
if (!isset($_SESSION['usuario'])) {
  http_response_code(401);
  exit('No autenticado');
}
$yo = $_SESSION['usuario'];
$miRol = strtoupper($yo['permisos'] ?? 'PROFESIONAL');
if (!in_array($miRol, ['ADMIN','DIRECTOR','PROFESIONAL'], true)) {
  http_response_code(403);
  exit('Rol inválido');
}

// Forzar errores con excepciones
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Utilidades
function toNull($v){ $v = trim((string)$v); return ($v === '' ? null : $v); }
function genPwd($len=10){
  $chars='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';
  $s=''; for($i=0;$i<$len;$i++) $s.=$chars[random_int(0,strlen($chars)-1)];
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

// Estado UI
$exito = false;
$msg   = null;
$copiable = ['usuario'=>null,'pwd'=>null,'correo'=>null];

// POST -> insertar
if ($_SERVER['REQUEST_METHOD']==='POST') {
  // Datos PROFESIONAL (opcionales varios)
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

  // Datos USUARIO
  $Nombre_usuario = trim((string)($_POST['Nombre_usuario'] ?? ''));
  $Permisos       = strtoupper(trim((string)($_POST['Permisos'] ?? 'PROFESIONAL')));
  if ($Nombre_usuario === '') {
    $msg = 'Debes ingresar un nombre de usuario.';
  } elseif (!in_array($Permisos, ['ADMIN','DIRECTOR','PROFESIONAL'], true)) {
    $msg = 'Permisos inválidos.';
  } else {
    // Transacción: insert profesional + usuario
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
      // Id insertado
      $id_prof = (int)$conn->lastInsertId();

      // Generar contraseña
      $pwdPlano = genPwd(10);
      $pwdHash  = password_hash($pwdPlano, PASSWORD_DEFAULT);

      // Insert usuario
      $sqlU = "INSERT INTO dbo.usuarios (Nombre_usuario, Contraseña, Estado_usuario, Id_profesional, Permisos)
               VALUES (?,?,?,?,?)";
      $stU = $conn->prepare($sqlU);
      $stU->execute([$Nombre_usuario, $pwdHash, 1, $id_prof, $Permisos]);

      $conn->commit();
      $exito = true;
      $msg   = "Profesional y usuario creados correctamente.";
      $copiable = [
        'usuario' => $Nombre_usuario,
        'pwd'     => $pwdPlano,
        'correo'  => $Correo_profesional
      ];
    } catch(Throwable $e){
      if ($conn->inTransaction()) $conn->rollBack();
      $msg = 'Error al registrar: '.$e->getMessage();
    }
  }
}

// Escuelas para select (si aplica)
$escuelas = getEscuelas($conn);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Registrar profesional / usuario</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
/* mínimo para no romper tu CSS existente */
.copy-wrap{background:#111a2b;border:1px solid #1f2a44;border-radius:12px;padding:14px;margin-top:16px}
.copy-row{display:flex;gap:8px;margin:.35rem 0}
.copy-row input{flex:1} .copy-btn{white-space:nowrap}
</style>
</head>
<body class="<?= ($_COOKIE['modo_oscuro'] ?? 'false') === 'true' ? 'dark-mode' : '' ?>">
<div class="container">
  <h2 class="mb-3">Registrar Profesional y Usuario</h2>

  <?php if ($msg): ?>
    <div class="alert <?= $exito?'alert-success':'alert-danger' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <?php if ($exito && $copiable['usuario']): ?>
    <div class="copy-wrap">
      <h5 class="mb-2">Credenciales generadas</h5>
      <div class="copy-row">
        <input class="form-control" id="c_user" value="<?= htmlspecialchars($copiable['usuario']) ?>" readonly>
        <button class="btn btn-secondary copy-btn" onclick="copy('c_user')">Copiar usuario</button>
      </div>
      <div class="copy-row">
        <input class="form-control" id="c_pwd" value="<?= htmlspecialchars($copiable['pwd']) ?>" readonly>
        <button class="btn btn-secondary copy-btn" onclick="copy('c_pwd')">Copiar contraseña</button>
      </div>
      <div class="copy-row">
        <input class="form-control" id="c_mail" value="<?= htmlspecialchars($copiable['correo'] ?? '') ?>" readonly>
        <button class="btn btn-secondary copy-btn" onclick="copy('c_mail')">Copiar correo</button>
      </div>
    </div>
    <hr>
  <?php endif; ?>

  <form method="post" autocomplete="off">
    <div class="row">
      <div class="col-md-6">
        <label class="form-label">Nombres</label>
        <input name="Nombre_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Nombre_profesional']??'') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Apellidos</label>
        <input name="Apellido_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Apellido_profesional']??'') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">RUT</label>
        <input name="Rut_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Rut_profesional']??'') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Nacimiento</label>
        <input type="date" name="Nacimiento_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Nacimiento_profesional']??'') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Teléfono</label>
        <input name="Celular_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Celular_profesional']??'') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Domicilio</label>
        <input name="Domicilio_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Domicilio_profesional']??'') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Correo</label>
        <input type="email" name="Correo_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Correo_profesional']??'') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Estado civil</label>
        <input name="Estado_civil_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Estado_civil_profesional']??'') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Banco</label>
        <input list="bancosCL" name="Banco_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Banco_profesional']??'') ?>">
        <datalist id="bancosCL">
          <?php foreach($bancos as $b): ?><option value="<?= htmlspecialchars($b) ?>"></option><?php endforeach; ?>
        </datalist>
      </div>
      <div class="col-md-4">
        <label class="form-label">Tipo de cuenta</label>
        <input list="tipocuenta" name="Tipo_cuenta_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Tipo_cuenta_profesional']??'') ?>">
        <datalist id="tipocuenta">
          <option value="Cuenta Corriente"></option>
          <option value="Cuenta Vista"></option>
          <option value="Cuenta RUT"></option>
          <option value="Ahorro"></option>
        </datalist>
      </div>

      <div class="col-md-4">
        <label class="form-label">N° de cuenta</label>
        <input name="Cuenta_B_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Cuenta_B_profesional']??'') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">AFP</label>
        <input list="afpCL" name="AFP_profesional" class="form-control" value="<?= htmlspecialchars($_POST['AFP_profesional']??'') ?>">
        <datalist id="afpCL">
          <?php foreach($afps as $a): ?><option value="<?= htmlspecialchars($a) ?>"></option><?php endforeach; ?>
        </datalist>
      </div>
      <div class="col-md-4">
        <label class="form-label">Salud</label>
        <input name="Salud_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Salud_profesional']??'') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Cargo</label>
        <input name="Cargo_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Cargo_profesional']??'') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Horas</label>
        <input type="number" name="Horas_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Horas_profesional']??'') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Fecha ingreso</label>
        <input type="date" name="Fecha_ingreso" class="form-control" value="<?= htmlspecialchars($_POST['Fecha_ingreso']??'') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Tipo profesional</label>
        <input name="Tipo_profesional" class="form-control" value="<?= htmlspecialchars($_POST['Tipo_profesional']??'') ?>">
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
        <input name="Nombre_usuario" required class="form-control" value="<?= htmlspecialchars($_POST['Nombre_usuario']??'') ?>">
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
