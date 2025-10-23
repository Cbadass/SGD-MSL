<?php
// buscar_apoderados.php
session_start();
require_once 'includes/db.php';
require_once 'includes/roles.php';

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 3) {
    echo json_encode([]);
    exit;
}

// ========== Obtener alcance ==========
$alcance = getAlcanceUsuario($conn, $_SESSION['usuario'] ?? []);
$idsEstudiantesPermitidos = $alcance['estudiantes'];
// ====================================

$sql = "
  SELECT Id_apoderado AS id,
         Rut_apoderado AS rut,
         Nombre_apoderado AS nombre,
         Apellido_apoderado AS apellido
    FROM apoderados
   WHERE (Rut_apoderado LIKE ? OR Nombre_apoderado LIKE ? OR Apellido_apoderado LIKE ?)
";

$params = ["%$q%", "%$q%", "%$q%"];

// Filtrar por estudiantes permitidos
if ($idsEstudiantesPermitidos !== null) {
    if (empty($idsEstudiantesPermitidos) || $idsEstudiantesPermitidos === [0]) {
        echo json_encode([]);
        exit;
    }
    $placeholders = implode(',', array_fill(0, count($idsEstudiantesPermitidos), '?'));
    $sql .= " AND Id_apoderado IN (
        SELECT DISTINCT Id_apoderado 
        FROM estudiantes 
        WHERE Id_estudiante IN ($placeholders) AND Id_apoderado IS NOT NULL
    ) ";
    $params = array_merge($params, $idsEstudiantesPermitidos);
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));