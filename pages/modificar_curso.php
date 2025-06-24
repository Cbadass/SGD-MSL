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
<h2>Editar Curso</h2>

  <form method="POST" action="../guardar_modificacion_curso.php" class="form-grid" novalidate>
    <input type="hidden" name="Id_curso" value="<?= $curso['Id_curso'] ?>">

    <div class="col-md-6 mt-1">
      <label class="form-label">Tipo de Curso</label>
      <input name="Tipo_curso" type="text" class="form-control input-width" required
             value="<?= htmlspecialchars($curso['Tipo_curso']) ?>">
    </div>
    <div class="col-md-3 mt-1">
      <label class="form-label">Grado</label>
      <input name="Grado_curso" type="text" class="form-control input-width" required
             value="<?= htmlspecialchars($curso['Grado_curso']) ?>">
    </div>
    <div class="col-md-3 mt-1">
      <label class="form-label">Sección</label>
      <input name="seccion_curso" type="text" class="form-control input-width" required
             value="<?= htmlspecialchars($curso['seccion_curso']) ?>">
    </div>

    <div class="col-md-6 mt-1">
      <label class="form-label">Escuela</label>
      <select name="Id_escuela" class="form-select input-width" required>
        <option value="">Seleccione...</option>
        <?php foreach($escuelas as $e): ?>
          <option value="<?= $e['Id_escuela'] ?>"
            <?= $curso['Id_escuela']==$e['Id_escuela']?'selected':'' ?>>
            <?= htmlspecialchars($e['Nombre_escuela']) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>

    <div class="col-md-6 mt-1">
      <label class="form-label">Docente (opcional)</label>
      <input type="text" id="buscar_profesional" class="form-control input-width" placeholder="RUT o Nombre">
      <input type="hidden" name="Id_profesional" id="Id_profesional"
             value="<?= htmlspecialchars($curso['Id_profesional']) ?>">
      <div id="resultados_profesional" class="border">
        <?php if($curso['Id_profesional']): ?>
          <div class="resultado seleccionado input-width">
            <?= htmlspecialchars($curso['Rut_profesional']) ?> —
            <?= htmlspecialchars($curso['Nombre_profesional'].' '.$curso['Apellido_profesional']) ?>
            (Seleccionado)
          </div>
        <?php endif ?>
      </div>
    </div>

    <div class="col-12 subtitle mt-1">
      <button type="submit" class="btn btn-success btn-height mr-1">Guardar Cambios</button>
      <button class="btn btn-secondary btn-height">
        <a href="index.php?seccion=cursos" class="link-text">Cancelar</a>
      </button>
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
