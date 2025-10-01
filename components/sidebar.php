<?php
// components/sidebar.php
// Requisitos:
//  - Variable de sesión con usuario y permisos: $_SESSION['usuario']['permisos']
//  - Parámetro de routing de la app: $_GET['seccion']
//  - Este archivo se incluye desde layout principal (index.php)

// --- Seguridad básica de contexto
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$rol     = strtoupper($_SESSION['usuario']['permisos'] ?? 'GUEST');
$seccion = $_GET['seccion'] ?? 'perfil';

// --- Helpers locales (autónomos)
function is_active(string $name, string $current): string {
  return $name === $current ? 'active' : '';
}

/**
 * Abre automáticamente el <details> si la sección actual pertenece a ese grupo
 * @param array $items  Lista de slugs de secciones del grupo
 * @param string $current Sección actual
 */
function details_open(array $items, string $current): string {
  return in_array($current, $items, true) ? ' open' : '';
}

// --- Definición de grupos de navegación por rol
// Nota: Ajusta estos arrays si incorporas o renombras secciones.
$grupoPerfil = ['perfil'];

$grupoUsuarios = [
  'usuarios','registrar_usuario','modificar_profesional',
];

$grupoCursos = [
  'cursos','registrar_curso','modificar_curso',
];

$grupoEstudiantes = [
  'estudiantes','registrar_estudiante','modificar_estudiante',
];

$grupoApoderados = [
  'apoderados','registrar_apoderado','modificar_apoderado',
];

$grupoDocumentos = [
  'documentos','subir_documento','modificar_documento',
];

$grupoAsignaciones = [
  'asignaciones',
];

$grupoActividad = [
  'actividad',
];

$grupoClave = [
  'administrar_contraseña',
];

$grupoCatalogos = [
  'tipos_documento','cargos','afps','bancos',
];

// --- Visibilidad por rol
$showAdmin      = ($rol === 'ADMIN');
$showDirector   = ($rol === 'DIRECTOR');
$showProfesional= ($rol === 'PROFESIONAL');

// --- Política de visibilidad (sidebar):
// ADMIN: todo
// DIRECTOR: todo + catálogos (según requerimiento)
// PROFESIONAL: solo lo propio (sin catálogos, sin usuarios)
$canSeeUsuarios    = $showAdmin || $showDirector;
$canSeeCursos      = $showAdmin || $showDirector || $showProfesional;
$canSeeEstudiantes = $showAdmin || $showDirector || $showProfesional;
$canSeeApoderados  = $showAdmin || $showDirector || $showProfesional;
$canSeeDocumentos  = $showAdmin || $showDirector || $showProfesional;
$canSeeAsignaciones= $showAdmin || $showDirector || $showProfesional;
$canSeeActividad   = $showAdmin || $showDirector; // el profesional verá auditorías solo si decides habilitarlo
$canSeeClave       = $showAdmin || $showDirector || $showProfesional;
$canSeeCatalogos   = $showAdmin || $showDirector;

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

    <!-- USUARIOS (ADMIN/DIRECTOR) -->
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
          <a href="index.php?seccion=modificar_curso" class="<?= is_active('modificar_curso', $seccion) ?>">Modificar Curso</a>
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
          <a href="index.php?seccion=modificar_estudiante" class="<?= is_active('modificar_estudiante', $seccion) ?>">Modificar Estudiante</a>
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
          <a href="index.php?seccion=modificar_apoderado" class="<?= is_active('modificar_apoderado', $seccion) ?>">Modificar Apoderado</a>
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
          <a href="index.php?seccion=modificar_documento" class="<?= is_active('modificar_documento', $seccion) ?>">Modificar documento</a>
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

    <!-- ACTIVIDAD / AUDITORÍA (ADMIN/DIRECTOR) -->
    <?php if ($canSeeActividad): ?>
      <details<?= details_open($grupoActividad, $seccion) ?>>
        <summary>Actividad</summary>
        <div class="group-links">
          <a href="index.php?seccion=actividad" class="<?= is_active('actividad', $seccion) ?>">Registro de actividad</a>
        </div>
      </details>
    <?php endif; ?>

    <!-- ADMINISTRAR CONTRASEÑA -->
    <?php if ($canSeeClave): ?>
      <details<?= details_open($grupoClave, $seccion) ?>>
        <summary>Seguridad</summary>
        <div class="group-links">
          <a href="index.php?seccion=administrar_contraseña" class="<?= is_active('administrar_contraseña', $seccion) ?>">Administrar contraseña</a>
        </div>
      </details>
    <?php endif; ?>

    <!-- CATÁLOGOS (ADMIN/DIRECTOR) -->
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

<style>
/* Estilos mínimos por si tu CSS no los define aún */
.sidebar { width: 260px; padding: 10px; background: var(--sidebar-bg, #1f2937); color: #fff; }
.sidebar a { display:block; padding: 8px 10px; text-decoration:none; color:#e5e7eb; border-radius: 8px; }
.sidebar a.active { background:#374151; color:#fff; font-weight:600; }
.sidebar summary { cursor:pointer; padding:8px 10px; margin-top:6px; border-radius:10px; background:#111827; }
.sidebar .group-links { padding:6px 4px 10px 10px; }
.sidebar details[open] > summary { background:#0f172a; }
</style>
