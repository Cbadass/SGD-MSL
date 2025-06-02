<?php
try {
    require_once 'includes/db.php';
    require_once 'includes/storage.php';

    $azure = new AzureBlobStorage();
    $errorMsg = '';
    $documentos = [];

    $documentosPorPagina = 20;
    $paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    if ($paginaActual < 1) $paginaActual = 1;

    // Filtros de búsqueda avanzada
    $where = "1=1";
    $params = [];

    if (!empty($_GET['nombre'])) {
        $where .= " AND d.Nombre_documento LIKE ?";
        $params[] = "%" . $_GET['nombre'] . "%";
    }

    if (!empty($_GET['tipo'])) {
        $where .= " AND d.Tipo_documento LIKE ?";
        $params[] = "%" . $_GET['tipo'] . "%";
    }

    if (!empty($_GET['estudiante'])) {
        $where .= " AND (e.Nombre_estudiante LIKE ? OR e.Rut_estudiante LIKE ?)";
        $params[] = "%" . $_GET['estudiante'] . "%";
        $params[] = "%" . $_GET['estudiante'] . "%";
    }

    if (!empty($_GET['profesional'])) {
        $where .= " AND (p.Nombre_profesional LIKE ? OR p.Rut_profesional LIKE ?)";
        $params[] = "%" . $_GET['profesional'] . "%";
        $params[] = "%" . $_GET['profesional'] . "%";
    }

    if (!empty($_GET['fecha_subida_desde'])) {
        $where .= " AND d.Fecha_subido >= ?";
        $params[] = $_GET['fecha_subida_desde'];
    }
    if (!empty($_GET['fecha_subida_hasta'])) {
        $where .= " AND d.Fecha_subido <= ?";
        $params[] = $_GET['fecha_subida_hasta'];
    }

    // Ordenar por fecha
    $orden = "d.Fecha_subido DESC";
    if ($_GET['orden'] ?? '' === 'modificado') {
        $orden = "d.Fecha_modificacion DESC";
    }

    // Contar total de documentos
    $stmtTotal = $conn->prepare("SELECT COUNT(*) FROM documentos d
        LEFT JOIN estudiantes e ON d.Id_estudiante_doc = e.Id_estudiante
        LEFT JOIN profesionales p ON d.Id_prof_doc = p.Id_profesional
        WHERE $where");
    $stmtTotal->execute($params);
    $totalDocumentos = $stmtTotal->fetchColumn();
    $totalPaginas = ($totalDocumentos > 0) ? ceil($totalDocumentos / $documentosPorPagina) : 1;
    if ($paginaActual > $totalPaginas) $paginaActual = $totalPaginas;

    $offset = ($paginaActual - 1) * $documentosPorPagina;

    $sql = "
        SELECT d.Id_documento,
            d.Nombre_documento,
            d.Tipo_documento,
            d.Fecha_subido,
            d.Fecha_modificacion,
            d.Url_documento,
            d.Descripcion,
            d.Id_estudiante_doc,
            d.Id_prof_doc,
            d.Id_usuario_subido,
            u.Nombre_usuario AS Usuario_que_subio,
            e.Nombre_estudiante + ' ' + e.Apellido_estudiante AS Nombre_estudiante,
            p.Nombre_profesional + ' ' + p.Apellido_profesional AS Nombre_profesional
        FROM documentos d
        LEFT JOIN usuarios u ON d.Id_usuario_subido = u.Id_usuario
        LEFT JOIN estudiantes e ON d.Id_estudiante_doc = e.Id_estudiante
        LEFT JOIN profesionales p ON d.Id_prof_doc = p.Id_profesional
        WHERE $where
        ORDER BY $orden
        OFFSET $offset ROWS FETCH NEXT $documentosPorPagina ROWS ONLY
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $documentos = $stmt->fetchAll();

} catch (PDOException $e) {
    $errorMsg = "Error de la base de datos: " . $e->getMessage();
} catch (Exception $e) {
    $errorMsg = "Error general: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Lista de Documentos</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="container mt-4">

<h2 class="mb-4">Lista de Documentos</h2>

<!-- Buscador avanzado -->
<div class="card p-4 mb-4">
  <form method="GET" class="form-grid">
    <input type="hidden" name="seccion" value="documentos">

    <div style="flex: 1 1 240px;">
      <label for="nombre" class="form-group">Nombre documento</label>
      <input type="text" name="nombre" id="nombre" class="form-control" placeholder="Ej: Informe..." value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>">
    </div>


    <div class="mb-3">
      <label for="tipo_documento" class="form-group">Tipo de documento</label>
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


    <div style="flex: 1 1 240px;">
      <label for="estudiante" class="form-group">Nombre/RUT Estudiante</label>
      <input type="text" name="estudiante" id="estudiante" class="form-control" placeholder="Ej: Juan Perez..." value="<?= htmlspecialchars($_GET['estudiante'] ?? '') ?>">
    </div>

    <div style="flex: 1 1 240px;">
      <label for="profesional" class="form-group">Nombre/RUT Profesional</label>
      <input type="text" name="profesional" id="profesional" class="form-control" placeholder="Ej: Maria Lopez..." value="<?= htmlspecialchars($_GET['profesional'] ?? '') ?>">
    </div>

    <div style="flex: 1 1 240px;">
      <label for="fecha_subida_desde" class="form-group">Fecha subida (desde)</label>
      <input type="date" name="fecha_subida_desde" id="fecha_subida_desde" class="form-control" value="<?= htmlspecialchars($_GET['fecha_subida_desde'] ?? '') ?>">
    </div>

    <div style="flex: 1 1 240px;">
      <label for="fecha_subida_hasta" class="form-group">Fecha subida (hasta)</label>
      <input type="date" name="fecha_subida_hasta" id="fecha_subida_hasta" class="form-control" value="<?= htmlspecialchars($_GET['fecha_subida_hasta'] ?? '') ?>">
    </div>

    <div style="flex: 1 1 240px;">
      <label for="orden" class="form-group">Ordenar por</label>
      <select name="orden" id="orden" class="form-select">
        <option value="subido" <?= ($_GET['orden'] ?? '') === 'subido' ? 'selected' : '' ?>>Fecha de subida</option>
        <option value="modificado" <?= ($_GET['orden'] ?? '') === 'modificado' ? 'selected' : '' ?>>Fecha de modificación</option>
      </select>
    </div>

    <div style="flex: 1 1 240px;">
      <label for="orden" class="form-group"></label>
      <button type="submit" class="btn btn-primary form-group">Buscar</button>
    </div>
  </form>
</div>

<?php if (!empty($errorMsg)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
<?php elseif (empty($documentos)): ?>
  <div class="alert alert-warning">No se encontraron documentos.</div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Tipo</th>
          <th>Subido</th>
          <th>Modificado</th>
          <th>Descripción</th>
          <th>Estudiante</th>
          <th>Profesional</th>
          <th>Usuario último editor</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($documentos as $doc): ?>
        <tr>
          <td><?= htmlspecialchars($doc['Id_documento']) ?></td>
          <td><?= htmlspecialchars($doc['Nombre_documento']) ?></td>
          <td><?= htmlspecialchars($doc['Tipo_documento']) ?></td>
          <td><?= htmlspecialchars($doc['Fecha_subido']) ?></td>
          <td><?= htmlspecialchars($doc['Fecha_modificacion'] ?? '-') ?></td>
          <td><?= htmlspecialchars($doc['Descripcion']) ?></td>
          <td><?= htmlspecialchars($doc['Nombre_estudiante'] ?? '-') ?></td>
          <td><?= htmlspecialchars($doc['Nombre_profesional'] ?? '-') ?></td>
          <td><?= htmlspecialchars($doc['Usuario_que_subio'] ?? 'Desconocido') ?></td>
          <td>
            <a href="?seccion=modificar_documento&id_documento=<?= htmlspecialchars($doc['Id_documento']) ?>" class="btn btn-warning btn-sm">Modificar</a>
            <a href="descargar.php?id_documento=<?= htmlspecialchars($doc['Id_documento']) ?>" class="btn btn-primary btn-sm">Descargar</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Paginación -->
  <nav>
    <ul class="pagination justify-content-center">
      <?php if ($paginaActual > 1): ?>
      <li class="page-item"><a class="page-link" href="?seccion=documentos&pagina=1">Primera</a></li>
      <li class="page-item"><a class="page-link" href="?seccion=documentos&pagina=<?= $paginaActual - 1 ?>">Anterior</a></li>
      <?php endif; ?>
      <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
      <li class="page-item <?= ($i === $paginaActual) ? 'active' : '' ?>"><a class="page-link" href="?seccion=documentos&pagina=<?= $i ?>"><?= $i ?></a></li>
      <?php endfor; ?>
      <?php if ($paginaActual < $totalPaginas): ?>
      <li class="page-item"><a class="page-link" href="?seccion=documentos&pagina=<?= $paginaActual + 1 ?>">Siguiente</a></li>
      <li class="page-item"><a class="page-link" href="?seccion=documentos&pagina=<?= $totalPaginas ?>">Última</a></li>
      <?php endif; ?>
    </ul>
  </nav>
<?php endif; ?>

</body>
</html>
