<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}
$seccion = $_GET['seccion'] ?? 'usuarios';
$modo_oscuro = $_COOKIE['modo_oscuro'] ?? 'false';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
  <?php include 'components/header.php'; ?>

  <div class="container">
    <?php include 'components/sidebar.php'; ?>

    <main class="main">
      <section class="section">
        <?php
        // Lista de secciones válidas
        $allowed = [
          'perfil',

          'usuarios',
          'registrar_usuario',
          'modificar_profesional',

          'cursos',
          'registrar_curso',
          'modificar_curso',

          'estudiantes',
          'registrar_estudiante',
          'modificar_estudiante',

          'apoderados',
          'registrar_apoderado',
          'modificar_apoderado',

          'documentos',
          'subir_documento',
          'modificar_documento',

          'asignaciones',
          'actividad'
        ];

        $seccion = $_GET['seccion'] ?? 'usuarios';
        $file    = in_array($seccion, $allowed)
                 ? __DIR__ . "/pages/{$seccion}.php"
                 : __DIR__ . "/pages/error404.php";

        if (! file_exists($file)) {
            die("ERROR: Archivo no encontrado: $file");
        }

        include $file;
        ?>
      </section>
    </main>
  </div>

  <script>
    document.getElementById('modoToggle').addEventListener('click', () => {
      const dark = document.body.classList.toggle('dark-mode');
      document.cookie = `modo_oscuro=${dark}; path=/; max-age=31536000`;
    });
  </script>
</body>
</html>
