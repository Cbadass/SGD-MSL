<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/storage.php';  // Nuestra clase AzureBlobStorage

// 1) Validar sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// 2) Capturar ID desde POST
$id = intval($_POST['id_documento'] ?? 0);
if ($id <= 0) {
    die("ID inválido.");
}

// 3) Recuperar URL actual del documento
$stmt0 = $conn->prepare("SELECT Url_documento FROM documentos WHERE Id_documento = ?");
$stmt0->execute([$id]);
$orig = $stmt0->fetch(PDO::FETCH_ASSOC);
if (!$orig) {
    die("Documento no encontrado.");
}
$url_documento = $orig['Url_documento'];

// 4) Capturar resto de campos
$nombre         = trim($_POST['nombre']            ?? '');
$tipo           = trim($_POST['tipo_documento']   ?? '');
$descripcion    = trim($_POST['descripcion']       ?? '');
$id_estudiante  = intval($_POST['id_estudiante']   ?? 0) ?: null;
$id_profesional = intval($_POST['id_profesional']  ?? 0) ?: null;

// 5) Procesar archivo si se subió uno nuevo
if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
    $allow = ['doc','docx','odt','pdf','txt','xls','xlsx','ods','ppt','pptx','odp','jpg','jpeg','png'];
    if (!in_array($ext, $allow, true)) {
        die("Extensión no permitida. Solo Office, PDF, TXT o imágenes.");
    }

    // Generar un nombre único para el blob
    $blobName = time() . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    $contenido = fopen($_FILES['archivo']['tmp_name'], 'r');

    $storage = new AzureBlobStorage();

    // Si ya existía un blob anterior, opcionalmente extraer su nombre y borrarlo
    // (dependerá de cómo guardaste la URL; aquí asumimos que el blobName es todo lo después del contenedor)
    $parsed = parse_url($url_documento, PHP_URL_PATH);
    $parts = explode('/', trim($parsed, '/'));
    $oldBlob = end($parts);
    if ($oldBlob && $oldBlob !== $blobName) {
        $storage->borrarBlob($oldBlob);
    }

    // Subir el nuevo blob
    if (!$storage->subirBlob($blobName, $contenido)) {
        die("Error al subir el archivo a Azure Blob.");
    }

    // Construir la nueva URL pública
    $account = getenv('AZURE_STORAGE_ACCOUNT');  // define en .env o en el entorno
    $url_documento = "https://{$account}.blob.core.windows.net/documentos/{$blobName}";
}

// 6) Ejecutar UPDATE
$sql = "
    UPDATE documentos
    SET 
      Nombre_documento   = :nombre,
      Tipo_documento     = :tipo,
      Descripcion        = :desc,
      Url_documento      = :url,
      Id_estudiante_doc  = :idest,
      Id_prof_doc        = :idprof,
      Fecha_modificacion = GETDATE()
    WHERE Id_documento = :id
";
$stmt = $conn->prepare($sql);
$stmt->execute([
    ':nombre'  => $nombre,
    ':tipo'    => $tipo,
    ':desc'    => $descripcion,
    ':url'     => $url_documento,
    ':idest'   => $id_estudiante,
    ':idprof'  => $id_profesional,
    ':id'      => $id
]);

// 7) Redirigir de vuelta
header("Location: index.php?seccion=documentos");
exit;
