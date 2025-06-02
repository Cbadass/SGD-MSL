<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/storage.php'; // Tu clase para Azure Blob

try {
    if (!isset($_SESSION['usuario'])) {
        throw new Exception("No autorizado.");
    }

    $id = intval($_POST['id_documento'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $tipo = trim($_POST['tipo_documento'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $id_estudiante = intval($_POST['id_estudiante'] ?? 0) ?: null;
    $id_profesional = intval($_POST['id_profesional'] ?? 0) ?: null;
    $id_editor = $_SESSION['usuario']['id'];

    if ($id <= 0 || empty($nombre)) {
        throw new Exception("Datos inválidos.");
    }

    // Obtener el nombre del blob actual (opcional para eliminarlo después)
    $stmtOld = $conn->prepare("SELECT Url_documento FROM documentos WHERE Id_documento = ?");
    $stmtOld->execute([$id]);
    $docOld = $stmtOld->fetch();
    $oldBlobName = $docOld ? basename($docOld['Url_documento']) : null;

    // Actualizar la información del documento, incluyendo los nuevos IDs de estudiante y profesional
    $sql = "UPDATE documentos
            SET Nombre_documento = ?, Tipo_documento = ?, Descripcion = ?, Id_usuario_subido = ?, Id_estudiante_doc = ?, Id_prof_doc = ?, Fecha_modificacion = GETDATE()
            WHERE Id_documento = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$nombre, $tipo, $descripcion, $id_editor, $id_estudiante, $id_profesional, $id]);

    // Si se subió un nuevo archivo
    if (!empty($_FILES['archivo']['name'])) {
        $archivo = $_FILES['archivo'];
        $nombreArchivo = basename($archivo['name']);
        $contenidoArchivo = file_get_contents($archivo['tmp_name']);

        // Validar extensión en el servidor también
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

        // Generar nombre único
        $tipoLimpio = preg_replace('/[^a-zA-Z0-9]/', '', $tipo);
        $fechaHora = date('YmdHis');

        $nombreEstudiante = $estudiante ? preg_replace('/[^a-zA-Z0-9]/', '', $estudiante['Nombre_estudiante'] . $estudiante['Apellido_estudiante']) : 'SinEstudiante';
        $nombreProfesional = $profesional ? preg_replace('/[^a-zA-Z0-9]/', '', $profesional['Nombre_profesional'] . $profesional['Apellido_profesional']) : 'SinProfesional';

        $nombreBlob = "{$tipoLimpio}-{$fechaHora}-{$nombreEstudiante}-{$nombreProfesional}.{$extensionArchivo}";

        // Subir a Azure Blob Storage
        $azure = new AzureBlobStorage();
        $subido = $azure->subirBlob($nombreBlob, $contenidoArchivo);

        if ($subido) {
            // Actualizar la URL del documento en la base de datos
            $urlDocumento = "https://documentossgd.blob.core.windows.net/documentos/$nombreBlob";
            $stmt = $conn->prepare("UPDATE documentos SET Url_documento = ? WHERE Id_documento = ?");
            $stmt->execute([$urlDocumento, $id]);

            // Eliminar el archivo anterior en Azure (opcional)
            if ($oldBlobName) {
                $azure->borrarBlob($oldBlobName);
            }
        } else {
            throw new Exception("Error al subir el nuevo archivo a Azure.");
        }
    }

    // Redirigir de nuevo a la lista de documentos
    header("Location: index.php?seccion=documentos");
    exit;

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
