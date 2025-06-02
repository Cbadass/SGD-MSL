<?php
/**
 * includes/storage.php
 * Conexión a Azure Blob Storage y generación manual de SAS para descargas seguras.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

class AzureBlobStorage {
    private $blobClient;
    private $containerName = "documentos"; // Nombre real de tu contenedor en Azure

    /**
     * Constructor: inicializa el cliente Blob usando la cadena de conexión.
     */
    public function __construct() {
        $connectionString = getenv('AZURE_STORAGE_CONNECTION_STRING');
        $this->blobClient = BlobRestProxy::createBlobService($connectionString);
    }

    /**
     * Sube un archivo al contenedor.
     * @param string $blobName Nombre que tendrá el blob en Azure.
     * @param mixed $contenido Contenido binario o recurso de archivo.
     * @return bool Éxito o fallo.
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
     * Borra un blob específico del contenedor.
     * @param string $blobName Nombre del archivo.
     * @return bool Éxito o fallo.
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

    /**
     * Genera una URL segura (SAS) para descargar un archivo desde Azure.
     * Este método no expone la clave, solo genera la firma para acceso temporal.
     *
     * @param string $blobName Nombre del blob.
     * @param int $duracionMinutos Tiempo de validez en minutos.
     * @return string URL segura con SAS.
     */
    public function generarSASManual($blobName, $duracionMinutos = 60) {
        $accountName = getenv('AZURE_STORAGE_ACCOUNT_NAME');
        $accountKey = base64_decode(getenv('AZURE_STORAGE_ACCOUNT_KEY'));
        $container = $this->containerName;
        $permissions = 'r'; // Solo lectura
        $expiry = gmdate('Y-m-d\TH:i:s\Z', strtotime("+$duracionMinutos minutes"));

        // Construir stringToSign con exactamente 5 campos
        $stringToSign = implode("\n", [
            $permissions,                         // sp
            $expiry,                              // se
            "/blob/$accountName/$container/$blobName", // canonicalized resource
            '2021-08-06',                         // sv
            'b'                                   // sr
        ]);

        // Limpiar saltos de línea de Windows
        $stringToSign = str_replace("\r", '', $stringToSign);

        // Firma HMAC-SHA256
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, $accountKey, true));

        // Armar la URL final
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
