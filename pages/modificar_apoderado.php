<?php
// modificar_apoderado.php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auditoria.php';  // <-- auditoría

// 1) Protege la página
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// 2) Recoge el ID
$id = intval($_GET['Id_apoderado'] ?? 0);
if ($id <= 0) die("ID inválido.");

// — Funciones de RUT —
function cleanRut($rut) {
    return preg_replace('/[^0-9kK]/', '', $rut);
}
function dvRut($rut) {
    $R = cleanRut($rut);
    $digits = substr($R, 0, -1);
    $dv      = strtoupper(substr($R, -1));
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
    $R      = cleanRut($rut);
    $number = substr($R, 0, -1);
    $dv     = strtoupper(substr($R, -1));
    return number_format($number, 0, ',', '.') . "-$dv";
}

// 3) Trae datos actuales
$stmt = $conn->prepare("SELECT * FROM apoderados WHERE Id_apoderado = ?");
$stmt->execute([$id]);
$est = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$est) die("Apoderado no encontrado.");

// formatea el RUT
$est['Rut_apoderado'] = formatRut($est['Rut_apoderado']);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 4) Captura y valida
    $nombre   = trim($_POST['Nombre_apoderado']   ?? '');
    $apellido = trim($_POST['Apellido_apoderado'] ?? '');
    $rut      = trim($_POST['Rut_apoderado']      ?? '');
    $numero   = trim($_POST['Numero_apoderado']   ?? '');
    $correo   = trim($_POST['Correo_apoderado']   ?? '');
    $esPadre  = trim($_POST['Escolaridad_padre']  ?? '');
    $esMadre  = trim($_POST['Escolaridad_madre']  ?? '');
    $ocPadre  = trim($_POST['Ocupacion_padre']    ?? '');
    $ocMadre  = trim($_POST['Ocupacion_madre']    ?? '');

    if (!$nombre || !$apellido || !$rut) {
        $message = "<p class='text-danger'>Complete nombre, apellido y RUT.</p>";
    }
    elseif (!dvRut($rut)) {
        $message = "<p class='text-danger'>RUT inválido.</p>";
    }
    else {
        $rutFmt = formatRut($rut);
        // Unicidad de RUT
        $chk = $conn->prepare("
            SELECT COUNT(*) FROM apoderados
             WHERE Rut_apoderado = ? AND Id_apoderado <> ?
        ");
        $chk->execute([$rutFmt, $id]);
        if ($chk->fetchColumn() > 0) {
            $message = "<p class='text-danger'>Otro apoderado ya usa el RUT $rutFmt.</p>";
        } else {
            // guardar datos anteriores para auditoría
            $antes = $est;

            // 5) UPDATE
            $sql = "
              UPDATE apoderados
                 SET Nombre_apoderado   = ?,
                     Apellido_apoderado = ?,
                     Rut_apoderado      = ?,
                     Numero_apoderado   = ?,
                     Correo_apoderado   = ?,
                     Escolaridad_padre  = ?,
                     Escolaridad_madre  = ?,
                     Ocupacion_padre    = ?,
                     Ocupacion_madre    = ?
               WHERE Id_apoderado = ?
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $nombre, $apellido, $rutFmt,
                $numero, $correo,
                $esPadre, $esMadre,
                $ocPadre, $ocMadre,
                $id
            ]);

            // datos nuevos para auditoría
            $despues = [
                'Nombre_apoderado'   => $nombre,
                'Apellido_apoderado' => $apellido,
                'Rut_apoderado'      => $rutFmt,
                'Numero_apoderado'   => $numero,
                'Correo_apoderado'   => $correo,
                'Escolaridad_padre'  => $esPadre,
                'Escolaridad_madre'  => $esMadre,
                'Ocupacion_padre'    => $ocPadre,
                'Ocupacion_madre'    => $ocMadre
            ];

            // registrar auditoría
            $usuarioLog = $_SESSION['usuario']['id'];
            registrarAuditoria(
                $conn,
                $usuarioLog,
                'apoderados',
                $id,
                'UPDATE',
                $antes,
                $despues
            );

            header("Location: index.php?seccion=apoderados");
            exit;
        }
    }
}
?>

<h2 class="mb-4">Editar Apoderado</h2>
<?= $message ?>
<form method="POST" class="row g-3 needs-validation" novalidate>
  <input type="hidden" name="Id_apoderado" value="<?= $est['Id_apoderado'] ?>">

  <div class="col-md-6">
    <label class="form-label">Nombres</label>
    <input name="Nombre_apoderado" class="form-control input-width" type="text" required
           value="<?= htmlspecialchars($est['Nombre_apoderado']) ?>">
  </div>
  <div class="col-md-6">
    <label class="form-label">Apellidos</label>
    <input name="Apellido_apoderado" class="form-control input-width" type="text" required
           value="<?= htmlspecialchars($est['Apellido_apoderado']) ?>">
  </div>
  <div class="col-md-4">
    <label class="form-label">RUT</label>
    <input name="Rut_apoderado" class="form-control input-width" type="text" placeholder="20.384.593-4" required
           value="<?= htmlspecialchars($est['Rut_apoderado']) ?>">
  </div>
  <div class="col-md-4">
    <label class="form-label">Teléfono</label>
    <input name="Numero_apoderado" class="form-control input-width" type="text"
           value="<?= htmlspecialchars($est['Numero_apoderado']) ?>">
  </div>
  <div class="col-md-4">
    <label class="form-label">Correo electrónico</label>
    <input name="Correo_apoderado" type="email" class="form-control input-width"
           value="<?= htmlspecialchars($est['Correo_apoderado']) ?>">
  </div>
  <div class="col-md-6">
    <label class="form-label">Escolaridad Padre</label>
    <input name="Escolaridad_padre" class="form-control input-width" type="text"
           value="<?= htmlspecialchars($est['Escolaridad_padre']) ?>">
  </div>
  <div class="col-md-6">
    <label class="form-label">Escolaridad Madre</label>
    <input name="Escolaridad_madre" class="form-control input-width" type="text"
           value="<?= htmlspecialchars($est['Escolaridad_madre']) ?>">
  </div>
  <div class="col-md-6">
    <label class="form-label">Ocupación Padre</label>
    <input name="Ocupacion_padre" class="form-control input-width" type="text"
           value="<?= htmlspecialchars($est['Ocupacion_padre']) ?>">
  </div>
  <div class="col-md-6">
    <label class="form-label">Ocupación Madre</label>
    <input name="Ocupacion_madre" class="form-control input-width" type="text"
           value="<?= htmlspecialchars($est['Ocupacion_madre']) ?>">
  </div>
  <div class="col-12 mt-1">
    <button type="submit" class="btn btn-success btn-height mr-1">Guardar Cambios</button>
    <button class="btn btn-secondary btn-height">
        <a class="link-text" href="index.php?seccion=apoderados" >Cancelar</a>
    </button>
  </div>
</form>
