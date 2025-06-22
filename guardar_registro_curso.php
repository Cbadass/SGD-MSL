<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auditoria.php';  // <-- Aquí cargas la función registrarAuditoria()

try {
    // 1) Protección
    if (!isset($_SESSION['usuario'])) {
        throw new Exception("No autorizado.");
    }

    // 2) Capturar datos de POST
    $tipo       = trim($_POST['Tipo_curso']        ?? '');
    $grado      = trim($_POST['Grado_curso']       ?? '');  // ahora es string
    $seccion    = trim($_POST['seccion_curso']     ?? '');
    $idEscuela  = intval($_POST['Id_escuela']      ?? 0);
    $idProfes   = intval($_POST['Id_profesional']  ?? 0) ?: null;

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

    // 4b) Auditoría: capturamos el ID recién insertado y los datos nuevos
    $nuevoId = $conn->lastInsertId();
    registrarAuditoria(
        $conn,
        $_SESSION['usuario']['id'],   // ID del usuario logueado
        'cursos',                     // Tabla afectada
        $nuevoId,                     // ID del registro insertado
        'INSERT',                     // Acción realizada
        null,                         // No hay datos anteriores
        [                             // Datos nuevos
            'Tipo_curso'    => $tipo,
            'Grado_curso'   => $grado,
            'seccion_curso' => $seccion,
            'Id_escuela'    => $idEscuela,
            'Id_profesional'=> $idProfes,
        ]
    );

    // 5) Redirigir a la lista
    header("Location: index.php?seccion=cursos");
    exit;

} catch (Exception $e) {
    echo "<p class='text-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
