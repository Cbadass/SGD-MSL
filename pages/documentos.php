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
    $orden = "d.Fecha_subido DESC"; // predeterminado
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
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">

<h2 class="mb-4">Lista de Documentos</h2>

<!-- Buscador avanzado -->
<div class="card p-4 mb-4">
  <form method="GET" class="row g-3 align-items-end">
    <input type="hidden" name="seccion" value="documentos">

    <div class="col-md-3">
      <label for="nombre" class="form-label">Nombre documento</label>
      <input type="text" name="nombre" id="nombre" class="form-control" placeholder="Ej: Informe..." value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>">
    </div>

    <div class="col-md-3">
      <label for="tipo" class="form-label">Tipo documento</label>
      <input type="text" name="tipo" id="tipo" class="form-control" placeholder="Ej: Certificado..." value="<?= htmlspecialchars($_GET['tipo'] ?? '') ?>">
    </div>

    <div class="col-md-3">
      <label for="estudiante" class="form-label">Nombre/RUT Estudiante</label>
      <input type="text" name="estudiante" id="estudiante" class="form-control" placeholder="Ej: Juan Perez..." value="<?= htmlspecialchars($_GET['estudiante'] ?? '') ?>">
    </div>

    <div class="col-md-3">
      <label for="profesional" class="form-label">Nombre/RUT Profesional</label>
      <input type="text" name="profesional" id="profesional" class="form-control" placeholder="Ej: Maria Lopez..." value="<?= htmlspecialchars($_GET['profesional'] ?? '') ?>">
    </div>

    <div class="col-md-3">
      <label for="fecha_subida_desde" class="form-label">Fecha subida (desde)</label>
      <input type="date" name="fecha_subida_desde" id="fecha_subida_desde" class="form-control" value="<?= htmlspecialchars($_GET['fecha_subida_desde'] ?? '') ?>">
    </div>

    <div class="col-md-3">
      <label for="fecha_subida_hasta" class="form-label">Fecha subida (hasta)</label>
      <input type="date" name="fecha_subida_hasta" id="fecha_subida_hasta" class="form-control" value="<?= htmlspecialchars($_GET['fecha_subida_hasta'] ?? '') ?>">
    </div>

    <div class="col-md-3">
      <label for="orden" class="form-label">Ordenar por</label>
      <select name="orden" id="orden" class="form-select">
        <option value="subido" <?= ($_GET['orden'] ?? '') === 'subido' ? 'selected' : '' ?>>Fecha de subida</option>
        <option value="modificado" <?= ($_GET['orden'] ?? '') === 'modificado' ? 'selected' : '' ?>>Fecha de modificación</option>
      </select>
    </div>

    <div class="col-md-3">
      <button type="submit" class="btn btn-primary w-100">Buscar</button>
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
