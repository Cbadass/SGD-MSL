<?php
// buscar_apoderados.php
require_once 'includes/db.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 3) {
    echo json_encode([]);
    exit;
}

// Preparar y ejecutar la consulta (SQL Server)
$stmt = $conn->prepare("
    SELECT TOP (10)
        Id_apoderado AS id,
        Rut_apoderado AS rut,
        Nombre_apoderado AS nombre,
        Apellido_apoderado AS apellido,
        Numero_apoderado AS numero,
        Correo_apoderado AS correo
    FROM apoderados
    WHERE Rut_apoderado LIKE ? 
       OR Nombre_apoderado LIKE ? 
       OR Apellido_apoderado LIKE ?
    ORDER BY Nombre_apoderado ASC
");
$like = "%{$q}%";
$stmt->execute([$like, $like, $like]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
