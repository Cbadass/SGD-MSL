<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auditoria.php';  // <-- incluir auditoría

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
    $tipo       = trim($_POST['Tipo_curso']        ?? '');
    $grado      = trim($_POST['Grado_curso']       ?? '');  // ahora es string
    $seccion    = trim($_POST['seccion_curso']     ?? '');
    $idEscuela  = intval($_POST['Id_escuela']     ?? 0);
    $idProfes   = intval($_POST['Id_profesional'] ?? 0) ?: null;

    // 4) Validaciones
    if ($tipo === '' || $grado === '' || $seccion === '' || $idEscuela <= 0) {
        throw new Exception("Complete todos los campos obligatorios.");
    }

    // 5) Actualizar en la BD
    $sql = "
        UPDATE cursos
           SET Tipo_curso     = ?,
               Grado_curso    = ?,  -- ahora almacenamos cadena
               seccion_curso  = ?,
               Id_escuela     = ?,
               Id_profesional = ?
         WHERE Id_curso = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$tipo, $grado, $seccion, $idEscuela, $idProfes, $idCurso]);

    // Auditoría de la modificación
    $usuarioLog = $_SESSION['usuario']['id'];
    $datosNuevos = [
        'Tipo_curso'     => $tipo,
        'Grado_curso'    => $grado,
        'seccion_curso'  => $seccion,
        'Id_escuela'     => $idEscuela,
        'Id_profesional' => $idProfes,
    ];
    registrarAuditoria($conn, $usuarioLog, 'cursos', $idCurso, 'UPDATE', null, $datosNuevos);

    // 6) Redirigir a la lista
    header("Location: index.php?seccion=cursos");
    exit;

} catch (Exception $e) {
    echo "<p class='text-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
