<?php
// guardar_modificacion_estudiante.php
session_start();
require_once 'includes/db.php';
require_once 'includes/auditoria.php';  // <-- Incluimos auditoría

// — Funciones de RUT —
function cleanRut($rut) {
    return preg_replace('/[^0-9kK]/', '', $rut);
}
function dvRut($rut) {
    $R = cleanRut($rut);
    $digits = substr($R, 0, -1);
    $dv      = strtoupper(substr($R, -1));
    $sum = 0; $mult = 2;
    for ($i = strlen($digits) - 1; $i >= 0; $i--) {
        $sum += $digits[$i] * $mult;
        $mult = $mult < 7 ? $mult + 1 : 2;
    }
    $res = 11 - ($sum % 11);
    if ($res == 11) $expected = '0';
    elseif ($res == 10) $expected = 'K';
    else $expected = (string)$res;
    return $dv === $expected;
}
function formatRut($rut) {
    $R = cleanRut($rut);
    $number = substr($R, 0, -1);
    $dv     = strtoupper(substr($R, -1));
    return number_format($number, 0, ',', '.') . "-$dv";
}

try {
    if (!isset($_SESSION['usuario'])) {
        throw new Exception("No autorizado.");
    }

    // 1) Validar ID
    $id = intval($_POST['Id_estudiante'] ?? 0);
    if ($id <= 0) throw new Exception("ID inválido.");

    // 2) Capturar campos
    $nombre    = trim($_POST['Nombre_estudiante']   ?? '');
    $apellido  = trim($_POST['Apellido_estudiante'] ?? '');
    $rutRaw    = trim($_POST['Rut_estudiante']      ?? '');
    $nac       = trim($_POST['Fecha_nacimiento']    ?? '');
    $ing       = trim($_POST['Fecha_ingreso']       ?? '');
    $estado    = intval($_POST['Estado_estudiante'] ?? 1);
    $curso     = intval($_POST['Id_curso']          ?? 0) ?: null;
    $apoderado = intval($_POST['Id_apoderado']      ?? 0) ?: null;

    // 3) Validaciones
    if (!$nombre || !$apellido || !$rutRaw) {
        throw new Exception("Complete nombres, apellidos y RUT.");
    }
    if (!dvRut($rutRaw)) {
        throw new Exception("RUT inválido.");
    }
    $rutFmt = formatRut($rutRaw);
    // Unicidad
    $chk = $conn->prepare("
        SELECT COUNT(*) FROM estudiantes
         WHERE Rut_estudiante = ? AND Id_estudiante <> ?
    ");
    $chk->execute([$rutFmt, $id]);
    if ($chk->fetchColumn() > 0) {
        throw new Exception("Ya existe otro estudiante con RUT $rutFmt.");
    }

    // 4) Transaction
    $conn->beginTransaction();

    $sql = "
      UPDATE estudiantes
         SET Nombre_estudiante   = :nom,
             Apellido_estudiante = :ape,
             Rut_estudiante      = :rut,
             Fecha_nacimiento    = :nac,
             Fecha_ingreso       = :ing,
             Estado_estudiante   = :est,
             Id_curso            = :cur,
             Id_apoderado        = :apo
       WHERE Id_estudiante = :id
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':nom' => $nombre,
        ':ape' => $apellido,
        ':rut' => $rutFmt,
        ':nac' => $nac,
        ':ing' => $ing,
        ':est' => $estado,
        ':cur' => $curso,
        ':apo' => $apoderado,
        ':id'  => $id
    ]);

    // Auditoría de la modificación
    $usuarioLog = $_SESSION['usuario']['id'];
    $datosNuevos = [
        'Nombre_estudiante'   => $nombre,
        'Apellido_estudiante' => $apellido,
        'Rut_estudiante'      => $rutFmt,
        'Fecha_nacimiento'    => $nac,
        'Fecha_ingreso'       => $ing,
        'Estado_estudiante'   => $estado,
        'Id_curso'            => $curso,
        'Id_apoderado'        => $apoderado
    ];
    registrarAuditoria($conn, $usuarioLog, 'estudiantes', $id, 'UPDATE', null, $datosNuevos);

    $conn->commit();

    header("Location: index.php?seccion=estudiantes");
    exit;

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo "<p class='text-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
