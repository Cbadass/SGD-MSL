<?php
try {
    require_once 'includes/db.php';
    require_once 'includes/storage.php';

    $azure = new AzureBlobStorage();
    $errorMsg = '';
    $documentos = [];

    // Paginación
    $documentosPorPagina = 20;
    $paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    if ($paginaActual < 1) $paginaActual = 1;

    // Calcular total de documentos
    $totalDocumentosQuery = "SELECT COUNT(*) FROM documentos";
    $stmtTotal = $conn->query($totalDocumentosQuery);
    if (!$stmtTotal) {
        throw new Exception("Error al contar documentos: " . implode(", ", $conn->errorInfo()));
    }
    $totalDocumentos = $stmtTotal->fetchColumn();

    $totalPaginas = ($totalDocumentos > 0) ? ceil($totalDocumentos / $documentosPorPagina) : 1;
    if ($paginaActual > $totalPaginas) $paginaActual = $totalPaginas;

    // Consulta paginada con JOINs
    $offset = ($paginaActual - 1) * $documentosPorPagina;
    $sql = "
    SELECT d.Id_documento, d.Nombre_documento, d.Tipo_documento, d.Fecha_subido, d.Fecha_modificacion,
           d.Url_documento, d.Descripcion,
           e.Nombre_estudiante + ' ' + e.Apellido_estudiante AS Nombre_estudiante,
           p.Nombre_profesional + ' ' + p.Apellido_profesional AS Nombre_profesional
    FROM documentos d
    LEFT JOIN estudiantes e ON d.Id_estudiante_doc = e.Id_estudiante
    LEFT JOIN profesionales p ON d.Id_prof_doc = p.Id_profesional
    ORDER BY d.Id_documento DESC
    OFFSET $offset ROWS FETCH NEXT $documentosPorPagina ROWS ONLY
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

<h2 class="mb-4">Lista de Documentos</h2>

<?php if (!empty($errorMsg)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
<?php elseif (empty($documentos)): ?>
    <div class="alert alert-warning">No se encontraron documentos.</div>
<?php else: ?>
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
                <td>
                    <?php
                        $nombreBlob = basename($doc['Url_documento']);
                        $urlDescarga = $azure->obtenerBlobUrlConSAS($nombreBlob, 60);
                    ?>
                    <a href="<?= htmlspecialchars($urlDescarga) ?>" class="btn btn-primary btn-sm" target="_blank">Descargar</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Paginación -->
    <nav>
        <ul class="pagination">
            <?php if ($paginaActual > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?seccion=documentos&pagina=1">Primera</a>
            </li>
            <li class="page-item">
                <a class="page-link" href="?seccion=documentos&pagina=<?= $paginaActual - 1 ?>">Anterior</a>
            </li>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
            <li class="page-item <?= ($i === $paginaActual) ? 'active' : '' ?>">
                <a class="page-link" href="?seccion=documentos&pagina=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>

            <?php if ($paginaActual < $totalPaginas): ?>
            <li class="page-item">
                <a class="page-link" href="?seccion=documentos&pagina=<?= $paginaActual + 1 ?>">Siguiente</a>
            </li>
            <li class="page-item">
                <a class="page-link" href="?seccion=documentos&pagina=<?= $totalPaginas ?>">Última</a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>
