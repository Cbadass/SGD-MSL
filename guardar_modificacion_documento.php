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
    $id_editor = $_SESSION['usuario']['id'];

    if ($id <= 0 || empty($nombre)) {
        throw new Exception("Datos inválidos.");
    }

    // Obtener el nombre del blob actual
    try {
        $stmtOld = $conn->prepare("SELECT Url_documento FROM documentos WHERE Id_documento = ?");
        $stmtOld->execute([$id]);
        $docOld = $stmtOld->fetch();
        $oldBlobName = $docOld ? basename($docOld['Url_documento']) : null;
        echo "Archivo antiguo: $oldBlobName<br>";
    } catch (Exception $e) {
        echo "Error obteniendo archivo antiguo: " . $e->getMessage() . "<br>";
    }

    // Actualizar información general del documento
    try {
        $sql = "UPDATE documentos
                SET Nombre_documento = ?, Tipo_documento = ?, Descripcion = ?, Id_usuario_subido  = ?, Fecha_modificacion = GETDATE()
                WHERE Id_documento = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$nombre, $tipo, $descripcion, $id_editor, $id]);
        echo "Documento actualizado.<br>";
    } catch (Exception $e) {
        echo "Error actualizando documento: " . $e->getMessage() . "<br>";
    }

    // Si se subió un nuevo archivo
    if (!empty($_FILES['archivo']['name'])) {
        try {
            $archivo = $_FILES['archivo'];
            $nombreArchivo = basename($archivo['name']);
            $contenidoArchivo = file_get_contents($archivo['tmp_name']);
            echo "Archivo nuevo recibido: $nombreArchivo<br>";

            // Subir a Azure Blob Storage
            $azure = new AzureBlobStorage();
            $subido = $azure->subirBlob($nombreArchivo, $contenidoArchivo);
            echo "Subida a Azure: " . ($subido ? "OK" : "FALLÓ") . "<br>";

            if ($subido) {
                // Actualizar URL en la base de datos
                $urlDocumento = "https://documentossgd.blob.core.windows.net/documentos/$nombreArchivo";
                $stmt = $conn->prepare("UPDATE documentos SET Url_documento = ? WHERE Id_documento = ?");
                $stmt->execute([$urlDocumento, $id]);
                echo "URL actualizada en la BD.<br>";

                // Eliminar el archivo anterior en Azure (opcional)
                if ($oldBlobName) {
                    $azure->borrarBlob($oldBlobName);
                    echo "Archivo antiguo eliminado de Azure.<br>";
                }
            } else {
                throw new Exception("Error al subir el nuevo archivo a Azure.");
            }
        } catch (Exception $e) {
            echo "Error con archivo nuevo: " . $e->getMessage() . "<br>";
        }
    }

    echo "Proceso completado. Redirigiendo...";
    header("Refresh: 3; URL=index.php?seccion=documentos");
    exit;

} catch (Exception $e) {
    echo "Error general: " . $e->getMessage() . "<br>";
}
?>
