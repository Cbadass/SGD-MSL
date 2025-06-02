<?php
require_once 'includes/db.php';
$q = trim($_GET['q'] ?? '');
if (strlen($q) >= 3) {
  $stmt = $conn->prepare("
    SELECT 
      e.Id_estudiante as id, 
      e.Rut_estudiante as rut, 
      e.Nombre_estudiante as nombre, 
      e.Apellido_estudiante as apellido, 
      c.Tipo_curso, 
      c.Grado_curso, 
      es.Nombre_escuela
    FROM estudiantes e
    LEFT JOIN cursos c ON e.Id_curso = c.Id_curso
    LEFT JOIN escuelas es ON e.Id_escuela = es.Id_escuela
    WHERE e.Rut_estudiante LIKE ? OR e.Nombre_estudiante LIKE ? OR e.Apellido_estudiante LIKE ?
  ");
  $like = "%$q%";
  $stmt->execute([$like, $like, $like]);
  echo json_encode($stmt->fetchAll());
} else {
  echo json_encode([]);
}
?>
