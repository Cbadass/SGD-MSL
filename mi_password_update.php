<?php
// mi_password_update.php
session_start();
require_once 'includes/db.php';
require_once 'includes/password_utils.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autenticado']);
    exit;
}

$uid = (int)($_SESSION['usuario']['id'] ?? $_SESSION['usuario']['Id_usuario'] ?? 0);
$pwd_actual = (string)($_POST['pwd_actual'] ?? '');
$pwd_nueva  = (string)($_POST['pwd_nueva']  ?? '');
$pwd_conf   = (string)($_POST['pwd_conf']   ?? '');

$resultado = cambiarContrasenaPropia($conn, $uid, $pwd_actual, $pwd_nueva, $pwd_conf);

if (!$resultado['ok']) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => $resultado['msg']]);
    exit;
}

echo json_encode(['ok' => true, 'msg' => $resultado['msg']]);
