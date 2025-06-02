<?php
/**
 * includes/storage.php
 * Conexión a Azure Blob Storage y funciones básicas
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

class AzureBlobStorage {
    private $blobClient;
    private $containerName = "documentos"; // 

    /**
     * Constructor: inicializa el cliente con la cadena de conexión
     */
    public function __construct() {
        // Obtiene la cadena de conexión desde las variables de entorno en Azure
        $connectionString = getenv('AZURE_STORAGE_CONNECTION_STRING');

        // Inicializa el cliente Blob
        $this->blobClient = BlobRestProxy::createBlobService($connectionString);
    }

    /**
     * Lista los blobs del contenedor
     * @return array Lista con ['nombre' => ..., 'url' => ...]
     */
    public function listarBlobs() {
        try {
            $listBlobsOptions = new \MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions();
            $blobs = [];
    
            do {
                $result = $this->blobClient->listBlobs($this->containerName, $listBlobsOptions);
    
                foreach ($result->getBlobs() as $blob) {
                    $blobs[] = [
                        'nombre' => $blob->getName(),
                        'url'    => $blob->getUrl()
                    ];
                }
    
                // Token para la próxima página
                $continuationToken = $result->getContinuationToken();
                $listBlobsOptions->setContinuationToken($continuationToken);
    
            } while ($continuationToken !== null);
    
            return $blobs;
    
        } catch (ServiceException $e) {
            echo "Error al listar blobs: " . $e->getMessage();
            return [];
        }
    }
       
    /**
     * Genera la URL pública de un blob específico
     * @param string $blobName Nombre del archivo
     * @return string URL completa para descarga
     */
    public function obtenerBlobUrl($blobName) {
        return "https://documentossgd.blob.core.windows.net/{$this->containerName}/{$blobName}";
    }

    /**
     * Sube un archivo al contenedor
     * @param string $blobName Nombre con el que se almacenará en el contenedor
     * @param mixed $contenido Contenido binario o string
     * @return bool Éxito o fallo
     */
    public function subirBlob($blobName, $contenido) {
        try {
            $this->blobClient->createBlockBlob($this->containerName, $blobName, $contenido);
            return true;
        } catch (ServiceException $e) {
            echo "Error al subir blob: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Borra un blob específico del contenedor
     * @param string $blobName Nombre del archivo
     * @return bool Éxito o fallo
     */
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
