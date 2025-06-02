<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Subir Documento</title>
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

<h2 class="mb-4">Subir Documento</h2>

<form method="POST" action="procesar_subir_documento.php" enctype="multipart/form-data">
  <div class="mb-3">
    <label for="nombre" class="form-label">Nombre del Documento</label>
    <input type="text" class="form-control" name="nombre" id="nombre" required>
  </div>

  <div class="mb-3">
    <label for="tipo_documento" class="form-label">Tipo de documento</label>
    <select name="tipo_documento" id="tipo_documento" class="form-select" required>
      <optgroup label="Estudiantes">
        <option value="Certificado de Nacimiento">Certificado de Nacimiento</option>
        <!-- ...otros tipos igual que en modificar_documento.php... -->
        <option value="Estado de avance a la Familia Junio">Estado de avance a la Familia Junio</option>
      </optgroup>
      <optgroup label="Docentes">
        <option value="Curriculum">Curriculum</option>
        <!-- ...otros tipos igual que en modificar_documento.php... -->
        <option value="Licencia conducir legalizada">Licencia conducir legalizada</option>
      </optgroup>
    </select>
  </div>

  <div class="mb-3">
    <label for="descripcion" class="form-label">Descripción</label>
    <textarea class="form-control" name="descripcion" id="descripcion"></textarea>
  </div>

  <div class="mb-3">
    <label for="archivo" class="form-label">Archivo</label>
    <input type="file" class="form-control" name="archivo" id="archivo" required>
  </div>

  <!-- Buscador Estudiante -->
  <div class="mb-3">
    <label class="form-label">Buscar Estudiante</label>
    <input type="text" id="buscar_estudiante" class="form-control" placeholder="RUT o Nombre">
    <input type="hidden" name="id_estudiante" id="id_estudiante">
    <div id="resultados_estudiante" class="border mt-1"></div>
  </div>

  <!-- Buscador Profesional -->
  <div class="mb-3">
    <label class="form-label">Buscar Profesional</label>
    <input type="text" id="buscar_profesional" class="form-control" placeholder="RUT o Nombre">
    <input type="hidden" name="id_profesional" id="id_profesional">
    <div id="resultados_profesional" class="border mt-1"></div>
  </div>

  <button type="submit" class="btn btn-success">Subir Documento</button>
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

document.getElementById('archivo').addEventListener('change', function() {
  const archivo = this.files[0];
  if (!archivo) return;

  const nombreArchivo = archivo.name.toLowerCase();
  const extensionesPermitidas = ['doc', 'docx', 'odt', 'pdf', 'txt', 'xls', 'xlsx', 'ods', 'ppt', 'pptx', 'odp', 'jpg', 'jpeg', 'png', 'gif'];
  const extension = nombreArchivo.split('.').pop();
  if (!extensionesPermitidas.includes(extension)) {
    alert('Tipo de archivo no permitido. Solo se permiten documentos de Office, PDF, TXT e imágenes.');
    this.value = '';
  }
});
</script>

</body>
</html>
