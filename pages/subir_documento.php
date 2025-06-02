<?php
require_once 'includes/db.php';
require_once 'includes/storage.php';

$errorMsg = '';
$successMsg = '';

// Inicializar ID seleccionado
$idEstudianteSeleccionado = '';
$idProfesionalSeleccionado = '';

// Procesar formulario de subida
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre_documento'])) {
    $nombreDocumento = trim($_POST['nombre_documento']);
    $tipoDocumento = trim($_POST['tipo_documento']);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $idEstudiante = $_POST['id_estudiante'] ?: null;
    $idProfesional = $_POST['id_profesional'] ?: null;

    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = "Por favor, selecciona un archivo válido.";
    } elseif (empty($nombreDocumento) || empty($tipoDocumento)) {
        $errorMsg = "Completa todos los campos obligatorios.";
    } else {
        try {
            $archivoTmp = $_FILES['archivo']['tmp_name'];
            $archivoNombre = basename($_FILES['archivo']['name']);
            $azure = new AzureBlobStorage();
            $exito = $azure->subirBlob($archivoNombre, fopen($archivoTmp, 'r'));

            if ($exito) {
                $url = $azure->obtenerBlobUrl($archivoNombre);
                $sql = "INSERT INTO documentos (Nombre_documento, Tipo_documento, Fecha_subido, Url_documento, Descripcion, Id_estudiante_doc, Id_prof_doc)
                        VALUES (?, ?, GETDATE(), ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $nombreDocumento,
                    $tipoDocumento,
                    $url,
                    $descripcion,
                    $idEstudiante,
                    $idProfesional
                ]);

                $successMsg = "Documento subido exitosamente.";
            } else {
                $errorMsg = "Error al subir el archivo a Azure.";
            }
        } catch (PDOException $e) {
            $errorMsg = "Error en la base de datos: " . $e->getMessage();
        } catch (Exception $e) {
            $errorMsg = "Error: " . $e->getMessage();
        }
    }
}

// Búsqueda de estudiantes
$estudiantesEncontrados = [];
if (!empty($_GET['busqueda_estudiante'])) {
    $busqueda = trim($_GET['busqueda_estudiante']);
    $sql = "
        SELECT e.Id_estudiante, e.Nombre_estudiante, e.Apellido_estudiante, e.Rut_estudiante,
               es.Nombre_escuela, c.Tipo_curso, c.Grado_curso
        FROM estudiantes e
        LEFT JOIN escuelas es ON e.Id_escuela = es.Id_escuela
        LEFT JOIN cursos c ON e.Id_curso = c.Id_curso
        WHERE e.Nombre_estudiante LIKE ? OR e.Apellido_estudiante LIKE ?
              OR e.Rut_estudiante LIKE ? OR es.Nombre_escuela LIKE ?
              OR (c.Tipo_curso + ' ' + c.Grado_curso) LIKE ?";
    $stmt = $conn->prepare($sql);
    $like = "%$busqueda%";
    $stmt->execute([$like, $like, $like, $like, $like]);
    $estudiantesEncontrados = $stmt->fetchAll();
}

// Búsqueda de profesionales
$profesionalesEncontrados = [];
if (!empty($_GET['busqueda_profesional'])) {
    $busqueda = trim($_GET['busqueda_profesional']);
    $sql = "
        SELECT p.Id_profesional, p.Nombre_profesional, p.Apellido_profesional, p.Rut_profesional,
               p.Cargo_profesional, es.Nombre_escuela
        FROM profesionales p
        LEFT JOIN escuelas es ON p.Id_escuela_prof = es.Id_escuela
        WHERE p.Nombre_profesional LIKE ? OR p.Apellido_profesional LIKE ?
              OR p.Rut_profesional LIKE ? OR p.Cargo_profesional LIKE ?
              OR es.Nombre_escuela LIKE ?";
    $stmt = $conn->prepare($sql);
    $like = "%$busqueda%";
    $stmt->execute([$like, $like, $like, $like, $like]);
    $profesionalesEncontrados = $stmt->fetchAll();
}
?>

<h2 class="mb-4">Subir Nuevo Documento</h2>

<?php if (!empty($errorMsg)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
<?php elseif (!empty($successMsg)): ?>
<div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>

<div class="row">
  <!-- Formulario principal -->
  <div class="col-md-7">
    <form method="post" enctype="multipart/form-data" class="bg-light p-4 rounded shadow-sm">
      <div class="mb-3">
        <label for="nombre_documento" class="form-label">Nombre del Documento <span class="text-danger">*</span></label>
        <input type="text" name="nombre_documento" id="nombre_documento" class="form-control" required>
      </div>

      <div class="mb-3">
        <label for="tipo_documento" class="form-label">Tipo de Documento <span class="text-danger">*</span></label>
        <input type="text" name="tipo_documento" id="tipo_documento" class="form-control" required>
      </div>

      <div class="mb-3">
        <label for="descripcion" class="form-label">Descripción (opcional)</label>
        <textarea name="descripcion" id="descripcion" class="form-control" rows="3"></textarea>
      </div>

      <!-- Inputs ocultos que se llenan con los buscadores -->
      <input type="hidden" name="id_estudiante" id="id_estudiante" value="">
      <input type="hidden" name="id_profesional" id="id_profesional" value="">

      <div class="mb-3">
        <label for="archivo" class="form-label">Archivo <span class="text-danger">*</span></label>
        <input type="file" name="archivo" id="archivo" class="form-control" required>
      </div>

      <button type="submit" class="btn btn-success">Subir Documento</button>
    </form>
  </div>

  <!-- Buscadores -->
  <div class="col-md-5">
    <h5>🔍 Buscar Estudiante</h5>
    <form method="get" action="" class="mb-3">
        <input type="hidden" name="seccion" value="subir_documento">
        <div class="input-group mb-2">
            <input type="text" name="busqueda_estudiante" class="form-control" placeholder="Nombre/RUT/Escuela/Curso">
            <button class="btn btn-outline-primary" type="submit">Buscar</button>
        </div>
    </form>

    <?php if ($estudiantesEncontrados): ?>
      <ul class="list-group mb-4">
        <?php foreach ($estudiantesEncontrados as $e): ?>
          <li class="list-group-item d-flex justify-content-between align-items-start">
            <div>
              <strong><?= htmlspecialchars("{$e['Nombre_estudiante']} {$e['Apellido_estudiante']}") ?></strong><br>
              RUT: <?= htmlspecialchars($e['Rut_estudiante']) ?><br>
              Escuela: <?= htmlspecialchars($e['Nombre_escuela'] ?? '-') ?><br>
              Curso: <?= htmlspecialchars("{$e['Tipo_curso']} {$e['Grado_curso']}") ?>
            </div>
            <button class="btn btn-sm btn-success ms-2" onclick="seleccionarEstudiante(<?= $e['Id_estudiante'] ?>, '<?= htmlspecialchars("{$e['Nombre_estudiante']} {$e['Apellido_estudiante']}") ?>')">Seleccionar</button>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <h5>🔍 Buscar Profesional</h5>
    <form method="get" action="">
        <input type="hidden" name="seccion" value="subir_documento">
        <div class="input-group mb-2">
            <input type="text" name="busqueda_profesional" class="form-control" placeholder="Nombre/RUT/Cargo/Escuela">
            <button class="btn btn-outline-primary" type="submit">Buscar</button>
        </div>
    </form>

    <?php if ($profesionalesEncontrados): ?>
      <ul class="list-group">
        <?php foreach ($profesionalesEncontrados as $p): ?>
          <li class="list-group-item d-flex justify-content-between align-items-start">
            <div>
              <strong><?= htmlspecialchars("{$p['Nombre_profesional']} {$p['Apellido_profesional']}") ?></strong><br>
              RUT: <?= htmlspecialchars($p['Rut_profesional']) ?><br>
              Cargo: <?= htmlspecialchars($p['Cargo_profesional']) ?><br>
              Escuela: <?= htmlspecialchars($p['Nombre_escuela'] ?? '-') ?>
            </div>
            <button class="btn btn-sm btn-success ms-2" onclick="seleccionarProfesional(<?= $p['Id_profesional'] ?>, '<?= htmlspecialchars("{$p['Nombre_profesional']} {$p['Apellido_profesional']}") ?>')">Seleccionar</button>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<script>
function seleccionarEstudiante(id, nombre) {
  document.getElementById('id_estudiante').value = id;
  alert('Estudiante seleccionado: ' + nombre);
}
function seleccionarProfesional(id, nombre) {
  document.getElementById('id_profesional').value = id;
  alert('Profesional seleccionado: ' + nombre);
}
</script>
