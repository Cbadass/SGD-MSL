<?php
// pages/registrar_estudiante.php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auditoria.php';

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
    if ($res === 11) $expected = '0';
    elseif ($res === 10) $expected = 'K';
    else $expected = (string)$res;
    return $dv === $expected;
}
function formatRut($rut) {
    $R      = cleanRut($rut);
    $number = substr($R, 0, -1);
    $dv     = strtoupper(substr($R, -1));
    return number_format($number, 0, ',', '.') . "-$dv";
}

// 2) Carga cursos (con su escuela)
$stmt = $conn->query("
    SELECT 
      c.Id_curso,
      CONCAT(c.Tipo_curso,' - ',c.Grado_curso,'/',c.seccion_curso,
             ' (',esc.Nombre_escuela,')') AS desc_curso
    FROM cursos c
    LEFT JOIN escuelas esc ON c.Id_escuela = esc.Id_escuela
    ORDER BY c.Tipo_curso, c.Grado_curso, c.seccion_curso
");
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$data   = [
    'nombre'      => '',
    'apellido'    => '',
    'rut'         => '',
    'fecha_nac'   => '',
    'fecha_ing'   => '',
    'Id_curso'    => '',
    'Id_apoderado'=> ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 3) Captura y sanitiza
    foreach ($data as $k => &$v) {
        $v = trim($_POST[$k] ?? '');
    }
    unset($v);

    // 4) Validaciones
    if ($data['nombre'] === '') {
        $errors[] = "El nombre es obligatorio.";
    }
    if ($data['apellido'] === '') {
        $errors[] = "El apellido es obligatorio.";
    }
    if ($data['rut'] === '') {
        $errors[] = "El RUT es obligatorio.";
    } elseif (!dvRut($data['rut'])) {
        $errors[] = "El RUT no es válido.";
    } else {
        $rut_fmt = formatRut($data['rut']);
        $chk = $conn->prepare("SELECT COUNT(*) FROM estudiantes WHERE Rut_estudiante = ?");
        $chk->execute([$rut_fmt]);
        if ($chk->fetchColumn() > 0) {
            $errors[] = "Ya existe un estudiante con RUT $rut_fmt.";
        }
    }
    if ($data['fecha_nac'] === '' || $data['fecha_ing'] === '') {
        $errors[] = "Fechas de nacimiento e ingreso son obligatorias.";
    }

    if (empty($errors)) {
        // Determina Id_escuela desde curso
        $id_curso     = intval($data['Id_curso'])    ?: null;
        $id_apoderado = intval($data['Id_apoderado'])?: null;
        if ($id_curso) {
            $stmtE = $conn->prepare("SELECT Id_escuela FROM cursos WHERE Id_curso = ?");
            $stmtE->execute([$id_curso]);
            $rowE = $stmtE->fetch();
            $id_escuela = $rowE['Id_escuela'] ?? null;
        } else {
            $id_escuela = null;
        }

        // Inserta estudiante
        $stmtI = $conn->prepare("
            INSERT INTO estudiantes
              (Nombre_estudiante, Apellido_estudiante, Rut_estudiante,
               Fecha_nacimiento, Fecha_ingreso, Estado_estudiante,
               Id_curso, Id_apoderado, Id_escuela)
            VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)
        ");
        $ok = $stmtI->execute([
            $data['nombre'],
            $data['apellido'],
            $rut_fmt,
            $data['fecha_nac'],
            $data['fecha_ing'],
            $id_curso,
            $id_apoderado,
            $id_escuela
        ]);

        if ($ok) {
            // 5) Auditoría
            $nuevo = [
                'Nombre_estudiante'   => $data['nombre'],
                'Apellido_estudiante' => $data['apellido'],
                'Rut_estudiante'      => $rut_fmt,
                'Fecha_nacimiento'    => $data['fecha_nac'],
                'Fecha_ingreso'       => $data['fecha_ing'],
                'Estado_estudiante'   => 1,
                'Id_curso'            => $id_curso,
                'Id_apoderado'        => $id_apoderado,
                'Id_escuela'          => $id_escuela
            ];
            $newId      = $conn->lastInsertId();
            $usuario_id = $_SESSION['usuario']['id'];
            registrarAuditoria($conn, $usuario_id, 'estudiantes', $newId, 'INSERT', null, $nuevo);

            // 6) Redirección clásica + JS + Meta-refresh
            header("Location: index.php?seccion=estudiantes");
            echo '<script>location.href="index.php?seccion=estudiantes";</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=index.php?seccion=estudiantes"></noscript>';
            exit;
        } else {
            $errors[] = "Error inesperado al guardar.";
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
    /* Grid para el layout principal */
    .layout { display:grid; grid-template-columns:250px 1fr; gap:1rem; }
    /* Grid para el formulario */
    .form-grid {
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
      gap:1rem;
      margin-top:1rem;
    }
    .form-grid label {
      font-weight:600; margin-bottom:0.25rem;
    }
    .form-grid input,
    .form-grid select {
      padding:0.5rem;
      border:1px solid #ccc;
      border-radius:4px;
      width:100%;
    }
    .alert { padding:0.75rem; background:#f8d7da; color:#842029; border-radius:4px; }
  </style>
</head>
<body class="<?= (($_COOKIE['modo_oscuro'] ?? '') === 'true' ? 'dark-mode' : '') ?>">
  <?php include __DIR__ . '/../components/header.php'; ?>

  <div class="container layout">
    <?php include __DIR__ . '/../components/sidebar.php'; ?>
    <main>
      <h2>Registrar nuevo estudiante</h2>

      <?php if ($errors): ?>
      <div class="alert">
        <ul>
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach ?>
        </ul>
      </div>
      <?php endif ?>

      <form method="POST" class="form-grid" novalidate>
        <div>
          <label for="nombre">Nombres *</label>
          <input id="nombre" name="nombre" required
                 value="<?= htmlspecialchars($data['nombre']) ?>">
        </div>
        <div>
          <label for="apellido">Apellidos *</label>
          <input id="apellido" name="apellido" required
                 value="<?= htmlspecialchars($data['apellido']) ?>">
        </div>
        <div>
          <label for="rut">RUT *</label>
          <input id="rut" name="rut" placeholder="20.384.593-4" required
                 value="<?= htmlspecialchars($data['rut']) ?>">
        </div>
        <div>
          <label for="fecha_nac">Fecha de nacimiento *</label>
          <input id="fecha_nac" name="fecha_nac" type="date" required
                 value="<?= htmlspecialchars($data['fecha_nac']) ?>">
        </div>
        <div>
          <label for="fecha_ing">Fecha de ingreso *</label>
          <input id="fecha_ing" name="fecha_ing" type="date" required
                 value="<?= htmlspecialchars($data['fecha_ing']) ?>">
        </div>
        <div>
          <label for="Id_curso">Curso (opcional)</label>
          <select id="Id_curso" name="Id_curso">
            <option value="">-- Sin curso --</option>
            <?php foreach ($cursos as $c): ?>
            <option value="<?= $c['Id_curso'] ?>"
              <?= ($data['Id_curso'] == $c['Id_curso']) ? 'selected' : ''?>>
              <?= htmlspecialchars($c['desc_curso']) ?>
            </option>
            <?php endforeach ?>
          </select>
        </div>
        <div>
          <label for="buscar_apoderado">Apoderado (opcional)</label>
          <input type="text" id="buscar_apoderado" placeholder="RUT o Nombre">
          <input type="hidden" name="Id_apoderado" id="Id_apoderado"
                 value="<?= htmlspecialchars($data['Id_apoderado']) ?>">
          <div id="resultados_apoderado" style="border:1px solid #ccc; max-height:150px; overflow:auto;"></div>
        </div>
        <div style="grid-column:1/-1; text-align:right;">
          <button type="submit" class="btn btn-primary">Guardar Estudiante</button>
        </div>
      </form>
    </main>
  </div>

  <script>
  // Buscador de apoderados (igual al de subir_documento)
  function buscar(endpoint, query, cont, idInput) {
    if (query.length < 3) { cont.innerHTML = ''; return; }
    fetch(endpoint + '?q=' + encodeURIComponent(query))
      .then(r => r.json())
      .then(data => {
        cont.innerHTML = '';
        if (!data.length) {
          cont.innerHTML = '<div class="p-2 text-muted">Sin resultados</div>';
          return;
        }
        data.forEach(item => {
          const div = document.createElement('div');
          div.textContent = `${item.rut} — ${item.nombre} ${item.apellido}`;
          div.style.padding = '0.5rem'; div.style.cursor = 'pointer';
          div.onclick = () => {
            document.getElementById(idInput).value = item.id;
            cont.innerHTML = `<div style="background:#d1e7dd;padding:0.5rem;">
                               ${div.textContent} (Seleccionado)
                             </div>`;
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
