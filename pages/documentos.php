<?php
try {
    require_once 'includes/db.php';
    require_once 'includes/storage.php';

    $azure = new AzureBlobStorage();
    $errorMsg = '';
    $documentos = [];

    $documentosPorPagina = 20;
    $paginaActual = max((int)($_GET['pagina'] ?? 1), 1);

    $where = "1=1";
    $params = [];

    function agregarFiltro(&$where, &$params, $campo, $valor) {
        if (!empty($valor)) {
            $where .= " AND $campo LIKE ?";
            $params[] = "%$valor%";
        }
    }

    agregarFiltro($where, $params, 'd.Nombre_documento', $_GET['nombre'] ?? '');
    agregarFiltro($where, $params, 'd.Tipo_documento', $_GET['tipo_documento'] ?? '');

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

    $ordenOpciones = [
        'subido' => 'd.Fecha_subido DESC',
        'modificado' => 'd.Fecha_modificacion DESC'
    ];
    $orden = $ordenOpciones[$_GET['orden'] ?? 'subido'] ?? $ordenOpciones['subido'];

    $stmtTotal = $conn->prepare("
        SELECT COUNT(*) FROM documentos d
        LEFT JOIN estudiantes e ON d.Id_estudiante_doc = e.Id_estudiante
        LEFT JOIN profesionales p ON d.Id_prof_doc = p.Id_profesional
        WHERE $where
    ");
    $stmtTotal->execute($params);
    $totalDocumentos = (int)$stmtTotal->fetchColumn();
    $totalPaginas = max(1, ceil($totalDocumentos / $documentosPorPagina));
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
               CONCAT(e.Nombre_estudiante, ' ', e.Apellido_estudiante) AS Nombre_estudiante,
               CONCAT(p.Nombre_profesional, ' ', p.Apellido_profesional) AS Nombre_profesional
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
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

<h2 class="mb-4">Lista de Documentos <?= $totalDocumentos ? "($totalDocumentos encontrados)" : '' ?></h2>

<!-- Filtro de búsqueda -->
<div class="card p-4 mb-4">
  <form method="GET" class="form-grid">
    <input type="hidden" name="seccion" value="documentos">

    <div><label>Nombre documento</label>
      <input type="text" name="nombre" value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>" class="form-control">
    </div>

    <div>
      <label>Tipo de documento</label>
      <select name="tipo_documento" class="form-select">
        <option value="">Todos</option>
        <?php
        $tipos = [
          "Certificado de Nacimiento", "Ficha de Matrícula", "Informe Psicológico", "Curriculum",
          "Certificado de título", "Ficha personal", "Contrato de trabajo", "Certificados de perfeccionamientos"
        ];
        foreach ($tipos as $tipo) {
          $selected = ($_GET['tipo_documento'] ?? '') === $tipo ? 'selected' : '';
          echo "<option value=\"$tipo\" $selected>$tipo</option>";
        }
        ?>
      </select>
    </div>

    <div>
      <label>Nombre/RUT Estudiante</label>
      <input type="text" name="estudiante" value="<?= htmlspecialchars($_GET['estudiante'] ?? '') ?>" class="form-control">
    </div>

    <div>
      <label>Nombre/RUT Profesional</label>
      <input type="text" name="profesional" value="<?= htmlspecialchars($_GET['profesional'] ?? '') ?>" class="form-control">
    </div>

    <div>
      <label>Fecha subida (desde)</label>
      <input type="date" name="fecha_subida_desde" value="<?= htmlspecialchars($_GET['fecha_subida_desde'] ?? '') ?>" class="form-control">
    </div>

    <div>
      <label>Fecha subida (hasta)</label>
      <input type="date" name="fecha_subida_hasta" value="<?= htmlspecialchars($_GET['fecha_subida_hasta'] ?? '') ?>" class="form-control">
    </div>

    <div>
      <label>Ordenar por</label>
      <select name="orden" class="form-select">
        <option value="subido" <?= ($_GET['orden'] ?? '') === 'subido' ? 'selected' : '' ?>>Fecha de subida</option>
        <option value="modificado" <?= ($_GET['orden'] ?? '') === 'modificado' ? 'selected' : '' ?>>Fecha de modificación</option>
      </select>
    </div>

    <div>
      <label style="display:block;">&nbsp;</label>
      <button type="submit" class="btn btn-primary">Buscar</button>
    </div>
  </form>
</div>

<!-- Mensajes -->
<?php if (!empty($errorMsg)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
<?php elseif (empty($documentos)): ?>
  <div class="alert alert-warning">No se encontraron documentos.</div>
<?php else: ?>

<!-- Tabla de resultados -->
  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th>Nombre Documento</th>
          <th>Tipo</th>
          <th>Subido</th>
          <th>Modificado</th>
          <th>Descripción</th>
          <th>Estudiante</th>
          <th>Profesional</th>
          <th>Usuario</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($documentos as $doc): ?>
        <tr>
          <td><?= htmlspecialchars($doc['Nombre_documento']) ?></td>
          <td><?= htmlspecialchars($doc['Tipo_documento']) ?></td>
          <td><?= htmlspecialchars($doc['Fecha_subido']) ?></td>
          <td><?= htmlspecialchars($doc['Fecha_modificacion'] ?? '-') ?></td>
          <td><?= htmlspecialchars($doc['Descripcion']) ?></td>
          <td><?= htmlspecialchars($doc['Nombre_estudiante'] ?? '-') ?></td>
          <td><?= htmlspecialchars($doc['Nombre_profesional'] ?? '-') ?></td>
          <td><?= htmlspecialchars($doc['Usuario_que_subio'] ?? 'Desconocido') ?></td>
          <td>
            <a href="?seccion=modificar_documento&id_documento=<?= $doc['Id_documento'] ?>" class="btn btn-warning btn-sm">Modificar</a>
            <a href="descargar.php?id_documento=<?= $doc['Id_documento'] ?>" class="btn btn-primary btn-sm">Descargar</a>
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
      <?php for ($i = max(1, $paginaActual - 2); $i <= min($totalPaginas, $paginaActual + 2); $i++): ?>
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
