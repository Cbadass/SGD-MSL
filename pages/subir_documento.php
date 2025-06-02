<?php


require_once 'includes/db.php';
require_once 'includes/storage.php';

$errorMsg = '';
$successMsg = '';

// Procesar el formulario de subida si se ha enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar los campos requeridos
    $nombreDocumento = trim($_POST['nombre_documento'] ?? '');
    $tipoDocumento = trim($_POST['tipo_documento'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $idEstudiante = $_POST['id_estudiante'] ?? null;
    $idProfesional = $_POST['id_profesional'] ?? null;

    // Validar que haya archivo
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = "Por favor, selecciona un archivo válido.";
    } elseif (empty($nombreDocumento) || empty($tipoDocumento)) {
        $errorMsg = "Por favor, completa todos los campos obligatorios.";
    } else {
        try {
            $archivoTmp = $_FILES['archivo']['tmp_name'];
            $archivoNombre = basename($_FILES['archivo']['name']);

            // Inicializar Azure
            $azure = new AzureBlobStorage();
            $exito = $azure->subirBlob($archivoNombre, fopen($archivoTmp, 'r'));

            if ($exito) {
                // Guardar en la base de datos
                $sql = "INSERT INTO documentos (Nombre_documento, Tipo_documento, Fecha_subido, Url_documento, Descripcion, Id_estudiante_doc, Id_prof_doc)
                        VALUES (?, ?, GETDATE(), ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $url = $azure->obtenerBlobUrl($archivoNombre);
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
            $errorMsg = "Error al guardar en la base de datos: " . $e->getMessage();
        } catch (Exception $e) {
            $errorMsg = "Error general: " . $e->getMessage();
        }
    }
}
?>

<h2>Subir nuevo documento</h2>

<?php if (!empty($errorMsg)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
<?php elseif (!empty($successMsg)): ?>
  <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <div class="mb-3">
    <label for="nombre_documento" class="form-label">Nombre del documento</label>
    <input type="text" name="nombre_documento" id="nombre_documento" class="form-control" required>
  </div>

  <div class="mb-3">
    <label for="tipo_documento" class="form-label">Tipo de documento</label>
    <input type="text" name="tipo_documento" id="tipo_documento" class="form-control" required>
  </div>

  <div class="mb-3">
    <label for="descripcion" class="form-label">Descripción</label>
    <textarea name="descripcion" id="descripcion" class="form-control"></textarea>
  </div>

  <div class="mb-3">
    <label for="id_estudiante" class="form-label">ID Estudiante (opcional)</label>
    <input type="number" name="id_estudiante" id="id_estudiante" class="form-control">
  </div>

  <div class="mb-3">
    <label for="id_profesional" class="form-label">ID Profesional (opcional)</label>
    <input type="number" name="id_profesional" id="id_profesional" class="form-control">
  </div>

  <div class="mb-3">
    <label for="archivo" class="form-label">Archivo</label>
    <input type="file" name="archivo" id="archivo" class="form-control" required>
  </div>

  <button type="submit" class="btn btn-primary">Subir Documento</button>
</form>
