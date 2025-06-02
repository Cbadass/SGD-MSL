<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$id_documento = intval($_GET['id_documento'] ?? 0);
if ($id_documento <= 0) die("ID inválido.");

$stmt = $conn->prepare("SELECT * FROM documentos WHERE Id_documento = ?");
$stmt->execute([$id_documento]);
$doc = $stmt->fetch();

if (!$doc) die("Documento no encontrado.");
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Modificar Documento</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.resultado {
  cursor: pointer;
  padding: 6px 10px;
  border-bottom: 1px solid #ddd;
}
.resultado:hover {
  background-color: #f0f0f0;
}
.seleccionado {
  background-color: #d1e7dd !important;
  font-weight: bold;
}
</style>
</head>
<body class="container mt-4">

<h2 class="mb-4">Modificar Documento</h2>

<form method="POST" action="/guardar_modificacion_documento.php" enctype="multipart/form-data">
  <input type="hidden" name="id_documento" value="<?= $doc['Id_documento'] ?>">
  <input type="hidden" name="id_estudiante" id="id_estudiante" value="<?= htmlspecialchars($doc['Id_estudiante_doc'] ?? '') ?>">
  <input type="hidden" name="id_profesional" id="id_profesional" value="<?= htmlspecialchars($doc['Id_prof_doc'] ?? '') ?>">

  <div class="mb-3">
    <label for="nombre" class="form-label">Nombre del Documento</label>
    <input type="text" class="form-control" name="nombre" value="<?= htmlspecialchars($doc['Nombre_documento']) ?>" required>
  </div>

  <div class="mb-3">
    <label for="tipo" class="form-label">Tipo de Documento</label>
    <input type="text" class="form-control" name="tipo_documento" value="<?= htmlspecialchars($doc['Tipo_documento']) ?>">
  </div>

  <div class="mb-3">
    <label for="descripcion" class="form-label">Descripción</label>
    <textarea class="form-control" name="descripcion"><?= htmlspecialchars($doc['Descripcion']) ?></textarea>
  </div>

  <div class="mb-3">
    <label for="archivo" class="form-label">Actualizar archivo (opcional)</label>
    <input type="file" class="form-control" name="archivo">
  </div>

  <!-- Buscador Estudiante -->
  <div class="mb-3">
    <label class="form-label">Buscar Estudiante</label>
    <input type="text" id="buscar_estudiante" class="form-control" placeholder="RUT o Nombre">
    <div id="resultados_estudiante" class="border mt-1"></div>
  </div>

  <!-- Buscador Profesional -->
  <div class="mb-3">
    <label class="form-label">Buscar Profesional</label>
    <input type="text" id="buscar_profesional" class="form-control" placeholder="RUT o Nombre">
    <div id="resultados_profesional" class="border mt-1"></div>
  </div>

  <div class="mt-3">
    <button type="submit" class="btn btn-success">Guardar cambios</button>
    <a href="index.php?seccion=documentos" class="btn btn-secondary">Cancelar</a>
  </div>
</form>

<script>
function buscar(endpoint, query, contenedor, idInput) {
  if (query.length < 3) {
    contenedor.innerHTML = '';
    return;
  }
  fetch(`${endpoint}?q=${encodeURIComponent(query)}`)
    .then(res => res.json())
    .then(data => {
      contenedor.innerHTML = '';
      if (data.length === 0) {
        contenedor.innerHTML = '<div class="p-2 text-muted">Sin resultados</div>';
        return;
      }
      data.forEach(item => {
        const div = document.createElement('div');
        if (endpoint.includes('estudiantes')) {
          div.textContent = `${item.rut} - ${item.nombre} ${item.apellido} (${item.Tipo_curso || ''} ${item.Grado_curso || ''} / ${item.Nombre_escuela || ''})`;
        } else {
          div.textContent = `${item.rut} - ${item.nombre} ${item.apellido} (${item.Cargo_profesional || ''} / ${item.Nombre_escuela || ''})`;
        }
        div.className = 'resultado';
        div.onclick = () => {
          document.getElementById(idInput).value = item.id;
          div.classList.add('seleccionado');
          contenedor.innerHTML = `<div class="resultado seleccionado">${div.textContent} (Seleccionado)</div>`;
        };
        contenedor.appendChild(div);
      });
    });
}

document.getElementById('buscar_estudiante').addEventListener('input', function() {
  buscar('buscar_estudiantes.php', this.value.trim(), document.getElementById('resultados_estudiante'), 'id_estudiante');
});

document.getElementById('buscar_profesional').addEventListener('input', function() {
  buscar('buscar_profesionales.php', this.value.trim(), document.getElementById('resultados_profesional'), 'id_profesional');
});
</script>

</body>
</html>
