<?php
session_start();
require_once 'includes/db.php'; // Tu conexión PDO

// Validar login
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    die("No autorizado.");
}

$id_documento = intval($_GET['id_documento'] ?? 0);
if ($id_documento <= 0) {
    http_response_code(400);
    die("ID de documento inválido.");
}

// Obtener el usuario logueado
$usuario = $_SESSION['usuario'];

// Verificar que el documento pertenece a su Id_profesional o es admin
$sql = "SELECT * FROM documentos WHERE Id_documento = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$id_documento]);
$documento = $stmt->fetch();

if (!$documento) {
    http_response_code(404);
    die("Documento no encontrado.");
}

// Validar permisos
if (
    $usuario['permisos'] !== 'admin' && 
    $documento['Id_prof_doc'] != $usuario['id_profesional']
) {
    http_response_code(403);
    die("No tienes permisos para descargar este archivo.");
}

// Descargar el archivo real desde Azure Blob Storage
$accountName = getenv('AZURE_STORAGE_ACCOUNT_NAME');
$accountKey = base64_decode(getenv('AZURE_STORAGE_ACCOUNT_KEY'));
$container = 'documentos';
$blobName = basename($documento['Url_documento']);
$urlBlob = "https://$accountName.blob.core.windows.net/$container/$blobName";

$date = gmdate('D, d M Y H:i:s') . ' GMT';
$canonicalizedHeaders = "x-ms-date:$date\nx-ms-version:2021-08-06";
$canonicalizedResource = "/$accountName/$container/$blobName";
$stringToSign = "GET\n\n\n\n\n\n\n\n\n\n\n\n$canonicalizedHeaders\n$canonicalizedResource";
$signature = base64_encode(hash_hmac('sha256', $stringToSign, $accountKey, true));
$authHeader = "SharedKey $accountName:$signature";

// Hacer la solicitud a Azure
$ch = curl_init($urlBlob);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "x-ms-date: $date",
    "x-ms-version: 2021-08-06",
    "Authorization: $authHeader"
]);

$contenido = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode === 200) {
    // Enviar archivo como descarga al navegador
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($documento['Nombre_documento']) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    echo $contenido;
} else {
    http_response_code(500);
    die("Error al descargar el archivo desde Azure.");
}
?>
