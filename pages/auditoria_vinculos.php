<?php
// pages/auditoria_vinculos.php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/roles.php';

requireAnyRole(['ADMIN']);

$usuariosSinProfesional = $conn->query('
    SELECT Id_usuario, Nombre_usuario, Permisos
      FROM usuarios
     WHERE Id_profesional IS NULL OR Id_profesional = 0
     ORDER BY Id_usuario
')->fetchAll(PDO::FETCH_ASSOC);

$profesSinEscuelaStmt = $conn->prepare('
    SELECT Id_profesional, Nombre_profesional, Apellido_profesional, Correo_profesional
      FROM profesionales
     WHERE Id_escuela_prof IS NULL OR Id_escuela_prof = 0
     ORDER BY Id_profesional
');
$profesSinEscuelaStmt->execute();
$profesSinEscuela = $profesSinEscuelaStmt->fetchAll(PDO::FETCH_ASSOC);

$asignacionesHuerfanasStmt = $conn->prepare('
    SELECT a.Id_asignacion,
           a.Id_profesional,
           p.Nombre_profesional,
           a.Id_estudiante,
           e.Nombre_estudiante
      FROM Asignaciones a
 LEFT JOIN profesionales p ON a.Id_profesional = p.Id_profesional
 LEFT JOIN estudiantes   e ON a.Id_estudiante   = e.Id_estudiante
     WHERE p.Id_profesional IS NULL OR e.Id_estudiante IS NULL
     ORDER BY a.Id_asignacion
');
$asignacionesHuerfanasStmt->execute();
$asignacionesHuerfanas = $asignacionesHuerfanasStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<h2 class="mb-4">Auditoría de vínculos críticos</h2>
<p class="mb-3">Herramienta de soporte para detectar cuentas sin relaciones clave entre usuarios, profesionales, escuelas y asignaciones.</p>

<section class="mb-5">
  <h3>Usuarios sin profesional asociado</h3>
  <?php if ($usuariosSinProfesional): ?>
    <div class="alert alert-warning">Revisa cada usuario y asigna un profesional desde <strong>Visualizar Profesionales</strong>.</div>
    <div class="table-responsive" style="max-height:300px; overflow-y:auto;">
      <table class="table table-striped table-bordered">
        <thead class="table-dark">
          <tr>
            <th>ID Usuario</th>
            <th>Usuario</th>
            <th>Rol</th>
            <th>Acción sugerida</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($usuariosSinProfesional as $u): ?>
            <tr>
              <td><?= (int)$u['Id_usuario'] ?></td>
              <td><?= htmlspecialchars($u['Nombre_usuario']) ?></td>
              <td><?= htmlspecialchars($u['Permisos'] ?? 'N/A') ?></td>
              <td>
                <a class="btn btn-sm btn-primary link-text" href="index.php?seccion=usuarios&Id_usuario=<?= (int)$u['Id_usuario'] ?>">Abrir en Usuarios</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="alert alert-success">No se detectaron usuarios sin profesional asociado.</div>
  <?php endif; ?>
</section>

<section class="mb-5">
  <h3>Profesionales sin escuela asignada</h3>
  <?php if ($profesSinEscuela): ?>
    <div class="alert alert-warning">Actualiza la escuela desde la opción <strong>Modificar Profesional</strong>.</div>
    <div class="table-responsive" style="max-height:300px; overflow-y:auto;">
      <table class="table table-striped table-bordered">
        <thead class="table-dark">
          <tr>
            <th>ID Profesional</th>
            <th>Nombre</th>
            <th>Correo</th>
            <th>Acción sugerida</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($profesSinEscuela as $p): ?>
            <?php $nombre = trim(($p['Nombre_profesional'] ?? '') . ' ' . ($p['Apellido_profesional'] ?? '')); ?>
            <tr>
              <td><?= (int)$p['Id_profesional'] ?></td>
              <td><?= htmlspecialchars($nombre ?: '(Sin nombre)') ?></td>
              <td><?= htmlspecialchars($p['Correo_profesional'] ?? '') ?></td>
              <td>
                <a class="btn btn-sm btn-primary link-text" href="index.php?seccion=modificar_profesional&Id_profesional=<?= (int)$p['Id_profesional'] ?>">Asignar escuela</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="alert alert-success">Todos los profesionales tienen escuela asociada.</div>
  <?php endif; ?>
</section>

<section>
  <h3>Asignaciones huérfanas</h3>
  <?php if ($asignacionesHuerfanas): ?>
    <div class="alert alert-warning">Elimina o corrige las asignaciones sin profesional o sin estudiante.</div>
    <div class="table-responsive" style="max-height:300px; overflow-y:auto;">
      <table class="table table-striped table-bordered">
        <thead class="table-dark">
          <tr>
            <th>ID Asignación</th>
            <th>Profesional</th>
            <th>Estudiante</th>
            <th>Acción sugerida</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($asignacionesHuerfanas as $a): ?>
            <tr>
              <td><?= (int)$a['Id_asignacion'] ?></td>
              <td>
                <?php if ($a['Id_profesional']): ?>
                  <?= htmlspecialchars($a['Nombre_profesional'] ?? '') ?> (ID <?= (int)$a['Id_profesional'] ?>)
                <?php else: ?>
                  <span class="text-danger">Profesional inexistente</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($a['Id_estudiante']): ?>
                  <?= htmlspecialchars($a['Nombre_estudiante'] ?? '') ?> (ID <?= (int)$a['Id_estudiante'] ?>)
                <?php else: ?>
                  <span class="text-danger">Estudiante inexistente</span>
                <?php endif; ?>
              </td>
              <td>
                <a class="btn btn-sm btn-primary link-text" href="index.php?seccion=asignaciones">Revisar en Asignaciones</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="alert alert-success">No existen asignaciones huérfanas registradas.</div>
  <?php endif; ?>
</section>
