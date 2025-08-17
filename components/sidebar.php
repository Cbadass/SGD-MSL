<?php
// components/sidebar.php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Rol actual (ADMIN / DIRECTOR / PROFESIONAL)
$rol = $_SESSION['usuario']['permisos'] ?? 'GUEST';

// Asegurar $seccion para marcar activo (por si no vino de index.php)
$seccion = $seccion ?? ($_GET['seccion'] ?? 'usuarios');

// Helpers
function active(string $s, string $seccion): string {
  return $seccion === $s ? 'active' : '';
}
function currentIn(array $list, string $seccion): bool {
  return in_array($seccion, $list, true);
}

// Grupos (para auto-open)
$grp_inicio       = ['perfil'];
$grp_profesionales= ['usuarios','registrar_usuario'];
$grp_estudiantes  = ['estudiantes','registrar_estudiante'];
$grp_apoderados   = ['apoderados','registrar_apoderado'];
$grp_cursos       = ['cursos','registrar_curso'];
$grp_documentos   = ['documentos','subir_documento'];
$grp_asignaciones = ['asignaciones'];
$grp_actividad    = ['actividad'];
$grp_mi_trabajo   = ['cursos','estudiantes','apoderados','documentos'];
?>
<aside class="sidebar">
  <nav>
    <!-- Estilos mínimos para que el summary luzca como "botón" y combine con tu CSS -->
    <style>
      .sidebar details { margin: 8px 0; }
      .sidebar summary {
        list-style: none; cursor: pointer; padding: 10px 16px; margin: 0 12px 6px 12px;
        border-radius: 10px; background:rgb(134, 130, 238); color: #333; font-weight: 600;
        user-select: none; outline: none; border-left: 5px solid #6e62f4;
      }
      .sidebar summary::-webkit-details-marker { display: none; }
      .sidebar details[open] > summary { background: #d3d2f3; }
      .sidebar .group-links { padding: 0 0 8px 0; }
      .sidebar .group-links a { display:block; margin: 2px 12px; }
      .sidebar h3 { display:none; } /* ocultamos h3 para usar summary como encabezado */
    </style>

    <!-- INICIO (visible para todos) -->
    <details <?= currentIn($grp_inicio, $seccion) ? 'open' : '' ?>>
      <summary>Inicio</summary>
      <div class="group-links">
        <a href="?seccion=perfil" class="<?= active('perfil', $seccion) ?>">Mi Perfil</a>
      </div>
    </details>

    <?php if (in_array($rol, ['ADMIN','DIRECTOR'], true)): ?>
      <details <?= currentIn($grp_profesionales, $seccion) ? 'open' : '' ?>>
        <summary>Profesionales</summary>
        <div class="group-links">
          <a href="?seccion=usuarios" class="<?= active('usuarios', $seccion) ?>">Profesionales</a>
          <a href="?seccion=registrar_usuario" class="<?= active('registrar_usuario', $seccion) ?>">Registrar Profesional</a>
        </div>
      </details>

      <details <?= currentIn($grp_estudiantes, $seccion) ? 'open' : '' ?>>
        <summary>Estudiantes</summary>
        <div class="group-links">
          <a href="?seccion=estudiantes" class="<?= active('estudiantes', $seccion) ?>">Estudiantes</a>
          <a href="?seccion=registrar_estudiante" class="<?= active('registrar_estudiante', $seccion) ?>">Registrar Estudiante</a>
        </div>
      </details>

      <details <?= currentIn($grp_apoderados, $seccion) ? 'open' : '' ?>>
        <summary>Apoderados</summary>
        <div class="group-links">
          <a href="?seccion=apoderados" class="<?= active('apoderados', $seccion) ?>">Apoderados</a>
          <a href="?seccion=registrar_apoderado" class="<?= active('registrar_apoderado', $seccion) ?>">Registrar Apoderado</a>
        </div>
      </details>

      <details <?= currentIn($grp_cursos, $seccion) ? 'open' : '' ?>>
        <summary>Cursos</summary>
        <div class="group-links">
          <a href="?seccion=cursos" class="<?= active('cursos', $seccion) ?>">Cursos</a>
          <a href="?seccion=registrar_curso" class="<?= active('registrar_curso', $seccion) ?>">Crear Curso</a>
        </div>
      </details>

      <details <?= currentIn($grp_documentos, $seccion) ? 'open' : '' ?>>
        <summary>Documentos</summary>
        <div class="group-links">
          <a href="?seccion=documentos" class="<?= active('documentos', $seccion) ?>">Documentos</a>
          <a href="?seccion=subir_documento" class="<?= active('subir_documento', $seccion) ?>">Subir Documento</a>
        </div>
      </details>

      <details <?= currentIn($grp_asignaciones, $seccion) ? 'open' : '' ?>>
        <summary>Asignaciones</summary>
        <div class="group-links">
          <a href="?seccion=asignaciones" class="<?= active('asignaciones', $seccion) ?>">Asignaciones</a>
        </div>
      </details>

      <details <?= currentIn($grp_actividad, $seccion) ? 'open' : '' ?>>
        <summary>Actividad</summary>
        <div class="group-links">
          <a href="?seccion=actividad" class="<?= active('actividad', $seccion) ?>">Registro de actividad</a>
        </div>
      </details>
    <?php endif; ?>

    <?php if ($rol === 'PROFESIONAL'): ?>
      <details <?= currentIn($grp_mi_trabajo, $seccion) ? 'open' : '' ?>>
        <summary>Mi trabajo</summary>
        <div class="group-links">
          <a href="?seccion=cursos" class="<?= active('cursos', $seccion) ?>">Cursos</a>
          <a href="?seccion=estudiantes" class="<?= active('estudiantes', $seccion) ?>">Estudiantes</a>
          <a href="?seccion=apoderados" class="<?= active('apoderados', $seccion) ?>">Apoderados</a>
          <a href="?seccion=documentos" class="<?= active('documentos', $seccion) ?>">Documentos</a>
        </div>
      </details>
    <?php endif; ?>
  </nav>
</aside>
