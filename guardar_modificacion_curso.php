<?php
session_start();
require_once 'includes/db.php';

try {
    // 1) Protección
    if (!isset($_SESSION['usuario'])) {
        throw new Exception("No autorizado.");
    }

    // 2) Validar ID
    $idCurso = intval($_POST['Id_curso'] ?? 0);
    if ($idCurso <= 0) {
        throw new Exception("ID de curso inválido.");
    }

    // 3) Capturar datos de POST
    $tipo       = trim($_POST['Tipo_curso']     ?? '');
    $grado      = intval($_POST['Grado_curso']  ?? 0);
    $seccion    = trim($_POST['seccion_curso']  ?? '');
    $idEscuela  = intval($_POST['Id_escuela']  ?? 0);
    $idProfes   = intval($_POST['Id_profesional'] ?? 0) ?: null;

    // 4) Validaciones
    if ($tipo === '' || $grado <= 0 || $seccion === '' || $idEscuela <= 0) {
        throw new Exception("Complete todos los campos obligatorios.");
    }

    // 5) Actualizar en la BD
    $sql = "
        UPDATE cursos
           SET Tipo_curso     = ?,
               Grado_curso    = ?,
               seccion_curso  = ?,
               Id_escuela     = ?,
               Id_profesional = ?
         WHERE Id_curso = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$tipo, $grado, $seccion, $idEscuela, $idProfes, $idCurso]);

    // 6) Redirigir a la lista
    header("Location: index.php?seccion=cursos");
    exit;

} catch (Exception $e) {
    echo "<p class='text-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
