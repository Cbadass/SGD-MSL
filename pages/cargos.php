<?php
// pages/cargos.php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auditoria.php';

$rol = strtoupper($_SESSION['usuario']['permisos'] ?? 'GUEST');
if (!in_array($rol, ['ADMIN','DIRECTOR'], true)) { http_response_code(403); exit('No autorizado'); }

$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nombre = trim($_POST['Nombre'] ?? '');
  if ($nombre === '') {
    $msg = ['type'=>'error','text'=>'El nombre es obligatorio.'];
  } else {
    try {
      $st = $conn->prepare("INSERT INTO cargos (Nombre) VALUES (?)");
      $st->execute([$nombre]);
      $nuevoId = $conn->lastInsertId();
      registrarAuditoria($conn, (int)$_SESSION['usuario']['id'], 'cargos', $nuevoId, 'INSERT', null, ['Nombre'=>$nombre]);
      $msg = ['type'=>'ok','text'=>'Cargo creado.'];
    } catch (PDOException $e) {
      $msg = ['type'=>'error','text'=>'No se pudo crear: '.htmlspecialchars($e->getMessage())];
    }
  }
}

$rows = $conn->query("SELECT Id_cargo, Nombre, Activo, Creado_en FROM cargos ORDER BY Nombre")->fetchAll();
?>
<h2>Crear Cargo</h2>
<?php if ($msg): ?>
  <div class="alert alert-<?= $msg['type']==='ok'?'success':'danger' ?>"><?= $msg['text'] ?></div>
<?php endif; ?>

<form method="post" data-requires-confirm>
  <div class="form-grid">
    <div class="form-group">
      <label>Nombre <span class="text-danger">*</span></label>
      <input type="text" name="Nombre" required>
    </div>
  </div>
  <button class="btn btn-primary" type="submit">Guardar</button>
</form>

<hr>
<h3>Cargos existentes</h3>
<div class="table-scrollable">
  <table class="table table-striped table-bordered">
    <thead><tr><th>ID</th><th>Nombre</th><th>Activo</th><th>Creado</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= (int)$r['Id_cargo'] ?></td>
        <td><?= htmlspecialchars($r['Nombre']) ?></td>
        <td><?= (int)$r['Activo'] ? 'Sí':'No' ?></td>
        <td><?= htmlspecialchars($r['Creado_en']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
