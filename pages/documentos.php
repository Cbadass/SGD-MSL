<?php
require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/../includes/storage.php';

// Inicializa la clase de Azure Blob Storage
$azure = new AzureBlobStorage();

// Paginación
$documentosPorPagina = 20;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($paginaActual < 1) $paginaActual = 1;

// Calcular total de documentos
$totalDocumentosQuery = "SELECT COUNT(*) FROM documentos";
$totalDocumentos = $conn->query($totalDocumentosQuery)->fetchColumn();

$totalPaginas = ceil($totalDocumentos / $documentosPorPagina);
if ($paginaActual > $totalPaginas) $paginaActual = $totalPaginas;

// Consulta paginada con JOINs a estudiantes y profesionales
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
$documentos = $stmt->fetchAll();
?>

<h2 class="mb-4">Lista de Documentos</h2>

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
            <td><?= htmlspecialchars($doc['Fecha_modificacion']) ?></td>
            <td><?= htmlspecialchars($doc['Descripcion']) ?></td>
            <td><?= htmlspecialchars($doc['Nombre_estudiante'] ?? '-') ?></td>
            <td><?= htmlspecialchars($doc['Nombre_profesional'] ?? '-') ?></td>
            <td><a href="<?= htmlspecialchars($doc['Url_documento']) ?>" class="btn btn-primary btn-sm" target="_blank">Descargar</a></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- Paginación -->
<nav>
    <ul class="pagination">
        <?php if ($paginaActual > 1): ?>
        <li class="page-item">
            <a class="page-link" href="?pagina=1">Primera</a>
        </li>
        <li class="page-item">
            <a class="page-link" href="?pagina=<?= $paginaActual - 1 ?>">Anterior</a>
        </li>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
        <li class="page-item <?= $i === $paginaActual ? 'active' : '' ?>">
            <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>

        <?php if ($paginaActual < $totalPaginas): ?>
        <li class="page-item">
            <a class="page-link" href="?pagina=<?= $paginaActual + 1 ?>">Siguiente</a>
        </li>
        <li class="page-item">
            <a class="page-link" href="?pagina=<?= $totalPaginas ?>">Última</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>

</body>
</html>
