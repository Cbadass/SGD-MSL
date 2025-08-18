<?php
// reset_password.php
session_start();
require_once 'includes/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
  echo json_encode(['ok'=>false,'msg'=>'No autenticado']); exit;
}

$mi_permiso = strtolower($_SESSION['usuario']['permisos'] ?? 'user');
if (!in_array($mi_permiso, ['admin','director'], true)) {
  echo json_encode(['ok'=>false,'msg'=>'Sin permiso']); exit;
}

$Id_usuario = (int)($_POST['Id_usuario'] ?? 0);
if ($Id_usuario <= 0) { echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }

function genTempPwd($len=12){
  $chars='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@$%';
  $out=''; for($i=0;$i<$len;$i++) $out.=$chars[random_int(0,strlen($chars)-1)];
  return $out;
}
$temp = genTempPwd(12);
$hash = password_hash($temp, PASSWORD_DEFAULT);

$up = $conn->prepare("UPDATE usuarios SET Contraseña=? WHERE Id_usuario=?");
$up->execute([$hash, $Id_usuario]);

echo json_encode(['ok'=>true,'temp'=>$temp]);
