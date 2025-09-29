<?php
// includes/session.php

// Detección de HTTPS detrás de Azure App Service (reverse proxy)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// Endurece cookies de sesión
if (PHP_SESSION_NONE === session_status()) {
  session_name('SGDMSLSESSID');
  session_set_cookie_params([
    'lifetime' => 0,        // hasta cerrar el navegador
    'path'     => '/',
    'domain'   => '',       // por defecto
    'secure'   => $isHttps, // TRUE en HTTPS
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}

// CSRF por defecto (un token por sesión)
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/** Redirige a login si no hay usuario en sesión */
function require_login(): void {
  if (empty($_SESSION['usuario'])) {
    header('Location: /login.php');
    exit;
  }
}

/** Devuelve el token CSRF actual */
function csrf_token(): string {
  return (string)($_SESSION['csrf_token'] ?? '');
}

/** Valida CSRF en peticiones POST (lanza 400 si no corresponde) */
function check_csrf_post(): void {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)$token)) {
      http_response_code(400);
      die('Solicitud inválida (CSRF).');
    }
  }
}

// Atajos de acceso a usuario/rol (opcionales, útiles)
function current_user(): ?array { return $_SESSION['usuario'] ?? null; }
function user_id(): ?int { return isset($_SESSION['usuario']['id']) ? (int)$_SESSION['usuario']['id'] : null; }
function user_role(): ?string { return isset($_SESSION['usuario']['permisos']) ? strtoupper((string)$_SESSION['usuario']['permisos']) : null; }
function user_profesional_id(): ?int { return isset($_SESSION['usuario']['id_profesional']) ? (int)$_SESSION['usuario']['id_profesional'] : null; }
