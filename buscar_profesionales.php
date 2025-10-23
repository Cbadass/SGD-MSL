<?php
// buscar_profesionales.php
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
$idsProfesionalesPermitidos = $alcance['profesionales'];
// ====================================

$sql = "
  SELECT 
    p.Id_profesional as id, 
    p.Rut_profesional as rut, 
    p.Nombre_profesional as nombre, 
    p.Apellido_profesional as apellido, 
    p.Cargo_profesional, 
    e.Nombre_escuela
  FROM profesionales p
  LEFT JOIN escuelas e ON p.Id_escuela_prof = e.Id_escuela
  WHERE (p.Rut_profesional LIKE ? OR p.Nombre_profesional LIKE ? OR p.Apellido_profesional LIKE ?)
    AND " . filtrarPorIDs($idsProfesionalesPermitidos, 'p.Id_profesional');

$params = ["%$q%", "%$q%", "%$q%"];
agregarParametrosFiltro($params, $idsProfesionalesPermitidos);

$stmt = $conn->prepare($sql);
$stmt->execute($params);
echo json_encode($stmt->fetchAll());
?>