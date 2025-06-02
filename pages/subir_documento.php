<?php
require_once 'includes/bd.php';

$sql = "SELECT * FROM documentos";
$stmt = $conn->query($sql);

if (!$stmt) {
    die("Error en la consulta: " . implode(", ", $conn->errorInfo()));
}

$documentos = $stmt->fetchAll();

echo "<pre>";
print_r("hola mundo");
print_r($documentos);
echo "</pre>";
?>
