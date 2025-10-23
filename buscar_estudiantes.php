<?php
// buscar_estudiantes.php
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
  WHERE (e.Rut_estudiante LIKE ? OR e.Nombre_estudiante LIKE ? OR e.Apellido_estudiante LIKE ?)
    AND " . filtrarPorIDs($idsEstudiantesPermitidos, 'e.Id_estudiante');

$params = ["%$q%", "%$q%", "%$q%"];
agregarParametrosFiltro($params, $idsEstudiantesPermitidos);

$stmt = $conn->prepare($sql);
$stmt->execute($params);
echo json_encode($stmt->fetchAll());
?>