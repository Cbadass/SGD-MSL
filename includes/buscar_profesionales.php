<?php
require_once 'includes/db.php';
$q = trim($_GET['q'] ?? '');
if (strlen($q) >= 3) {
  $stmt = $conn->prepare("SELECT Id_profesional, Rut_profesional, Nombre_profesional, Apellido_profesional FROM profesionales WHERE Rut_profesional LIKE ? OR Nombre_profesional LIKE ?");
  $like = "%$q%";
  $stmt->execute([$like, $like]);
  echo json_encode($stmt->fetchAll());
} else {
  echo json_encode([]);
}
?>
