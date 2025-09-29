<?php

session_start();
require_once 'includes/db.php'; // Tu conexión PDO

// Verificar login
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    die("No autorizado.");
}

$id_documento = intval($_GET['id_documento'] ?? 0);
if ($id_documento <= 0) {
    http_response_code(400);
    die("ID de documento inválido.");
}

$usuario = $_SESSION['usuario'];

// Obtener el documento
$sql = "SELECT * FROM documentos WHERE Id_documento = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$id_documento]);
$documento = $stmt->fetch();

if (!$documento) {
    http_response_code(404);
    die("Documento no encontrado.");
}

// Verificar permisos
if (
    $usuario['permisos'] !== 'ADMIN' && 
    $documento['Id_prof_doc'] != $usuario['id_profesional']
) {
    http_response_code(403);
    die("No tienes permisos para descargar este archivo.");
}

// Obtener la extensión real del archivo (a partir de la URL)
$ext = pathinfo($documento['Url_documento'], PATHINFO_EXTENSION);

// Armar el nombre final para la descarga (con extensión)
$nombreUsuario = pathinfo($documento['Nombre_documento'], PATHINFO_FILENAME); // solo nombre, sin extensión
$nombreDescarga = $nombreUsuario . ($ext ? '.' . $ext : ''); // agrega extensión real

// Descargar el archivo desde la URL pública
$urlBlobPublico = $documento['Url_documento'];

// Descargar el archivo a un archivo temporal
$tempFile = tempnam(sys_get_temp_dir(), 'blob_');
file_put_contents($tempFile, file_get_contents($urlBlobPublico));

// Servir como descarga
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $nombreDescarga . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
readfile($tempFile);

unlink($tempFile);
exit;
?>
