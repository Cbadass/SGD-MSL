<?php
// registrar_apoderado.php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auditoria.php';  // <-- auditoría integrado

// 1) Protege la página
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// — Funciones de RUT —
function cleanRut($rut) {
    return preg_replace('/[^0-9kK]/', '', $rut);
}
function dvRut($rut) {
    $R = cleanRut($rut);
    $digits = substr($R, 0, -1);
    $dv     = strtoupper(substr($R, -1));
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

// 2) Inicializa datos y mensaje
$data = [
    'Nombre_apoderado'   => '',
    'Apellido_apoderado' => '',
    'Rut_apoderado'      => '',
    'Numero_apoderado'   => '',
    'Escolaridad_padre'  => '',
    'Escolaridad_madre'  => '',
    'Ocupacion_padre'    => '',
    'Ocupacion_madre'    => '',
    'Correo_apoderado'   => ''
];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 3) Colecta datos
    foreach ($data as $key => $_) {
        $data[$key] = trim($_POST[$key] ?? '');
    }

    // 4) Validaciones
    if (!$data['Nombre_apoderado'] || !$data['Apellido_apoderado'] || !$data['Rut_apoderado']) {
        $message = "<p class='text-danger'>Complete nombre, apellido y RUT.</p>";
    }
    elseif (!dvRut($data['Rut_apoderado'])) {
        $message = "<p class='text-danger'>RUT inválido.</p>";
    }
    else {
        // Formatea RUT
        $data['Rut_apoderado'] = formatRut($data['Rut_apoderado']);
        // Unicidad de RUT
        $chk = $conn->prepare("SELECT COUNT(*) FROM apoderados WHERE Rut_apoderado = ?");
        $chk->execute([$data['Rut_apoderado']]);
        if ($chk->fetchColumn() > 0) {
            $message = "<p class='text-danger'>Ya existe un apoderado con RUT {$data['Rut_apoderado']}.</p>";
        } else {
            // 5) Insertar
            $sql = "
                INSERT INTO apoderados
                  (Nombre_apoderado, Apellido_apoderado, Rut_apoderado,
                   Numero_apoderado, Escolaridad_padre, Escolaridad_madre,
                   Ocupacion_padre, Ocupacion_madre, Correo_apoderado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_values($data));

            // registrar auditoría
            $newId     = $conn->lastInsertId();
            $usuarioId = $_SESSION['usuario']['id'];
            registrarAuditoria(
                $conn,
                $usuarioId,
                'apoderados',
                $newId,
                'INSERT',
                null,
                $data
            );

            header("Location: index.php?seccion=apoderados");
            exit;
        }
    }
}
?>

<h2 class="mb-4">Registrar Nuevo Apoderado</h2>
<?= $message ?>
<form method="POST" class="form-grid" novalidate>
  <h3 class = "mb-4 subtitle">Datos personales</h3>
  <div class="mb-4">
    <label class="form-label">Nombres</label>
    <input name="Nombre_apoderado" class="form-control input-width" type="text" required
           value="<?= htmlspecialchars($data['Nombre_apoderado']) ?>">
  </div>
  <div class="mb-4">
    <label class="form-label">Apellidos</label>
    <input name="Apellido_apoderado" class="form-control input-width" type="text" required
           value="<?= htmlspecialchars($data['Apellido_apoderado']) ?>">
  </div>
  <div class="mb-4">
    <label class="form-label">RUT</label>
    <input name="Rut_apoderado" class="form-control input-width" type="text" placeholder="20.384.593-4" required
           value="<?= htmlspecialchars($data['Rut_apoderado']) ?>">
  </div>
  <div class="mb-4">
    <label class="form-label">Teléfono</label>
    <input name="Numero_apoderado" class="form-control input-width" type="text"
           value="<?= htmlspecialchars($data['Numero_apoderado']) ?>">
  </div>
  <div class="mb-4">
    <label class="form-label">Correo electrónico</label>
    <input name="Correo_apoderado" type="email" class="form-control input-width"
           value="<?= htmlspecialchars($data['Correo_apoderado']) ?>">
  </div>
  <div class="mb-4">
    <label class="form-label">Escolaridad Padre</label>
    <input name="Escolaridad_padre" class="form-control input-width" type="text"
           value="<?= htmlspecialchars($data['Escolaridad_padre']) ?>">
  </div>
  <div class="mb-4">
    <label class="form-label">Escolaridad Madre</label>
    <input name="Escolaridad_madre" class="form-control input-width" type="text"
           value="<?= htmlspecialchars($data['Escolaridad_madre']) ?>">
  </div>
  <div class="mb-4">
    <label class="form-label">Ocupación Padre</label>
    <input name="Ocupacion_padre" class="form-control input-width" type="text"
           value="<?= htmlspecialchars($data['Ocupacion_padre']) ?>">
  </div>
  <div class="mb-4">
    <label class="form-label">Ocupación Madre</label>
    <input name="Ocupacion_madre" class="form-control input-width" type="text"
           value="<?= htmlspecialchars($data['Ocupacion_madre']) ?>">
  </div>
  <div class="col-12">
    <button type="submit" class="btn btn-success btn-height mr-1">Guardar Datos</button>
    <button class="btn btn-secondary btn-height">
      <a class="link-text" href="index.php?seccion=apoderados">Cancelar</a>
    </button>
  </div>
</form>
