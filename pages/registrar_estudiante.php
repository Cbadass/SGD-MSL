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

// 3) Preparación de datos
$data = [
    'Nombre_estudiante'   => '',
    'Apellido_estudiante' => '',
    'Rut_estudiante'      => '',
    'Fecha_nacimiento'    => '',
    'Fecha_ingreso'       => '',
    'Id_curso'            => null,
    'Id_apoderado'        => null
];
$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Colecta todos los valores en $data
    foreach (array_keys($data) as $k) {
        $data[$k] = trim($_POST[$k] ?? '');
    }
    $data['Id_curso']     = intval($data['Id_curso']) ?: null;
    $data['Id_apoderado'] = intval($data['Id_apoderado']) ?: null;

    // Validaciones
    if (!$data['Nombre_estudiante'] ||
        !$data['Apellido_estudiante'] ||
        !$data['Rut_estudiante'] ||
        !$data['Fecha_nacimiento'] ||
        !$data['Fecha_ingreso']) {
        $errors[] = "Complete todos los campos obligatorios.";
    }
    if (!empty($data['Rut_estudiante']) && !dvRut($data['Rut_estudiante'])) {
        $errors[] = "RUT inválido.";
    }

    if (empty($errors)) {
        $rut_fmt = formatRut($data['Rut_estudiante']);
        $stmtRut = $conn->prepare("SELECT COUNT(*) FROM estudiantes WHERE Rut_estudiante = ?");
        $stmtRut->execute([$rut_fmt]);
        if ($stmtRut->fetchColumn() > 0) {
            $errors[] = "Ya existe un estudiante con RUT $rut_fmt.";
        }
    }

    if (empty($errors)) {
        // Determinar escuela desde el curso
        if ($data['Id_curso']) {
            $stmtE = $conn->prepare("SELECT Id_escuela FROM cursos WHERE Id_curso = ?");
            $stmtE->execute([$data['Id_curso']]);
            $rowE = $stmtE->fetch();
            $id_escuela = $rowE['Id_escuela'] ?? null;
        } else {
            $id_escuela = null;
        }

        // Inserción + auditoría
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

            // registrar auditoría
            $usuarioLog = $_SESSION['usuario']['id'];
            $auditData = $data;
            $auditData['Rut_estudiante'] = $rut_fmt;
            registrarAuditoria(
                $conn,
                $usuarioLog,
                'estudiantes',
                $newId,
                'INSERT',
                null,
                $auditData
            );

            $conn->commit();
            header("Location: index.php?seccion=estudiantes");
            exit;
        } catch (Exception $ex) {
            $conn->rollBack();
            $message = "<p class='text-danger'>Error al guardar: " 
                     . htmlspecialchars($ex->getMessage()) . "</p>";
        }
    } else {
        $message = '<div class="alert alert-danger"><ul>';
        foreach ($errors as $e) {
            $message .= "<li>" . htmlspecialchars($e) . "</li>";
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
    /* grid para labels e inputs */
    
    /* .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 1rem;
    } */

    .form-grid label {
      display: block;
      margin-bottom: .25rem;
      font-weight: bold;
    }
    .form-grid input,
    .form-grid select {
      width: 100%;
      padding: .5rem;
      box-sizing: border-box;
    }
    .resultado { cursor:pointer; padding:6px; border-bottom:1px solid #ddd; }
    .resultado:hover { background:#f0f0f0; }
    .seleccionado { background:#d1e7dd!important; font-weight:bold; }
  </style>
</head>
<body class="<?= ($_COOKIE['modo_oscuro'] ?? 'false') === 'true' ? 'dark-mode' : '' ?>">
<?php include '../header.php'; ?>
<div class="container d-flex">
  <?php include '../sidebar.php'; ?>
  <main class="main">
    <h2>Registrar nuevo estudiante</h2>
    <?= $message ?>

    <form method="POST" class="form-grid needs-validation" novalidate>
      <!-- Datos personales -->
      <h3 class="mb-4 subtitle">Datos personales</h3>
      <div>
        <label for="Nombre_estudiante">Nombres</label>
        <input id="Nombre_estudiante" type="text" name="Nombre_estudiante" required
               value="<?= htmlspecialchars($data['Nombre_estudiante']) ?>">
      </div>
      <div>
        <label for="Apellido_estudiante">Apellidos</label>
        <input id="Apellido_estudiante" type="text" name="Apellido_estudiante" required
               value="<?= htmlspecialchars($data['Apellido_estudiante']) ?>">
      </div>
      <div>
        <label for="Rut_estudiante">RUT</label>
        <input id="Rut_estudiante" name="Rut_estudiante" type="text" required
               placeholder="18.321.323-1"
               value="<?= htmlspecialchars($data['Rut_estudiante']) ?>">
      </div>
      <div>
        <label for="Fecha_nacimiento">Fecha de nacimiento</label>
        <input id="Fecha_nacimiento" name="Fecha_nacimiento" type="date" required
               value="<?= htmlspecialchars($data['Fecha_nacimiento']) ?>">
      </div>
      <div>
        <label for="Id_curso">Curso (opcional)</label>
        <select id="Id_curso" name="Id_curso">
          <option value="">-- Sin curso --</option>
          <?php foreach($cursos as $c): ?>
            <option value="<?= $c['Id_curso'] ?>"
              <?= $data['Id_curso']==$c['Id_curso'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['desc_curso']) ?>
            </option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label for="Fecha_ingreso">Fecha de ingreso</label>
        <input id="Fecha_ingreso" name="Fecha_ingreso" type="date" required
               value="<?= htmlspecialchars($data['Fecha_ingreso']) ?>">
      </div>
      <!-- Datos apoderado -->
      <h3 class="mb-4 subtitle">Datos de Apoderados</h3>
      <div>
        <label for="buscar_apoderado">Apoderado (opcional)</label>
        <input type="text" id="buscar_apoderado" placeholder="RUT o Nombre">
        <input type="hidden" name="Id_apoderado" id="Id_apoderado"
               value="<?= htmlspecialchars($data['Id_apoderado']) ?>">
        <div id="resultados_apoderado" class="border mt-1"></div>
      </div>
      <div style="grid-column:1/-1;">
        <button type="submit" class="btn btn-success">Guardar Datos</button>
      </div>
    </form>
  </main>
</div>

<script>
function buscar(endpoint, query, cont, idInput) {
  if (query.length < 3) { cont.innerHTML = ''; return; }
  fetch(endpoint + '?q=' + encodeURIComponent(query))
    .then(r=>r.json()).then(data=>{
      cont.innerHTML = '';
      if (!data.length) {
        cont.innerHTML = '<div class="p-2 text-muted">Sin resultados</div>';
        return;
      }
      data.forEach(item=>{
        const div = document.createElement('div');
        div.className = 'resultado';
        div.textContent = `${item.rut} - ${item.nombre} ${item.apellido}`;
        div.onclick = ()=>{
          document.getElementById(idInput).value = item.id;
          cont.innerHTML = `<div class="resultado seleccionado">${div.textContent} (Seleccionado)</div>`;
        };
        cont.appendChild(div);
      });
    });
}
document.getElementById('buscar_apoderado')
  .addEventListener('input', e=>{
    buscar('buscar_apoderados.php',
           e.target.value.trim(),
           document.getElementById('resultados_apoderado'),
           'Id_apoderado');
  });
</script>
</body>
</html>
