<?php
// includes/password_utils.php

declare(strict_types=1);

/**
 * Valida y actualiza la contraseña del propio usuario.
 *
 * @return array{ok:bool,msg:string,hashAnterior?:string,hashNueva?:string}
 */
function cambiarContrasenaPropia(PDO $conn, int $usuarioId, string $pwdActual, string $pwdNueva, string $pwdConfirmacion): array
{
    $pwdActual   = trim($pwdActual);
    $pwdNueva    = trim($pwdNueva);
    $pwdConfirmacion = trim($pwdConfirmacion);

    if ($usuarioId <= 0) {
        return ['ok' => false, 'msg' => 'Sesión inválida.'];
    }

    if ($pwdActual === '' || $pwdNueva === '' || $pwdConfirmacion === '') {
        return ['ok' => false, 'msg' => 'Debes completar todos los campos.'];
    }

    if (strlen($pwdNueva) < 8) {
        return ['ok' => false, 'msg' => 'La nueva contraseña debe tener al menos 8 caracteres.'];
    }

    if ($pwdNueva !== $pwdConfirmacion) {
        return ['ok' => false, 'msg' => 'La confirmación de contraseña no coincide.'];
    }

    $stmt = $conn->prepare('SELECT Contraseña FROM usuarios WHERE Id_usuario = ?');
    $stmt->execute([$usuarioId]);
    $hashActual = $stmt->fetchColumn();
    if (!$hashActual) {
        return ['ok' => false, 'msg' => 'Usuario no encontrado.'];
    }

    if (!password_verify($pwdActual, $hashActual)) {
        return ['ok' => false, 'msg' => 'La contraseña actual ingresada es incorrecta.'];
    }

    if (password_verify($pwdNueva, $hashActual) || hash_equals($pwdActual, $pwdNueva)) {
        return ['ok' => false, 'msg' => 'La nueva contraseña debe ser distinta a la actual.'];
    }

    $hashNuevo = password_hash($pwdNueva, PASSWORD_DEFAULT);

    $update = $conn->prepare('UPDATE usuarios SET Contraseña = ? WHERE Id_usuario = ?');
    $update->execute([$hashNuevo, $usuarioId]);

    return [
        'ok' => true,
        'msg' => 'Contraseña actualizada correctamente.',
        'hashAnterior' => (string)$hashActual,
        'hashNueva' => $hashNuevo,
    ];
}
