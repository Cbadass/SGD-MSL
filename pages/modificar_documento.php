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


<h2 class="mb-4">Modificar Documento</h2>

<form method="POST" class="form-grid" action="/guardar_modificacion_documento.php" enctype="multipart/form-data">
    <input type="hidden" name="id_documento" value="<?= $doc['Id_documento'] ?>">
    <input type="hidden" name="id_estudiante" id="id_estudiante" value="<?= htmlspecialchars($doc['Id_estudiante_doc'] ?? '') ?>">
    <input type="hidden" name="id_profesional" id="id_profesional" value="<?= htmlspecialchars($doc['Id_prof_doc'] ?? '') ?>">

    <div class="mt-1">
        <label for="nombre" class="form-label">Nombre del Documento</label>
        <input type="text" class="form-control" name="nombre" value="<?= htmlspecialchars($doc['Nombre_documento']) ?>" required>
    </div>

    <div class="mt-1">
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


  <div class="mt-1">
    <label for="descripcion" class="form-label">Descripción</label>
    <textarea class="form-control" name="descripcion" style="padding: 10px; border-radius: 6px; border: 1px solid #ccc; font-size: 14px; width: 100%;" placeholder="Ingresa una descripción"><?= htmlspecialchars($doc['Descripcion']) ?></textarea>
  </div>

  <div class="mt-1">
    <label for="archivo" class="form-label">Actualizar archivo (opcional)</label>
    <input type="file" class="form-control" name="archivo" id="archivo">
    </div>

<!-- Buscador Estudiante -->
<div class="mt-1">
  <label class="form-label">Buscar Estudiante</label>
  <input type="text" id="buscar_estudiante" class="form-control" placeholder="RUT o Nombre">
  <input type="hidden" name="id_estudiante" id="id_estudiante" value="<?= htmlspecialchars($doc['Id_estudiante_doc'] ?? '') ?>">
  <div id="resultados_estudiante" class="border"></div>
  <?php
    if ($doc['Id_estudiante_doc']) {
        $stmtEst = $conn->prepare("SELECT Rut_estudiante, Nombre_estudiante, Apellido_estudiante FROM estudiantes WHERE Id_estudiante = ?");
        $stmtEst->execute([$doc['Id_estudiante_doc']]);
        $est = $stmtEst->fetch();
        if ($est) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('resultados_estudiante').innerHTML =
                    `<div class='resultado seleccionado'>{$est['Rut_estudiante']} - {$est['Nombre_estudiante']} {$est['Apellido_estudiante']} (Seleccionado)</div>`;
                });
            </script>";
        }
    }
  ?>
</div>

<!-- Buscador Profesional -->
<div class="mt-1">
  <label class="form-label">Buscar Profesional</label>
  <input type="text" id="buscar_profesional" class="form-control" placeholder="RUT o Nombre">
  <input type="hidden" name="id_profesional" id="id_profesional" value="<?= htmlspecialchars($doc['Id_prof_doc'] ?? '') ?>">
  <div id="resultados_profesional" class="border"></div>
  <?php
    if ($doc['Id_prof_doc']) {
        $stmtProf = $conn->prepare("SELECT Rut_profesional, Nombre_profesional, Apellido_profesional FROM profesionales WHERE Id_profesional = ?");
        $stmtProf->execute([$doc['Id_prof_doc']]);
        $prof = $stmtProf->fetch();
        if ($prof) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('resultados_profesional').innerHTML =
                    `<div class='resultado seleccionado'>{$prof['Rut_profesional']} - {$prof['Nombre_profesional']} {$prof['Apellido_profesional']} (Seleccionado)</div>`;
                });
            </script>";
        }
    }
  ?>
</div>


  <div class="subtitle mt-1">
    <button type="submit" class="btn btn-success btn-height mr-1">Guardar cambios</button>
    <button class="btn btn-secondary btn-height">
      <a href="index.php?seccion=documentos" class="link-text">Cancelar</a>
    </button>
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
