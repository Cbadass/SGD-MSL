<?php
// components/sidebar.php
// La sesión ya está abierta por includes/session.php desde index.php

$rol = strtoupper($_SESSION['usuario']['permisos'] ?? 'GUEST');

// Asegura $seccion (fallback por rol)
if (!isset($seccion)) {
  $seccion = $_GET['seccion'] ?? null;
}
if ($seccion === null || $seccion === '') {
  if (in_array($rol, ['ADMIN','DIRECTOR'], true)) {
    $seccion = 'usuarios';
  } elseif ($rol === 'PROFESIONAL') {
    $seccion = 'cursos';
  } else {
    $seccion = 'perfil';
  }
}

// Helpers
function is_active(string $s, string $current): string {
  return $current === $s ? 'active' : '';
}
function details_open(array $list, string $current): string {
  return in_array($current, $list, true) ? ' open' : '';
}

// Grupos (para auto-open)
$grp_inicio        = ['perfil'];
$grp_profesionales = ['usuarios','registrar_usuario'];
$grp_estudiantes   = ['estudiantes','registrar_estudiante'];
$grp_apoderados    = ['apoderados','registrar_apoderado'];
$grp_cursos        = ['cursos','registrar_curso'];
$grp_documentos    = ['documentos','subir_documento'];
$grp_asignaciones  = ['asignaciones'];
$grp_actividad     = ['actividad'];
$grp_mi_trabajo    = ['cursos','estudiantes','apoderados','documentos'];
?>
<aside class="sidebar">
  <nav>
    <!-- (Opcional) mini estilos -->
    <style>
      .sidebar details { margin: 8px 0; }
      .sidebar summary {
        list-style: none; cursor: pointer; padding: 10px 16px; margin: 0 12px 6px 12px;
        border-radius: 10px; background: #fff; color: #333; font-weight: 600;
        user-select: none; outline: none; border-left: 5px solid #6e62f4;
      }
      .dark-mode .sidebar summary { background: #1f1b2e; color:#e7e5ff; border-left-color:#8b80ff; }
      .sidebar summary::-webkit-details-marker { display: none; }
      .sidebar details[open] > summary { background:#b2b0eb; }
      .dark-mode .sidebar details[open] > summary { background:#2f2a4a; }
      .sidebar .group-links { padding: 0 0 8px 0; }
      .sidebar .group-links a { display:block; margin: 2px 12px; }
      .sidebar .group-links a.active { font-weight:700; text-decoration:underline; }
      .sidebar h3 { display:none; }
    </style>

    <!-- INICIO -->
    <details<?= details_open($grp_inicio, $seccion) ?>>
      <summary>Inicio</summary>
      <div class="group-links">
        <a href="index.php?seccion=perfil" class="<?= is_active('perfil', $seccion) ?>">Mi Perfil</a>
      </div>
    </details>

    <?php if (in_array($rol, ['ADMIN','DIRECTOR'], true)): ?>
      <details<?= details_open($grp_profesionales, $seccion) ?>>
        <summary>Profesionales</summary>
        <div class="group-links">
          <a href="index.php?seccion=usuarios" class="<?= is_active('usuarios', $seccion) ?>">Profesionales</a>
          <a href="index.php?seccion=registrar_usuario" class="<?= is_active('registrar_usuario', $seccion) ?>">Registrar Profesional</a>
        </div>
      </details>

      <details<?= details_open($grp_estudiantes, $seccion) ?>>
        <summary>Estudiantes</summary>
        <div class="group-links">
          <a href="index.php?seccion=estudiantes" class="<?= is_active('estudiantes', $seccion) ?>">Estudiantes</a>
          <a href="index.php?seccion=registrar_estudiante" class="<?= is_active('registrar_estudiante', $seccion) ?>">Registrar Estudiante</a>
        </div>
      </details>

      <details<?= details_open($grp_apoderados, $seccion) ?>>
        <summary>Apoderados</summary>
        <div class="group-links">
          <a href="index.php?seccion=apoderados" class="<?= is_active('apoderados', $seccion) ?>">Apoderados</a>
          <a href="index.php?seccion=registrar_apoderado" class="<?= is_active('registrar_apoderado', $seccion) ?>">Registrar Apoderado</a>
        </div>
      </details>

      <details<?= details_open($grp_cursos, $seccion) ?>>
        <summary>Cursos</summary>
        <div class="group-links">
          <a href="index.php?seccion=cursos" class="<?= is_active('cursos', $seccion) ?>">Cursos</a>
          <a href="index.php?seccion=registrar_curso" class="<?= is_active('registrar_curso', $seccion) ?>">Crear Curso</a>
        </div>
      </details>

      <details<?= details_open($grp_documentos, $seccion) ?>>
        <summary>Documentos</summary>
        <div class="group-links">
          <a href="index.php?seccion=documentos" class="<?= is_active('documentos', $seccion) ?>">Documentos</a>
          <a href="index.php?seccion=subir_documento" class="<?= is_active('subir_documento', $seccion) ?>">Subir Documento</a>
        </div>
      </details>

      <details<?= details_open($grp_asignaciones, $seccion) ?>>
        <summary>Asignaciones</summary>
        <div class="group-links">
          <a href="index.php?seccion=asignaciones" class="<?= is_active('asignaciones', $seccion) ?>">Asignaciones</a>
        </div>
      </details>

      <details<?= details_open($grp_actividad, $seccion) ?>>
        <summary>Actividad</summary>
        <div class="group-links">
          <a href="index.php?seccion=actividad" class="<?= is_active('actividad', $seccion) ?>">Registro de actividad</a>
        </div>
      </details>
    <?php endif; ?>

    <?php if ($rol === 'PROFESIONAL'): ?>
      <details<?= details_open($grp_mi_trabajo, $seccion) ?>>
        <summary>Mi trabajo</summary>
        <div class="group-links">
          <a href="index.php?seccion=cursos" class="<?= is_active('cursos', $seccion) ?>">Cursos</a>
          <a href="index.php?seccion=estudiantes" class="<?= is_active('estudiantes', $seccion) ?>">Estudiantes</a>
          <a href="index.php?seccion=apoderados" class="<?= is_active('apoderados', $seccion) ?>">Apoderados</a>
          <a href="index.php?seccion=documentos" class="<?= is_active('documentos', $seccion) ?>">Documentos</a>
        </div>
      </details>
    <?php endif; ?>
  </nav>
</aside>
