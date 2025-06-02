<?php
/**
 * includes/storage.php
 * Conexión a Azure Blob Storage y funciones básicas (con generación de SAS)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Common\Models\SharedAccessSignatureHelper;

class AzureBlobStorage {
    private $blobClient;
    private $containerName = "documentos"; // Contenedor real en Azure

    /**
     * Constructor: inicializa el cliente con la cadena de conexión
     */
    public function __construct() {
        $connectionString = getenv('AZURE_STORAGE_CONNECTION_STRING');
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

                // Continuación para la siguiente página
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
     * Genera la URL pública (no SAS)
     * @param string $blobName Nombre del archivo
     * @return string URL pública (no funciona si contenedor es privado)
     */
    public function obtenerBlobUrl($blobName) {
        return "https://documentossgd.blob.core.windows.net/{$this->containerName}/{$blobName}";
    }

    /**
     * Genera una URL con SAS Token (temporal y segura)
     * @param string $blobName Nombre del archivo
     * @param int $duracionMinutos Duración en minutos de validez
     * @return string URL completa con SAS Token
     */
    public function obtenerBlobUrlConSAS($blobName, $duracionMinutos = 60) {
        $accountName = getenv('AZURE_STORAGE_ACCOUNT_NAME');
        $accountKey = getenv('AZURE_STORAGE_ACCOUNT_KEY');

        // Helper para generar el SAS
        $sasHelper = new SharedAccessSignatureHelper($accountName, $accountKey);

        // Fecha de expiración
        $expiry = gmdate("Y-m-d\TH:i:s\Z", strtotime("+$duracionMinutos minutes"));

        // Generar el token SAS (solo lectura: 'r')
        $sasToken = $sasHelper->generateBlobServiceSharedAccessSignatureToken(
            "b",
            "{$this->containerName}/$blobName",
            'r',
            $expiry
        );

        // Construir la URL final
        return "https://$accountName.blob.core.windows.net/{$this->containerName}/$blobName?$sasToken";
    }

    /**
     * Sube un archivo al contenedor
     * @param string $blobName Nombre con el que se almacenará
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
     * Borra un blob específico
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
