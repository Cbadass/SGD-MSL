<?php
// modificar_profesional.php  (cambios mínimos)
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/db.php'; // $conn (PDO)

if (!isset($_SESSION['usuario'])) { http_response_code(401); exit('No autenticado.'); }
try { $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Throwable $e) {}

function nvl($v){ $v = trim((string)$v); return $v === '' ? null : $v; }
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

$msg = null; $ok=false;

// Guardar cambios (tu lógica existente + nulos permitidos)
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
    $ok=true; $msg='Datos actualizados.';
    // refrescar
    $st->execute([$id_prof]); $prof = $st->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $msg = 'Error al guardar: ' . $e->getMessage();
  }
}

$escuelas = getEscuelas($conn);
// Para panel de copiado (si se necesita mostrar)
$__usr  = $prof['Nombre_usuario']     ?? '';
$__mail = $prof['Correo_profesional'] ?? '';
$__pw   = $_GET['pw'] ?? null; // úsalo si vienes de un cambio de contraseña en otra pantalla
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Modificar profesional</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<div class="container">

  <h2>Editar Profesional</h2>

  <?php if ($msg): ?>
    <div><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- Bloque simple de copiado SIN tocar tu CSS -->
  <?php if ($__usr || $__mail || $__pw): ?>
    <p><strong>Datos de acceso</strong></p>
    <?php if ($__usr): ?>
      <p>Usuario:
        <input id="m-user" value="<?= htmlspecialchars($__usr) ?>" readonly>
        <button type="button" onclick="copiar('m-user')">Copiar</button>
      </p>
    <?php endif; ?>
    <?php if ($__mail): ?>
      <p>Correo:
        <input id="m-mail" value="<?= htmlspecialchars($__mail) ?>" readonly>
        <button type="button" onclick="copiar('m-mail')">Copiar</button>
      </p>
    <?php endif; ?>
    <?php if ($__pw): ?>
      <p>Contraseña:
        <input id="m-pass" value="<?= htmlspecialchars($__pw) ?>" readonly>
        <button type="button" onclick="copiar('m-pass')">Copiar</button>
      </p>
    <?php endif; ?>
    <hr>
  <?php endif; ?>

  <!-- Tu formulario original; solo agrego datalist en Banco/AFP y deja opcionales los que acepta NULL -->
  <form method="post" autocomplete="off">
    <!-- … deja el resto de tus campos igual … -->

    <label>Banco</label>
    <input name="Banco_profesional" list="bancosCL" value="<?= htmlspecialchars($prof['Banco_profesional'] ?? '') ?>">

    <label>AFP</label>
    <input name="AFP_profesional" list="afpCL" value="<?= htmlspecialchars($prof['AFP_profesional'] ?? '') ?>">

    <!-- ejemplo: correo/teléfono sin required si tu BD permite NULL -->
    <!-- <input name="Correo_profesional" type="email" value="<?= htmlspecialchars($prof['Correo_profesional'] ?? '') ?>"> -->
    <!-- <input name="Celular_profesional" value="<?= htmlspecialchars($prof['Celular_profesional'] ?? '') ?>"> -->

    <!-- resto de campos sin cambios -->

    <div style="margin-top:8px;">
      <button type="submit">Guardar</button>
      <a href="index.php?seccion=perfil&Id_profesional=<?= $id_prof ?>">Volver</a>
    </div>
  </form>

  <!-- datalists -->
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
