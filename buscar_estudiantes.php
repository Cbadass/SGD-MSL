<?php
require_once 'includes/db.php';
$q = trim($_GET['q'] ?? '');
if (strlen($q) >= 3) {
  $stmt = $conn->prepare("SELECT Id_estudiante as id, Rut_estudiante as rut, Nombre_estudiante as nombre, Apellido_estudiante as apellido FROM estudiantes WHERE Rut_estudiante LIKE ? OR Nombre_estudiante LIKE ?");
  $like = "%$q%";
  $stmt->execute([$like, $like]);
  echo json_encode($stmt->fetchAll());
} else {
  echo json_encode([]);
}
?>
