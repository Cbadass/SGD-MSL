<?php
// guardar_modificacion_estudiante.php
session_start();
require_once 'includes/db.php';

try {
    if (!isset($_SESSION['usuario'])) {
        throw new Exception("No autorizado.");
    }

    // 1) Validar IDs
    $id_estudiante = intval($_POST['Id_estudiante'] ?? 0);
    if ($id_estudiante <= 0) {
        throw new Exception("ID de estudiante inválido.");
    }
    $id_apoderado = intval($_POST['Id_apoderado'] ?? 0);

    // 2) Capturar campos de estudiante
    $nombre_estudiante     = trim($_POST['Nombre_estudiante']    ?? '');
    $apellido_estudiante   = trim($_POST['Apellido_estudiante']  ?? '');
    $rut_estudiante        = trim($_POST['Rut_estudiante']       ?? '');
    $fecha_nacimiento      = trim($_POST['Fecha_nacimiento']     ?? '');
    $fecha_ingreso         = trim($_POST['Fecha_ingreso']        ?? '');

    // 3) Capturar campos de apoderado
    $nombre_apoderado      = trim($_POST['Nombre_apoderado']     ?? '');
    $apellido_apoderado    = trim($_POST['Apellido_apoderado']   ?? '');
    $domicilio_apoderado   = trim($_POST['Domicilio_apoderado']  ?? '');
    $escolaridad_padre     = trim($_POST['Escolaridad_padre']    ?? '');
    $escolaridad_madre     = trim($_POST['Escolaridad_madre']    ?? '');
    $correo_apoderado      = trim($_POST['Correo_apoderado']     ?? '');
    $ocupacion_padre       = trim($_POST['Ocupacion_padre']      ?? '');
    $ocupacion_madre       = trim($_POST['Ocupacion_madre']      ?? '');
    $numero_apoderado      = trim($_POST['Numero_apoderado']     ?? '');

    // 4) Iniciar transacción
    $conn->beginTransaction();

    // 5) UPDATE estudiantes
    $sqlEst = "
        UPDATE estudiantes
           SET Nombre_estudiante  = :nom,
               Apellido_estudiante= :ape,
               Rut_estudiante     = :rut,
               Fecha_nacimiento   = :fnac,
               Fecha_ingreso      = :fing,
               Id_apoderado       = :idapo
         WHERE Id_estudiante = :idest
    ";
    $stmtEst = $conn->prepare($sqlEst);
    $stmtEst->execute([
        ':nom'    => $nombre_estudiante,
        ':ape'    => $apellido_estudiante,
        ':rut'    => $rut_estudiante,
        ':fnac'   => $fecha_nacimiento,
        ':fing'   => $fecha_ingreso,
        ':idapo'  => $id_apoderado ?: null,
        ':idest'  => $id_estudiante,
    ]);

    // 6) UPDATE apoderados si aplica
    if ($id_apoderado > 0) {
        $sqlApo = "
            UPDATE apoderados
               SET Nombre_apoderado   = :napo,
                   Apellido_apoderado = :aapo,
                   Domicilio_apoderado= :dom,
                   Escolaridad_padre  = :ep,
                   Escolaridad_madre  = :em,
                   Correo_apoderado   = :mail,
                   Ocupacion_padre    = :op,
                   Ocupacion_madre    = :om,
                   Numero_apoderado   = :num
             WHERE Id_apoderado = :idapo
        ";
        $stmtApo = $conn->prepare($sqlApo);
        $stmtApo->execute([
            ':napo' => $nombre_apoderado,
            ':aapo' => $apellido_apoderado,
            ':dom'  => $domicilio_apoderado,
            ':ep'   => $escolaridad_padre,
            ':em'   => $escolaridad_madre,
            ':mail' => $correo_apoderado,
            ':op'   => $ocupacion_padre,
            ':om'   => $ocupacion_madre,
            ':num'  => $numero_apoderado,
            ':idapo'=> $id_apoderado,
        ]);
    }

    // 7) Commit y redirección
    $conn->commit();
    header("Location: index.php?seccion=estudiantes");
    exit;

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "<p class='text-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
