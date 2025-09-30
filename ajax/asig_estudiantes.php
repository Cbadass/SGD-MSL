<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$rol  = strtoupper($_SESSION['usuario']['permisos'] ?? 'GUEST');
$idP  = (int)($_SESSION['usuario']['id_profesional'] ?? 0);
$q    = trim((string)($_GET['q'] ?? ''));
$max  = 20;

if ($q === '') { echo json_encode(['results'=>[]]); exit; }

function getDirectorEscuelaId(PDO $conn, int $idProfesional): ?int {
  $st = $conn->prepare("SELECT Id_escuela_prof FROM profesionales WHERE Id_profesional = ?");
  $st->execute([$idProfesional]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  return $r && $r['Id_escuela_prof'] ? (int)$r['Id_escuela_prof'] : null;
}

$params = [ "%$q%", "%$q%", "%$q%" ];
$sql = "
  SELECT TOP {$max}
    s.Id_estudiante,
    s.Rut_estudiante,
    s.Nombre_estudiante, s.Apellido_estudiante,
    e.Nombre_escuela AS escuela
  FROM estudiantes s
  LEFT JOIN escuelas e ON e.Id_escuela = s.Id_escuela
  WHERE (s.Rut_estudiante LIKE ? OR s.Nombre_estudiante LIKE ? OR s.Apellido_estudiante LIKE ?)
";
if ($rol === 'DIRECTOR' && $idP > 0) {
  if ($esc = getDirectorEscuelaId($conn, $idP)) {
    $sql .= " AND s.Id_escuela = ? ";
    $params[] = $esc;
  }
}
$sql .= " ORDER BY s.Apellido_estudiante, s.Nombre_estudiante";

$st = $conn->prepare($sql);
$st->execute($params);

$out = [];
while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
  $nombre = trim(($row['Nombre_estudiante'] ?? '').' '.($row['Apellido_estudiante'] ?? ''));
  $meta   = ($row['Rut_estudiante'] ?? '') . ' · ' . ($row['escuela'] ?? '');
  $out[]  = ['id'=>(int)$row['Id_estudiante'], 'text'=>$nombre, 'meta'=>$meta];
}
echo json_encode(['results'=>$out], JSON_UNESCAPED_UNICODE);
