<?php
require_once __DIR__ . '/includes/session.php';
require_login(); // redirige a /login.php si no hay sesión

$modo_oscuro = $_COOKIE['modo_oscuro'] ?? 'false';
$rolActual   = strtoupper($_SESSION['usuario']['permisos'] ?? 'GUEST');

// --- Lista blanca de secciones
$ALLOWED = [
  'perfil',

  'usuarios','registrar_usuario','modificar_profesional',
  'cursos','registrar_curso','modificar_curso',
  'estudiantes','registrar_estudiante','modificar_estudiante',
  'apoderados','registrar_apoderado','modificar_apoderado',

  'documentos','subir_documento','modificar_documento',

  'asignaciones',
  'actividad',
  'administrar_contraseña',

  // ======== AGREGADOS (Catálogos) ========
  'tipos_documento','cargos','afps','bancos',
];

// --- Mapa secciones -> roles permitidos (403 si no coincide)
$SECTION_ROLES = [
  'perfil'                   => ['ADMIN','DIRECTOR','PROFESIONAL'],

  'usuarios'                 => ['ADMIN','DIRECTOR'],
  'registrar_usuario'        => ['ADMIN','DIRECTOR'],
  'modificar_profesional'    => ['ADMIN','DIRECTOR'],

  'cursos'                   => ['ADMIN','DIRECTOR','PROFESIONAL'],
  'registrar_curso'          => ['ADMIN','DIRECTOR'],
  'modificar_curso'          => ['ADMIN','DIRECTOR'],

  'estudiantes'              => ['ADMIN','DIRECTOR','PROFESIONAL'],
  'registrar_estudiante'     => ['ADMIN','DIRECTOR'],
  'modificar_estudiante'     => ['ADMIN','DIRECTOR'],

  'apoderados'               => ['ADMIN','DIRECTOR','PROFESIONAL'],
  'registrar_apoderado'      => ['ADMIN','DIRECTOR'],
  'modificar_apoderado'      => ['ADMIN','DIRECTOR'],

  'documentos'               => ['ADMIN','DIRECTOR','PROFESIONAL'],
  'subir_documento'          => ['ADMIN','DIRECTOR','PROFESIONAL'],
  'modificar_documento'      => ['ADMIN','DIRECTOR'],

  'asignaciones'             => ['ADMIN','DIRECTOR'],
  'actividad'                => ['ADMIN','DIRECTOR'],
  'administrar_contraseña'   => ['ADMIN','DIRECTOR','PROFESIONAL'],

  // ======== AGREGADOS (Catálogos) ========
  'tipos_documento'          => ['ADMIN','DIRECTOR'],
  'cargos'                   => ['ADMIN','DIRECTOR'],
  'afps'                     => ['ADMIN','DIRECTOR'],
  'bancos'                   => ['ADMIN','DIRECTOR'],
];

// --- Resolver sección (default por rol)
$seccion = $_GET['seccion'] ?? (
  in_array($rolActual, ['ADMIN','DIRECTOR'], true) ? 'usuarios'
  : ($rolActual === 'PROFESIONAL' ? 'cursos' : 'perfil')
);

// Sanitizar: basename para evitar ../
$seccion = basename($seccion);

// Validar lista blanca
if (!in_array($seccion, $ALLOWED, true)) {
  $seccion = 'perfil';
}

// Validar rol
$rolesPermitidos = $SECTION_ROLES[$seccion] ?? ['ADMIN','DIRECTOR','PROFESIONAL'];
if (!in_array($rolActual, $rolesPermitidos, true)) {
  http_response_code(403);
  $file = __DIR__ . "/pages/error403.php";
} else {
  $file = __DIR__ . "/pages/{$seccion}.php";
}

// Comprobar archivo
if (!file_exists($file)) {
  http_response_code(404);
  $file = __DIR__ . "/pages/error404.php";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>SGD Multisenluz</title>
  <link rel="icon" href="source/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="style.css">
  <style>
    body {
      background-color: <?= $modo_oscuro === 'true' ? '#121212' : '#e8e8fc' ?>;
      color: <?= $modo_oscuro === 'true' ? '#eee' : '#333' ?>;
    }
  </style>
</head>
<body class="<?= $modo_oscuro === 'true' ? 'dark-mode' : '' ?>">
  <?php include __DIR__ . '/components/header.php'; ?>

  <div class="container">
    <?php
      // pasa $seccion al sidebar para activo
      include __DIR__ . '/components/sidebar.php';
    ?>

    <main class="main">
      <section class="section">
        <?php include $file; ?>
      </section>
    </main>
  </div>

  <script>
    const btn = document.getElementById('modoToggle');
    if (btn) {
      btn.addEventListener('click', () => {
        const dark = document.body.classList.toggle('dark-mode');
        document.cookie = `modo_oscuro=${dark}; path=/; max-age=31536000`;
      });
    }
  </script>
</body>
</html>
