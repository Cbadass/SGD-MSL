<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/storage.php';

try {
    // 1) Autorización
    if (!isset($_SESSION['usuario'])) {
        throw new Exception("No autorizado.");
    }

    // 2) Capturar ID del documento
    $id = intval($_POST['id_documento'] ?? 0);
    if ($id <= 0) {
        throw new Exception("ID inválido.");
    }

    // 3) Recuperar URL existente
    $stmt0 = $conn->prepare("SELECT Url_documento FROM documentos WHERE Id_documento = ?");
    $stmt0->execute([$id]);
    $orig = $stmt0->fetch(PDO::FETCH_ASSOC);
    if (!$orig) {
        throw new Exception("Documento no encontrado.");
    }
    $url_documento = $orig['Url_documento'];

    // 4) Capturar campos del formulario
    $nombre         = trim($_POST['nombre']            ?? '');
    $tipo           = trim($_POST['tipo_documento']   ?? '');
    $descripcion    = trim($_POST['descripcion']       ?? '');
    $id_estudiante  = intval($_POST['id_estudiante']   ?? 0) ?: null;
    $id_profesional = intval($_POST['id_profesional']  ?? 0) ?: null;

    if (empty($nombre) || empty($tipo)) {
        throw new Exception("Nombre y tipo de documento son obligatorios.");
    }

    // 5) Si llega un nuevo archivo, validarlo y subir a Azure
    if (!empty($_FILES['archivo']['name']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $archivo        = $_FILES['archivo'];
        $nombreArchivo  = basename($archivo['name']);
        $contenido      = file_get_contents($archivo['tmp_name']);
        $ext            = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));

        // a) Validar extensión
        $permitidas = ['doc','docx','odt','pdf','txt','xls','xlsx','ods','ppt','pptx','odp','jpg','jpeg','png','gif'];
        if (!in_array($ext, $permitidas, true)) {
            throw new Exception("El tipo de archivo '$ext' no está permitido.");
        }

        // b) Obtener datos de estudiante y profesional para el nombre del blob
        $stmtEst = $conn->prepare("SELECT Nombre_estudiante, Apellido_estudiante FROM estudiantes WHERE Id_estudiante = ?");
        $stmtEst->execute([$id_estudiante]);
        $est = $stmtEst->fetch(PDO::FETCH_ASSOC);

        $stmtProf = $conn->prepare("SELECT Nombre_profesional, Apellido_profesional FROM profesionales WHERE Id_profesional = ?");
        $stmtProf->execute([$id_profesional]);
        $prof = $stmtProf->fetch(PDO::FETCH_ASSOC);

        $tipoL    = preg_replace('/[^a-zA-Z0-9]/', '', $tipo);
        $ts       = date('YmdHis');
        $nEst     = $est ? preg_replace('/[^a-zA-Z0-9]/', '', $est['Nombre_estudiante'] . $est['Apellido_estudiante']) : 'SinEst';
        $nProf    = $prof ? preg_replace('/[^a-zA-Z0-9]/', '', $prof['Nombre_profesional'] . $prof['Apellido_profesional']) : 'SinProf';

        $blobName = "{$tipoL}-{$ts}-{$nEst}-{$nProf}.{$ext}";

        $azure    = new AzureBlobStorage();

        // c) Borrar blob anterior (extraer nombre desde URL)
        $path     = parse_url($url_documento, PHP_URL_PATH);
        $parts    = explode('/', trim($path, '/'));
        $oldBlob  = end($parts);
        if ($oldBlob && $oldBlob !== $blobName) {
            $azure->borrarBlob($oldBlob);
        }

        // d) Subir nuevo blob
        if (!$azure->subirBlob($blobName, $contenido)) {
            throw new Exception("Error al subir el archivo a Azure.");
        }

        // e) Reconstruir URL pública
        $account        = getenv('AZURE_STORAGE_ACCOUNT');
        $url_documento  = "https://{$account}.blob.core.windows.net/documentos/{$blobName}";
    }

    // 6) Ejecutar UPDATE en la base de datos
    $sql = "
        UPDATE documentos
           SET Nombre_documento   = :nombre,
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
        ':nombre' => $nombre,
        ':tipo'   => $tipo,
        ':desc'   => $descripcion,
        ':url'    => $url_documento,
        ':idest'  => $id_estudiante,
        ':idprof' => $id_profesional,
        ':id'     => $id
    ]);

    // 7) Redirigir al listado
    header("Location: index.php?seccion=documentos");
    exit;

} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
