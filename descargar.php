<?php
// descargar.php
session_start();
require_once 'includes/db.php';
require_once 'includes/roles.php';

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
$alcance = getAlcanceUsuario($conn, $usuario);
$diagnosticos = $alcance['diagnosticos'] ?? [];
$idsEstudiantesPermitidos = $alcance['estudiantes'] ?? null;

// Obtener el documento
$sql = "SELECT * FROM documentos WHERE Id_documento = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$id_documento]);
$documento = $stmt->fetch();

if (!$documento) {
    http_response_code(404);
    die("Documento no encontrado.");
}

// ========== Validar permisos según rol ==========
$puedeDescargar = false;

if ($alcance['rol'] === 'ADMIN') {
    $puedeDescargar = true;
    
} elseif ($alcance['rol'] === 'DIRECTOR') {
    // Validar que el documento sea de su escuela
    if ($documento['Id_estudiante_doc']) {
        // Documento de estudiante: verificar que el estudiante sea de la escuela del director
        $stmt = $conn->prepare("
            SELECT 1 FROM estudiantes e
            INNER JOIN cursos c ON e.Id_curso = c.Id_curso
            WHERE e.Id_estudiante = ? AND c.Id_escuela = ?
        ");
        $stmt->execute([$documento['Id_estudiante_doc'], $alcance['escuela_id']]);
        $puedeDescargar = (bool)$stmt->fetchColumn();
    } elseif ($documento['Id_prof_doc']) {
        // Documento de profesional: verificar que el profesional sea de la escuela
        $stmt = $conn->prepare("
            SELECT 1 FROM profesionales WHERE Id_profesional = ? AND Id_escuela_prof = ?
        ");
        $stmt->execute([$documento['Id_prof_doc'], $alcance['escuela_id']]);
        $puedeDescargar = (bool)$stmt->fetchColumn();
    }
    
} else { // PROFESIONAL
    // Solo puede descargar si:
    // 1. Es su propio documento (Id_prof_doc)
    // 2. Es de un estudiante que tiene asignado
    if ($documento['Id_prof_doc'] == $alcance['id_profesional']) {
        $puedeDescargar = true;
    } elseif ($documento['Id_estudiante_doc']) {
        $stmt = $conn->prepare("
            SELECT 1 FROM Asignaciones 
            WHERE Id_profesional = ? AND Id_estudiante = ?
        ");
        $stmt->execute([$alcance['id_profesional'], $documento['Id_estudiante_doc']]);
        $puedeDescargar = (bool)$stmt->fetchColumn();
    }
}

if (!$puedeDescargar) {
    http_response_code(403);

    if (!empty($diagnosticos)) {
        die(implode(' ', $diagnosticos));
    }

    if ($alcance['rol'] === 'DIRECTOR' && empty($alcance['escuela_id'])) {
        die('Tu cuenta no está asociada a una escuela. Comunícate con soporte para completar el registro.');
    }

    if ($alcance['rol'] === 'PROFESIONAL') {
        if ((int)($alcance['id_profesional'] ?? 0) <= 0) {
            die('Tu cuenta no está vinculada a un profesional activo. Solicita asistencia a soporte.');
        }
        if ($idsEstudiantesPermitidos === [0]) {
            die('No tienes estudiantes asignados actualmente, por lo que no puedes descargar documentos vinculados.');
        }
    }

    die('No tienes permisos para descargar este archivo.');
}
// ================================================

// Obtener la extensión real del archivo
$ext = pathinfo($documento['Url_documento'], PATHINFO_EXTENSION);
$nombreUsuario = pathinfo($documento['Nombre_documento'], PATHINFO_FILENAME);
$nombreDescarga = $nombreUsuario . ($ext ? '.' . $ext : '');

// Descargar el archivo desde la URL pública
$urlBlobPublico = $documento['Url_documento'];
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