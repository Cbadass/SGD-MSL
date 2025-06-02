<?php
require_once 'includes/db.php';
$q = trim($_GET['q'] ?? '');
if (strlen($q) >= 3) {
  $stmt = $conn->prepare("SELECT Id_profesional as id, Rut_profesional as rut, Nombre_profesional as nombre, Apellido_profesional as apellido FROM profesionales WHERE Rut_profesional LIKE ? OR Nombre_profesional LIKE ?");
  $like = "%$q%";
  $stmt->execute([$like, $like]);
  echo json_encode($stmt->fetchAll());
} else {
  echo json_encode([]);
}
?>
