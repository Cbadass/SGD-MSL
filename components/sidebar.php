<?php
// components/sidebar.php (actualizado: se ocultan ítems solicitados)
// La sesión ya está abierta desde index.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$rol     = strtoupper($_SESSION['usuario']['permisos'] ?? 'GUEST');
$seccion = $_GET['seccion'] ?? 'perfil';

if (!function_exists('is_active')) {
  function is_active(string $name, string $current): string {
    return $name === $current ? 'active' : '';
  }
}
if (!function_exists('details_open')) {
  function details_open(array $items, string $current): string {
    return in_array($current, $items, true) ? ' open' : '';
  }
}

$grupoPerfil       = ['perfil'];
$grupoUsuarios     = ['usuarios','registrar_usuario','modificar_profesional']; // dejamos modificar_profesional
$grupoCursos       = ['cursos','registrar_curso']; // removido 'modificar_curso'
$grupoEstudiantes  = ['estudiantes','registrar_estudiante']; // removido 'modificar_estudiante'
$grupoApoderados   = ['apoderados','registrar_apoderado']; // removido 'modificar_apoderado'
$grupoDocumentos   = ['documentos','subir_documento']; // removido 'modificar_documento'
$grupoAsignaciones = ['asignaciones'];
$grupoActividad    = ['actividad'];
$grupoCatalogos    = ['tipos_documento','cargos','afps','bancos'];

$showAdmin        = ($rol === 'ADMIN');
$showDirector     = ($rol === 'DIRECTOR');
$showProfesional  = ($rol === 'PROFESIONAL');

$canSeeUsuarios     = $showAdmin || $showDirector;
$canSeeCursos       = $showAdmin || $showDirector || $showProfesional;
$canSeeEstudiantes  = $showAdmin || $showDirector || $showProfesional;
$canSeeApoderados   = $showAdmin || $showDirector || $showProfesional;
$canSeeDocumentos   = $showAdmin || $showDirector || $showProfesional;
$canSeeAsignaciones = $showAdmin || $showDirector || $showProfesional;
$canSeeActividad    = $showAdmin || $showDirector;
$canSeeCatalogos    = $showAdmin || $showDirector;
?>
<aside class="sidebar">
  <nav class="sidebar-nav">
    <!-- PERFIL -->
    <details<?= details_open($grupoPerfil, $seccion) ?>>
      <summary>Mi Perfil</summary>
      <div class="group-links">
        <a href="index.php?seccion=perfil" class="<?= is_active('perfil', $seccion) ?>">Perfil</a>
      </div>
    </details>

    <!-- USUARIOS -->
    <?php if ($canSeeUsuarios): ?>
      <details<?= details_open($grupoUsuarios, $seccion) ?>>
        <summary>Usuarios / Profesionales</summary>
        <div class="group-links">
          <a href="index.php?seccion=usuarios" class="<?= is_active('usuarios', $seccion) ?>">Visualizar Usuarios</a>
          <a href="index.php?seccion=registrar_usuario" class="<?= is_active('registrar_usuario', $seccion) ?>">Registrar Usuario</a>
          <a href="index.php?seccion=modificar_profesional" class="<?= is_active('modificar_profesional', $seccion) ?>">Modificar Profesional</a>
        </div>
      </details>
    <?php endif; ?>

    <!-- CURSOS -->
    <?php if ($canSeeCursos): ?>
      <details<?= details_open($grupoCursos, $seccion) ?>>
        <summary>Cursos</summary>
        <div class="group-links">
          <a href="index.php?seccion=cursos" class="<?= is_active('cursos', $seccion) ?>">Listado de Cursos</a>
          <?php if ($showAdmin || $showDirector): ?>
            <a href="index.php?seccion=registrar_curso" class="<?= is_active('registrar_curso', $seccion) ?>">Registrar Curso</a>
          <?php endif; ?>
        </div>
      </details>
    <?php endif; ?>

    <!-- ESTUDIANTES -->
    <?php if ($canSeeEstudiantes): ?>
      <details<?= details_open($grupoEstudiantes, $seccion) ?>>
        <summary>Estudiantes</summary>
        <div class="group-links">
          <a href="index.php?seccion=estudiantes" class="<?= is_active('estudiantes', $seccion) ?>">Listado de Estudiantes</a>
          <?php if ($showAdmin || $showDirector): ?>
            <a href="index.php?seccion=registrar_estudiante" class="<?= is_active('registrar_estudiante', $seccion) ?>">Registrar Estudiante</a>
          <?php endif; ?>
        </div>
      </details>
    <?php endif; ?>

    <!-- APODERADOS -->
    <?php if ($canSeeApoderados): ?>
      <details<?= details_open($grupoApoderados, $seccion) ?>>
        <summary>Apoderados</summary>
        <div class="group-links">
          <a href="index.php?seccion=apoderados" class="<?= is_active('apoderados', $seccion) ?>">Listado de Apoderados</a>
          <?php if ($showAdmin || $showDirector): ?>
            <a href="index.php?seccion=registrar_apoderado" class="<?= is_active('registrar_apoderado', $seccion) ?>">Registrar Apoderado</a>
          <?php endif; ?>
        </div>
      </details>
    <?php endif; ?>

    <!-- DOCUMENTOS -->
    <?php if ($canSeeDocumentos): ?>
      <details<?= details_open($grupoDocumentos, $seccion) ?>>
        <summary>Documentos</summary>
        <div class="group-links">
          <a href="index.php?seccion=documentos" class="<?= is_active('documentos', $seccion) ?>">Documentos</a>
          <a href="index.php?seccion=subir_documento" class="<?= is_active('subir_documento', $seccion) ?>">Subir documento</a>
        </div>
      </details>
    <?php endif; ?>

    <!-- ASIGNACIONES -->
    <?php if ($canSeeAsignaciones): ?>
      <details<?= details_open($grupoAsignaciones, $seccion) ?>>
        <summary>Asignaciones</summary>
        <div class="group-links">
          <a href="index.php?seccion=asignaciones" class="<?= is_active('asignaciones', $seccion) ?>">Ver Asignaciones</a>
        </div>
      </details>
    <?php endif; ?>

    <!-- ACTIVIDAD -->
    <?php if ($canSeeActividad): ?>
      <details<?= details_open($grupoActividad, $seccion) ?>>
        <summary>Actividad</summary>
        <div class="group-links">
          <a href="index.php?seccion=actividad" class="<?= is_active('actividad', $seccion) ?>">Registro de actividad</a>
        </div>
      </details>
    <?php endif; ?>

    <!-- CATÁLOGOS -->
    <?php if ($canSeeCatalogos): ?>
      <details<?= details_open($grupoCatalogos, $seccion) ?>>
        <summary>Catálogos</summary>
        <div class="group-links">
          <a href="index.php?seccion=tipos_documento" class="<?= is_active('tipos_documento', $seccion) ?>">Tipos de documento</a>
          <a href="index.php?seccion=cargos" class="<?= is_active('cargos', $seccion) ?>">Cargos</a>
          <a href="index.php?seccion=afps" class="<?= is_active('afps', $seccion) ?>">AFPs</a>
          <a href="index.php?seccion=bancos" class="<?= is_active('bancos', $seccion) ?>">Bancos</a>
        </div>
      </details>
    <?php endif; ?>
  </nav>
</aside>
