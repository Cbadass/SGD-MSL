<?php
// pages/perfil.php
session_start();
require_once __DIR__ . '/../includes/db.php';

// 1) Determinar qué perfil cargar
$id_prof = intval($_GET['Id_profesional'] ?? 0);
$id_apo  = intval($_GET['Id_apoderado']    ?? 0);
$id_est  = intval($_GET['Id_estudiante']   ?? 0);

// Si no vienen parámetros, cargamos el perfil del usuario en sesión (profesional)
if (!$id_prof && !$id_apo && !$id_est) {
    $uid = $_SESSION['usuario']['id'];
    $stmt = $conn->prepare("SELECT Id_profesional FROM usuarios WHERE Id_usuario = ?");
    $stmt->execute([$uid]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($r && $r['Id_profesional']) {
        $id_prof = (int)$r['Id_profesional'];
    }
}

// 2) Si nos pasaron Id_estudiante, determinamos su apoderado
if ($id_est && !$id_apo) {
    $stmt = $conn->prepare("SELECT Id_apoderado FROM estudiantes WHERE Id_estudiante = ?");
    $stmt->execute([$id_est]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $id_apo = $r['Id_apoderado'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Perfil</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="<?= ($_COOKIE['modo_oscuro'] ?? 'false') === 'true' ? 'dark-mode' : '' ?>">
  <?php include __DIR__ . '/../components/header.php'; ?>
  <div class="container d-flex">
    <?php include __DIR__ . '/../components/sidebar.php'; ?>
    <main class="main">

      <?php if ($id_prof): 
        // === Perfil PROFESIONAL ===
        $stmt = $conn->prepare("
          SELECT p.*, 
                 u.Nombre_usuario, u.Permisos, u.Estado_usuario, 
                 esc.Nombre_escuela
            FROM profesionales p
       LEFT JOIN usuarios      u   ON u.Id_profesional = p.Id_profesional
       LEFT JOIN escuelas      esc ON esc.Id_escuela   = p.Id_escuela_prof
           WHERE p.Id_profesional = ?
        ");
        $stmt->execute([$id_prof]);
        $prof = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($prof): ?>

        <h2 class="mb-4">Perfil Profesional</h2>
        <div class="card p-4 mb-4">
          <div class="form-grid">
            <div><label class="form-label">Usuario</label><div><?= htmlspecialchars($prof['Nombre_usuario']) ?></div></div>
            <div><label class="form-label">Permisos</label><div><?= htmlspecialchars($prof['Permisos']) ?></div></div>
            <div><label class="form-label">Estado</label><div><?= $prof['Estado_usuario']==1 ? 'Activo' : 'Inactivo' ?></div></div>
            <div><label class="form-label">Nombres</label><div><?= htmlspecialchars($prof['Nombre_profesional']) ?></div></div>
            <div><label class="form-label">Apellidos</label><div><?= htmlspecialchars($prof['Apellido_profesional']) ?></div></div>
            <div><label class="form-label">RUT</label><div><?= htmlspecialchars($prof['Rut_profesional']) ?></div></div>
            <div><label class="form-label">Nacimiento</label><div><?= htmlspecialchars($prof['Nacimiento_profesional']) ?></div></div>
            <div><label class="form-label">Domicilio</label><div><?= htmlspecialchars($prof['Domicilio_profesional']) ?></div></div>
            <div><label class="form-label">Teléfono</label><div><?= htmlspecialchars($prof['Celular_profesional']) ?></div></div>
            <div><label class="form-label">Correo</label><div><?= htmlspecialchars($prof['Correo_profesional']) ?></div></div>
            <div><label class="form-label">Estado Civil</label><div><?= htmlspecialchars($prof['Estado_civil_profesional']) ?></div></div>
            <div><label class="form-label">Banco</label><div><?= htmlspecialchars($prof['Banco_profesional']) ?></div></div>
            <div><label class="form-label">Tipo de cuenta</label><div><?= htmlspecialchars($prof['Tipo_cuenta_profesional']) ?></div></div>
            <div><label class="form-label">Cuenta</label><div><?= htmlspecialchars($prof['Cuenta_B_profesional']) ?></div></div>
            <div><label class="form-label">AFP</label><div><?= htmlspecialchars($prof['AFP_profesional']) ?></div></div>
            <div><label class="form-label">Salud</label><div><?= htmlspecialchars($prof['Salud_profesional']) ?></div></div>
            <div><label class="form-label">Cargo</label><div><?= htmlspecialchars($prof['Cargo_profesional']) ?></div></div>
            <div><label class="form-label">Horas</label><div><?= htmlspecialchars($prof['Horas_profesional']) ?></div></div>
            <div><label class="form-label">Fecha Ingreso</label><div><?= htmlspecialchars($prof['Fecha_ingreso']) ?></div></div>
            <div><label class="form-label">Tipo Profesional</label><div><?= htmlspecialchars($prof['Tipo_profesional']) ?></div></div>
            <div><label class="form-label">Escuela</label><div><?= htmlspecialchars($prof['Nombre_escuela']) ?></div></div>
          </div>
          <div class="mt-3">
            <a href="index.php?seccion=modificar_profesional&Id_profesional=<?= $id_prof ?>"
               class="btn btn-sm btn-warning">Editar</a>
            <a href="index.php?seccion=documentos&id_prof=<?= $id_prof ?>&sin_estudiante=1"
               class="btn btn-sm btn-info">Documentos</a>
          </div>
        </div>

        <?php else: ?>
          <p class="text-warning">Profesional no encontrado.</p>
        <?php endif; ?>

      <?php elseif ($id_apo): 
        // === Perfil APODERADO ===
        $stmt = $conn->prepare("SELECT * FROM apoderados WHERE Id_apoderado = ?");
        $stmt->execute([$id_apo]);
        $apo = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($apo): ?>

        <h2 class="mb-4">Perfil Apoderado</h2>
        <div class="card p-4 mb-4">
          <div class="form-grid">
            <div><label class="form-label">Nombres</label><div><?= htmlspecialchars($apo['Nombre_apoderado']) ?></div></div>
            <div><label class="form-label">Apellidos</label><div><?= htmlspecialchars($apo['Apellido_apoderado']) ?></div></div>
            <div><label class="form-label">RUT</label><div><?= htmlspecialchars($apo['Rut_apoderado']) ?></div></div>
            <div><label class="form-label">Teléfono</label><div><?= htmlspecialchars($apo['Numero_apoderado']) ?></div></div>
            <div><label class="form-label">Correo</label><div><?= htmlspecialchars($apo['Correo_apoderado']) ?></div></div>
            <div><label class="form-label">Escolaridad Padre</label><div><?= htmlspecialchars($apo['Escolaridad_padre']) ?></div></div>
            <div><label class="form-label">Escolaridad Madre</label><div><?= htmlspecialchars($apo['Escolaridad_madre']) ?></div></div>
            <div><label class="form-label">Ocupación Padre</label><div><?= htmlspecialchars($apo['Ocupacion_padre']) ?></div></div>
            <div><label class="form-label">Ocupación Madre</label><div><?= htmlspecialchars($apo['Ocupacion_madre']) ?></div></div>
          </div>
          <div class="mt-3">
            <a href="index.php?seccion=modificar_apoderado&Id_apoderado=<?= $id_apo ?>"
               class="btn btn-sm btn-warning">Editar</a>
          </div>
        </div>

        <?php
        // === Estudiantes vinculados ===
        $stmt = $conn->prepare("
          SELECT 
            e.Id_estudiante,
            e.Nombre_estudiante, e.Apellido_estudiante,
            e.Rut_estudiante, e.Fecha_nacimiento, e.Fecha_ingreso,
            e.Estado_estudiante, e.Id_curso, e.Id_escuela,
            c.Tipo_curso, c.Grado_curso, c.seccion_curso,
            esc.Nombre_escuela
          FROM estudiantes e
     LEFT JOIN cursos   c   ON e.Id_curso   = c.Id_curso
     LEFT JOIN escuelas esc ON e.Id_escuela = esc.Id_escuela
         WHERE e.Id_apoderado = ?
         ORDER BY e.Apellido_estudiante, e.Nombre_estudiante
        ");
        $stmt->execute([$id_apo]);
        $hijos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($hijos): ?>

        <h3 class="mb-4 subtitle">Estudiantes vinculados</h3>
        <div style="max-height:400px; overflow-y:auto; border-radius:10px;">
          <table class="table table-striped table-bordered">
            <thead class="table-dark">
              <tr>
                <th>Nombre completo</th>
                <th>RUT</th>
                <th>Edad</th>
                <th>Ingreso</th>
                <th>Estado</th>
                <th>Curso</th>
                <th>Escuela</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php 
                $hoy = new DateTime();
                foreach ($hijos as $h): 
                  $nac = new DateTime($h['Fecha_nacimiento']);
                  $edad = $hoy->diff($nac)->y;
                  $full = "{$h['Nombre_estudiante']} {$h['Apellido_estudiante']}";
                  $curso = "{$h['Tipo_curso']}-{$h['Grado_curso']}-{$h['seccion_curso']}";
              ?>
              <tr>
                <td><?= htmlspecialchars($full) ?></td>
                <td><?= htmlspecialchars($h['Rut_estudiante']) ?></td>
                <td><?= $edad ?></td>
                <td><?= htmlspecialchars($h['Fecha_ingreso']) ?></td>
                <td><?= $h['Estado_estudiante']==1?'Activo':'Inactivo' ?></td>
                <td><?= htmlspecialchars($curso) ?></td>
                <td><?= htmlspecialchars($h['Nombre_escuela']) ?></td>
                <td>
                  <a href="index.php?seccion=modificar_estudiante&Id_estudiante=<?= $h['Id_estudiante'] ?>"
                     class="btn btn-sm btn-warning">Editar</a>
                  <a href="index.php?seccion=documentos&id_estudiante=<?= $h['Id_estudiante'] ?>&sin_profesional=1"
                     class="btn btn-sm btn-info">Documentos</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php else: ?>
          <p class="text-info">No se encontraron estudiantes vinculados.</p>
        <?php endif; // hijos ?>

        <?php else: ?>
          <p class="text-warning">Apoderado no encontrado.</p>
        <?php endif; // apo ?>

      <?php else: ?>
        <p class="text-warning">No se ha especificado un perfil para mostrar.</p>
      <?php endif; // prof vs apo ?>

    </main>
  </div>
</body>
</html>
