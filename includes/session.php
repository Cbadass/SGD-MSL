<?php
// includes/session.php
// ⚠️ No pongas BOM ni espacios antes de esta línea

// ========== Unificar nombre y flags de la cookie de sesión ==========
session_name('SGDMSLSESSID'); // ← Nombre ÚNICO del proyecto

if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime' => 0,           // Hasta cerrar navegador
    'path'     => '/',         // Todo el sitio
    'domain'   => '',          // Dominio actual
    'secure'   => true,        // Solo HTTPS (Azure usa HTTPS)
    'httponly' => true,        // No accesible desde JavaScript
    'samesite' => 'Lax',       // CSRF protection
  ]);
  session_start();
}
// ====================================================================

// CSRF por sesión (si lo usas en formularios POST)
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

// Helpers útiles
function csrf_token(): string {
  return (string)($_SESSION['csrf_token'] ?? '');
}

function check_csrf_post(): void {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)$token)) {
      http_response_code(400);
      die('Solicitud inválida (CSRF).');
    }
  }
}

function current_user(): ?array { 
  return $_SESSION['usuario'] ?? null; 
}

function user_id(): ?int { 
  return isset($_SESSION['usuario']['id']) ? (int)$_SESSION['usuario']['id'] : null; 
}

function user_role(): ?string { 
  return isset($_SESSION['usuario']['permisos']) ? strtoupper((string)$_SESSION['usuario']['permisos']) : null; 
}

function user_profesional_id(): ?int { 
  return isset($_SESSION['usuario']['id_profesional']) ? (int)$_SESSION['usuario']['id_profesional'] : null; 
}