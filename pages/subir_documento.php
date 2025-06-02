<?php
require_once 'includes/db.php';
require_once 'includes/storage.php';

$errorMsg = '';
$successMsg = '';

// Procesar formulario de subida
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombreDocumento = trim($_POST['nombre_documento'] ?? '');
    $tipoDocumento = trim($_POST['tipo_documento'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $idEstudiante = $_POST['id_estudiante'] ?: null;
    $idProfesional = $_POST['id_profesional'] ?: null;

    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = "Por favor, selecciona un archivo válido.";
    } elseif (empty($nombreDocumento) || empty($tipoDocumento)) {
        $errorMsg = "Completa todos los campos obligatorios.";
    } else {
        try {
            // Subir archivo al contenedor
            $archivoTmp = $_FILES['archivo']['tmp_name'];
            $archivoNombre = basename($_FILES['archivo']['name']);
            $contenido = fopen($archivoTmp, 'r');

            // Inicializar Azure
            $azure = new AzureBlobStorage();
            $exito = $azure->subirBlob($archivoNombre, $contenido);

            if ($exito) {
                // Guardar en la base de datos la URL pública (sin SAS, solo para referencia interna)
                $url = "https://documentossgd.blob.core.windows.net/documentos/{$archivoNombre}";
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
            $errorMsg = "Error general: " . $e->getMessage();
        }
    }
}
?>

<h2 class="mb-4">Subir Nuevo Documento</h2>

<?php if (!empty($errorMsg)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
<?php elseif (!empty($successMsg)): ?>
<div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>

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
    <textarea name="descripcion" id="de
