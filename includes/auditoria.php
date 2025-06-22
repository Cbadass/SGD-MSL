<?php
function registrarAuditoria(PDO $conn, int $usuarioId, string $tabla, $registroId, string $accion, array $antes = null, array $despues = null) {
    $sql = "INSERT INTO Auditoria
            (Usuario_id, Tabla, Registro_id, Accion, Datos_anteriores, Datos_nuevos)
            VALUES (:u, :t, :r, :a, :ant, :dep)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':u'   => $usuarioId,
        ':t'   => $tabla,
        ':r'   => (string)$registroId,
        ':a'   => $accion,
        ':ant' => $antes   ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
        ':dep' => $despues ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null
    ]);
}
