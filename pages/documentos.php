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

    $totalDocumentosQuery = "SELECT COUNT(*) FROM documentos";
    $stmtTotal = $conn->query($totalDocumentosQuery);
    if (!$stmtTotal) {
        throw new Exception("Error al contar documentos: " . implode(", ", $conn->errorInfo()));
    }
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
        ORDER BY d.Id_documento DESC;

    ";

    $stmt = $conn->query($sql);
    if (!$stmt) {
        throw new Exception("Error en la consulta principal: " . implode(", ", $conn->errorInfo()));
    }
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

<?php if (!empty($errorMsg)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
<?php endif; ?>

<h2 class="mb-4">Lista de Documentos</h2>

<?php if (empty($documentos) && empty($errorMsg)): ?>
    <div class="alert alert-warning">No se encontraron documentos.</div>
<?php elseif (!empty($documentos)): ?>
    <table class="table table-striped">
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
                <th>Descargar</th>
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
                    <?php $nombreBlob = basename($doc['Url_documento']); ?>
                    <?php if (!empty($doc['Id_documento'])): ?>
                    <a href="descargar.php?id_documento=<?= htmlspecialchars($doc['Id_documento']) ?>" class="btn btn-primary btn-sm">Descargar</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <nav>
        <ul class="pagination">
            <?php if ($paginaActual > 1): ?>
            <li class="page-item"><a class="page-link" href="?seccion=documentos&pagina=1">Primera</a></li>
            <li class="page-item"><a class="page-link" href="?seccion=documentos&pagina=<?= $paginaActual - 1 ?>">Anterior</a></li>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
            <li class="page-item <?= ($i === $paginaActual) ? 'active' : '' ?>">
                <a class="page-link" href="?seccion=documentos&pagina=<?= $i ?>"><?= $i ?></a>
            </li>
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
