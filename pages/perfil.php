<?php
// pages/perfil.php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/roles.php';

if (empty($_SESSION['usuario'])) {
    header('Location: ../login.php');
    exit;
}

$usuarioSesion = $_SESSION['usuario'];
$alcance = getAlcanceUsuario($conn, $usuarioSesion);
$diagnosticos = $alcance['diagnosticos'] ?? [];

$id_prof = (int)($_GET['Id_profesional'] ?? 0);
$id_apo  = (int)($_GET['Id_apoderado'] ?? 0);
$id_est  = (int)($_GET['Id_estudiante'] ?? 0);

// Si no se especifica ningún parámetro se intenta mostrar el perfil del profesional en sesión.
if (!$id_prof && !$id_apo && !$id_est) {
    $idProfesionalSesion = (int)($usuarioSesion['id_profesional'] ?? 0);
    if ($idProfesionalSesion > 0) {
        $id_prof = $idProfesionalSesion;
    }
}

$actorUserId = (int)($usuarioSesion['id'] ?? $usuarioSesion['Id_usuario'] ?? 0);
$mensaje = '';
$prof = null;
$apo = null;
$hijos = [];

// Si se recibe un Id_estudiante se valida el alcance antes de usarlo para derivar al apoderado.
if ($id_est > 0) {
    if (!puedeAccederEstudiante($conn, $alcance, $id_est)) {
        http_response_code(403);
        require __DIR__ . '/error403.php';
        return;
    }
    $stmt = $conn->prepare('SELECT Id_apoderado FROM estudiantes WHERE Id_estudiante = ?');
    $stmt->execute([$id_est]);
    $rel = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($rel && !empty($rel['Id_apoderado'])) {
        $id_apo = (int)$rel['Id_apoderado'];
    } elseif (!$id_apo) {
        $mensaje = 'El estudiante seleccionado no tiene un apoderado asignado.';
    }
}

if ($id_prof > 0) {
    if (!puedeAccederProfesional($conn, $alcance, $id_prof)) {
        http_response_code(403);
        require __DIR__ . '/error403.php';
        return;
    }

    $stmt = $conn->prepare('
          SELECT p.*,
                 u.Id_usuario AS UidUsuario, u.Nombre_usuario, u.Permisos, u.Estado_usuario,
                 esc.Nombre_escuela
            FROM profesionales p
       LEFT JOIN usuarios      u   ON u.Id_profesional = p.Id_profesional
       LEFT JOIN escuelas      esc ON esc.Id_escuela   = p.Id_escuela_prof
           WHERE p.Id_profesional = ?
    ');
    $stmt->execute([$id_prof]);
    $prof = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$prof) {
        $mensaje = 'Profesional no encontrado.';
    }
} elseif ($id_apo > 0) {
    if (!puedeAccederApoderado($conn, $alcance, $id_apo)) {
        http_response_code(403);
        require __DIR__ . '/error403.php';
        return;
    }

    $stmt = $conn->prepare('SELECT * FROM apoderados WHERE Id_apoderado = ?');
    $stmt->execute([$id_apo]);
    $apo = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($apo) {
        $stmt = $conn->prepare('
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
        ');
        $stmt->execute([$id_apo]);
        $hijos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $mensaje = 'Apoderado no encontrado.';
    }
}
?>
<div class="container d-flex">
  <main class="main">
    <?php if (!empty($diagnosticos)): ?>
      <div class="alert alert-info">
        <?php foreach ($diagnosticos as $diag): ?>
          <p class="mb-1"><?= htmlspecialchars($diag) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($mensaje): ?>
      <div class="alert alert-warning"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($prof): ?>
      <?php
        $puedeEditarPerfil = canEditProfile(
          $alcance['rol'],
          $actorUserId,
          isset($prof['UidUsuario']) ? (int)$prof['UidUsuario'] : null,
          $alcance['escuela_id'] ?? null,
          isset($prof['Id_escuela_prof']) ? (int)$prof['Id_escuela_prof'] : null
        );
        $puedeVerDocumentos = $alcance['rol'] !== 'PROFESIONAL'
          || (int)($alcance['id_profesional'] ?? 0) === (int)$prof['Id_profesional'];
        $esPerfilPropio = !empty($prof['UidUsuario']) && $actorUserId === (int)$prof['UidUsuario'];
      ?>
      <h2 class="mb-4">Perfil Profesional</h2>
      <div class="card p-4 mb-4 profile">
        <div class="form-grid">
          <div><label class="form-label">Usuario</label><div><?= htmlspecialchars($prof['Nombre_usuario']) ?></div></div>
          <div><label class="form-label">Permisos</label><div><?= htmlspecialchars($prof['Permisos']) ?></div></div>
          <div><label class="form-label">Estado</label><div><?= ((int)$prof['Estado_usuario'] === 1) ? 'Activo' : 'Inactivo' ?></div></div>
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
          <?php if ($puedeEditarPerfil): ?>
            <button class="btn btn-sm btn-height btn-warning mr-1">
              <a class="link-text" href="index.php?seccion=modificar_profesional&Id_profesional=<?= $id_prof ?>">Editar</a>
            </button>
          <?php endif; ?>
          <?php if ($puedeVerDocumentos): ?>
            <button class="btn btn-sm btn-height btn-info mr-1">
              <a class="link-text" href="index.php?seccion=documentos&id_prof=<?= $id_prof ?>&sin_estudiante=1">Documentos</a>
            </button>
          <?php endif; ?>
        </div>
        <?php if ($esPerfilPropio): ?>
          <div class="card border-info mt-3" style="max-width:480px;">
            <div class="card-body">
              <h3 class="h5">Protege tu cuenta</h3>
              <p class="mb-2">Actualiza tu contraseña de forma segura siguiendo estos pasos:</p>
              <ul class="mb-3 pl-3">
                <li>Ingresa tu contraseña actual para confirmar tu identidad.</li>
                <li>Define una nueva contraseña distinta, con al menos 8 caracteres.</li>
                <li>Confirma la nueva contraseña antes de guardar los cambios.</li>
              </ul>
              <a class="btn btn-warning btn-sm" href="index.php?seccion=mi_password_update">🔒 Cambiar contraseña</a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php elseif ($apo): ?>
      <h2 class="mb-4">Perfil Apoderado</h2>
      <div class="card profile">
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
        <?php if (in_array($alcance['rol'], ['ADMIN', 'DIRECTOR'], true)): ?>
          <div class="mt-3">
            <button class="btn btn-sm btn-warning btn-height">
              <a href="index.php?seccion=modificar_apoderado&Id_apoderado=<?= $id_apo ?>" class="link-text">Editar</a>
            </button>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($hijos): ?>
        <h3 class="mb-4 subtitle">Estudiantes vinculados</h3>
        <div style="max-height:400px; overflow-y:auto; border-radius:10px;">
          <table class="table table-striped table-bordered">
            <thead class="table-dark">
              <tr>
                <th>Nombre Completo</th>
                <th>RUT</th>
                <th>Fecha de Nacimiento</th>
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
                  $puedeVerEst = puedeAccederEstudiante($conn, $alcance, (int)$h['Id_estudiante']);
                  $puedeEditarEst = $puedeVerEst && in_array($alcance['rol'], ['ADMIN', 'DIRECTOR'], true);
              ?>
              <tr>
                <td><?= htmlspecialchars($full) ?></td>
                <td><?= htmlspecialchars($h['Rut_estudiante']) ?></td>
                <td><?= htmlspecialchars($h['Fecha_nacimiento']) ?></td>
                <td><?= $edad ?></td>
                <td><?= htmlspecialchars($h['Fecha_ingreso']) ?></td>
                <td><?= ((int)$h['Estado_estudiante'] === 1) ? 'Activo' : 'Inactivo' ?></td>
                <td><?= htmlspecialchars($curso) ?></td>
                <td><?= htmlspecialchars($h['Nombre_escuela']) ?></td>
                <td>
                  <?php if ($puedeEditarEst): ?>
                    <a href="index.php?seccion=modificar_estudiante&Id_estudiante=<?= (int)$h['Id_estudiante'] ?>"
                       class="btn btn-sm btn-warning link-text">Editar</a>
                  <?php endif; ?>
                  <?php if ($puedeVerEst): ?>
                    <a href="index.php?seccion=documentos&id_estudiante=<?= (int)$h['Id_estudiante'] ?>&sin_profesional=1"
                       class="btn btn-sm btn-info link-text">Documentos</a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-info">No se encontraron estudiantes vinculados.</p>
      <?php endif; ?>
    <?php elseif (!$mensaje): ?>
      <p class="text-warning">No se ha especificado un perfil para mostrar.</p>
    <?php endif; ?>
  </main>
</div>
