<?php
session_start();
require_once 'includes/db.php';

try {
    // 1) Protección
    if (!isset($_SESSION['usuario'])) {
        throw new Exception("No autorizado.");
    }

    // 2) Capturar datos de POST
    $tipo       = trim($_POST['Tipo_curso']        ?? '');
    $grado      = trim($_POST['Grado_curso']       ?? '');  // ahora es string
    $seccion    = trim($_POST['seccion_curso']     ?? '');
    $idEscuela  = intval($_POST['Id_escuela']     ?? 0);
    $idProfes   = intval($_POST['Id_profesional'] ?? 0) ?: null;

    // 3) Validaciones
    if ($tipo === '' || $grado === '' || $seccion === '' || $idEscuela <= 0) {
        throw new Exception("Complete todos los campos obligatorios.");
    }

    // 4) Insertar en la BD
    $sql = "
        INSERT INTO cursos
            (Tipo_curso, Grado_curso, seccion_curso, Id_escuela, Id_profesional)
        VALUES
            (?, ?, ?, ?, ?)
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$tipo, $grado, $seccion, $idEscuela, $idProfes]);

    // 5) Redirigir a la lista
    header("Location: index.php?seccion=cursos");
    exit;

} catch (Exception $e) {
    echo "<p class='text-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
