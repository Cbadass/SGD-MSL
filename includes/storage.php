<?php
/**
 * includes/storage.php
 * Conexión a Azure Blob Storage y generación manual de SAS para descargas seguras
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

class AzureBlobStorage {
    private $blobClient;
    private $containerName = "documentos"; // Contenedor real

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

    /**
     * Genera un SAS manualmente usando la clave de almacenamiento
     * @param string $blobName Nombre del archivo
     * @param int $duracionMinutos Validez en minutos
     * @return string URL segura con SAS
     */
    public function generarSASManual($blobName, $duracionMinutos = 60) {
        $accountName = getenv('AZURE_STORAGE_ACCOUNT_NAME');
        $accountKey = base64_decode(getenv('AZURE_STORAGE_ACCOUNT_KEY'));
        $container = $this->containerName;
        $permissions = 'r';
        $expiry = gmdate('Y-m-d\TH:i:s\Z', strtotime("+$duracionMinutos minutes"));
    
        // Construir stringToSign con exactamente 5 campos
        $stringToSign = implode("\n", [
            $permissions,                         // sp
            $expiry,                              // se
            "/blob/$accountName/$container/$blobName", // canonicalized resource
            '2021-08-06',                         // sv
            'b'                                   // sr
        ]);
    
        // Firma HMAC-SHA256
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, $accountKey, true));
    
        // Query final
        $queryString = http_build_query([
            'sv' => '2021-08-06',
            'sr' => 'b',
            'sig' => $signature,
            'se' => $expiry,
            'sp' => $permissions
        ]);
    
        return "https://$accountName.blob.core.windows.net/$container/$blobName?$queryString";
    }
    
    
}
?>
