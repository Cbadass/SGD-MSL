<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

try {
    if (!isset($_SESSION['usuario'])) {
        throw new Exception("No autorizado.");
    }

    // 1) Validar ID
    $id = intval($_POST['Id_estudiante'] ?? 0);
    if ($id <= 0) throw new Exception("ID inválido.");

    // 2) Capturar campos
    $nombre   = trim($_POST['Nombre_estudiante'] ?? '');
    $apellido = trim($_POST['Apellido_estudiante'] ?? '');
    $rut      = trim($_POST['Rut_estudiante'] ?? '');
    $nac      = trim($_POST['Fecha_nacimiento'] ?? '');
    $ing      = trim($_POST['Fecha_ingreso'] ?? '');
    $estado   = intval($_POST['Estado_estudiante'] ?? 1);
    $curso    = !empty($_POST['Id_curso']) ? intval($_POST['Id_curso']) : null;
    $apo      = !empty($_POST['Id_apoderado']) ? intval($_POST['Id_apoderado']) : null;

    // (Opcional) Aquí puedes repetir las validaciones que necesites
    // if (!$nombre || !$apellido) throw new Exception("Complete campos obligatorios.");

    // 3) Update en estudiantes
    $sql = "
      UPDATE estudiantes
         SET Nombre_estudiante = :nom,
             Apellido_estudiante = :ape,
             Rut_estudiante = :rut,
             Fecha_nacimiento = :nac,
             Fecha_ingreso = :ing,
             Estado_estudiante = :est,
             Id_curso = :cur,
             Id_apoderado = :apo
       WHERE Id_estudiante = :id
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
      ':nom' => $nombre,
      ':ape' => $apellido,
      ':rut' => $rut,
      ':nac' => $nac,
      ':ing' => $ing,
      ':est' => $estado,
      ':cur' => $curso,
      ':apo' => $apo,
      ':id'  => $id,
    ]);

    header("Location: index.php?seccion=estudiantes");
    exit;

} catch (Exception $e) {
    echo "<p class='text-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
