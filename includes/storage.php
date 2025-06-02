<?php
/**
 * includes/storage.php
 * Conexión a Azure Blob Storage para subir y borrar blobs.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

class AzureBlobStorage {
    private $blobClient;
    private $containerName = "documentos"; // Nombre real de tu contenedor en Azure

    public function __construct() {
        $connectionString = getenv('AZURE_STORAGE_CONNECTION_STRING');
        $this->blobClient = BlobRestProxy::createBlobService($connectionString);
    }

    public function subirBlob($blobName, $contenido) {
        try {
            $this->blobClient->createBlockBlob($this->containerName, $blobName, $contenido);
            return true;
        } catch (ServiceException $e) {
            echo "Error al subir blob: " . $e->getMessage();
            return false;
        }
    }

    public function borrarBlob($blobName) {
        try {
            $this->blobClient->deleteBlob($this->containerName, $blobName);
            return true;
        } catch (ServiceException $e) {
            echo "Error al borrar blob: " . $e->getMessage();
            return false;
        }
    }
}
?>
