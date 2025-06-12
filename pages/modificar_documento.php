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
        <label for="tipo_documento" class="form-label">Tipo de documento</label>
        <select name="tipo_documento" id="tipo_documento" class="form-select" required>
          <?php
          $tipos_estudiantes = [
            "Certificado de Nacimiento", "Ficha de Matrícula", "Certificado alumno prioritario", "Antecedentes en caso de emergencia",
            "Autorización para evaluar y reevaluar", "Autorización de la muda", "Informe Psicológico", "Protocolos de prueba aplicada",
            "Prueba de conducta adaptativa ICAAP", "Formulario de ingreso FUDEI", "Formulario NEEP", "Plan de Apoyo Individual PAI",
            "Formulario NEET", "Plan de Adecuaciones Curriculares Individualizado PACI", "Informe pedagógico curricular",
            "Informe a la Familia", "Informe Pedagógico 1er semestre", "Informe Pedagógico 2do semestre", "Informe Personalidad 1er semestre",
            "Informe Personalidad 2do semestre", "Informe Vocacional 1er semestre", "Informe vocacional 2do semestre",
            "Informe de Notas 1er semestre", "Informe de notas 2do semestre", "Certificado de estudios MINEDUC", "Valoración de salud",
            "Informe fonoaudiológico", "Informe kinesiológico", "Informe Terapeuta Ocupacional", "Derivaciones a especialistas",
            "Informes médicos", "Recetas médicas", "Antecedentes judiciales", "Pruebas diagnósticas", "Hoja de vida del estudiante",
            "Ficha desregulación emocional y conductual DEC", "Otros", "Declaración de matrícula", "Screening",
            "Test Comprensión auditiva del Lenguaje TECAL", "Test para evaluar procesos de simplificación fonológica TEPROSIF",
            "Test de la articulación a la repetición TAR", "Habilidades pragmáticas", "Órganos fonoarticulatorios",
            "Formulario NEEP reevaluación (diciembre)", "Informe a la Familia Marzo", "Estado de avance a la Familia Junio"
          ];

          $tipos_docentes = [
            "Curriculum", "Certificado de título", "Certificado de registro MINEDUC", "Certificado de antecedentes para fines especiales",
            "Certificado de consulta de inhabilidades para trabajar con menores de edad", "Certificado de consulta de inhabilidades por maltrato relevante",
            "Ficha personal", "Contrato de trabajo", "Recepción del Reglamento Interno de Higiene y Seguridad", "Anexos de contratos",
            "Certificado de afiliación AFP", "Certificado de afiliación al sistema de salud", "Certificados de perfeccionamientos",
            "Carta aviso de cese de funciones", "Finiquito", "Certificado de estudios para fines laborales", "Licencia de Educación Media",
            "Certificado de inscripción en el Registro Nacional de Prestadores Individuales de Salud", "Hoja de vida conductr",
            "Licencia conducir legalizada"
          ];

          echo '<optgroup label="Estudiantes">';
          foreach ($tipos_estudiantes as $tipo) {
            $selected = ($doc['Tipo_documento'] === $tipo) ? 'selected' : '';
            echo "<option value=\"$tipo\" $selected>$tipo</option>";
          }
          echo '</optgroup>';

          echo '<optgroup label="Docentes">';
          foreach ($tipos_docentes as $tipo) {
            $selected = ($doc['Tipo_documento'] === $tipo) ? 'selected' : '';
            echo "<option value=\"$tipo\" $selected>$tipo</option>";
          }
          echo '</optgroup>';
          ?>
        </select>

    </div>


  <div class="mb-3">
    <label for="descripcion" class="form-label">Descripción</label>
    <textarea class="form-control" name="descripcion"><?= htmlspecialchars($doc['Descripcion']) ?></textarea>
  </div>

  <div class="mb-3">
    <label for="archivo" class="form-label">Actualizar archivo (opcional)</label>
    <input type="file" class="form-control" name="archivo" id="archivo">
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
<script>
document.getElementById('archivo').addEventListener('change', function() {
  const archivo = this.files[0];
  if (!archivo) return;

  const nombreArchivo = archivo.name.toLowerCase();
  const extensionesPermitidas = ['doc', 'docx', 'odt', 'pdf', 'txt', 'xls', 'xlsx', 'ods', 'ppt', 'pptx', 'odp', 'jpg', 'jpeg', 'png', 'gif'];

  const extension = nombreArchivo.split('.').pop();
  if (!extensionesPermitidas.includes(extension)) {
    alert('Tipo de archivo no permitido. Solo se permiten documentos de Office, PDF, TXT e imágenes.');
    this.value = ''; // Limpia el campo para evitar el envío
  }
});
</script>

<script>
document.getElementById('archivo').addEventListener('change', function() {
  const archivo = this.files[0];
  if (!archivo) return;

  const nombreArchivo = archivo.name.toLowerCase();
  const extensionesPermitidas = ['doc', 'docx', 'odt', 'pdf', 'txt', 'xls', 'xlsx', 'ods', 'ppt', 'pptx', 'odp', 'jpg', 'jpeg', 'png'];

  const extension = nombreArchivo.split('.').pop();
  if (!extensionesPermitidas.includes(extension)) {
    alert('Tipo de archivo no permitido. Solo se permiten documentos de Office, PDF, TXT e imágenes.');
    this.value = ''; // Limpiar el campo para forzar la selección de uno válido
  }
});
</script>

</body>
</html>
