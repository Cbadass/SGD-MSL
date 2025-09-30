<?php
// pages/asignaciones.php
declare(strict_types=1);

// --- Sesión (por si entran directo) ---
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['usuario'])) { header('Location: ../login.php'); exit; }

// --- Includes del proyecto ---
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/roles.php';
require_once __DIR__ . '/../includes/auditoria.php';

// -----------------------------
// CSRF local (como en tu proyecto)
// -----------------------------
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

// -----------------------------
// AUTORIZACIÓN (ADMIN o DIRECTOR)
// -----------------------------
requireAnyRole(['ADMIN','DIRECTOR']);

$usuarioId         = (int)($_SESSION['usuario']['id'] ?? 0);
$rolActual         = strtoupper((string)($_SESSION['usuario']['permisos'] ?? ''));
$idProfesionalUser = isset($_SESSION['usuario']['id_profesional']) ? (int)$_SESSION['usuario']['id_profesional'] : null;

// -----------------------------
// ALCANCE POR ESCUELA (DIRECTOR)
// -----------------------------
function asig_getDirectorEscuelaId(PDO $conn, int $idProfesional): ?int {
  $st = $conn->prepare("SELECT Id_escuela_prof FROM profesionales WHERE Id_profesional = ?");
  $st->execute([$idProfesional]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row && $row['Id_escuela_prof'] ? (int)$row['Id_escuela_prof'] : null;
}
$escuelaDirectorId = null;
if ($rolActual === 'DIRECTOR' && $idProfesionalUser) {
  $escuelaDirectorId = asig_getDirectorEscuelaId($conn, $idProfesionalUser);
}

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
    $flash = ['tipo' => 'error', 'msg' => 'Completa profesional y tipo de asignación.'];
  } elseif ($tipo === 'ESTUDIANTE' && !$idEstudiante) {
    $flash = ['tipo' => 'error', 'msg' => 'Debes seleccionar un estudiante.'];
  } elseif ($tipo === 'CURSO' && !$idCurso) {
    $flash = ['tipo' => 'error', 'msg' => 'Debes seleccionar un curso.'];
  } else {
    try {
      // Validación de alcance para DIRECTOR
      if ($escuelaDirectorId) {
        // Profesional destino
        $q = $conn->prepare("SELECT COUNT(1) FROM profesionales WHERE Id_profesional = ? AND Id_escuela_prof = ?");
        $q->execute([$idProfesional, $escuelaDirectorId]);
        if (!$q->fetchColumn()) { throw new RuntimeException('No puedes asignar profesionales fuera de tu escuela.'); }

        if ($tipo === 'ESTUDIANTE') {
          $q = $conn->prepare("
            SELECT COUNT(1)
            FROM estudiantes s
            INNER JOIN cursos c ON c.Id_curso = s.Id_curso
            WHERE s.Id_estudiante = ? AND c.Id_escuela = ?
          ");
          $q->execute([$idEstudiante, $escuelaDirectorId]);
          if (!$q->fetchColumn()) { throw new RuntimeException('El estudiante no pertenece a tu escuela.'); }
        } else { // CURSO
          $q = $conn->prepare("SELECT COUNT(1) FROM cursos WHERE Id_curso = ? AND Id_escuela = ?");
          $q->execute([$idCurso, $escuelaDirectorId]);
          if (!$q->fetchColumn()) { throw new RuntimeException('El curso no pertenece a tu escuela.'); }
        }
      }

      $conn->beginTransaction();

      if ($tipo === 'ESTUDIANTE') {
        // Asignación individual (única por UNIQUE si existe)
        $ins = $conn->prepare("INSERT INTO Asignaciones (Id_profesional, Id_estudiante) VALUES (?, ?)");
        try {
          $ins->execute([$idProfesional, $idEstudiante]);
          registrarAuditoria($conn, $usuarioId, 'Asignaciones', null, 'INSERT', null, [
            'Id_profesional' => $idProfesional,
            'Id_estudiante'  => $idEstudiante
          ]);
        } catch (PDOException $e) {
          $code = (int)($e->errorInfo[1] ?? 0);
          if ($code !== 2627) { throw $e; } // 2627 = UNIQUE en SQL Server
        }
        $conn->commit();
        $flash = ['tipo' => 'ok', 'msg' => 'Asignación creada (estudiante).'];

      } else { // CURSO
        // 1) Asignación del curso (trazabilidad)
        $insC = $conn->prepare("INSERT INTO Asignaciones (Id_profesional, Id_curso) VALUES (?, ?)");
        try {
          $insC->execute([$idProfesional, $idCurso]);
          registrarAuditoria($conn, $usuarioId, 'Asignaciones', null, 'INSERT', null, [
            'Id_profesional' => $idProfesional,
            'Id_curso'       => $idCurso
          ]);
        } catch (PDOException $e) {
          $code = (int)($e->errorInfo[1] ?? 0);
          if ($code !== 2627) { throw $e; }
        }

        // 2) Traer estudiantes del curso
        $q = $conn->prepare("SELECT Id_estudiante FROM estudiantes WHERE Id_curso = ?");
        $q->execute([$idCurso]);
        $alumnos = $q->fetchAll(PDO::FETCH_COLUMN);

        // 3) Inserciones individuales evitando duplicados
        $insE = $conn->prepare("INSERT INTO Asignaciones (Id_profesional, Id_estudiante) VALUES (?, ?)");
        $creados = 0;
        foreach ($alumnos as $idEst) {
          try {
            $insE->execute([$idProfesional, (int)$idEst]);
            $creados++;
          } catch (PDOException $e) {
            $code = (int)($e->errorInfo[1] ?? 0);
            if ($code !== 2627) { throw $e; }
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
      // Validación alcance DIRECTOR
      if ($escuelaDirectorId) {
        $st = $conn->prepare("
          SELECT TOP 1 a.Id_asignacion
          FROM Asignaciones a
          LEFT JOIN profesionales p ON p.Id_profesional = a.Id_profesional
          LEFT JOIN estudiantes s    ON s.Id_estudiante = a.Id_estudiante
          LEFT JOIN cursos c         ON c.Id_curso = COALESCE(s.Id_curso, a.Id_curso)
          WHERE a.Id_asignacion = ?
            AND (p.Id_escuela_prof = ? OR c.Id_escuela = ?)
        ");
        $st->execute([$idAsignacion, $escuelaDirectorId, $escuelaDirectorId]);
        if (!$st->fetchColumn()) { throw new RuntimeException('No puedes eliminar asignaciones fuera de tu escuela.'); }
      }

      // Snapshot previo
      $st = $conn->prepare("SELECT * FROM Asignaciones WHERE Id_asignacion = ?");
      $st->execute([$idAsignacion]);
      $antes = $st->fetch(PDO::FETCH_ASSOC);

      $del = $conn->prepare("DELETE FROM Asignaciones WHERE Id_asignacion = ?");
      $del->execute([$idAsignacion]);

      registrarAuditoria($conn, $usuarioId, 'Asignaciones', $idAsignacion, 'DELETE', $antes ?: null, null);

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

// Consulta
$sqlListado = "
  SELECT
    a.Id_asignacion, a.Fecha_asignacion,
    a.Id_estudiante, a.Id_curso,

    p.Nombre_profesional    AS pNombre,
    p.Apellido_profesional  AS pApellido,
    eprof.Nombre_escuela    AS escuela_prof,

    se.Nombre_estudiante    AS eNombre,
    se.Apellido_estudiante  AS eApellido,

    c.Tipo_curso, c.Grado_curso, c.seccion_curso,

    sc.Nombre_escuela       AS escuela_destino
  FROM Asignaciones a
  INNER JOIN profesionales p ON p.Id_profesional = a.Id_profesional
  LEFT  JOIN escuelas eprof   ON eprof.Id_escuela = p.Id_escuela_prof
  LEFT  JOIN estudiantes se   ON se.Id_estudiante = a.Id_estudiante
  LEFT  JOIN cursos c         ON c.Id_curso = a.Id_curso
  LEFT  JOIN cursos cse       ON cse.Id_curso = se.Id_curso
  LEFT  JOIN escuelas sc      ON sc.Id_escuela = COALESCE(c.Id_escuela, cse.Id_escuela)
  WHERE {$where}
  ORDER BY a.Fecha_asignacion DESC, a.Id_asignacion DESC
";

$asignaciones = [];
$errListado = null;
try {
  $st = $conn->prepare($sqlListado);
  $st->execute($params);
  $asignaciones = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $code = (int)($e->errorInfo[1] ?? 0);
  if ($code === 208) { $errListado = 'La tabla "Asignaciones" no existe.'; }
  else { $errListado = 'No se pudo cargar el listado: ' . htmlspecialchars($e->getMessage()); }
}

function nombreProf(array $r): string {
  return trim(($r['pNombre'] ?? '').' '.($r['pApellido'] ?? ''));
}
function nombreEst(array $r): string {
  return trim(($r['eNombre'] ?? '').' '.($r['eApellido'] ?? ''));
}
function textoCurso(array $r): string {
  $sec = isset($r['seccion_curso']) && $r['seccion_curso'] !== null && $r['seccion_curso'] !== '' ? ' - '.$r['seccion_curso'] : '';
  return trim(($r['Tipo_curso'] ?? '').' '.($r['Grado_curso'] ?? '').$sec);
}
?>
<div class="content">
  <h2>Asignaciones</h2>

  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['tipo'] === 'ok' ? 'success' : 'danger' ?>">
      <?= htmlspecialchars($flash['msg']) ?>
    </div>
  <?php endif; ?>

  <?php if ($errListado): ?>
    <div class="alert alert-danger"><?= $errListado ?></div>
  <?php endif; ?>

  <div class="card p-3 mb-3">
    <h3 class="mb-2">Nueva asignación</h3>
    <form method="post" class="form-grid" id="form-asignacion" onsubmit="return validarAsignacion()">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="crear">

      <!-- PROFESIONAL destino (buscador por nombre o RUT) -->
      <div>
        <label>Profesional destino</label>
        <input type="text" id="busca-prof" class="form-control" placeholder="Nombre o RUT..." autocomplete="off">
        <input type="hidden" name="Id_profesional" id="Id_profesional" required>
        <div class="typeahead" id="lista-prof"></div>
      </div>

      <!-- Tipo -->
      <div>
        <label>Tipo de asignación</label>
        <select name="tipo" id="tipo" required
          onchange="
            const v=this.value;
            document.getElementById('blk-est').style.display   = v==='ESTUDIANTE' ? 'block':'none';
            document.getElementById('blk-curso').style.display = v==='CURSO'      ? 'block':'none';
          ">
          <option value="ESTUDIANTE">Estudiante</option>
          <option value="CURSO">Curso (asigna todos sus alumnos)</option>
        </select>
      </div>

      <!-- ESTUDIANTE (buscador por nombre o RUT) -->
      <div id="blk-est">
        <label>Estudiante</label>
        <input type="text" id="busca-est" class="form-control" placeholder="Nombre o RUT..." autocomplete="off">
        <input type="hidden" name="Id_estudiante" id="Id_estudiante">
        <div class="typeahead" id="lista-est"></div>
      </div>

      <!-- CURSO (buscador por tipo/grado/sección) -->
      <div id="blk-curso" style="display:none">
        <label>Curso</label>
        <input type="text" id="busca-curso" class="form-control" placeholder="Tipo, grado o sección..." autocomplete="off">
        <input type="hidden" name="Id_curso" id="Id_curso">
        <div class="typeahead" id="lista-curso"></div>
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
              $esEst = !empty($a['Id_estudiante']);
              $tipo  = $esEst ? 'ESTUDIANTE' : 'CURSO';
              $dest  = $esEst ? nombreEst($a) : textoCurso($a);
            ?>
            <tr>
              <td><?= (int)$a['Id_asignacion'] ?></td>
              <td><?= htmlspecialchars(nombreProf($a)) ?></td>
              <td><?= htmlspecialchars($a['escuela_prof'] ?? '') ?></td>
              <td><span class="badge"><?= htmlspecialchars($tipo) ?></span></td>
              <td><?= htmlspecialchars($dest ?? '') ?></td>
              <td><?= htmlspecialchars($a['escuela_destino'] ?? '') ?></td>
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
          <?php if (empty($asignaciones)): ?>
            <tr><td colspan="8" class="text-center text-muted">Sin asignaciones.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<style>
/* Typeahead simple (igual estilo que usas) */
.typeahead { position: relative; }
.typeahead ul {
  position: absolute; z-index: 10; left: 0; right: 0;
  margin: 2px 0 0; padding: 0; list-style: none;
  background: #fff; border: 1px solid #ddd; border-radius: 6px; max-height: 260px; overflow:auto;
}
.typeahead li { padding: 8px 10px; cursor: pointer; }
.typeahead li:hover, .typeahead li.active { background: #ecebfd; }
.dark-mode .typeahead ul { background:#1f1b2e; border-color:#2f2a4a; }
.dark-mode .typeahead li:hover, .dark-mode .typeahead li.active { background:#2f2a4a; }
</style>

<script>
function debounce(fn, ms){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; }

function setupSearcher({inputId, hiddenId, listId, url, toView}) {
  const $inp = document.getElementById(inputId);
  const $hid = document.getElementById(hiddenId);
  const $box = document.getElementById(listId);

  const render = (items) => {
    if (!items || !items.length) { $box.innerHTML=''; return; }
    const ul = document.createElement('ul');
    items.forEach((it)=>{
      const li = document.createElement('li');
      li.innerHTML = toView(it); // it = {id, rut, nombre, apellido, ...}
      li.onclick = ()=>{
        // Setea texto visible e id oculto
        if (it.nombre || it.apellido) {
          $inp.value = (it.nombre||'') + ' ' + (it.apellido||'');
        } else if (it.Tipo_curso) {
          $inp.value = (it.Tipo_curso||'') + ' ' + (it.Grado_curso||'') + (it.seccion_curso?(' - '+it.seccion_curso):'');
        } else {
          $inp.value = it.id;
        }
        $hid.value = it.id;
        $box.innerHTML = '';
      };
      ul.appendChild(li);
    });
    $box.innerHTML = ''; $box.appendChild(ul);
  };

  const search = debounce(async ()=>{
    const q = $inp.value.trim();
    $hid.value = '';
    if (q.length < 3) { $box.innerHTML=''; return; }
    try {
      const res  = await fetch(url + encodeURIComponent(q), {credentials:'same-origin'});
      const data = await res.json(); // ARRAY plano (igual que Documentos)
      render(Array.isArray(data) ? data : []);
    } catch (e) {
      console.error(e);
    }
  }, 250);

  $inp.addEventListener('input', search);
  $inp.addEventListener('blur', ()=> setTimeout(()=> $box.innerHTML='', 150));
}

// Endpoints AJAX PROPIOS para no interferir con Documentos:
setupSearcher({
  inputId:'busca-prof', hiddenId:'Id_profesional', listId:'lista-prof',
  url:'ajax/asig_buscar_profesionales.php?q=',
  toView:(r)=> `<strong>${(r.nombre||'')} ${(r.apellido||'')}</strong><br><small>${(r.rut||'')} · ${(r.Nombre_escuela||'')}</small>`
});
setupSearcher({
  inputId:'busca-est', hiddenId:'Id_estudiante', listId:'lista-est',
  url:'ajax/asig_buscar_estudiantes.php?q=',
  toView:(r)=> `<strong>${(r.nombre||'')} ${(r.apellido||'')}</strong><br><small>${(r.rut||'')} · ${(r.Nombre_escuela||'')}</small>`
});
setupSearcher({
  inputId:'busca-curso', hiddenId:'Id_curso', listId:'lista-curso',
  url:'ajax/asig_buscar_cursos.php?q=',
  toView:(r)=> `<strong>${(r.Tipo_curso||'')} ${(r.Grado_curso||'')}${r.seccion_curso?(' - '+r.seccion_curso):''}</strong><br><small>${(r.Nombre_escuela||'')}</small>`
});

function validarAsignacion() {
  const tipo = document.getElementById('tipo').value;
  const idProf = document.getElementById('Id_profesional').value;
  if (!idProf) { alert('Selecciona un profesional.'); return false; }
  if (tipo === 'ESTUDIANTE') {
    if (!document.getElementById('Id_estudiante').value) { alert('Selecciona un estudiante.'); return false; }
  } else {
    if (!document.getElementById('Id_curso').value) { alert('Selecciona un curso.'); return false; }
  }
  return true;
}
</script>
