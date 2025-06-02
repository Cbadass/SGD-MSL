<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/storage.php';

try {
    if (!isset($_SESSION['usuario'])) {
        throw new Exception("No autorizado.");
    }

    $nombre = trim($_POST['nombre'] ?? '');
    $tipo = trim($_POST['tipo_documento'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $id_estudiante = intval($_POST['id_estudiante'] ?? 0) ?: null;
    $id_profesional = intval($_POST['id_profesional'] ?? 0) ?: null;
    $id_usuario = $_SESSION['usuario']['id'];

    if (empty($nombre) || empty($tipo) || empty($_FILES['archivo']['name'])) {
        throw new Exception("Datos inválidos o incompletos.");
    }

    $archivo = $_FILES['archivo'];
    $nombreArchivo = basename($archivo['name']);
    $contenidoArchivo = file_get_contents($archivo['tmp_name']);

    // Validar extensión en el servidor
    $extensionesPermitidas = ['doc', 'docx', 'odt', 'pdf', 'txt', 'xls', 'xlsx', 'ods', 'ppt', 'pptx', 'odp', 'jpg', 'jpeg', 'png', 'gif'];
    $extensionArchivo = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));

    if (!in_array($extensionArchivo, $extensionesPermitidas)) {
        throw new Exception("El tipo de archivo '$extensionArchivo' no está permitido.");
    }

    // Obtener nombres para generar el nombre único
    $stmtEst = $conn->prepare("SELECT Nombre_estudiante, Apellido_estudiante FROM estudiantes WHERE Id_estudiante = ?");
    $stmtEst->execute([$id_estudiante]);
    $estudiante = $stmtEst->fetch();

    $stmtProf = $conn->prepare("SELECT Nombre_profesional, Apellido_profesional FROM profesionales WHERE Id_profesional = ?");
    $stmtProf->execute([$id_profesional]);
    $profesional = $stmtProf->fetch();

    $tipoLimpio = preg_replace('/[^a-zA-Z0-9]/', '', $tipo);
    $fechaHora = date('YmdHis');

    $nombreEstudiante = $estudiante ? preg_replace('/[^a-zA-Z0-9]/', '', $estudiante['Nombre_estudiante'] . $estudiante['Apellido_estudiante']) : 'SinEstudiante';
    $nombreProfesional = $profesional ? preg_replace('/[^a-zA-Z0-9]/', '', $profesional['Nombre_profesional'] . $profesional['Apellido_profesional']) : 'SinProfesional';

    $nombreBlob = "{$tipoLimpio}-{$fechaHora}-{$nombreEstudiante}-{$nombreProfesional}.{$extensionArchivo}";

    // Subir a Azure Blob Storage
    $azure = new AzureBlobStorage();
    $subido = $azure->subirBlob($nombreBlob, $contenidoArchivo);

    if (!$subido) {
        throw new Exception("Error al subir el archivo a Azure.");
    }

    // Guardar en la base de datos
    $urlDocumento = "https://documentossgd.blob.core.windows.net/documentos/$nombreBlob";
    $sql = "INSERT INTO documentos
            (Nombre_documento, Tipo_documento, Descripcion, Url_documento, Id_estudiante_doc, Id_prof_doc, Id_usuario_subido, Fecha_subido)
            VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE())";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$nombre, $tipo, $descripcion, $urlDocumento, $id_estudiante, $id_profesional, $id_usuario]);

    // Redirigir de nuevo a la lista de documentos
    header("Location: index.php?seccion=documentos");
    exit;

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
