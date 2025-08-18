<?php
// mi_password_update.php
session_start();
require_once 'includes/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
  echo json_encode(['ok'=>false,'msg'=>'No autenticado']); exit;
}

$uid = (int)($_SESSION['usuario']['id'] ?? 0);
$pwd_actual = trim($_POST['pwd_actual'] ?? '');
$pwd_nueva  = trim($_POST['pwd_nueva']  ?? '');
$pwd_conf   = trim($_POST['pwd_conf']   ?? '');

if (!$uid) { echo json_encode(['ok'=>false,'msg'=>'Sesión inválida']); exit; }
if ($pwd_nueva === '' || $pwd_nueva !== $pwd_conf || strlen($pwd_nueva) < 8) {
  echo json_encode(['ok'=>false,'msg'=>'La nueva contraseña debe coincidir y tener 8+ caracteres.']); exit;
}

// Traer hash actual
$st = $conn->prepare("SELECT Contraseña FROM usuarios WHERE Id_usuario=?");
$st->execute([$uid]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) { echo json_encode(['ok'=>false,'msg'=>'Usuario no encontrado']); exit; }

if (!password_verify($pwd_actual, $row['Contraseña'])) {
  echo json_encode(['ok'=>false,'msg'=>'Contraseña actual inválida']); exit;
}

// Guardar nuevo hash
$hash = password_hash($pwd_nueva, PASSWORD_DEFAULT);
$up = $conn->prepare("UPDATE usuarios SET Contraseña=? WHERE Id_usuario=?");
$up->execute([$hash, $uid]);

echo json_encode(['ok'=>true]);
