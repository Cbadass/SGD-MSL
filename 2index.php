<?php
session_start();

// Obtener la sección solicitada (por defecto 'usuarios')
$seccion = $_GET['seccion'] ?? 'usuarios';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>SGD Multisenluz</title>

<!-- Modo oscuro: activar antes de cargar CSS para evitar parpadeo -->
<script>
  if (localStorage.getItem('modo-oscuro') === 'true') {
    document.documentElement.classList.add('dark-mode');
  }
</script>

<style>
  body { margin: 0; font-family: 'Segoe UI', sans-serif; background-color: #e8e8fc; color: #333; }
  .container { display: flex; }
  .sidebar { width: 250px; background-color: #d3d2f3; height: 100vh; padding: 20px 0; display: flex; flex-direction: column; justify-content: space-between; }
  .sidebar h3 { margin: 10px 20px; color: #3b3b8c; font-size: 14px; text-transform: uppercase; }
  .sidebar a { display: block; padding: 10px 20px; color: #333; text-decoration: none; font-weight: 500; font-size: 14px; }
  .sidebar a:hover, .sidebar a.active { background-color: #bcbaf3; border-left: 5px solid #6e62f4; }
  .main { flex: 1; padding: 20px 40px; }
  .header { background-color: #875ff5; color: white; padding: 15px 30px; font-size: 22px; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
  .section { margin-top: 30px; background-color: white; padding: 30px; border-radius: 10px; }
  
  /* Modo oscuro */
  .dark-mode { background-color: #121212; color: #eee; }
  .dark-mode .header { background-color: #1f1f1f; color: #f1f1f1; }
  .dark-mode .sidebar { background-color: #1e1e2f; }
  .dark-mode .sidebar a { color: #ccc; }
  .dark-mode .sidebar a.active, .dark-mode .sidebar a:hover { background-color: #333; border-left: 5px solid #6e62f4; }
  .dark-mode .main .section { background-color: #222; color: #eee; }
  .dark-mode .btn { background-color: #333; color: #eee; }
  .dark-mode table th { background-color: #444; }
  .dark-mode table td { background-color: #333; }
  .dark-mode .section { background-color: #222; color: #eee;}

</style>
</head>

<body>
<div class="header">
  SGD Multisenluz
  <button id="modoToggle" onclick="toggleDarkMode()" style="background: none; border: none; color: white; font-size: 16px; cursor: pointer;">
    🌙 Modo Oscuro
  </button>
</div>

<div class="container">
  <div class="sidebar">
    <div>
      <h3>Administrador</h3>
      <a href="?seccion=usuarios" class="<?= $seccion === 'usuarios' ? 'active' : '' ?>">Visualizar Usuarios</a>
      <a href="?seccion=registrar_usuario" class="<?= $seccion === 'registrar_usuario' ? 'active' : '' ?>">Registrar Usuario</a>
      <a href="?seccion=cursos" class="<?= $seccion === 'cursos' ? 'active' : '' ?>">Visualizar Cursos</a>
      <a href="?seccion=estudiantes" class="<?= $seccion === 'estudiantes' ? 'active' : '' ?>">Visualizar Estudiantes</a>
      <a href="?seccion=registrar_estudiante" class="<?= $seccion === 'registrar_estudiante' ? 'active' : '' ?>">Registrar Estudiante</a>
      <a href="?seccion=actividad" class="<?= $seccion === 'actividad' ? 'active' : '' ?>">Registro de Actividad</a>

      <h3>Documentos</h3>
      <a href="?seccion=documentos" class="<?= $seccion === 'documentos' ? 'active' : '' ?>">Tabla de Documentos</a>
      <a href="?seccion=subir_documento" class="<?= $seccion === 'subir_documento' ? 'active' : '' ?>">Subir Documentos</a>

      <h3>Asignaciones</h3>
      <a href="?seccion=asignaciones" class="<?= $seccion === 'asignaciones' ? 'active' : '' ?>">Gestión de Asignaciones</a>
    </div>
  </div>

  <div class="main">
    <div class="section">
      <?php
      // Incluir la página correspondiente
      $allowedPages = [
        'usuarios', 'registrar_usuario', 'cursos', 'estudiantes',
        'registrar_estudiante', 'actividad', 'documentos', 'subir_documento', 'asignaciones'
      ];

      if (in_array($seccion, $allowedPages)) {
        $file = "pages/$seccion.php";
        if (file_exists($file)) {
          include $file;
        } else {
          echo "<h2>Página no encontrada</h2>";
        }
      } else {
        echo "<h2>Error 404 - Sección no encontrada</h2>";
      }
      ?>
    </div>
  </div>
</div>

<script>
function toggleDarkMode() {
  document.documentElement.classList.toggle('dark-mode');
  const darkMode = document.documentElement.classList.contains('dark-mode');
  localStorage.setItem('modo-oscuro', darkMode);
  actualizarBotonModo(darkMode);
}

function actualizarBotonModo(darkMode) {
  const boton = document.getElementById('modoToggle');
  boton.textContent = darkMode ? "☀️ Modo Claro" : "🌙 Modo Oscuro";
}

// Al cargar la página, ajusta el texto del botón
if (localStorage.getItem('modo-oscuro') === 'true') {
  actualizarBotonModo(true);
} else {
  actualizarBotonModo(false);
}
</script>

</body>
</html>
