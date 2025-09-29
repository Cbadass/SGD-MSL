<?php
// pages/asignaciones.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';     // asegura sesión iniciada + $ _SESSION['usuario']
require_once __DIR__ . '/../includes/db.php';          // $conn (PDO a Azure SQL)
require_once __DIR__ . '/../includes/roles.php';       // requireAnyRole, hasAnyRole, etc.
require_once __DIR__ . '/../includes/auditoria.php';   // registrarAuditoria($conn, $idUsuario, ...)

// -----------------------------
// AUTORIZACIÓN
// -----------------------------
requireAnyRole(['ADMIN', 'DIRECTOR']);

$usuarioId         = (int)($_SESSION['usuario']['id'] ?? 0);
$rolActual         = strtoupper((string)($_SESSION['usuario']['permisos'] ?? ''));
$idProfesionalUser = isset($_SESSION['usuario']['id_profesional']) ? (int)$_SESSION['usuario']['id_profesional'] : null;

// -----------------------------
// CSRF
// -----------------------------
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function check_csrf(): void {
  if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(400);
    die('Solicitud inválida (CSRF).');
  }
}

// -----------------------------
// HELPERS JSON SEGURO
// -----------------------------
function json_safe($value): string {
  return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
function utf8ize($mixed) {
  if (is_array($mixed)) {
    foreach ($mixed as $k => $v) $mixed[$k] = utf8ize($v);
    return $mixed;
  }
  if (is_string($mixed)) {
    return mb_convert_encoding($mixed, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
  }
  return $mixed;
}

// -----------------------------
// ALCANCE POR ESCUELA (DIRECTOR)
// -----------------------------
function getDirectorEscuelaId(PDO $conn, int $idProfesional): ?int {
  $st = $conn->prepare("SELECT Id_escuela_prof FROM profesionales WHERE Id_profesional = ?");
  $st->execute([$idProfesional]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) return null;
  return isset($row['Id_escuela_prof']) ? (int)$row['Id_escuela_prof'] : null;
}

$escuelaDirectorId = null;
if ($rolActual === 'DIRECTOR' && $idProfesionalUser) {
  $escuelaDirectorId = getDirectorEscuelaId($conn, $idProfesionalUser);
}

// -----------------------------
// DATOS PARA FORMULARIO (filtrados si DIRECTOR)
// -----------------------------
$params = [];
$sqlProfesionales = "
  SELECT p.Id_profesional,
         CONCAT(p.Nombre_prof, ' ', p.Apellido_prof) AS nombre,
         e.Nombre_escuela AS escuela
  FROM profesionales p
  LEFT JOIN escuelas e ON e.Id_escuela = p.Id_escuela_prof
  INNER JOIN usuarios u ON u.Id_profesional = p.Id_profesional
  WHERE u.Permisos = 'PROFESIONAL'
";
if ($escuelaDirectorId) {
  $sqlProfesionales .= " AND p.Id_escuela_prof = ? ";
  $params[] = $escuelaDirectorId;
}
$sqlProfesionales .= " ORDER BY nombre";

$st = $conn->prepare($sqlProfesionales);
$st->execute($params);
$profesionales = $st->fetchAll(PDO::FETCH_ASSOC);

// Cursos
$params = [];
$sqlCursos = "
  SELECT c.Id_curso,
         CONCAT(c.Tipo_curso, ' ', c.Grado_curso, COALESCE(CONCAT(' - ', c.seccion_curso), '')) AS curso,
         e.Nombre_escuela AS escuela
  FROM cursos c
  INNER JOIN escuelas e ON e.Id_escuela = c.Id_escuela
";
if ($escuelaDirectorId) {
  $sqlCursos .= " WHERE c.Id_escuela = ? ";
  $params[] = $escuelaDirectorId;
}
$sqlCursos .= " ORDER BY e.Nombre_escuela, curso";

$st = $conn->prepare($sqlCursos);
$st->execute($params);
$cursos = $st->fetchAll(PDO::FETCH_ASSOC);

// Estudiantes
$params = [];
$sqlEst = "
  SELECT s.Id_estudiante,
         CONCAT(s.Nombre_estudiante, ' ', s.Apellido_estudiante) AS nombre,
         e.Nombre_escuela AS escuela
  FROM estudiantes s
  INNER JOIN cursos c ON c.Id_curso = s.Id_curso
  INNER JOIN escuelas e ON e.Id_escuela = c.Id_escuela
";
if ($escuelaDirectorId) {
  $sqlEst .= " WHERE e.Id_escuela = ? ";
  $params[] = $escuelaDirectorId;
}
$sqlEst .= " ORDER BY e.Nombre_escuela, nombre";

$st = $conn->prepare($sqlEst);
$st->execute($params);
$estudiantes = $st->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------
// ACCIONES (CREAR / ELIMINAR)
// -----------------------------
$flash = null;

// Crear asignación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear') {
  check_csrf();

  $idProfesional = (int)($_POST['Id_profesional'] ?? 0);
  $tipo          = (string)($_POST['tipo'] ?? '');   // 'ESTUDIANTE' | 'CURSO'
  $idEstudiante  = isset($_POST['Id_estudiante']) ? (int)$_POST['Id_estudiante'] : null;
  $idCurso       = isset($_POST['Id_curso']) ? (int)$_POST['Id_curso'] : null;

  if ($idProfesional <= 0 || !in_array($tipo, ['ESTUDIANTE','CURSO'], true)) {
    $flash = ['tipo' => 'error', 'msg' => 'Datos inválidos.'];
  } else {
    try {
      // Si es DIRECTOR, validamos que todo sea de su escuela
      if ($escuelaDirectorId) {
        // Profesional destino
        $q = $conn->prepare("SELECT COUNT(1) FROM profesionales WHERE Id_profesional = ? AND Id_escuela_prof = ?");
        $q->execute([$idProfesional, $escuelaDirectorId]);
        if (!$q->fetchColumn()) {
          throw new RuntimeException('No puedes asignar profesionales fuera de tu escuela.');
        }
        if ($tipo === 'ESTUDIANTE') {
          $q = $conn->prepare("
            SELECT COUNT(1)
            FROM estudiantes s
            INNER JOIN cursos c ON c.Id_curso = s.Id_curso
            WHERE s.Id_estudiante = ? AND c.Id_escuela = ?
          ");
          $q->execute([$idEstudiante, $escuelaDirectorId]);
          if (!$q->fetchColumn()) {
            throw new RuntimeException('El estudiante no pertenece a tu escuela.');
          }
        } else { // CURSO
          $q = $conn->prepare("SELECT COUNT(1) FROM cursos WHERE Id_curso = ? AND Id_escuela = ?");
          $q->execute([$idCurso, $escuelaDirectorId]);
          if (!$q->fetchColumn()) {
            throw new RuntimeException('El curso no pertenece a tu escuela.');
          }
        }
      }

      $conn->beginTransaction();

      if ($tipo === 'ESTUDIANTE') {
        // Inserta asignación individual (evita duplicados por UNIQUE)
        $ins = $conn->prepare("INSERT INTO Asignaciones (Id_profesional, Id_estudiante) VALUES (?, ?)");
        try {
          $ins->execute([$idProfesional, $idEstudiante]);
          registrarAuditoria(
            $conn, $usuarioId, 'Asignaciones', null, 'INSERT',
            null,
            ['Id_profesional' => $idProfesional, 'Id_estudiante' => $idEstudiante]
          );
          
        } catch (PDOException $e) {
          // 2627 = violación de UNIQUE en SQL Server
          $sqlState = $e->errorInfo[0] ?? null;
          $code     = (int)($e->errorInfo[1] ?? 0);
          if ($code !== 2627) throw $e;
        }
        $conn->commit();
        $flash = ['tipo' => 'ok', 'msg' => 'Asignación creada (estudiante).'];

      } else {
        // 1) Inserta asignación por curso (trazabilidad)
        $insC = $conn->prepare("INSERT INTO Asignaciones (Id_profesional, Id_curso) VALUES (?, ?)");
        try {
          $insC->execute([$idProfesional, $idCurso]);
          registrarAuditoria(
            $conn, $usuarioId, 'Asignaciones', null, 'INSERT',
            null,
            ['Id_profesional' => $idProfesional, 'Id_curso' => $idCurso]
          );
          
        } catch (PDOException $e) {
          $code = (int)($e->errorInfo[1] ?? 0);
          if ($code !== 2627) throw $e;
        }

        // 2) Traer estudiantes del curso
        $q = $conn->prepare("SELECT Id_estudiante FROM estudiantes WHERE Id_curso = ?");
        $q->execute([$idCurso]);
        $alumnos = $q->fetchAll(PDO::FETCH_COLUMN);

        // 3) Insertar asignaciones por estudiante (evitando duplicados)
        $insE = $conn->prepare("INSERT INTO Asignaciones (Id_profesional, Id_estudiante) VALUES (?, ?)");
        $creados = 0;
        foreach ($alumnos as $idEst) {
          try {
            $insE->execute([$idProfesional, (int)$idEst]);
            $creados++;
          } catch (PDOException $e) {
            $code = (int)($e->errorInfo[1] ?? 0);
            if ($code !== 2627) throw $e;
          }
        }

        $conn->commit();
        $flash = ['tipo' => 'ok', 'msg' => "Asignación por curso completada. Alumnos asignados: {$creados}."];
      }

    } catch (Throwable $ex) {
      if ($conn->inTransaction()) $conn->rollBack();
      $flash = ['tipo' => 'error', 'msg' => 'No se pudo crear la asignación: ' . htmlspecialchars($ex->getMessage())];
    }
  }
}

// Eliminar asignación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
  check_csrf();
  $idAsignacion = (int)($_POST['Id_asignacion'] ?? 0);

  if ($idAsignacion > 0) {
    try {
      // Si es DIRECTOR, validamos que la asignación esté en su escuela
      if ($escuelaDirectorId) {
        $st = $conn->prepare("
          SELECT TOP 1 a.Id_asignacion
          FROM Asignaciones a
          LEFT JOIN profesionales p ON p.Id_profesional = a.Id_profesional
          LEFT JOIN estudiantes s ON s.Id_estudiante = a.Id_estudiante
          LEFT JOIN cursos c ON c.Id_curso = COALESCE(s.Id_curso, a.Id_curso)
          WHERE a.Id_asignacion = ?
            AND (
              p.Id_escuela_prof = ? OR c.Id_escuela = ?
            )
        ");
        $st->execute([$idAsignacion, $escuelaDirectorId, $escuelaDirectorId]);
        if (!$st->fetchColumn()) {
          throw new RuntimeException('No puedes eliminar asignaciones fuera de tu escuela.');
        }
      }

      // Snapshot previo (para auditoría)
      $st = $conn->prepare("SELECT * FROM Asignaciones WHERE Id_asignacion = ?");
      $st->execute([$idAsignacion]);
      $antes = $st->fetch(PDO::FETCH_ASSOC);

      $del = $conn->prepare("DELETE FROM Asignaciones WHERE Id_asignacion = ?");
      $del->execute([$idAsignacion]);

      registrarAuditoria(
        $conn, $usuarioId, 'Asignaciones', $idAsignacion, 'DELETE',
        $antes ?: null,
        null
      );
      

      $flash = ['tipo' => 'ok', 'msg' => 'Asignación eliminada.'];

    } catch (Throwable $ex) {
      $flash = ['tipo' => 'error', 'msg' => 'No se pudo eliminar: ' . htmlspecialchars($ex->getMessage())];
    }
  }
}

// -----------------------------
// LISTADO + FILTROS
// -----------------------------
$filtroTipo = (string)($_GET['tipo'] ?? 'TODOS'); // TODOS | ESTUDIANTE | CURSO
$where      = "1=1";
$params     = [];

if ($filtroTipo === 'ESTUDIANTE') {
  $where .= " AND a.Id_estudiante IS NOT NULL ";
} elseif ($filtroTipo === 'CURSO') {
  $where .= " AND a.Id_curso IS NOT NULL ";
}

// Alcance por escuela si DIRECTOR
if ($escuelaDirectorId) {
  $where .= " AND (
    p.Id_escuela_prof = ? 
    OR EXISTS (
      SELECT 1
      FROM cursos cx
      WHERE cx.Id_curso = COALESCE(s.Id_curso, a.Id_curso)
        AND cx.Id_escuela = ?
    )
  )";
  $params[] = $escuelaDirectorId;
  $params[] = $escuelaDirectorId;
}

$sqlListado = "
  SELECT
    a.Id_asignacion, a.Fecha_asignacion,
    a.Id_estudiante, a.Id_curso,
    CONCAT(p.Nombre_prof, ' ', p.Apellido_prof) AS profesional,
    eprof.Nombre_escuela AS escuela_prof,
    CASE WHEN a.Id_estudiante IS NOT NULL
      THEN CONCAT(se.Nombre_estudiante, ' ', se.Apellido_estudiante)
      ELSE NULL END AS estudiante,
    sc.Nombre_escuela AS escuela_est,
    CASE WHEN a.Id_curso IS NOT NULL
      THEN CONCAT(c.Tipo_curso, ' ', c.Grado_curso, COALESCE(CONCAT(' - ', c.seccion_curso), ''))
      ELSE NULL END AS curso
  FROM Asignaciones a
  INNER JOIN profesionales p ON p.Id_profesional = a.Id_profesional
  LEFT JOIN escuelas eprof    ON eprof.Id_escuela = p.Id_escuela_prof
  LEFT JOIN estudiantes se    ON se.Id_estudiante = a.Id_estudiante
  LEFT JOIN cursos c          ON c.Id_curso = a.Id_curso
  LEFT JOIN cursos cse        ON cse.Id_curso = se.Id_curso
  LEFT JOIN escuelas sc       ON sc.Id_escuela = COALESCE(c.Id_escuela, cse.Id_escuela)
  WHERE {$where}
  ORDER BY a.Fecha_asignacion DESC, a.Id_asignacion DESC
";
$st = $conn->prepare($sqlListado);
$st->execute($params);
$asignaciones = $st->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------
// RENDER
// -----------------------------
?>
<div class="content">
  <h2>Asignaciones</h2>

  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['tipo'] === 'ok' ? 'success' : 'danger' ?>">
      <?= htmlspecialchars($flash['msg']) ?>
    </div>
  <?php endif; ?>

  <div class="card p-3 mb-3">
    <h3 class="mb-2">Nueva asignación</h3>
    <form method="post" class="form-grid">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="accion" value="crear">

      <div>
        <label>Profesional destino</label>
        <select name="Id_profesional" required>
          <option value="">-- Selecciona --</option>
          <?php foreach ($profesionales as $p): ?>
            <option value="<?= (int)$p['Id_profesional'] ?>">
              <?= htmlspecialchars($p['nombre']) ?> (<?= htmlspecialchars($p['escuela'] ?? 's/escuela') ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label>Tipo de asignación</label>
        <select name="tipo" id="tipo" required
          onchange="
            const v=this.value;
            document.getElementById('blk-est').style.display = v==='ESTUDIANTE' ? 'block':'none';
            document.getElementById('blk-curso').style.display = v==='CURSO' ? 'block':'none';
          ">
          <option value="ESTUDIANTE">Estudiante</option>
          <option value="CURSO">Curso (asigna todos sus alumnos)</option>
        </select>
      </div>

      <div id="blk-est">
        <label>Estudiante</label>
        <select name="Id_estudiante">
          <option value="">-- Selecciona --</option>
          <?php foreach ($estudiantes as $s): ?>
            <option value="<?= (int)$s['Id_estudiante'] ?>">
              <?= htmlspecialchars($s['nombre']) ?> (<?= htmlspecialchars($s['escuela'] ?? 's/escuela') ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="blk-curso" style="display:none">
        <label>Curso</label>
        <select name="Id_curso">
          <option value="">-- Selecciona --</option>
          <?php foreach ($cursos as $c): ?>
            <option value="<?= (int)$c['Id_curso'] ?>">
              <?= htmlspecialchars($c['curso']) ?> (<?= htmlspecialchars($c['escuela'] ?? 's/escuela') ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="align-self:end">
        <button class="btn btn-primary">Asignar</button>
      </div>
    </form>
    <small class="text-muted">Si eliges “Curso”, se crearán asignaciones para todos sus estudiantes (evitando duplicados).</small>
  </div>

  <div class="card p-3">
    <form class="mb-3" method="get">
      <label>Filtrar por tipo:</label>
      <select name="tipo" onchange="this.form.submit()">
        <option value="TODOS"     <?= $filtroTipo==='TODOS'?'selected':'' ?>>Todos</option>
        <option value="ESTUDIANTE"<?= $filtroTipo==='ESTUDIANTE'?'selected':'' ?>>Solo Estudiantes</option>
        <option value="CURSO"     <?= $filtroTipo==='CURSO'?'selected':'' ?>>Solo Cursos</option>
      </select>
    </form>

    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Profesional</th>
            <th>Escuela (prof.)</th>
            <th>Tipo</th>
            <th>Estudiante / Curso</th>
            <th>Escuela (destino)</th>
            <th>Fecha</th>
            <th style="width:90px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($asignaciones as $a): ?>
            <?php
              $tipo = $a['Id_estudiante'] ? 'ESTUDIANTE' : 'CURSO';
              $dest = $a['Id_estudiante'] ? $a['estudiante'] : $a['curso'];
            ?>
            <tr>
              <td><?= (int)$a['Id_asignacion'] ?></td>
              <td><?= htmlspecialchars($a['profesional'] ?? '') ?></td>
              <td><?= htmlspecialchars($a['escuela_prof'] ?? '') ?></td>
              <td><span class="badge"><?= htmlspecialchars($tipo) ?></span></td>
              <td><?= htmlspecialchars($dest ?? '') ?></td>
              <td><?= htmlspecialchars($a['escuela_est'] ?? '') ?></td>
              <td><?= htmlspecialchars((string)$a['Fecha_asignacion']) ?></td>
              <td>
                <form method="post" onsubmit="return confirm('¿Eliminar asignación?')">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="Id_asignacion" value="<?= (int)$a['Id_asignacion'] ?>">
                  <button class="btn btn-sm btn-danger">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($asignaciones)): ?>
            <tr><td colspan="8" class="text-center text-muted">Sin asignaciones.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
