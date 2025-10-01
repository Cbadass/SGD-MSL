<?php
// logout.php
declare(strict_types=1);

session_start();

// Vaciar variables de sesión
$_SESSION = [];

// Eliminar cookie de sesión
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

// Destruir sesión
session_destroy();

// (Opcional) si quieres limpiar la cookie de tema oscuro, descomenta:
// setcookie('modo_oscuro', '', time() - 3600, '/');

// Redirigir a login
header('Location: login.php');
exit;
