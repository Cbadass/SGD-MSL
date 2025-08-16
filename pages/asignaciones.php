<?php
session_start();
require_once 'includes/db.php';

// Verifica login
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$rol = $_SESSION['usuario']['permisos'];
$idUsuario = $_SESSION['usuario']['Id_usuario'];
$idEscuela = $_SESSION['usuario']['Id_escuela'] ?? null; // si tu director tiene asociada escuela

// 1. Obtener lista de profesionales según rol
if ($rol === 'ADMIN') {
    $stmt = $conn->query("SELECT Id_profesional, Nombre_prof, Apellido_prof FROM profesionales ORDER BY Apellido_prof");
} else if ($rol === 'DIRECTOR') {
    $stmt = $conn->prepare("SELECT Id_profesional, Nombre_prof, Apellido_prof 
                            FROM profesionales 
                            WHERE Id_escuela_prof = ?");
    $stmt->execute([$idEscuela]);
} else {
    die("Acceso no autorizado");
}
$profesionales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Procesar asignaciones (insert/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['asignar_curso'])) {
        $idProf = $_POST['profesional'];
        $idCurso = $_POST['curso'];
        $stmt = $conn->prepare("INSERT INTO profesional_curso (Id_profesional, Id_curso) VALUES (?, ?) 
                                ON DUPLICATE KEY UPDATE Id_profesional = Id_profesional");
        $stmt->execute([$idProf, $idCurso]);
    }
    if (isset($_POST['quitar_curso'])) {
        $idProf = $_POST['profesional'];
        $idCurso = $_POST['curso'];
        $stmt = $conn->prepare("DELETE FROM profesional_curso WHERE Id_profesional = ? AND Id_curso = ?");
        $stmt->execute([$idProf, $idCurso]);
    }
    if (isset($_POST['asignar_estudiante'])) {
        $idProf = $_POST['profesional'];
        $idEst = $_POST['estudiante'];
        $stmt = $conn->prepare("INSERT INTO profesional_estudiante (Id_profesional, Id_estudiante) VALUES (?, ?)
                                ON DUPLICATE KEY UPDATE Id_profesional = Id_profesional");
        $stmt->execute([$idProf, $idEst]);
    }
    if (isset($_POST['quitar_estudiante'])) {
        $idProf = $_POST['profesional'];
        $idEst = $_POST['estudiante'];
        $stmt = $conn->prepare("DELETE FROM profesional_estudiante WHERE Id_profesional = ? AND Id_estudiante = ?");
        $stmt->execute([$idProf, $idEst]);
    }
    header("Location: asignaciones.php?profesional=" . urlencode($_POST['profesional']));
    exit;
}

// 3. Si hay un profesional seleccionado
$profesionalSel = $_GET['profesional'] ?? null;
$cursosAsignados = [];
$estudiantesAsignados = [];
if ($profesionalSel) {
    $stmt = $conn->prepare("SELECT c.Id_curso, c.Tipo_curso, c.Grado_curso
                            FROM cursos c
                            INNER JOIN profesional_curso pc ON c.Id_curso = pc.Id_curso
                            WHERE pc.Id_profesional = ?");
    $stmt->execute([$profesionalSel]);
    $cursosAsignados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("SELECT e.Id_estudiante, e.Nombre_estudiante, e.Apellido_estudiante
                            FROM estudiantes e
                            INNER JOIN profesional_estudiante pe ON e.Id_estudiante = pe.Id_estudiante
                            WHERE pe.Id_profesional = ?");
    $stmt->execute([$profesionalSel]);
    $estudiantesAsignados = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 4. Obtener listas disponibles (para selects)
$cursos = $conn->query("SELECT Id_curso, Tipo_curso, Grado_curso FROM cursos ORDER BY Grado_curso")->fetchAll(PDO::FETCH_ASSOC);
$estudiantes = $conn->query("SELECT Id_estudiante, Nombre_estudiante, Apellido_estudiante FROM estudiantes ORDER BY Apellido_estudiante")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Asignaciones</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <h1>Asignaciones</h1>

  <!-- Selección de profesional -->
  <form method="get" class="mb-4">
    <label class="form-label">Seleccionar Profesional:</label>
    <select name="profesional" class="form-select" onchange="this.form.submit()">
      <option value="">-- Seleccione --</option>
      <?php foreach ($profesionales as $p): ?>
        <option value="<?= $p['Id_profesional'] ?>" <?= ($profesionalSel == $p['Id_profesional']) ? 'selected' : '' ?>>
          <?= $p['Apellido_prof'] ?>, <?= $p['Nombre_prof'] ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if ($profesionalSel): ?>
  <div class="row">
    <!-- Asignar cursos -->
    <div class="col-md-6">
      <h3>Cursos asignados</h3>
      <form method="post" class="mb-3 d-flex">
        <input type="hidden" name="profesional" value="<?= $profesionalSel ?>">
        <select name="curso" class="form-select me-2">
          <?php foreach ($cursos as $c): ?>
            <option value="<?= $c['Id_curso'] ?>"><?= $c['Tipo_curso'] ?> <?= $c['Grado_curso'] ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary" name="asignar_curso">Asignar</button>
      </form>
      <ul class="list-group">
        <?php foreach ($cursosAsignados as $ca): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <?= $ca['Tipo_curso'] ?> <?= $ca['Grado_curso'] ?>
            <form method="post" class="m-0">
              <input type="hidden" name="profesional" value="<?= $profesionalSel ?>">
              <input type="hidden" name="curso" value="<?= $ca['Id_curso'] ?>">
              <button class="btn btn-danger btn-sm" name="quitar_curso">Quitar</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <!-- Asignar estudiantes -->
    <div class="col-md-6">
      <h3>Estudiantes asignados</h3>
      <form method="post" class="mb-3 d-flex">
        <input type="hidden" name="profesional" value="<?= $profesionalSel ?>">
        <select name="estudiante" class="form-select me-2">
          <?php foreach ($estudiantes as $e): ?>
            <option value="<?= $e['Id_estudiante'] ?>"><?= $e['Apellido_estudiante'] ?>, <?= $e['Nombre_estudiante'] ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary" name="asignar_estudiante">Asignar</button>
      </form>
      <ul class="list-group">
        <?php foreach ($estudiantesAsignados as $ea): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <?= $ea['Apellido_estudiante'] ?>, <?= $ea['Nombre_estudiante'] ?>
            <form method="post" class="m-0">
              <input type="hidden" name="profesional" value="<?= $profesionalSel ?>">
              <input type="hidden" name="estudiante" value="<?= $ea['Id_estudiante'] ?>">
              <button class="btn btn-danger btn-sm" name="quitar_estudiante">Quitar</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <?php endif; ?>
</body>
</html>
