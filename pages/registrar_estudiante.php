<?php
// pages/registrar_estudiante.php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auditoria.php';  // <-- auditoría

// 1) Protege la página
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// — Funciones de validación/formato de RUT —
function cleanRut($rut) {
    return preg_replace('/[^0-9kK]/', '', $rut);
}
function dvRut($rut) {
    $RUT    = cleanRut($rut);
    $digits = substr($RUT, 0, -1);
    $dv     = strtoupper(substr($RUT, -1));
    $sum = 0; $mult = 2;
    for ($i = strlen($digits) - 1; $i >= 0; $i--) {
        $sum  += $digits[$i] * $mult;
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

// 2) Carga listados para selects
$stmtC = $conn->query("
    SELECT 
      c.Id_curso,
      CONCAT(c.Tipo_curso,' - ',c.Grado_curso,'/',c.seccion_curso,
             ' (',esc.Nombre_escuela,')') AS desc_curso
    FROM cursos c
    LEFT JOIN escuelas esc ON c.Id_escuela = esc.Id_escuela
    ORDER BY c.Tipo_curso, c.Grado_curso, c.seccion_curso
");
$cursos = $stmtC->fetchAll(PDO::FETCH_ASSOC);

// 3) Inicializa datos y errores
$data = [
    'Nombre_estudiante' => '',
    'Apellido_estudiante' => '',
    'Rut_estudiante' => '',
    'Fecha_nacimiento' => '',
    'Fecha_ingreso' => '',
    'Id_curso' => '',
    'Id_apoderado' => '',
];
$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // — Colecta todo en $data —
    foreach (array_keys($data) as $key) {
        $data[$key] = trim($_POST[$key] ?? '');
    }
    // sanitiza enteros
    $data['Id_curso']     = intval($data['Id_curso']) ?: null;
    $data['Id_apoderado'] = intval($data['Id_apoderado']) ?: null;

    // — Validaciones —
    if (!$data['Nombre_estudiante'] || !$data['Apellido_estudiante']
        || !$data['Rut_estudiante'] || !$data['Fecha_nacimiento']
        || !$data['Fecha_ingreso']
    ) {
        $errors[] = "Complete todos los campos obligatorios.";
    }
    if ($data['Rut_estudiante'] && !dvRut($data['Rut_estudiante'])) {
        $errors[] = "RUT inválido.";
    }
    if (empty($errors)) {
        // formatea RUT y unicidad
        $rut_fmt = formatRut($data['Rut_estudiante']);
        $stmtRut = $conn->prepare("SELECT COUNT(*) FROM estudiantes WHERE Rut_estudiante = ?");
        $stmtRut->execute([$rut_fmt]);
        if ($stmtRut->fetchColumn() > 0) {
            $errors[] = "Ya existe un estudiante con RUT $rut_fmt.";
        }
    }

    if (empty($errors)) {
        // determina escuela
        if ($data['Id_curso']) {
            $stmtE = $conn->prepare("SELECT Id_escuela FROM cursos WHERE Id_curso = ?");
            $stmtE->execute([$data['Id_curso']]);
            $rowE = $stmtE->fetch();
            $id_escuela = $rowE['Id_escuela'] ?? null;
        } else {
            $id_escuela = null;
        }

        // — Inserción y auditoría —
        $conn->beginTransaction();
        try {
            $stmtI = $conn->prepare("
                INSERT INTO estudiantes
                  (Nombre_estudiante, Apellido_estudiante, Rut_estudiante,
                   Fecha_nacimiento, Fecha_ingreso, Estado_estudiante,
                   Id_curso, Id_apoderado, Id_escuela)
                VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)
            ");
            $stmtI->execute([
                $data['Nombre_estudiante'],
                $data['Apellido_estudiante'],
                $rut_fmt,
                $data['Fecha_nacimiento'],
                $data['Fecha_ingreso'],
                $data['Id_curso'],
                $data['Id_apoderado'],
                $id_escuela
            ]);
            $newId = $conn->lastInsertId();
            // registra auditoría
            $usuarioLog = $_SESSION['usuario']['id'];
            // incluye rut formateado
            $auditData = $data;
            $auditData['Rut_estudiante'] = $rut_fmt;
            registrarAuditoria($conn, $usuarioLog, 'estudiantes', $newId, 'INSERT', null, $auditData);

            $conn->commit();
            // redirección robusta
            header("Location: index.php?seccion=estudiantes");
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            $message = "<p class='text-danger'>Error al guardar: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        // concatena errores
        $message = '<div class="alert alert-danger"><ul>';
        foreach ($errors as $err) {
            $message .= "<li>".htmlspecialchars($err)."</li>";
        }
        $message .= '</ul></div>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registrar Estudiante</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .resultado { cursor:pointer; padding:6px; border-bottom:1px solid #ddd; }
    .resultado:hover { background:#f0f0f0; }
    .seleccionado { background:#d1e7dd!important; font-weight:bold; }
  </style>
</head>
<body class="<?= ($_COOKIE['modo_oscuro'] ?? 'false')==='true'?'dark-mode':'' ?>">
<?php include '../header.php'; ?>
<div class="container d-flex">
  <?php include '../sidebar.php'; ?>
  <main class="main">
    <h2>Registrar nuevo estudiante</h2>
    <?= $message ?>

    <form method="POST" class="row g-3 needs-validation" novalidate>
      <div class="col-md-6">
        <label class="form-label">Nombres</label>
        <input name="Nombre_estudiante" class="form-control" required
               value="<?= htmlspecialchars($data['Nombre_estudiante']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Apellidos</label>
        <input name="Apellido_estudiante" class="form-control" required
               value="<?= htmlspecialchars($data['Apellido_estudiante']) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">RUT</label>
        <input name="Rut_estudiante" class="form-control" required
               placeholder="20.384.593-4"
               value="<?= htmlspecialchars($data['Rut_estudiante']) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Fecha de nacimiento</label>
        <input name="Fecha_nacimiento" type="date" class="form-control" required
               value="<?= htmlspecialchars($data['Fecha_nacimiento']) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Fecha de ingreso</label>
        <input name="Fecha_ingreso" type="date" class="form-control" required
               value="<?= htmlspecialchars($data['Fecha_ingreso']) ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Curso (opcional)</label>
        <select name="Id_curso" class="form-select">
          <option value="">-- Sin curso --</option>
          <?php foreach($cursos as $c): ?>
            <option value="<?= $c['Id_curso'] ?>"
              <?= $data['Id_curso']==$c['Id_curso']?'selected':'' ?>>
              <?= htmlspecialchars($c['desc_curso']) ?>
            </option>
          <?php endforeach ?>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Apoderado (opcional)</label>
        <input type="text" id="buscar_apoderado" class="form-control" placeholder="RUT o Nombre">
        <input type="hidden" name="Id_apoderado" id="Id_apoderado"
               value="<?= htmlspecialchars($data['Id_apoderado']) ?>">
        <div id="resultados_apoderado" class="border mt-1"></div>
      </div>

      <div class="col-12">
        <button type="submit" class="btn btn-success">Guardar Datos</button>
      </div>
    </form>
  </main>
</div>

<script>
function buscar(endpoint, query, cont, idInput) {
  if (query.length < 3) { cont.innerHTML = ''; return; }
  fetch(endpoint + '?q=' + encodeURIComponent(query))
    .then(res => res.json())
    .then(data => {
      cont.innerHTML = '';
      if (!data.length) {
        cont.innerHTML = '<div class="p-2 text-muted">Sin resultados</div>';
        return;
      }
      data.forEach(item => {
        const div = document.createElement('div');
        div.className = 'resultado';
        div.textContent = `${item.rut} - ${item.nombre} ${item.apellido}`;
        div.onclick = () => {
          document.getElementById(idInput).value = item.id;
          cont.innerHTML = `<div class="resultado seleccionado">${div.textContent} (Seleccionado)</div>`;
        };
        cont.appendChild(div);
      });
    });
}
document.getElementById('buscar_apoderado')
  .addEventListener('input', e => {
    buscar('buscar_apoderados.php', e.target.value.trim(),
           document.getElementById('resultados_apoderado'),
           'Id_apoderado');
  });
</script>
</body>
</html>
