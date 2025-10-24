<?php
// components/sidebar.php (actualizado: se ocultan ítems solicitados)
// La sesión ya está abierta desde index.php

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$rol = strtoupper($_SESSION['usuario']['permisos'] ?? 'GUEST');
$seccion = $_GET['seccion'] ?? 'perfil';

if (!function_exists('is_active')) {
  function is_active(string $name, string $current): string
  {
    return $name === $current ? 'active' : '';
  }
}
if (!function_exists('details_open')) {
  function details_open(array $items, string $current): string
  {
    return in_array($current, $items, true) ? ' open' : '';
  }
}

$grupoPerfil = ['perfil', 'mi_password_update'];
$grupoUsuarios = ['usuarios', 'registrar_usuario', 'administrar_contraseña']; // removido modificar_profesional
$grupoCursos = ['cursos', 'registrar_curso']; // removido 'modificar_curso'
$grupoEstudiantes = ['estudiantes', 'registrar_estudiante']; // removido 'modificar_estudiante'
$grupoApoderados = ['apoderados', 'registrar_apoderado']; // removido 'modificar_apoderado'
$grupoDocumentos = ['documentos', 'subir_documento']; // removido 'modificar_documento'
$grupoAsignaciones = ['asignaciones'];
$grupoActividad = ['actividad'];
$grupoAuditoria = ['auditoria_vinculos'];
$grupoCatalogos = ['tipos_documento', 'cargos', 'afps', 'bancos'];

$showAdmin = ($rol === 'ADMIN');
$showDirector = ($rol === 'DIRECTOR');
$showProfesional = ($rol === 'PROFESIONAL');

$canSeeUsuarios = $showAdmin || $showDirector;
$canSeeCursos = $showAdmin || $showDirector || $showProfesional;
$canSeeEstudiantes = $showAdmin || $showDirector || $showProfesional;
$canSeeApoderados = $showAdmin || $showDirector || $showProfesional;
$canSeeDocumentos = $showAdmin || $showDirector || $showProfesional;
$canSeeAsignaciones = $showAdmin || $showDirector || $showProfesional;
$canSeeActividad = $showAdmin || $showDirector;
$canSeeCatalogos = $showAdmin || $showDirector;
?>
<aside class="sidebar">
  <nav class="sidebar-nav">
    <style>
      .sidebar details {
        margin: 8px 0;
      }

      .sidebar summary {
        list-style: none;
        cursor: pointer;
        padding: 10px 16px;
        margin: 0 12px 6px 12px;
        border-radius: 10px;
        background: #fff;
        color: #333;
        font-weight: 600;
        user-select: none;
        outline: none;
        border-left: 5px solid #6e62f4;
      }

      .dark-mode .sidebar summary {
        background: #1f1b2e;
        color: #e7e5ff;
        border-left-color: #8b80ff;
      }

      .sidebar summary::-webkit-details-marker {
        display: none;
      }

      .sidebar details[open]>summary {
        background: #b2b0eb;
      }

      .dark-mode .sidebar details[open]>summary {
        background: #2f2a4a;
      }

      .sidebar .group-links {
        padding: 0 0 8px 0;
      }

      .sidebar .group-links a {
        display: block;
        margin: 2px 12px;
      }

      .sidebar .group-links a.active {
        font-weight: 700;
        text-decoration: underline;
      }

      .sidebar h3 {
        display: none;
      }
    </style>
    <!-- PERFIL -->
    <details<?= details_open($grupoPerfil, $seccion) ?>>
      <summary>Mi Perfil</summary>
      <div class="group-links">
        <a href="index.php?seccion=perfil" class="<?= is_active('perfil', $seccion) ?>">Perfil</a>
        <a href="index.php?seccion=mi_password_update" class="<?= is_active('mi_password_update', $seccion) ?>">Cambiar contraseña</a>
      </div>
      </details>

      <!-- USUARIOS -->
      <?php if ($canSeeUsuarios): ?>
        <details<?= details_open($grupoUsuarios, $seccion) ?>>
          <summary>Usuarios / Profesionales</summary>
          <div class="group-links">
            <a href="index.php?seccion=usuarios" class="<?= is_active('usuarios', $seccion) ?>">Visualizar Profesionales</a>
            <a href="index.php?seccion=registrar_usuario"
              class="<?= is_active('registrar_usuario', $seccion) ?>">Registrar Profesional</a>
            <?php if ($showAdmin || $showDirector): ?>
              <a href="index.php?seccion=administrar_contraseña" class="<?= is_active('administrar_contraseña', $seccion) ?>">Gestionar contraseñas</a>
            <?php endif; ?>
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
                <a href="index.php?seccion=registrar_curso" class="<?= is_active('registrar_curso', $seccion) ?>">Registrar
                  Curso</a>
              <?php endif; ?>
            </div>
            </details>
          <?php endif; ?>

          <!-- ESTUDIANTES -->
          <?php if ($canSeeEstudiantes): ?>
            <details<?= details_open($grupoEstudiantes, $seccion) ?>>
              <summary>Estudiantes</summary>
              <div class="group-links">
                <a href="index.php?seccion=estudiantes" class="<?= is_active('estudiantes', $seccion) ?>">Listado de
                  Estudiantes</a>
                <?php if ($showAdmin || $showDirector): ?>
                  <a href="index.php?seccion=registrar_estudiante"
                    class="<?= is_active('registrar_estudiante', $seccion) ?>">Registrar Estudiante</a>
                <?php endif; ?>
              </div>
              </details>
            <?php endif; ?>

            <!-- APODERADOS -->
            <?php if ($canSeeApoderados): ?>
              <details<?= details_open($grupoApoderados, $seccion) ?>>
                <summary>Apoderados</summary>
                <div class="group-links">
                  <a href="index.php?seccion=apoderados" class="<?= is_active('apoderados', $seccion) ?>">Listado de
                    Apoderados</a>
                  <?php if ($showAdmin || $showDirector): ?>
                    <a href="index.php?seccion=registrar_apoderado"
                      class="<?= is_active('registrar_apoderado', $seccion) ?>">Registrar Apoderado</a>
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
                    <a href="index.php?seccion=subir_documento"
                      class="<?= is_active('subir_documento', $seccion) ?>">Subir documento</a>
                  </div>
                  </details>
                <?php endif; ?>

                <!-- ASIGNACIONES -->
                <?php if ($canSeeAsignaciones): ?>
                  <details<?= details_open($grupoAsignaciones, $seccion) ?>>
                    <summary>Asignaciones</summary>
                    <div class="group-links">
                      <a href="index.php?seccion=asignaciones" class="<?= is_active('asignaciones', $seccion) ?>">Ver
                        Asignaciones</a>
                    </div>
                    </details>
                  <?php endif; ?>

                  <!-- ACTIVIDAD -->
                  <?php if ($canSeeActividad): ?>
                    <details<?= details_open($grupoActividad, $seccion) ?>>
                      <summary>Actividad</summary>
                      <div class="group-links">
                        <a href="index.php?seccion=actividad" class="<?= is_active('actividad', $seccion) ?>">Registro de
                          actividad</a>
                      </div>
                      </details>
                  <?php endif; ?>

                  <!-- AUDITORÍA -->
                  <?php if ($showAdmin): ?>
                    <details<?= details_open($grupoAuditoria, $seccion) ?>>
                      <summary>Auditoría</summary>
                      <div class="group-links">
                        <a href="index.php?seccion=auditoria_vinculos" class="<?= is_active('auditoria_vinculos', $seccion) ?>">Vínculos pendientes</a>
                      </div>
                    </details>
                  <?php endif; ?>

                    <!-- CATÁLOGOS -->
                    <?php if ($canSeeCatalogos): ?>
                      <details<?= details_open($grupoCatalogos, $seccion) ?>>
                        <summary>Catálogos</summary>
                        <div class="group-links">
                          <a href="index.php?seccion=tipos_documento"
                            class="<?= is_active('tipos_documento', $seccion) ?>">Tipos de documento</a>
                          <a href="index.php?seccion=cargos" class="<?= is_active('cargos', $seccion) ?>">Cargos</a>
                          <a href="index.php?seccion=afps" class="<?= is_active('afps', $seccion) ?>">AFPs</a>
                          <a href="index.php?seccion=bancos" class="<?= is_active('bancos', $seccion) ?>">Bancos</a>
                        </div>
                        </details>
                      <?php endif; ?>
  </nav>
</aside>