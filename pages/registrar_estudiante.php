<?php
// pages/registrar_estudiante.php
session_start();
require_once __DIR__ . '/../includes/db.php';

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

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // — Captura y trim de inputs —
    $nombre       = trim($_POST['Nombre_estudiante'] ?? '');
    $apellido     = trim($_POST['Apellido_estudiante'] ?? '');
    $rut          = trim($_POST['Rut_estudiante'] ?? '');
    $fecha_nac    = trim($_POST['Fecha_nacimiento'] ?? '');
    $fecha_ing    = trim($_POST['Fecha_ingreso'] ?? '');
    $id_curso     = intval($_POST['Id_curso'] ?? 0) ?: null;
    $id_apoderado = intval($_POST['Id_apoderado'] ?? 0) ?: null;

    // — Validaciones —
    if (!$nombre || !$apellido || !$rut || !$fecha_nac || !$fecha_ing) {
        $message = "<p class='text-danger'>Complete todos los campos obligatorios.</p>";
    }
    elseif (!dvRut($rut)) {
        $message = "<p class='text-danger'>RUT inválido.</p>";
    }
    else {
        // Formatear RUT
        $rut_fmt = formatRut($rut);
        // Unicidad de RUT
        $stmtRut = $conn->prepare("SELECT COUNT(*) FROM estudiantes WHERE Rut_estudiante = ?");
        $stmtRut->execute([$rut_fmt]);
        if ($stmtRut->fetchColumn() > 0) {
            $message = "<p class='text-danger'>Ya existe un estudiante con RUT $rut_fmt.</p>";
        } else {
            // Determinar escuela a partir del curso (si hay)
            if ($id_curso) {
                $stmtE = $conn->prepare("SELECT Id_escuela FROM cursos WHERE Id_curso = ?");
                $stmtE->execute([$id_curso]);
                $rowE = $stmtE->fetch();
                $id_escuela = $rowE['Id_escuela'] ?? null;
            } else {
                $id_escuela = null;
            }
            // Insertar
            $stmtI = $conn->prepare("
                INSERT INTO estudiantes
                  (Nombre_estudiante, Apellido_estudiante, Rut_estudiante,
                   Fecha_nacimiento, Fecha_ingreso, Estado_estudiante,
                   Id_curso, Id_apoderado, Id_escuela)
                VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)
            ");
            $ok = $stmtI->execute([
                $nombre, $apellido, $rut_fmt,
                $fecha_nac, $fecha_ing,
                $id_curso, $id_apoderado, $id_escuela
            ]);
            if ($ok) {
                // Redirección limpia
                header("Location: index.php?seccion=estudiantes");
                // Fallback JS / meta-refresh si header falla
                echo '<script>window.location.href="index.php?seccion=estudiantes";</script>';
                echo '<noscript><meta http-equiv="refresh" content="0;url=index.php?seccion=estudiantes"></noscript>';
                exit;
            } else {
                $message = "<p class='text-danger'>Error al guardar el estudiante.</p>";
            }
        }
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
<body class="<?= ($_COOKIE['modo_oscuro'] ?? 'false') === 'true' ? 'dark-mode' : '' ?>">
<?php include __DIR__ . '/../components/header.php'; ?>
<div class="container d-flex">
  <?php include __DIR__ . '/../components/sidebar.php'; ?>
  <main class="main">
      <h2>Registrar nuevo estudiante</h2>
      <?= $message ?>

      <form method="POST" class="row g-3 needs-validation" novalidate>
        <div class="col-md-6">
          <label class="form-label">Nombres</label>
          <input name="Nombre_estudiante" class="form-control" required
                 value="<?= htmlspecialchars($_POST['Nombre_estudiante'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Apellidos</label>
          <input name="Apellido_estudiante" class="form-control" required
                 value="<?= htmlspecialchars($_POST['Apellido_estudiante'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">RUT</label>
          <input name="Rut_estudiante" class="form-control" placeholder="20.384.593-4" required
                 value="<?= htmlspecialchars($_POST['Rut_estudiante'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Fecha de nacimiento</label>
          <input name="Fecha_nacimiento" type="date" class="form-control" required
                 value="<?= htmlspecialchars($_POST['Fecha_nacimiento'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Fecha de ingreso</label>
          <input name="Fecha_ingreso" type="date" class="form-control" required
                 value="<?= htmlspecialchars($_POST['Fecha_ingreso'] ?? '') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Curso (opcional)</label>
          <select name="Id_curso" class="form-select">
            <option value="">-- Sin curso --</option>
            <?php foreach ($cursos as $c): ?>
              <option value="<?= $c['Id_curso'] ?>"
                <?= (isset($_POST['Id_curso']) && $_POST['Id_curso']==$c['Id_curso']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['desc_curso']) ?>
              </option>
            <?php endforeach ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Apoderado (opcional)</label>
          <input type="text" id="buscar_apoderado" class="form-control" placeholder="RUT o Nombre">
          <input type="hidden" name="Id_apoderado" id="Id_apoderado"
                 value="<?= htmlspecialchars($_POST['Id_apoderado'] ?? '') ?>">
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
  if (query.length < 3) {
    cont.innerHTML = '';
    return;
  }
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
