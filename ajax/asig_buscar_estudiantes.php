<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

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

$params = ["%$q%", "%$q%", "%$q%"];
$sql = "
  SELECT TOP 20
    s.Id_estudiante                 AS id,
    s.Rut_estudiante                AS rut,
    s.Nombre_estudiante             AS nombre,
    s.Apellido_estudiante           AS apellido,
    c.Tipo_curso,
    c.Grado_curso,
    c.seccion_curso,
    e.Nombre_escuela                AS Nombre_escuela
  FROM estudiantes s
  INNER JOIN cursos c   ON c.Id_curso   = s.Id_curso
  INNER JOIN escuelas e ON e.Id_escuela = c.Id_escuela
  WHERE (
    s.Rut_estudiante LIKE ? OR
    s.Nombre_estudiante LIKE ? OR
    s.Apellido_estudiante LIKE ?
  )
";

if ($rol === 'DIRECTOR' && $idProfUS > 0) {
  $esc = asig_getDirectorEscuelaId($conn, $idProfUS);
  if ($esc) {
    $sql .= " AND c.Id_escuela = ? ";
    $params[] = $esc;
  }
}

$sql .= " ORDER BY s.Apellido_estudiante, s.Nombre_estudiante";

$st = $conn->prepare($sql);
$st->execute($params);

echo json_encode($st->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
