<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/session.php'; // NO redirige; solo abre sesión si existe
require_once __DIR__ . '/../includes/db.php';

// Helpers locales
function asig_getDirectorEscuelaId(PDO $conn, int $idProfesional): ?int {
  $st = $conn->prepare("SELECT Id_escuela_prof FROM profesionales WHERE Id_profesional = ?");
  $st->execute([$idProfesional]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  return $r && $r['Id_escuela_prof'] ? (int)$r['Id_escuela_prof'] : null;
}

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) < 3) { echo json_encode([]); exit; }

$rol      = strtoupper($_SESSION['usuario']['permisos'] ?? 'GUEST');
$idProfUS = (int)($_SESSION['usuario']['id_profesional'] ?? 0);
$params   = ["%$q%", "%$q%", "%$q%"];

$sql = "
  SELECT TOP 20
    p.Id_profesional                AS id,
    p.Rut_profesional               AS rut,
    p.Nombre_profesional            AS nombre,
    p.Apellido_profesional          AS apellido,
    e.Nombre_escuela                AS Nombre_escuela
  FROM profesionales p
  LEFT JOIN escuelas e ON e.Id_escuela = p.Id_escuela_prof
  INNER JOIN usuarios u ON u.Id_profesional = p.Id_profesional
  WHERE u.Permisos = 'PROFESIONAL'
    AND (
      p.Rut_profesional LIKE ? OR
      p.Nombre_profesional LIKE ? OR
      p.Apellido_profesional LIKE ?
    )
";

if ($rol === 'DIRECTOR' && $idProfUS > 0) {
  $esc = asig_getDirectorEscuelaId($conn, $idProfUS);
  if ($esc) {
    $sql .= " AND p.Id_escuela_prof = ? ";
    $params[] = $esc;
  }
}

$sql .= " ORDER BY p.Apellido_profesional, p.Nombre_profesional";

$st = $conn->prepare($sql);
$st->execute($params);

echo json_encode($st->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
