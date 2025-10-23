<?php
// logout.php
declare(strict_types=1);

// ========== CRÍTICO: Usar el mismo nombre de sesión ==========
session_name('SGDMSLSESSID'); // ← IGUAL que en session.php
// =============================================================

session_start();

// Vaciar variables de sesión
$_SESSION = [];

// Eliminar cookie de sesión con los MISMOS parámetros
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  
  setcookie(
    session_name(),           // Nombre correcto: SGDMSLSESSID
    '',                       // Valor vacío
    time() - 42000,           // Expiración en el pasado
    $params['path'],          // Mismo path
    $params['domain'],        // Mismo domain
    $params['secure'],        // Mismo secure
    $params['httponly']       // Mismo httponly
  );
  
  // ========== EXTRA: Forzar eliminación con parámetros específicos ==========
  // Por si los parámetros de sesión fueron modificados después de crearla
  setcookie(
    'SGDMSLSESSID',          // Nombre explícito
    '',
    time() - 42000,
    '/',                     // Path raíz
    '',                      // Domain vacío (actual)
    true,                    // Secure (HTTPS)
    true                     // HttpOnly
  );
  // =========================================================================
}

// Destruir sesión
session_destroy();

// ========== OPCIONAL: Limpiar cookie de modo oscuro ==========
if (isset($_COOKIE['modo_oscuro'])) {
  setcookie('modo_oscuro', '', time() - 3600, '/');
}
// ============================================================

// Redirigir a login con ruta absoluta
header('Location: /login.php');
exit;