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
<link rel="stylesheet" href="style.css">

</head>
<body class="container mt-4">

<h2 class="mb-4">Subir Documento</h2>

<form method="POST" action="procesar_subir_documento.php" enctype="multipart/form-data" class="form-grid">
    <div class="mb-3">
        <label for="nombre" class="form-label">Nombre del Documento</label>
        <input type="text" class="form-control" name="nombre" id="nombre" required>
    </div>


    <div class="mb-3">
        <label for="tipo_documento" class="form-label">Tipo de documento</label>
        <select name="tipo_documento" id="tipo_documento" class="form-select" required>
            <optgroup label="Estudiantes">
                <option value="Certificado de Nacimiento">Certificado de Nacimiento</option>
                <option value="Ficha de Matrícula">Ficha de Matrícula</option>
                <option value="Certificado alumno prioritario">Certificado alumno prioritario</option>
                <option value="Antecedentes en caso de emergencia">Antecedentes en caso de emergencia</option>
                <option value="Autorización para evaluar y reevaluar">Autorización para evaluar y reevaluar</option>
                <option value="Autorización de la muda">Autorización de la muda</option>
                <option value="Informe Psicológico">Informe Psicológico</option>
                <option value="Protocolos de prueba aplicada">Protocolos de prueba aplicada</option>
                <option value="Prueba de conducta adaptativa ICAAP">Prueba de conducta adaptativa ICAAP</option>
                <option value="Formulario de ingreso FUDEI">Formulario de ingreso FUDEI</option>
                <option value="Formulario NEEP">Formulario NEEP</option>
                <option value="Plan de Apoyo Individual PAI">Plan de Apoyo Individual PAI</option>
                <option value="Formulario NEET">Formulario NEET</option>
                <option value="Plan de Adecuaciones Curriculares Individualizado PACI">Plan de Adecuaciones Curriculares Individualizado PACI</option>
                <option value="Informe pedagógico curricular">Informe pedagógico curricular</option>
                <option value="Informe a la Familia">Informe a la Familia</option>
                <option value="Informe Pedagógico 1er semestre">Informe Pedagógico 1er semestre</option>
                <option value="Informe Pedagógico 2do semestre">Informe Pedagógico 2do semestre</option>
                <option value="Informe Personalidad 1er semestre">Informe Personalidad 1er semestre</option>
                <option value="Informe Personalidad 2do semestre">Informe Personalidad 2do semestre</option>
                <option value="Informe Vocacional 1er semestre">Informe Vocacional 1er semestre</option>
                <option value="Informe vocacional 2do semestre">Informe vocacional 2do semestre</option>
                <option value="Informe de Notas 1er semestre">Informe de Notas 1er semestre</option>
                <option value="Informe de notas 2do semestre">Informe de notas 2do semestre</option>
                <option value="Certificado de estudios MINEDUC">Certificado de estudios MINEDUC</option>
                <option value="Valoración de salud">Valoración de salud</option>
                <option value="Informe fonoaudiológico">Informe fonoaudiológico</option>
                <option value="Informe kinesiológico">Informe kinesiológico</option>
                <option value="Informe Terapeuta Ocupacional">Informe Terapeuta Ocupacional</option>
                <option value="Derivaciones a especialistas">Derivaciones a especialistas</option>
                <option value="Informes médicos">Informes médicos</option>
                <option value="Recetas médicas">Recetas médicas</option>
                <option value="Antecedentes judiciales">Antecedentes judiciales</option>
                <option value="Pruebas diagnósticas">Pruebas diagnósticas</option>
                <option value="Hoja de vida del estudiante">Hoja de vida del estudiante</option>
                <option value="Ficha desregulación emocional y conductual DEC">Ficha desregulación emocional y conductual DEC</option>
                <option value="Otros">Otros</option>
                <option value="Declaración de matrícula">Declaración de matrícula</option>
                <option value="Screening">Screening</option>
                <option value="Test Comprensión auditiva del Lenguaje TECAL">Test Comprensión auditiva del Lenguaje TECAL</option>
                <option value="Test para evaluar procesos de simplificación fonológica TEPROSIF">Test para evaluar procesos de simplificación fonológica TEPROSIF</option>
                <option value="Test de la articulación a la repetición TAR">Test de la articulación a la repetición TAR</option>
                <option value="Habilidades pragmáticas">Habilidades pragmáticas</option>
                <option value="Órganos fonoarticulatorios">Órganos fonoarticulatorios</option>
                <option value="Formulario NEEP reevaluación (diciembre)">Formulario NEEP reevaluación (diciembre)</option>
                <option value="Informe a la Familia Marzo">Informe a la Familia Marzo</option>
                <option value="Estado de avance a la Familia Junio">Estado de avance a la Familia Junio</option>
            </optgroup>
                <optgroup label="Docentes">
                <option value="Curriculum">Curriculum</option>
                <option value="Certificado de título">Certificado de título</option>
                <option value="Certificado de registro MINEDUC">Certificado de registro MINEDUC</option>
                <option value="Certificado de antecedentes para fines especiales">Certificado de antecedentes para fines especiales</option>
                <option value="Certificado de consulta de inhabilidades para trabajar con menores de edad">Certificado de consulta de inhabilidades para trabajar con menores de edad</option>
                <option value="Certificado de consulta de inhabilidades por maltrato relevante">Certificado de consulta de inhabilidades por maltrato relevante</option>
                <option value="Ficha personal">Ficha personal</option>
                <option value="Contrato de trabajo">Contrato de trabajo</option>
                <option value="Recepción del Reglamento Interno de Higiene y Seguridad">Recepción del Reglamento Interno de Higiene y Seguridad</option>
                <option value="Anexos de contratos">Anexos de contratos</option>
                <option value="Certificado de afiliación AFP">Certificado de afiliación AFP</option>
                <option value="Certificado de afiliación al sistema de salud">Certificado de afiliación al sistema de salud</option>
                <option value="Certificados de perfeccionamientos">Certificados de perfeccionamientos</option>
                <option value="Carta aviso de cese de funciones">Carta aviso de cese de funciones</option>
                <option value="Finiquito">Finiquito</option>
                <option value="Certificado de estudios para fines laborales">Certificado de estudios para fines laborales</option>
                <option value="Licencia de Educación Media">Licencia de Educación Media</option>
                <option value="Certificado de inscripción en el Registro Nacional de Prestadores Individuales de Salud">Certificado de inscripción en el Registro Nacional de Prestadores Individuales de Salud</option>
                <option value="Hoja de vida conductr">Hoja de vida conductr</option>
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
