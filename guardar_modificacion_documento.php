<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/storage.php';

try {
    // 1) Autorización
    if (!isset($_SESSION['usuario'])) {
        throw new Exception("No autorizado.");
    }

    // ID por POST
    $id = intval($_POST['id_documento'] ?? 0);
    if ($id <= 0) throw new Exception("ID inválido.");

    // URL actual
    $stmt0 = $conn->prepare("SELECT Url_documento FROM documentos WHERE Id_documento = ?");
    $stmt0->execute([$id]);
    $orig = $stmt0->fetch(PDO::FETCH_ASSOC);
    if (!$orig) throw new Exception("Documento no encontrado.");
    $url_documento = $orig['Url_documento'];

    // Campos del formulario
    $nombre         = trim($_POST['nombre']            ?? '');
    $tipo           = trim($_POST['tipo_documento']   ?? '');
    $descripcion    = trim($_POST['descripcion']       ?? '');
    $id_estudiante  = intval($_POST['id_estudiante']   ?? 0) ?: null;
    $id_profesional = intval($_POST['id_profesional']  ?? 0) ?: null;

    if (empty($nombre) || empty($tipo)) {
        throw new Exception("Nombre y tipo de documento son obligatorios.");
    }

    // Si llega archivo nuevo...
    if (!empty($_FILES['archivo']['name']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $archivo       = $_FILES['archivo'];
        $ext           = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $permitidas    = ['doc','docx','odt','pdf','txt','xls','xlsx','ods','ppt','pptx','odp','jpg','jpeg','png','gif'];
        if (!in_array($ext, $permitidas, true)) {
            throw new Exception("El tipo de archivo '$ext' no está permitido.");
        }

        // Generar nombre único
        $stmtEst = $conn->prepare("SELECT Nombre_estudiante, Apellido_estudiante FROM estudiantes WHERE Id_estudiante = ?");
        $stmtEst->execute([$id_estudiante]);
        $est = $stmtEst->fetch(PDO::FETCH_ASSOC);

        $stmtProf = $conn->prepare("SELECT Nombre_profesional, Apellido_profesional FROM profesionales WHERE Id_profesional = ?");
        $stmtProf->execute([$id_profesional]);
        $prof = $stmtProf->fetch(PDO::FETCH_ASSOC);

        $tipoL    = preg_replace('/[^a-zA-Z0-9]/', '', $tipo);
        $ts       = date('YmdHis');
        $nEst     = $est ? preg_replace('/[^a-zA-Z0-9]/', '',
                     $est['Nombre_estudiante'] . $est['Apellido_estudiante']) : 'SinEst';
        $nProf    = $prof ? preg_replace('/[^a-zA-Z0-9]/', '',
                     $prof['Nombre_profesional'] . $prof['Apellido_profesional']) : 'SinProf';

        $blobName = "{$tipoL}-{$ts}-{$nEst}-{$nProf}.{$ext}";
        $contenido= file_get_contents($archivo['tmp_name']);
        $azure    = new AzureBlobStorage();

        // Borrar blob anterior
        $path    = parse_url($url_documento, PHP_URL_PATH);
        $oldBlob = basename($path);
        if ($oldBlob && $oldBlob !== $blobName) {
            $azure->borrarBlob($oldBlob);
        }

        // Subir nuevo blob
        if (!$azure->subirBlob($blobName, $contenido)) {
            throw new Exception("Error al subir el archivo a Azure.");
        }

        // Aquí el cambio: extraer AccountName de la connection string
        $connectionString = getenv('AZURE_STORAGE_CONNECTION_STRING');
        if (!preg_match('/AccountName=([^;]+);/', $connectionString, $m)) {
            throw new Exception("No se pudo obtener el nombre de cuenta de la cadena de conexión.");
        }
        $account        = $m[1];
        $url_documento  = "https://{$account}.blob.core.windows.net/documentos/{$blobName}";
    }

    // UPDATE documentos
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
