<?php
// pages/buscar_apoderados.php
require_once __DIR__ . '/../includes/db.php';

$q = trim($_GET['q'] ?? '');

if (strlen($q) >= 3) {
    $sql = "
        SELECT
            Id_apoderado   AS id,
            Rut_apoderado  AS rut,
            Nombre_apoderado   AS nombre,
            Apellido_apoderado AS apellido,
            Numero_apoderado   AS numero,
            Correo_apoderado   AS correo
        FROM apoderados
        WHERE Rut_apoderado   LIKE ?
           OR Nombre_apoderado LIKE ?
           OR Apellido_apoderado LIKE ?
        ORDER BY Nombre_apoderado, Apellido_apoderado
    ";
    $like = "%{$q}%";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$like, $like, $like]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);
} else {
    // Si la búsqueda es muy corta, devolvemos vacío
    echo json_encode([]);
}
