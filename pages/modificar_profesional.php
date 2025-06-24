<?php
//modificar_profesional.php
session_start();
// 1) Conecta a la BD  
require_once __DIR__ . '/../includes/db.php';

// 2) Protege la página
if (!isset($_SESSION['usuario'])) {
    header("Location: ../login.php");
    exit;
}

// 3) Recoge el Id de la URL
$id = intval($_GET['Id_profesional'] ?? 0);
if ($id <= 0) {
    die("ID inválido.");
}

// 4) Trae datos del profesional + usuario
$stmt = $conn->prepare("
    SELECT p.*, u.Permisos, u.Estado_usuario
      FROM profesionales p
 LEFT JOIN usuarios u ON p.Id_profesional = u.Id_profesional
     WHERE p.Id_profesional = ?
");
$stmt->execute([$id]);
$prof = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$prof) {
    die("Profesional no encontrado.");
}

// 5) Listas para los <select>
$escuelas    = ['Multiverso'=>2,'Sendero'=>1,'Luz de luna'=>3];
$tipos_prof  = ['Docente','Administrativo','Asistente'];
$cargos      = [
    'Administradora','Directora',
    'Profesor(a) Diferencial','Profesor(a)',
    'Asistentes de la educación','Especialistas','Docente',
    'Psicologa','Fonoaudiologo','Kinesiologo','Terapeuta Ocupacional'
];
$bancos      = ['Banco Estado','Santander','Banco Falabella'];
$tipos_cta   = ['Corriente','Vista','Ahorro'];
$afps        = ['AFP Modelo','Habitat'];
$saludes     = ['FONASA','ISAPRE'];
$permisos    = ['user'=>'Usuario','admin'=>'Administrador'];
$estados_usr = ['1'=>'Activo','0'=>'Inactivo'];
?>

<h2>Editar Profesional</h2>

  <form method="POST" action="../guardar_modificacion_profesional.php" class="form-grid" novalidate>
    <input type="hidden" name="Id_profesional" value="<?= $prof['Id_profesional'] ?>">

    <!-- DAtos personales -->
    <h3 class = 'mb-4 subtitle'>Datos personales</h3>
    <div class="col-md-6">
      <label class="form-label">Nombres</label>
      <input name="nombre" class="form-control" type="text" required
             value="<?= htmlspecialchars($prof['Nombre_profesional']) ?>">
    </div>

    <div class="col-md-6">
      <label class="form-label">Apellidos</label>
      <input name="apellido" class="form-control" type="text" required
             value="<?= htmlspecialchars($prof['Apellido_profesional']) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label">Correo</label>
      <input name="correo" type="email" class="form-control" required
             value="<?= htmlspecialchars($prof['Correo_profesional']) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label">Teléfono</label>
      <input name="telefono" class="form-control" type="text" placeholder="+56 9 1234 5678" required
             value="<?= htmlspecialchars($prof['Celular_profesional']) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label">RUT</label>
      <input name="rut" class="form-control" type="text" placeholder="20.384.593-4" required
             value="<?= htmlspecialchars($prof['Rut_profesional']) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label">Fecha nacimiento</label>
      <input name="fecha_nacimiento" type="date" class="form-control"
             value="<?= htmlspecialchars($prof['Nacimiento_profesional']) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label">Tipo profesional</label>
      <select name="tipo_profesional" class="form-select" required>
        <option value="">Seleccione...</option>
        <?php foreach ($tipos_prof as $t): ?>
          <option <?= $prof['Tipo_profesional'] === $t ? 'selected' : '' ?>>
            <?= htmlspecialchars($t) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Cargo</label>
      <select name="cargo" class="form-select" required>
        <option value="">Seleccione...</option>
        <?php foreach ($cargos as $c): ?>
          <option <?= $prof['Cargo_profesional'] === $c ? 'selected' : '' ?>>
            <?= htmlspecialchars($c) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>

    <div class="col-md-2">
      <label class="form-label">Horas</label>
      <input name="horas" type="number" class="form-control" min="0"
             value="<?= htmlspecialchars($prof['Horas_profesional']) ?>">
    </div>

    <div class="col-md-2">
      <label class="form-label">Ingreso</label>
      <input name="fecha_ingreso" type="date" class="form-control"
             value="<?= htmlspecialchars($prof['Fecha_ingreso']) ?>">
    </div>

    <div class="col-md-6">
      <label class="form-label">Domicilio</label>
      <input name="domicilio" class="form-control" type="text"
             value="<?= htmlspecialchars($prof['Domicilio_profesional']) ?>">
    </div>

    <div class="col-md-6">
      <label class="form-label">Escuela</label>
      <select name="escuela" class="form-select" required>
        <option value="">Seleccione...</option>
        <?php foreach ($escuelas as $name => $eid): ?>
          <option value="<?= $eid ?>"
            <?= $prof['Id_escuela_prof'] == $eid ? 'selected' : '' ?>>
            <?= htmlspecialchars($name) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Estado civil</label>
      <input type="text" name="estado_civil" class="form-control" required
             value="<?= htmlspecialchars($prof['Estado_civil_profesional']) ?>">
    </div>

    <!-- DATOS BANCARIOS -->
    <h3 class = 'mb-4 subtitle'>Datos Bancarios</h3>
    <div class="col-md-4">
      <label class="form-label">Banco</label>
      <select name="banco" class="form-select" required>
        <option value="">Seleccione...</option>
        <?php foreach ($bancos as $b): ?>
          <option <?= $prof['Banco_profesional'] === $b ? 'selected' : '' ?>>
            <?= htmlspecialchars($b) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Tipo de cuenta</label>
      <select name="tipo_cuenta" class="form-select" required>
        <option value="">Seleccione...</option>
        <?php foreach ($tipos_cta as $tc): ?>
          <option <?= $prof['Tipo_cuenta_profesional'] === $tc ? 'selected' : '' ?>>
            <?= htmlspecialchars($tc) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">N° de cuenta</label>
      <input name="cuenta" class="form-control" type="text" required
             value="<?= htmlspecialchars($prof['Cuenta_B_profesional']) ?>">
    </div>

    <!-- Datos salud -->
    <h3 class = 'mb-4 subtitle'>Datos Salud</h3>
    <div class="col-md-4">
      <label class="form-label">AFP</label>
      <select name="afp" class="form-select" required>
        <option value="">Seleccione...</option>
        <?php foreach ($afps as $a): ?>
          <option <?= $prof['AFP_profesional'] === $a ? 'selected' : '' ?>>
            <?= htmlspecialchars($a) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Salud</label>
      <select name="salud" class="form-select" required>
        <option value="">Seleccione...</option>
        <?php foreach ($saludes as $s): ?>
          <option <?= $prof['Salud_profesional'] === $s ? 'selected' : '' ?>>
            <?= htmlspecialchars($s) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>

    <!-- Otros -->
    <div class="col-md-4">
      <label class="form-label">Permisos</label>
      <select name="permiso" class="form-select" required>
        <?php foreach ($permisos as $key => $label): ?>
          <option value="<?= $key ?>"
            <?= $prof['Permisos'] === $key ? 'selected' : '' ?>>
            <?= htmlspecialchars($label) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Estado usuario</label>
      <select name="estado_usuario" class="form-select" required>
        <?php foreach ($estados_usr as $val => $lbl): ?>
          <option value="<?= $val ?>"
            <?= $prof['Estado_usuario'] == $val ? 'selected' : '' ?>>
            <?= htmlspecialchars($lbl) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>

    <div class="col-12 subtitle">
      <button type="submit" class="btn btn-success btn-height">Guardar cambios</button>
      <button class="btn btn-secondary btn-height">
        <a class="link-text" href="index.php?seccion=usuarios">Cancelar</a>
      </button>
    </div>
  </form>
</body>
</html>
