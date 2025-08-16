<?php
// components/sidebar.php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Rol actual (ADMIN / DIRECTOR / PROFESIONAL)
$rol = $_SESSION['usuario']['permisos'] ?? 'GUEST';

// Asegurar $seccion para marcar activo (por si no vino de index.php)
$seccion = $seccion ?? ($_GET['seccion'] ?? 'usuarios');

// Helper corto para marcar activo
function active($s, $seccion) { return $seccion === $s ? 'active' : ''; }
?>
<aside class="sidebar">
  <nav>
    <!-- Inicio (visible para todos los roles válidos) -->
    <h3>Inicio</h3>
    <a href="?seccion=perfil" class="<?= active('perfil', $seccion) ?>">Mi Perfil</a>

    <?php if (in_array($rol, ['ADMIN','DIRECTOR'], true)): ?>
      <h3>Profesionales</h3>
      <a href="?seccion=usuarios" class="<?= active('usuarios', $seccion) ?>">Profesionales</a>
      <a href="?seccion=registrar_usuario" class="<?= active('registrar_usuario', $seccion) ?>">Registrar Profesional</a>

      <h3>Estudiantes</h3>
      <a href="?seccion=estudiantes" class="<?= active('estudiantes', $seccion) ?>">Estudiantes</a>
      <a href="?seccion=registrar_estudiante" class="<?= active('registrar_estudiante', $seccion) ?>">Registrar Estudiante</a>

      <h3>Apoderados</h3>
      <a href="?seccion=apoderados" class="<?= active('apoderados', $seccion) ?>">Apoderados</a>
      <a href="?seccion=registrar_apoderado" class="<?= active('registrar_apoderado', $seccion) ?>">Registrar Apoderado</a>

      <h3>Cursos</h3>
      <a href="?seccion=cursos" class="<?= active('cursos', $seccion) ?>">Cursos</a>
      <a href="?seccion=registrar_curso" class="<?= active('registrar_curso', $seccion) ?>">Crear Curso</a>

      <h3>Documentos</h3>
      <a href="?seccion=documentos" class="<?= active('documentos', $seccion) ?>">Documentos</a>
      <a href="?seccion=subir_documento" class="<?= active('subir_documento', $seccion) ?>">Subir Documento</a>

      <h3>Asignaciones</h3>
      <a href="?seccion=asignaciones" class="<?= active('asignaciones', $seccion) ?>">Asignaciones</a>

      <h3>Actividad</h3>
      <a href="?seccion=actividad" class="<?= active('actividad', $seccion) ?>">Registro de actividad</a>
    <?php endif; ?>

    <?php if ($rol === 'PROFESIONAL'): ?>
      <!-- Para PROFESIONAL solo estos tabs -->
      <h3>Mi trabajo</h3>
      <a href="?seccion=cursos" class="<?= active('cursos', $seccion) ?>">Cursos</a>
      <a href="?seccion=estudiantes" class="<?= active('estudiantes', $seccion) ?>">Estudiantes</a>
      <a href="?seccion=apoderados" class="<?= active('apoderados', $seccion) ?>">Apoderados</a>
      <a href="?seccion=documentos" class="<?= active('documentos', $seccion) ?>">Documentos</a>
      <!-- Importante: PROFESIONAL NO ve Usuarios, Asignaciones ni Actividad -->
    <?php endif; ?>

    <h3>Cuenta</h3>
    <a href="logout.php">Cerrar sesión</a>
  </nav>
</aside>
