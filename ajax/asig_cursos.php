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
    c.Id_curso, c.Tipo_curso, c.Grado_curso, c.seccion_curso,
    e.Nombre_escuela AS escuela
  FROM cursos c
  INNER JOIN escuelas e ON e.Id_escuela = c.Id_escuela
  WHERE (c.Tipo_curso LIKE ? OR c.Grado_curso LIKE ? OR c.seccion_curso LIKE ?)
";
if ($rol === 'DIRECTOR' && $idP > 0) {
  if ($esc = getDirectorEscuelaId($conn, $idP)) {
    $sql .= " AND c.Id_escuela = ? ";
    $params[] = $esc;
  }
}
$sql .= " ORDER BY e.Nombre_escuela, c.Tipo_curso, c.Grado_curso, c.seccion_curso";

$st = $conn->prepare($sql);
$st->execute($params);

$out = [];
while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
  $texto = trim(($row['Tipo_curso'] ?? '').' '.($row['Grado_curso'] ?? '').( ($row['seccion_curso']??'') ? ' - '.$row['seccion_curso'] : '' ));
  $meta  = $row['escuela'] ?? '';
  $out[] = ['id'=>(int)$row['Id_curso'], 'text'=>$texto, 'meta'=>$meta];
}
echo json_encode(['results'=>$out], JSON_UNESCAPED_UNICODE);
