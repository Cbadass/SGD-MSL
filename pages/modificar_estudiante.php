<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// 1) Protege la página
if (!isset($_SESSION['usuario'])) {
    header("Location: ../login.php");
    exit;
}

// 2) Recoge el Id de la URL
$id = intval($_GET['Id_estudiante'] ?? 0);
if ($id <= 0) {
    die("ID inválido.");
}

// 3) Trae datos del estudiante + apoderado
$stmt = $conn->prepare("
    SELECT e.*, a.Id_apoderado, a.Nombre_apoderado, a.Apellido_apoderado
      FROM estudiantes e
 LEFT JOIN apoderados a ON e.Id_apoderado = a.Id_apoderado
     WHERE e.Id_estudiante = ?
");
$stmt->execute([$id]);
$est = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$est) {
    die("Estudiante no encontrado.");
}

// 4) Carga cursos para el select
$stmt2 = $conn->query("
    SELECT c.Id_curso,
           CONCAT(c.Tipo_curso,' - ',c.Grado_curso,'/',c.seccion_curso,' (',esc.Nombre_escuela,')') AS desc_curso
      FROM cursos c
 LEFT JOIN escuelas esc ON c.Id_escuela = esc.Id_escuela
    ORDER BY c.Tipo_curso, c.Grado_curso, c.seccion_curso
");
$cursos = $stmt2->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Estudiante</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .resultado { cursor:pointer; padding:6px; border-bottom:1px solid #ddd; }
    .resultado:hover { background:#f0f0f0; }
    .seleccionado { background:#d1e7dd!important; font-weight:bold; }
  </style>
</head>
<body class="p-4">
  <h2>Editar Estudiante</h2>

  <form method="POST" action="../guardar_modificacion_estudiante.php" enctype="multipart/form-data" class="row g-3 needs-validation" novalidate>
    <input type="hidden" name="Id_estudiante" value="<?= $est['Id_estudiante'] ?>">

    <div class="col-md-4">
      <label class="form-label">Nombres</label>
      <input name="Nombre_estudiante" class="form-control" required
             value="<?= htmlspecialchars($est['Nombre_estudiante']) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Apellidos</label>
      <input name="Apellido_estudiante" class="form-control" required
             value="<?= htmlspecialchars($est['Apellido_estudiante']) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">RUT</label>
      <input name="Rut_estudiante" class="form-control" placeholder="20.384.593-4" required
             value="<?= htmlspecialchars($est['Rut_estudiante']) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label">Fecha de Nacimiento</label>
      <input name="Fecha_nacimiento" type="date" class="form-control"
             value="<?= htmlspecialchars($est['Fecha_nacimiento']) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Fecha de Ingreso</label>
      <input name="Fecha_ingreso" type="date" class="form-control"
             value="<?= htmlspecialchars($est['Fecha_ingreso']) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label">Estado</label>
      <select name="Estado_estudiante" class="form-select" required>
        <option value="1" <?= $est['Estado_estudiante']==1?'selected':'' ?>>Activo</option>
        <option value="0" <?= $est['Estado_estudiante']==0?'selected':'' ?>>Inactivo</option>
      </select>
    </div>

    <div class="col-md-6">
      <label class="form-label">Curso (opcional)</label>
      <select name="Id_curso" class="form-select">
        <option value="">-- Sin cambio --</option>
        <?php foreach($cursos as $c): ?>
          <option value="<?= $c['Id_curso'] ?>"
            <?= $est['Id_curso']==$c['Id_curso']?'selected':'' ?>>
            <?= htmlspecialchars($c['desc_curso']) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>

    <!-- Buscador Apoderado -->
    <div class="col-md-6">
      <label class="form-label">Apoderado (opcional)</label>
      <input type="text" id="buscar_apoderado" class="form-control" placeholder="RUT o Nombre">
      <input type="hidden" name="Id_apoderado" id="Id_apoderado"
             value="<?= htmlspecialchars($est['Id_apoderado']) ?>">
      <div id="resultados_apoderado" class="border mt-1">
        <?php if($est['Id_apoderado']): ?>
          <div class="resultado seleccionado">
            <?= htmlspecialchars($est['Rut_apoderado'] ?? '') ?>
            <?= htmlspecialchars($est['Nombre_apoderado'] . ' ' . $est['Apellido_apoderado']) ?>
            (Seleccionado)
          </div>
        <?php endif ?>
      </div>
    </div>

    <div class="col-12">
      <button type="submit" class="btn btn-success">Guardar cambios</button>
      <a href="index.php?seccion=estudiantes" class="btn btn-secondary">Cancelar</a>
    </div>
  </form>

  <script>
  function buscar(endpoint, query, cont, idInput) {
    if (query.length < 3) {
      cont.innerHTML = '';
      return;
    }
    fetch(endpoint + '?q=' + encodeURIComponent(query))
      .then(r=>r.json())
      .then(data=>{
        cont.innerHTML = '';
        if (!data.length) {
          cont.innerHTML = '<div class="p-2 text-muted">Sin resultados</div>';
          return;
        }
        data.forEach(item=>{
          const div = document.createElement('div');
          div.className = 'resultado';
          div.textContent = item.rut + ' - ' + item.nombre + ' ' + item.apellido;
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
      buscar('buscar_apoderados.php', e.target.value.trim(),
             document.getElementById('resultados_apoderado'),
             'Id_apoderado');
    });
  </script>
</body>
</html>
