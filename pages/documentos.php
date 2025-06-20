<?php
try {
    require_once 'includes/db.php';
    require_once 'includes/storage.php';

    // Normaliza un RUT eliminando todo menos dígitos y K
    function normalizarRut($rut) {
        return preg_replace('/[^0-9kK]/', '', $rut);
    }

    $azure      = new AzureBlobStorage();
    $errorMsg   = '';
    $documentos = [];

    // Parámetros de paginación
    $porPagina = 5;
    $pagina    = max((int)($_GET['pagina'] ?? 1), 1);

    // Parámetros de filtro específicos
    $id_prof       = intval($_GET['id_prof']        ?? 0);
    $sin_estud     = isset($_GET['sin_estudiante'])   && $_GET['sin_estudiante']   == 1;
    $id_est        = intval($_GET['id_estudiante']   ?? 0);
    $sin_profes    = isset($_GET['sin_profesional'])  && $_GET['sin_profesional']  == 1;

    // Construcción inicial del WHERE dinámico
    $where  = "1=1";
    $params = [];

    // Helper para LIKE
    function agregarFiltro(&$where, &$params, $campo, $valor) {
        if ($valor !== '') {
            $where    .= " AND $campo LIKE ?";
            $params[]  = "%{$valor}%";
        }
    }

    // Filtros básicos
    agregarFiltro($where, $params, 'd.Nombre_documento',   $_GET['nombre'] ?? '');
    agregarFiltro($where, $params, 'd.Tipo_documento',     $_GET['tipo_documento'] ?? '');

    // Búsqueda libre de estudiante
    if (!empty($_GET['estudiante'])) {
        $f = $_GET['estudiante'];
        $rn = normalizarRut($f);
        $where .= " AND (
            e.Nombre_estudiante LIKE ? OR
            REPLACE(REPLACE(REPLACE(LOWER(e.Rut_estudiante),'.',''),'-',''),'k','K') LIKE ?
        )";
        $params[] = "%{$f}%";
        $params[] = "%".strtolower($rn)."%";
    }

    // Búsqueda libre de profesional
    if (!empty($_GET['profesional'])) {
        $f = $_GET['profesional'];
        $rn = normalizarRut($f);
        $where .= " AND (
            p.Nombre_profesional LIKE ? OR
            REPLACE(REPLACE(REPLACE(LOWER(p.Rut_profesional),'.',''),'-',''),'k','K') LIKE ?
        )";
        $params[] = "%{$f}%";
        $params[] = "%".strtolower($rn)."%";
    }

    // Filtros de fechas
    if (!empty($_GET['fecha_subida_desde'])) {
        $where    .= " AND d.Fecha_subido >= ?";
        $params[]  = $_GET['fecha_subida_desde'];
    }
    if (!empty($_GET['fecha_subida_hasta'])) {
        $where    .= " AND d.Fecha_subido <= ?";
        $params[]  = $_GET['fecha_subida_hasta'];
    }

    // Opciones de orden
    $ordenOpciones = [
        'subido_desc'     => 'd.Fecha_subido DESC',
        'subido_asc'      => 'd.Fecha_subido ASC',
        'modificado_desc' => 'd.Fecha_modificacion DESC',
        'modificado_asc'  => 'd.Fecha_modificacion ASC',
    ];
    $orden = $ordenOpciones[$_GET['orden'] ?? 'subido_desc'] 
           ?? $ordenOpciones['subido_desc'];

    // Filtro “desde profesional”
    if ($id_prof > 0) {
        $where    .= " AND d.Id_prof_doc = ?";
        $params[]  = $id_prof;
        if ($sin_estud) {
            $where .= " AND d.Id_estudiante_doc IS NULL";
        }
    }
    // Filtro “desde estudiante”
    if ($id_est > 0) {
        $where    .= " AND d.Id_estudiante_doc = ?";
        $params[]  = $id_est;
        if ($sin_profes) {
            $where .= " AND d.Id_prof_doc IS NULL";
        }
    }

    // Conteo total para paginación
    $stmtTotal = $conn->prepare("
        SELECT COUNT(*)
          FROM documentos d
     LEFT JOIN estudiantes   e ON d.Id_estudiante_doc = e.Id_estudiante
     LEFT JOIN profesionales p ON d.Id_prof_doc       = p.Id_profesional
        WHERE $where
    ");
    $stmtTotal->execute($params);
    $total = (int)$stmtTotal->fetchColumn();
    $totalPag = max(1, ceil($total / $porPagina));
    if ($pagina > $totalPag) $pagina = $totalPag;
    $offset = ($pagina - 1) * $porPagina;

    // Consulta principal con joins
    $sql = "
        SELECT
          d.Id_documento,
          d.Nombre_documento,
          d.Tipo_documento,
          d.Fecha_subido,
          d.Fecha_modificacion,
          d.Descripcion,
          CONCAT(e.Nombre_estudiante,' ',e.Apellido_estudiante) AS Nombre_estudiante,
          CONCAT(p.Nombre_profesional,' ',p.Apellido_profesional)  AS Nombre_profesional,
          u.Nombre_usuario AS Usuario_subio
        FROM documentos d
   LEFT JOIN usuarios      u ON d.Id_usuario_subido   = u.Id_usuario
   LEFT JOIN estudiantes   e ON d.Id_estudiante_doc   = e.Id_estudiante
   LEFT JOIN profesionales p ON d.Id_prof_doc         = p.Id_profesional
        WHERE $where
        ORDER BY $orden
        OFFSET $offset ROWS FETCH NEXT $porPagina ROWS ONLY
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $errorMsg = $e->getMessage();
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

<h2 class="mb-4">
  <?php
    if ($id_prof > 0) {
      echo "Documentos del Profesional #{$id_prof}" 
         . ($sin_estud ? " (sin estudiante)" : "");
    } elseif ($id_est > 0) {
      echo "Documentos del Estudiante #{$id_est}" 
         . ($sin_profes ? " (sin profesional)" : "");
    } else {
      echo "Lista de Documentos";
    }
    echo " ({$total} encontrados)";
  ?>
</h2>

<!-- Filtro de búsqueda -->
<div class="card p-4 mb-4">
  <form method="GET" class="form-grid">
    <input type="hidden" name="seccion" value="documentos">
    <input type="hidden" name="id_prof" value="<?= htmlspecialchars($id_prof) ?>">
    <input type="hidden" name="sin_estudiante" value="<?= $sin_estud ? 1 : 0 ?>">
    <input type="hidden" name="id_estudiante" value="<?= htmlspecialchars($id_est) ?>">
    <input type="hidden" name="sin_profesional" value="<?= $sin_profes ? 1 : 0 ?>">

    <div>
      <label>Nombre documento</label>
      <input type="text" name="nombre" value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>" class="form-control">
    </div>
    <div>
      <label>Tipo de documento</label>
      <select name="tipo_documento" class="form-select">
        <option value="">Todos</option>
        <?php
        $tipos = [
          "Certificado de Nacimiento", "Ficha de Matrícula", "Informe Psicológico",
          "Informe Pedagógico 1er semestre", "Recetas médicas", "Curriculum",
          "Contrato de trabajo", "Certificados de perfeccionamientos"
        ];
        foreach ($tipos as $t) {
          $sel = ($_GET['tipo_documento'] ?? '') === $t ? 'selected' : '';
          echo "<option value=\"".htmlspecialchars($t)."\" $sel>".htmlspecialchars($t)."</option>";
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
        <?php foreach ($ordenOpciones as $key => $o): ?>
          <option value="<?= $key ?>" <?= ($_GET['orden'] ?? '') === $key ? 'selected' : '' ?>>
            <?= ucwords(str_replace(['_','desc','asc'], [' ',' más reciente primero',' más antiguo primero'], $key)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="display:flex; gap:10px; align-items:end;">
      <button type="submit" class="btn btn-primary">Buscar</button>
      <a href="?seccion=documentos" class="btn btn-secondary">Limpiar filtros</a>
    </div>
  </form>
</div>

<?php if (!empty($errorMsg)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
<?php elseif (empty($documentos)): ?>
  <div class="alert alert-warning">No se encontraron documentos.</div>
<?php else: ?>

  <div class="table-responsive">
    <table class="table table-striped">
      <thead>
        <tr>
          <th>Nombre</th><th>Tipo</th><th>Subido</th><th>Modificado</th>
          <th>Descripción</th><th>Estudiante</th><th>Profesional</th>
          <th>Usuario</th><th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($documentos as $d): ?>
        <tr>
          <td><?= htmlspecialchars($d['Nombre_documento']) ?></td>
          <td><?= htmlspecialchars($d['Tipo_documento']) ?></td>
          <td><?= htmlspecialchars($d['Fecha_subido']) ?></td>
          <td><?= htmlspecialchars($d['Fecha_modificacion'] ?? '-') ?></td>
          <td><?= htmlspecialchars($d['Descripcion']) ?></td>
          <td><?= htmlspecialchars($d['Nombre_estudiante']  ?: '-') ?></td>
          <td><?= htmlspecialchars($d['Nombre_profesional'] ?: '-') ?></td>
          <td><?= htmlspecialchars($d['Usuario_subio']) ?></td>
          <td>
            <a href="index.php?seccion=modificar_documento&id_documento=<?= $d['Id_documento'] ?>"
               class="btn btn-warning btn-sm">Modificar</a>
            <a href="descargar.php?id_documento=<?= $d['Id_documento'] ?>"
               class="btn btn-primary btn-sm">Descargar</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Paginación -->
  <nav>
    <ul class="pagination justify-content-center">
      <?php if ($pagina > 1): ?>
      <li class="page-item">
        <a class="page-link"
           href="index.php?seccion=documentos&pagina=1
                <?= $id_prof ? "&id_prof={$id_prof}&sin_estudiante=".($sin_estud?1:0) : '' ?>
                <?= $id_est  ? "&id_estudiante={$id_est}&sin_profesional=".($sin_profes?1:0) : '' ?>">
          « Primera
        </a>
      </li>
      <li class="page-item">
        <a class="page-link"
           href="index.php?seccion=documentos&pagina=<?= $pagina-1 ?>
                <?= $id_prof ? "&id_prof={$id_prof}&sin_estudiante=".($sin_estud?1:0) : '' ?>
                <?= $id_est  ? "&id_estudiante={$id_est}&sin_profesional=".($sin_profes?1:0) : '' ?>">
          ‹ Anterior
        </a>
      </li>
      <?php endif; ?>

      <?php for ($i = 1; $i <= $totalPag; $i++): ?>
      <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
        <a class="page-link"
           href="index.php?seccion=documentos&pagina=<?= $i ?>
                <?= $id_prof ? "&id_prof={$id_prof}&sin_estudiante=".($sin_estud?1:0) : '' ?>
                <?= $id_est  ? "&id_estudiante={$id_est}&sin_profesional=".($sin_profes?1:0) : '' ?>">
          <?= $i ?>
        </a>
      </li>
      <?php endfor; ?>

      <?php if ($pagina < $totalPag): ?>
      <li class="page-item">
        <a class="page-link"
           href="index.php?seccion=documentos&pagina=<?= $pagina+1 ?>
                <?= $id_prof ? "&id_prof={$id_prof}&sin_estudiante=".($sin_estud?1:0) : '' ?>
                <?= $id_est  ? "&id_estudiante={$id_est}&sin_profesional=".($sin_profes?1:0) : '' ?>">
          Siguiente ›
        </a>
      </li>
      <li class="page-item">
        <a class="page-link"
           href="index.php?seccion=documentos&pagina=<?= $totalPag ?>
                <?= $id_prof ? "&id_prof={$id_prof}&sin_estudiante=".($sin_estud?1:0) : '' ?>
                <?= $id_est  ? "&id_estudiante={$id_est}&sin_profesional=".($sin_profes?1:0) : '' ?>">
          Última »
        </a>
      </li>
      <?php endif; ?>
    </ul>
  </nav>
<?php endif; ?>

</body>
</html>
