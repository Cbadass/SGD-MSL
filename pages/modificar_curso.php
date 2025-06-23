<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// 1) Protege la página
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// 2) Valida ID en la URL
$id = intval($_GET['Id_curso'] ?? 0);
if ($id <= 0) die("ID inválido.");

// 3) Trae datos del curso
$stmt = $conn->prepare("
    SELECT c.*, p.Rut_profesional, p.Nombre_profesional, p.Apellido_profesional
      FROM cursos c
 LEFT JOIN profesionales p ON c.Id_profesional = p.Id_profesional
     WHERE c.Id_curso = ?
");
$stmt->execute([$id]);
$curso = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$curso) die("Curso no encontrado.");

// 4) Carga escuelas
$escuelas = $conn->query("
    SELECT Id_escuela, Nombre_escuela
      FROM escuelas
    ORDER BY Nombre_escuela
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!-- <!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Curso</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .resultado { cursor:pointer; padding:6px; border-bottom:1px solid #ddd; }
    .resultado:hover { background:#f0f0f0; }
    .seleccionado { background:#d1e7dd!important; font-weight:bold; }
  </style>
</head>
<body class="p-4">  -->
  <h2>Editar Curso</h2>

  <form method="POST" action="../guardar_modificacion_curso.php" class="row g-3 needs-validation" novalidate>
    <input type="hidden" name="Id_curso" value="<?= $curso['Id_curso'] ?>">

    <div class="col-md-6">
      <label class="form-label">Tipo de Curso</label>
      <input name="Tipo_curso" class="form-control" required
             value="<?= htmlspecialchars($curso['Tipo_curso']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Grado</label>
      <input name="Grado_curso" class="form-control" required
             value="<?= htmlspecialchars($curso['Grado_curso']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Sección</label>
      <input name="seccion_curso" class="form-control" required
             value="<?= htmlspecialchars($curso['seccion_curso']) ?>">
    </div>

    <div class="col-md-6">
      <label class="form-label">Escuela</label>
      <select name="Id_escuela" class="form-select" required>
        <option value="">Seleccione...</option>
        <?php foreach($escuelas as $e): ?>
          <option value="<?= $e['Id_escuela'] ?>"
            <?= $curso['Id_escuela']==$e['Id_escuela']?'selected':'' ?>>
            <?= htmlspecialchars($e['Nombre_escuela']) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>

    <div class="col-md-6">
      <label class="form-label">Docente (opcional)</label>
      <input type="text" id="buscar_profesional" class="form-control" placeholder="RUT o Nombre">
      <input type="hidden" name="Id_profesional" id="Id_profesional"
             value="<?= htmlspecialchars($curso['Id_profesional']) ?>">
      <div id="resultados_profesional" class="border mt-1">
        <?php if($curso['Id_profesional']): ?>
          <div class="resultado seleccionado">
            <?= htmlspecialchars($curso['Rut_profesional']) ?> —
            <?= htmlspecialchars($curso['Nombre_profesional'].' '.$curso['Apellido_profesional']) ?>
            (Seleccionado)
          </div>
        <?php endif ?>
      </div>
    </div>

    <div class="col-12">
      <button type="submit" class="btn btn-success">Guardar Cambios</button>
      <a href="index.php?seccion=cursos" class="btn btn-secondary">Cancelar</a>
    </div>
  </form>

  <script>
  function buscar(endpoint, query, cont, idInput) {
    if (query.length < 3) { cont.innerHTML = ''; return; }
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
          div.textContent = item.rut + ' — ' + item.nombre + ' ' + item.apellido;
          div.onclick = ()=>{
            document.getElementById(idInput).value = item.id;
            cont.innerHTML = `<div class="resultado seleccionado">${div.textContent} (Seleccionado)</div>`;
          };
          cont.appendChild(div);
        });
      });
  }

  document.getElementById('buscar_profesional')
    .addEventListener('input', e=>{
      buscar('buscar_profesionales.php', e.target.value.trim(),
             document.getElementById('resultados_profesional'),
             'Id_profesional');
    });
  </script>
</body>
</html>
