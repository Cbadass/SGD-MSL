<?php
// registrar_usuario.php  (cambios mínimos)
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/db.php'; // $conn (PDO)

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
  // Datos del profesional (opcionales = pueden ser NULL)
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

      // Panel para copiar
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
<body>
<div class="container">

  <h2>Registrar profesional y usuario</h2>

  <?php if ($msg): ?>
    <div><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <?php if ($ok): ?>
    <!-- MISMO MENSAJE PERO COPIABLE -->
    <p><strong>Profesional y usuario creados.</strong></p>
    <p>
      Usuario:
      <input id="cred-user" value="<?= htmlspecialchars($panelUsuario) ?>" readonly>
      <button type="button" onclick="copiar('cred-user')">Copiar</button>
    </p>
    <p>
      Correo:
      <input id="cred-mail" value="<?= htmlspecialchars($panelCorreo) ?>" readonly>
      <button type="button" onclick="copiar('cred-mail')">Copiar</button>
    </p>
    <p>
      Contraseña:
      <input id="cred-pass" value="<?= htmlspecialchars($panelPass) ?>" readonly>
      <button type="button" onclick="copiar('cred-pass')">Copiar</button>
    </p>
    <hr>
  <?php endif; ?>

  <!-- FORM: se conservan tus clases/estructura; sólo agrego datalist en Banco/AFP -->
  <form method="post" autocomplete="off">
    <!-- ... resto de tus filas/inputs ... solo muestro los relevantes -->

    <!-- Banco (con datalist, pero puedes escribir a mano) -->
    <label>Banco</label>
    <input name="Banco_profesional" list="bancosCL" value="<?= htmlspecialchars($_POST['Banco_profesional'] ?? '') ?>">

    <!-- AFP (con datalist) -->
    <label>AFP</label>
    <input name="AFP_profesional" list="afpCL" value="<?= htmlspecialchars($_POST['AFP_profesional'] ?? '') ?>">

    <!-- el resto de tus inputs quedan igual; quita `required` en los que tu BD permite NULL -->

    <!-- ejemplo de usuario del sistema -->
    <label>Nombre de usuario</label>
    <input name="Nombre_usuario" value="<?= htmlspecialchars($_POST['Nombre_usuario'] ?? '') ?>" required>

    <label>Permisos</label>
    <select name="Permisos">
      <option value="PROFESIONAL" <?= (($_POST['Permisos']??'PROFESIONAL')==='PROFESIONAL')?'selected':'' ?>>PROFESIONAL</option>
      <option value="DIRECTOR" <?= (($_POST['Permisos']??'')==='DIRECTOR')?'selected':'' ?>>DIRECTOR</option>
      <option value="ADMIN" <?= (($_POST['Permisos']??'')==='ADMIN')?'selected':'' ?>>ADMIN</option>
    </select>

    <div style="margin-top:8px;">
      <button type="submit">Guardar</button>
      <a href="index.php?seccion=profesionales">Cancelar</a>
    </div>
  </form>

  <!-- datalists (no cambian tu CSS) -->
  <datalist id="bancosCL">
    <?php foreach($bancos as $b): ?><option value="<?= htmlspecialchars($b) ?>"></option><?php endforeach; ?>
  </datalist>
  <datalist id="afpCL">
    <?php foreach($afps as $a): ?><option value="<?= htmlspecialchars($a) ?>"></option><?php endforeach; ?>
  </datalist>
</div>

<script>
function copiar(id){
  const el=document.getElementById(id);
  if(navigator.clipboard && window.isSecureContext){
    navigator.clipboard.writeText(el.value);
  }else{
    el.select(); el.setSelectionRange(0,99999); document.execCommand('copy');
  }
}
</script>
</body>
</html>
