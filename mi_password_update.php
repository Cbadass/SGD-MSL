<?php
// mi_password_update.php
session_start();
require_once 'includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$sendJson = static function (int $status, array $payload): void {
  http_response_code($status);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $sendJson(405, ['ok' => false, 'msg' => 'Método no permitido.']);
  exit;
}

if (!isset($_SESSION['usuario'])) {
  $sendJson(401, ['ok' => false, 'msg' => 'No autenticado.']);
  exit;
}

$uid = (int)($_SESSION['usuario']['id'] ?? $_SESSION['usuario']['Id_usuario'] ?? 0);
if ($uid <= 0) {
  $sendJson(400, ['ok' => false, 'msg' => 'Sesión inválida.']);
  exit;
}

$pwd_actual = trim((string)($_POST['pwd_actual'] ?? ''));
$pwd_nueva  = trim((string)($_POST['pwd_nueva'] ?? ''));
$pwd_conf   = trim((string)($_POST['pwd_conf'] ?? ''));

if ($pwd_actual === '' || $pwd_nueva === '' || $pwd_conf === '') {
  $sendJson(400, ['ok' => false, 'msg' => 'Completa todos los campos obligatorios.']);
  exit;
}

if (strlen($pwd_nueva) < 8) {
  $sendJson(400, ['ok' => false, 'msg' => 'La nueva contraseña debe tener al menos 8 caracteres.']);
  exit;
}

if ($pwd_nueva !== $pwd_conf) {
  $sendJson(400, ['ok' => false, 'msg' => 'La confirmación no coincide con la nueva contraseña.']);
  exit;
}

try {
  $st = $conn->prepare('SELECT Contraseña FROM usuarios WHERE Id_usuario = ? LIMIT 1');
  $st->execute([$uid]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    $sendJson(404, ['ok' => false, 'msg' => 'Usuario no encontrado.']);
    exit;
  }

  if (!password_verify($pwd_actual, $row['Contraseña'])) {
    $sendJson(400, ['ok' => false, 'msg' => 'La contraseña actual no es válida.']);
    exit;
  }

  if (password_verify($pwd_nueva, $row['Contraseña'])) {
    $sendJson(400, ['ok' => false, 'msg' => 'La nueva contraseña no puede ser igual a la actual.']);
    exit;
  }

  $hash = password_hash($pwd_nueva, PASSWORD_DEFAULT);
  $up = $conn->prepare('UPDATE usuarios SET Contraseña = ? WHERE Id_usuario = ?');
  $up->execute([$hash, $uid]);

  $sendJson(200, ['ok' => true, 'msg' => 'Contraseña actualizada correctamente.']);
} catch (Throwable $e) {
  $sendJson(500, ['ok' => false, 'msg' => 'Ocurrió un error al actualizar la contraseña.']);
}
