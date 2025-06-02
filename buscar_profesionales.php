<?php
require_once 'includes/db.php';
$q = trim($_GET['q'] ?? '');
if (strlen($q) >= 3) {
  $stmt = $conn->prepare("
    SELECT 
      p.Id_profesional as id, 
      p.Rut_profesional as rut, 
      p.Nombre_profesional as nombre, 
      p.Apellido_profesional as apellido, 
      p.Cargo_profesional, 
      e.Nombre_escuela
    FROM profesionales p
    LEFT JOIN escuelas e ON p.Id_escuela_prof = e.Id_escuela
    WHERE p.Rut_profesional LIKE ? OR p.Nombre_profesional LIKE ? OR p.Apellido_profesional LIKE ?
  ");
  $like = "%$q%";
  $stmt->execute([$like, $like, $like]);
  echo json_encode($stmt->fetchAll());
} else {
  echo json_encode([]);
}
?>
