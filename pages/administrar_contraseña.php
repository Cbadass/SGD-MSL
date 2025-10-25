<?php
// pages/administrar_contraseña.php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    exit('No autorizado');
}

$tituloPrincipal = 'Gestión de contraseñas';
?>
<h2><?= htmlspecialchars($tituloPrincipal, ENT_QUOTES, 'UTF-8') ?></h2>

<div class="alert alert-info" role="alert">
  El restablecimiento de contraseñas temporales ahora se realiza directamente desde la tabla de profesionales.
  Selecciona el botón <strong>Restablecer</strong> junto al profesional correspondiente para generar nuevas credenciales.
</div>
