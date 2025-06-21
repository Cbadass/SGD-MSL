<?php
require_once 'includes/db.php';
$q = trim($_GET['q'] ?? '');
if (strlen($q) < 3) {
    echo json_encode([]);
    exit;
}
$stmt = $conn->prepare("
  SELECT Id_apoderado AS id,
         Rut_apoderado AS rut,
         Nombre_apoderado AS nombre,
         Apellido_apoderado AS apellido
    FROM apoderados
   WHERE Rut_apoderado LIKE ?
      OR Nombre_apoderado LIKE ?
      OR Apellido_apoderado LIKE ?
");
$like = "%$q%";
$stmt->execute([$like, $like, $like]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
