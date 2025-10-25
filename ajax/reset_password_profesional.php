<?php
// ajax/reset_password_profesional.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/roles.php';
require_once __DIR__ . '/../includes/auditoria.php';

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autenticado']);
    exit;
}

try {
    $alcance = getAlcanceUsuario($conn, $_SESSION['usuario']);
    $rolActual = $alcance['rol'];

    if (!in_array($rolActual, ['ADMIN', 'DIRECTOR'], true)) {
        throw new RuntimeException('No tienes permisos para restablecer contraseñas.');
    }

    $idUsuarioTarget = (int)($_POST['Id_usuario'] ?? 0);
    if ($idUsuarioTarget <= 0) {
        throw new RuntimeException('Usuario objetivo inválido.');
    }

    $stmt = $conn->prepare('
        SELECT
            u.Id_usuario,
            u.Id_profesional,
            u.Nombre_usuario,
            u.Permisos,
            u.Contraseña,
            p.Nombre_profesional,
            p.Apellido_profesional,
            p.Correo_profesional,
            p.Rut_profesional,
            p.Id_escuela_prof,
            e.Nombre_escuela
        FROM usuarios u
        LEFT JOIN profesionales p ON p.Id_profesional = u.Id_profesional
        LEFT JOIN escuelas e ON e.Id_escuela = p.Id_escuela_prof
        WHERE u.Id_usuario = ?
    ');
    $stmt->execute([$idUsuarioTarget]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        throw new RuntimeException('El usuario solicitado no existe.');
    }

    $targetRol = ensureRole((string)($target['Permisos'] ?? 'PROFESIONAL'));
    $targetSchoolId = isset($target['Id_escuela_prof']) ? (int)$target['Id_escuela_prof'] : null;

    if (!canResetPassword($rolActual, $alcance['escuela_id'] ?? null, $targetSchoolId, $targetRol)) {
        throw new RuntimeException('No tienes permisos para restablecer la contraseña de este usuario.');
    }

    $tempPassword = substr(bin2hex(random_bytes(10)), 0, 10);
    $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

    $conn->beginTransaction();
    $conn
        ->prepare('UPDATE usuarios SET Contraseña = ? WHERE Id_usuario = ?')
        ->execute([$hash, $idUsuarioTarget]);

    registrarAuditoria(
        $conn,
        (int)($_SESSION['usuario']['id'] ?? $_SESSION['usuario']['Id_usuario'] ?? 0),
        'usuarios',
        $idUsuarioTarget,
        'UPDATE',
        ['Contraseña' => $target['Contraseña'] ?? null],
        ['Contraseña' => $hash]
    );

    $conn->commit();

    $response = [
        'nombre' => (string)($target['Nombre_profesional'] ?? ''),
        'apellido' => (string)($target['Apellido_profesional'] ?? ''),
        'correo' => (string)($target['Correo_profesional'] ?? ''),
        'rut' => (string)($target['Rut_profesional'] ?? ''),
        'escuela' => (string)($target['Nombre_escuela'] ?? ''),
        'usuario' => (string)($target['Nombre_usuario'] ?? ''),
        'password' => $tempPassword,
    ];

    echo json_encode(['ok' => true, 'data' => $response]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    $msg = $e->getMessage();
    if (!in_array($e->getCode(), [401, 403], true)) {
        error_log('[reset_password_profesional] ' . $msg);
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => $msg]);
}
