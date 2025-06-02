<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$id_documento = intval($_GET['id_documento'] ?? 0);
if ($id_documento <= 0) die("ID inválido.");

$stmt = $conn->prepare("SELECT * FROM documentos WHERE Id_documento = ?");
$stmt->execute([$id_documento]);
$doc = $stmt->fetch();

if (!$doc) die("Documento no encontrado.");

// Obtener datos de estudiante (si existe)
if (!empty($doc['Id_estudiante_doc'])) {
    $stmtEst = $conn->prepare("SELECT * FROM estudiantes WHERE Id_estudiante = ?");
    $stmtEst->execute([$doc['Id_estudiante_doc']]);
    $estudiante = $stmtEst->fetch();
} else {
    $estudiante = null;
}

// Obtener datos de profesional (si existe)
if (!empty($doc['Id_prof_doc'])) {
    $stmtProf = $conn->prepare("SELECT * FROM profesionales WHERE Id_profesional = ?");
    $stmtProf->execute([$doc['Id_prof_doc']]);
    $profesional = $stmtProf->fetch();
} else {
    $profesional = null;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Modificar Documento</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.container-form {
  display: flex;
  gap: 20px;
}
.card-usuario {
  border: 1px solid #ddd;
  padding: 10px;
  border-radius: 8px;
}
</style>
</head>
<body class="container mt-4">

<h2 class="mb-4">Modificar Documento</h2>

<form method="POST" action="/guardar_modificacion_documento.php" enctype="multipart/form-data">
  <input type="hidden" name="id_documento" value="<?= $doc['Id_documento'] ?>">

  <div class="container-form">
    <!-- Datos del documento -->
    <div class="flex-fill">
      <div class="mb-3">
        <label for="nombre" class="form-label">Nombre</label>
        <input type="text" class="form-control" name="nombre" id="nombre" value="<?= htmlspecialchars($doc['Nombre_documento']) ?>" required>
      </div>

      <div class="mb-3">
        <label for="tipo" class="form-label">Tipo de documento</label>
        <input type="text" class="form-control" name="tipo_documento" id="tipo" value="<?= htmlspecialchars($doc['Tipo_documento']) ?>">
      </div>

      <div class="mb-3">
        <label for="descripcion" class="form-label">Descripción</label>
        <textarea class="form-control" name="descripcion" id="descripcion"><?= htmlspecialchars($doc['Descripcion']) ?></textarea>
      </div>

      <div class="mb-3">
        <label for="archivo" class="form-label">Actualizar archivo (opcional)</label>
        <input type="file" class="form-control" name="archivo" id="archivo">
      </div>
    </div>

    <!-- Estudiante y Profesional -->
    <div class="flex-fill">
      <h5>Estudiante asociado</h5>
      <div class="card-usuario">
        <p><strong>RUT:</strong> <?= htmlspecialchars($estudiante['Rut_estudiante'] ?? '-') ?></p>
        <p><strong>Nombre:</strong> <?= htmlspecialchars($estudiante['Nombre_estudiante'] ?? '-') ?></p>
        <p><strong>Curso:</strong> <?= htmlspecialchars($estudiante['Id_curso'] ?? '-') ?></p>
      </div>

      <h5 class="mt-3">Profesional asociado</h5>
      <div class="card-usuario">
        <p><strong>RUT:</strong> <?= htmlspecialchars($profesional['Rut_profesional'] ?? '-') ?></p>
        <p><strong>Nombre:</strong> <?= htmlspecialchars($profesional['Nombre_profesional'] ?? '-') ?></p>
        <p><strong>Cargo:</strong> <?= htmlspecialchars($profesional['Cargo_profesional'] ?? '-') ?></p>
      </div>
    </div>
  </div>

  <div class="mt-4">
    <button type="submit" class="btn btn-success">Guardar cambios</button>
    <a href="index.php?seccion=documentos" class="btn btn-secondary">Cancelar</a>
  </div>
</form>

</body>
</html>
