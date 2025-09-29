<?php
// pages/asignaciones.php
declare(strict_types=1);

// --- Sesión mínima (tu patrón) ---
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['usuario'])) {
  header('Location: ../login.php'); exit;
}

// --- CSRF local (manteniendo tu enfoque original) ---
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (!function_exists('check_csrf')) {
  function check_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token    = $_POST['csrf_token'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!$token || !$expected || !hash_equals((string)$expected, (string)$token)) {
      http_response_code(400);
      die('Solicitud inválida (CSRF).');
    }
  }
}
if (!function_exists('csrf_field')) {
  function csrf_field(): string {
    $t = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="'.$t.'">';
  }
}

// --- Includes reales de tu proyecto ---
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/roles.php';
require_once __DIR__ . '/../includes/auditoria.php';

// --- Guard por rol ---
$rolActual = strtoupper($_SESSION['usuario']['permisos'] ?? 'GUEST');
if (!in_array($rolActual, ['ADMIN','DIRECTOR'], true)) {
  http_response_code(403);
  echo '<div class="card p-3"><h3>403 – Acceso denegado</h3></div>'; exit;
}

// --- Helpers alcance DIRECTOR ---
function getDirectorEscuelaId(PDO $conn, int $idProfesional): ?int {
  $st = $conn->prepare("SELECT Id_escuela_prof FROM dbo.profesionales WHERE Id_profesional = ?");
  $st->execute([$idProfesional]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row && $row['Id_escuela_prof'] ? (int)$row['Id_escuela_prof'] : null;
}

$usuarioId         = (int)($_SESSION['usuario']['id'] ?? 0);
$idProfesionalUser = isset($_SESSION['usuario']['id_profesional']) ? (int)$_SESSION['usuario']['id_profesional'] : null;
$escuelaDirectorId = ($rolActual === 'DIRECTOR' && $idProfesionalUser) ? getDirectorEscuelaId($conn, $idProfesionalUser) : null;

// --- Diagnóstico opcional en UI ---
$DIAG = (isset($_GET['diag']) && $_GET['diag'] == '1');
$diag_msgs = [];

// Verificar existencia de tabla Asignaciones (evita pantalla en blanco)
$tableExists = false;
try {
  $chk = $conn->query("SELECT OBJECT_ID('dbo.Asignaciones') AS oid");
  $tableExists = (bool)$chk->fetchColumn();
} catch (Throwable $e) {
  $diag_msgs[] = 'Error consultando OBJECT_ID: '.$e->getMessage();
}

$flash = null;

// =====================
// Cargar combos (siempre deben renderizar, no usan Asignaciones)
// =====================
try {
  // Profesionales (solo PROFESIONAL como destino)
  $sql = "
    SELECT p.Id_profesional,
           CONCAT(p.Nombre_prof, ' ', p.Apellido_prof) AS nombre,
           e.Nombre_escuela AS escuela
    FROM dbo.profesionales p
    LEFT JOIN dbo.escuelas e ON e.Id_escuela = p.Id_escuela_prof
    INNER JOIN dbo.usuarios u ON u.Id_profesional = p.Id_profesional
    WHERE u.Permisos = 'PROFESIONAL'
  ";
  $params = [];
  if ($escuelaDirectorId) { $sql .= " AND p.Id_escuela_prof = ? "; $params[] = $escuelaDirectorId; }
  $sql .= " ORDER BY nombre";
  $st = $conn->prepare($sql); $st->execute($params);
  $profesionales = $st->fetchAll(PDO::FETCH_ASSOC);

  // Cursos
  $sql = "
    SELECT c.Id_curso,
           CONCAT(c.Tipo_curso, ' ', c.Grado_curso, COALESCE(CONCAT(' - ', c.seccion_curso), '')) AS curso,
           e.Nombre_escuela AS escuela
    FROM dbo.cursos c
    INNER JOIN dbo.escuelas e ON e.Id_escuela = c.Id_escuela
  ";
  $params = [];
  if ($escuelaDirectorId) { $sql .= " WHERE c.Id_escuela = ? "; $params[] = $escuelaDirectorId; }
  $sql .= " ORDER BY e.Nombre_escuela, curso";
  $st = $conn->prepare($sql); $st->execute($params);
  $cursos = $st->fetchAll(PDO::FETCH_ASSOC);

  // Estudiantes
  $sql = "
    SELECT s.Id_estudiante,
           CONCAT(s.Nombre_estudiante, ' ', s.Apellido_estudiante) AS nombre,
           e.Nombre_escuela AS escuela
    FROM dbo.estudiantes s
    INNER JOIN dbo.cursos c ON c.Id_curso = s.Id_curso
    INNER JOIN dbo.escuelas e ON e.Id_escuela = c.Id_escuela
  ";
  $params = [];
  if ($escuelaDirectorId) { $sql .= " WHERE e.Id_escuela = ? "; $params[] = $escuelaDirectorId; }
  $sql .= " ORDER BY e.Nombre_escuela, nombre";
  $st = $conn->prepare($sql); $st->execute($params);
  $estudiantes = $st->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
  $flash = ['tipo'=>'error','msg'=>'Error cargando listas: '.htmlspecialchars($e->getMessage())];
  $profesionales = $cursos = $estudiantes = [];
}

// =====================
// Acciones POST (requieren tabla Asignaciones)
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  if (!$tableExists) {
    $flash = ['tipo'=>'error','msg'=>'La tabla dbo.Asignaciones no existe. Crea la tabla antes de asignar.'];
  } else {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
      $idProfesional = (int)($_POST['Id_profesional'] ?? 0);
      $tipo          = (string)($_POST['tipo'] ?? '');
      $idEstudiante  = isset($_POST['Id_estudiante']) ? (int)$_POST['Id_estudiante'] : null;
      $idCurso       = isset($_POST['Id_curso']) ? (int)$_POST['Id_curso'] : null;

      if ($idProfesional <= 0 || !in_array($tipo, ['ESTUDIANTE','CURSO'], true)) {
        $flash = ['tipo'=>'error','msg'=>'Datos inválidos.'];
      } else {
        try {
          // Alcance del DIRECTOR
          if ($escuelaDirectorId) {
            $q = $conn->prepare("SELECT COUNT(1) FROM dbo.profesionales WHERE Id_profesional = ? AND Id_escuela_prof = ?");
            $q->execute([$idProfesional, $escuelaDirectorId]);
            if (!$q->fetchColumn()) { throw new RuntimeException('No puedes asignar profesionales fuera de tu escuela.'); }

            if ($tipo === 'ESTUDIANTE') {
              $q = $conn->prepare("
                SELECT COUNT(1)
                FROM dbo.estudiantes s
                INNER JOIN dbo.cursos c ON c.Id_curso = s.Id_curso
                WHERE s.Id_estudiante = ? AND c.Id_escuela = ?
              ");
              $q->execute([$idEstudiante, $escuelaDirectorId]);
              if (!$q->fetchColumn()) { throw new RuntimeException('El estudiante no pertenece a tu escuela.'); }
            } else {
              $q = $conn->prepare("SELECT COUNT(1) FROM dbo.cursos WHERE Id_curso = ? AND Id_escuela = ?");
              $q->execute([$idCurso, $escuelaDirectorId]);
              if (!$q->fetchColumn()) { throw new RuntimeException('El curso no pertenece a tu escuela.'); }
            }
          }

          $conn->beginTransaction();

          if ($tipo === 'ESTUDIANTE') {
            $ins = $conn->prepare("INSERT INTO dbo.Asignaciones (Id_profesional, Id_estudiante) VALUES (?, ?)");
            try {
              $ins->execute([$idProfesional, $idEstudiante]);
              registrarAuditoria($conn, $usuarioId, 'Asignaciones', null, 'INSERT', null,
                ['Id_profesional'=>$idProfesional,'Id_estudiante'=>$idEstudiante]);
            } catch (PDOException $e) {
              $code = (int)($e->errorInfo[1] ?? 0);
              if ($code !== 2627) throw $e; // unique
            }
            $conn->commit();
            $flash = ['tipo'=>'ok','msg'=>'Asignación creada (estudiante).'];

          } else { // CURSO
            $insC = $conn->prepare("INSERT INTO dbo.Asignaciones (Id_profesional, Id_curso) VALUES (?, ?)");
            try {
              $insC->execute([$idProfesional, $idCurso]);
              registrarAuditoria($conn, $usuarioId, 'Asignaciones', null, 'INSERT', null,
                ['Id_profesional'=>$idProfesional,'Id_curso'=>$idCurso]);
            } catch (PDOException $e) {
              $code = (int)($e->errorInfo[1] ?? 0);
              if ($code !== 2627) throw $e;
            }

            $q = $conn->prepare("SELECT Id_estudiante FROM dbo.estudiantes WHERE Id_curso = ?");
            $q->execute([$idCurso]);
            $alumnos = $q->fetchAll(PDO::FETCH_COLUMN);

            $insE = $conn->prepare("INSERT INTO dbo.Asignaciones (Id_profesional, Id_estudiante) VALUES (?, ?)");
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
            $flash = ['tipo'=>'ok','msg'=>"Asignación por curso completada. Alumnos asignados: {$creados}."];
          }

        } catch (Throwable $ex) {
          if ($conn->inTransaction()) $conn->rollBack();
          $flash = ['tipo'=>'error','msg'=>'No se pudo crear la asignación: '.htmlspecialchars($ex->getMessage())];
        }
      }
    }

    if ($accion === 'eliminar') {
      $idAsignacion = (int)($_POST['Id_asignacion'] ?? 0);
      if ($idAsignacion > 0) {
        try {
          if ($escuelaDirectorId) {
            $st = $conn->prepare("
              SELECT TOP 1 a.Id_asignacion
              FROM dbo.Asignaciones a
              LEFT JOIN dbo.profesionales p ON p.Id_profesional = a.Id_profesional
              LEFT JOIN dbo.estudiantes s ON s.Id_estudiante = a.Id_estudiante
              LEFT JOIN dbo.cursos c ON c.Id_curso = COALESCE(s.Id_curso, a.Id_curso)
              WHERE a.Id_asignacion = ? AND (p.Id_escuela_prof = ? OR c.Id_escuela = ?)
            ");
            $st->execute([$idAsignacion, $escuelaDirectorId, $escuelaDirectorId]);
            if (!$st->fetchColumn()) { throw new RuntimeException('No puedes eliminar asignaciones fuera de tu escuela.'); }
          }

          $st = $conn->prepare("SELECT * FROM dbo.Asignaciones WHERE Id_asignacion = ?");
          $st->execute([$idAsignacion]);
          $antes = $st->fetch(PDO::FETCH_ASSOC);

          $del = $conn->prepare("DELETE FROM dbo.Asignaciones WHERE Id_asignacion = ?");
          $del->execute([$idAsignacion]);

          registrarAuditoria($conn, $usuarioId, 'Asignaciones', $idAsignacion, 'DELETE', $antes ?: null, null);

          $flash = ['tipo'=>'ok','msg'=>'Asignación eliminada.'];
        } catch (Throwable $ex) {
          $flash = ['tipo'=>'error','msg'=>'No se pudo eliminar: '.htmlspecialchars($ex->getMessage())];
        }
      }
    }
  }
}

// =====================
// Listado (si la tabla existe) con manejo de errores visible
// =====================
$asignaciones = [];
$errListado = null;

$filtroTipo = $_GET['tipo'] ?? 'TODOS';
$where = "1=1";
$params = [];

if ($filtroTipo === 'ESTUDIANTE') { $where .= " AND a.Id_estudiante IS NOT NULL "; }
elseif ($filtroTipo === 'CURSO')  { $where .= " AND a.Id_curso IS NOT NULL "; }

if ($escuelaDirectorId) {
  $where .= " AND ( p.Id_escuela_prof = ? OR EXISTS (
              SELECT 1 FROM dbo.cursos cx
              WHERE cx.Id_curso = COALESCE(s.Id_curso, a.Id_curso)
                AND cx.Id_escuela = ? ) )";
  $params[] = $escuelaDirectorId;
  $params[] = $escuelaDirectorId;
}

if ($tableExists) {
  try {
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
      FROM dbo.Asignaciones a
      INNER JOIN dbo.profesionales p ON p.Id_profesional = a.Id_profesional
      LEFT JOIN dbo.escuelas eprof    ON eprof.Id_escuela = p.Id_escuela_prof
      LEFT JOIN dbo.estudiantes se    ON se.Id_estudiante = a.Id_estudiante
      LEFT JOIN dbo.cursos c          ON c.Id_curso = a.Id_curso
      LEFT JOIN dbo.cursos cse        ON cse.Id_curso = se.Id_curso
      LEFT JOIN dbo.escuelas sc       ON sc.Id_escuela = COALESCE(c.Id_escuela, cse.Id_escuela)
      WHERE {$where}
      ORDER BY a.Fecha_asignacion DESC, a.Id_asignacion DESC
    ";
    $st = $conn->prepare($sqlListado);
    $st->execute($params);
    $asignaciones = $st->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    $code = (int)($e->errorInfo[1] ?? 0);
    if ($code === 208) { // Invalid object name
      $errListado = 'La tabla <b>dbo.Asignaciones</b> no existe. Ejecuta el script de creación en Azure SQL.';
    } else {
      $errListado = 'No se pudo cargar el listado: '.htmlspecialchars($e->getMessage());
    }
  }
} else {
  $errListado = 'La tabla <b>dbo.Asignaciones</b> no existe (OBJECT_ID devuelve NULL).';
}

// =====================
// Render
// =====================
?>
<div class="content">
  <h2>Asignaciones</h2>

  <?php if ($DIAG): ?>
    <div class="card p-3" style="border:1px dashed #c33; background:#fff6f6; color:#900">
      <b>DIAGNÓSTICO</b><br>
      Rol: <?= htmlspecialchars($rolActual) ?><br>
      Escuela (si Director): <?= $escuelaDirectorId ? (int)$escuelaDirectorId : 'N/A' ?><br>
      Tabla dbo.Asignaciones: <?= $tableExists ? 'OK' : 'NO EXISTE' ?><br>
      <?php if ($diag_msgs) { echo 'Notas: <ul>'; foreach ($diag_msgs as $m) echo '<li>'.htmlspecialchars($m).'</li>'; echo '</ul>'; } ?>
      <small>Quita <code>&diag=1</code> de la URL para ocultar este bloque.</small>
    </div>
  <?php endif; ?>

  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['tipo'] === 'ok' ? 'success' : 'danger' ?>">
      <?= $flash['msg'] ?>
    </div>
  <?php endif; ?>

  <?php if (!$tableExists): ?>
    <div class="alert alert-danger">
      La tabla <b>dbo.Asignaciones</b> no existe. Crea la tabla antes de usar esta sección.
    </div>
  <?php endif; ?>

  <div class="card p-3 mb-3">
    <h3 class="mb-2">Nueva asignación</h3>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
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
        <button class="btn btn-primary" <?= !$tableExists ? 'disabled title="Crea la tabla dbo.Asignaciones primero"' : '' ?>>Asignar</button>
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
      <?php if ($DIAG): ?><input type="hidden" name="diag" value="1"><?php endif; ?>
    </form>

    <?php if ($errListado): ?>
      <div class="alert alert-danger"><?= $errListado ?></div>
    <?php endif; ?>

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
            <?php $tipo = $a['Id_estudiante'] ? 'ESTUDIANTE' : 'CURSO';
                  $dest = $a['Id_estudiante'] ? $a['estudiante'] : $a['curso']; ?>
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
                  <?= csrf_field() ?>
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="Id_asignacion" value="<?= (int)$a['Id_asignacion'] ?>">
                  <button class="btn btn-sm btn-danger">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($asignaciones) && $tableExists && !$errListado): ?>
            <tr><td colspan="8" class="text-center text-muted">Sin asignaciones.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<style>
.badge { display:inline-block; padding:.25rem .5rem; border-radius:999px; font-size:.75rem; background:#ecebfd; }
.dark-mode .badge { background:#2f2a4a; }
</style>
