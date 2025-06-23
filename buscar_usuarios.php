<?php
// buscar_usuarios.php
require_once 'includes/db.php';

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 3) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT 
      u.Id_usuario   AS id,
      u.Nombre_usuario AS rut,
      COALESCE(p.Nombre_profesional, '')  AS nombre,
      COALESCE(p.Apellido_profesional, '') AS apellido
    FROM usuarios u
    LEFT JOIN profesionales p ON u.Id_profesional = p.Id_profesional
    WHERE 
      u.Nombre_usuario    LIKE ?
      OR p.Nombre_profesional LIKE ?
      OR p.Apellido_profesional LIKE ?
");

$like = "%$q%";
$stmt->execute([$like, $like, $like]);

header('Content-Type: application/json');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
