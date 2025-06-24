<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// 1) Protege la página
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// 2) Carga escuelas para el select
$escuelas = $conn->query("
    SELECT Id_escuela, Nombre_escuela
      FROM escuelas
    ORDER BY Nombre_escuela
")->fetchAll(PDO::FETCH_ASSOC);
?>
<h2>Registrar Nuevo Curso</h2>
  <form method="POST" action="../guardar_registro_curso.php" class="form-grid row g-3 needs-validation" novalidate>
    <div class="col-md-6">
      <label class="form-label">Tipo de Curso</label>
      <input name="Tipo_curso" type="text" class="form-control" required>
    </div>
    <div class="col-md-3 mt-1">
      <label class="form-label">Grado</label>
      <input name="Grado_curso" type="text" class="form-control" required>
    </div>
    <div class="col-md-3 mt-1">
      <label class="form-label">Sección</label>
      <input name="seccion_curso" type="text" class="form-control" required>
    </div>

    <div class="col-md-6 mt-1">
      <label class="form-label">Escuela</label>
      <select name="Id_escuela" class="form-select" required>
        <option value="">Seleccione...</option>
        <?php foreach($escuelas as $e): ?>
          <option value="<?= $e['Id_escuela'] ?>">
            <?= htmlspecialchars($e['Nombre_escuela']) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>

    <div class="col-md-6 mt-1">
      <label class="form-label">Docente (opcional)</label>
      <input type="text" id="buscar_profesional" class="form-control" placeholder="RUT o Nombre">
      <input type="hidden" name="Id_profesional" id="Id_profesional">
      <div id="resultados_profesional" class="border mt-1"></div>
    </div>

    <div class="col-12 mt-1 subtitle">
      <button type="submit" class="btn btn-success btn-height">Guardar Curso</button>
      <button class="btn btn-secondary btn-height">
        <a class="link-text" href="index.php?seccion=cursos">Cancelar</a>
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
