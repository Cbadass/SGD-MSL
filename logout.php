<?php
session_start();         // Inicia la sesión si no está iniciada
session_unset();         // Limpia todas las variables de sesión
session_destroy();       // Destruye la sesión actual

// Opcional: elimina la cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirige al login
header("Location: login.php");
exit;
