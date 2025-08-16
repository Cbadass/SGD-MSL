<?php
// sidebar.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Rol del usuario (ADMIN / DIRECTOR / PROFESIONAL)
$rol = $_SESSION['usuario']['permisos'] ?? 'GUEST';

// Archivo actual (para resaltar activo)
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$currentFile = basename($currentPath) ?: 'index.php';

function activeClass(string $file, string $currentFile): string {
    return $currentFile === $file ? 'active' : '';
}
function canSee(array $roles, string $rol): bool {
    return in_array($rol, $roles, true);
}

// Helpers para imprimir enlaces con control de rol
function navItem(string $file, string $label, array $roles, string $rol, string $currentFile): void {
    if (!canSee($roles, $rol)) return;
    $cls = activeClass($file, $currentFile);
    // Escapado básico
    $fileSafe = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
    $labelSafe = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    echo "<a href=\"{$fileSafe}\" class=\"{$cls}\">{$labelSafe}</a>\n";
}
?>
<!-- Componente de Sidebar -->
<aside class="sidebar">
  <div>
    <h3>Navegación</h3>
    <?php
      // Inicio: visible para todos los roles válidos
      navItem('index.php', 'Inicio', ['admin','DIRECTOR','PROFESIONAL'], $rol, $currentFile);

      // Bloque de gestión (solo Admin/Director)
      if (canSee(['admin','DIRECTOR'], $rol)) {
        echo "<h3>Gestión</h3>\n";
        navItem('usuarios.php',      'Usuarios',      ['admin','DIRECTOR'], $rol, $currentFile);
        navItem('profesionales.php', 'Profesionales', ['admin','DIRECTOR'], $rol, $currentFile);
        navItem('cursos.php',        'Cursos',        ['admin','DIRECTOR'], $rol, $currentFile);
        navItem('estudiantes.php',   'Estudiantes',   ['admin','DIRECTOR'], $rol, $currentFile);
        navItem('apoderados.php',    'Apoderados',    ['admin','DIRECTOR'], $rol, $currentFile);
        navItem('documentos.php',    'Documentos',    ['admin','DIRECTOR'], $rol, $currentFile);
        navItem('asignaciones.php',  'Asignaciones',  ['admin','DIRECTOR'], $rol, $currentFile); // NUEVO módulo
      }

      // Bloque de trabajo para Profesional (tabs limitados)
      if ($rol === 'PROFESIONAL') {
        echo "<h3>Mi trabajo</h3>\n";
        navItem('cursos.php',      'Cursos',      ['PROFESIONAL'], $rol, $currentFile);
        navItem('estudiantes.php', 'Estudiantes', ['PROFESIONAL'], $rol, $currentFile);
        navItem('apoderados.php',  'Apoderados',  ['PROFESIONAL'], $rol, $currentFile);
        navItem('documentos.php',  'Documentos',  ['PROFESIONAL'], $rol, $currentFile);
        // Importante: PROFESIONAL **no** ve Usuarios, Profesionales, Asignaciones ni Actividad.
      }

      // Auditoría / Actividad (solo Admin/Director)
      if (canSee(['admin','DIRECTOR'], $rol)) {
        echo "<h3>Monitoreo</h3>\n";
        navItem('actividad.php', 'Actividad', ['admin','DIRECTOR'], $rol, $currentFile);
      }
    ?>
  </div>

  <div>
    <h3>Cuenta</h3>
    <?php
      // Opcional: perfil
      // navItem('perfil.php', 'Mi perfil', ['admin','DIRECTOR','PROFESIONAL'], $rol, $currentFile);
      navItem('logout.php', 'Cerrar sesión', ['admin','DIRECTOR','PROFESIONAL'], $rol, $currentFile);
    ?>
  </div>
</aside>
