<?php
// guardar_modificacion_profesional.php
session_start();
require_once 'includes/db.php';
require_once 'includes/auditoria.php';
require_once 'includes/roles.php';
try {
    if (!isset($_SESSION['usuario'])) {
        throw new Exception("No autorizado.");
    }

    // 1) Validar ID
    $id = intval($_POST['Id_profesional'] ?? 0);
    if ($id <= 0) throw new Exception("ID inválido.");

    // 2) Capturar campos
    $nombre      = trim($_POST['nombre']            ?? '');
    $apellido    = trim($_POST['apellido']          ?? '');
    $correo      = trim($_POST['correo']            ?? '');
    $telefono    = trim($_POST['telefono']          ?? '');
    $rut         = trim($_POST['rut']               ?? '');
    $nacimiento  = trim($_POST['fecha_nacimiento']  ?? '');
    $tipo_prof   = $_POST['tipo_profesional']       ?? '';
    $cargo       = $_POST['cargo']                  ?? '';
    $horas       = intval($_POST['horas']           ?? 0);
    $fecha_ing   = trim($_POST['fecha_ingreso']     ?? '');
    $domicilio   = trim($_POST['domicilio']         ?? '');
    $estado_civ  = $_POST['estado_civil']           ?? '';
    $banco       = $_POST['banco']                  ?? '';
    $tipo_cta    = $_POST['tipo_cuenta']            ?? '';
    $cuenta      = trim($_POST['cuenta']            ?? '');
    $afp         = $_POST['afp']                    ?? '';
    $salud       = $_POST['salud']                  ?? '';
    $permiso     = ensureRole($_POST['permiso']                ?? '');
    $estado_usr  = intval($_POST['estado_usuario']  ?? 1);
    $escuela     = intval($_POST['escuela']         ?? 0);

    // 3) Validaciones (RUT, teléfono, listas permitidas…)
    //    Reúne las mismas comprobaciones que en create_profesional
    if ($permiso === 'DIRECTOR' && $escuela <= 0) {
        throw new Exception('Los directores deben mantener una escuela asignada. Selecciona una escuela antes de guardar.');
    }

    $stmtUsuario = $conn->prepare('SELECT Id_usuario, Id_profesional FROM usuarios WHERE Id_profesional = ?');
    $stmtUsuario->execute([$id]);
    $usuarioAsociado = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
    if (!$usuarioAsociado || (int)$usuarioAsociado['Id_profesional'] !== $id) {
        throw new Exception('No se encontró un usuario vinculado a este profesional. Contacta a soporte para regularizar el vínculo antes de editar.');
    }

    // 4) Transaction para UPDATE en dos tablas
    $conn->beginTransaction();

    // 4a) Update profesionales
    $sql1 = "
      UPDATE profesionales
         SET Nombre_profesional       = :nom,
             Apellido_profesional     = :ape,
             Rut_profesional          = :rut,
             Nacimiento_profesional   = :nac,
             Domicilio_profesional    = :dom,
             Celular_profesional      = :tel,
             Correo_profesional       = :mail,
             Estado_civil_profesional = :ec,
             Banco_profesional        = :bco,
             Tipo_cuenta_profesional  = :tcta,
             Cuenta_B_profesional     = :cta,
             AFP_profesional          = :afp,
             Salud_profesional        = :sal,
             Cargo_profesional        = :car,
             Horas_profesional        = :hrs,
             Fecha_ingreso            = :fing,
             Tipo_profesional         = :tprof,
             Id_escuela_prof          = :idesc
       WHERE Id_profesional = :idp
    ";
    $stmt1 = $conn->prepare($sql1);
    $stmt1->execute([
      ':nom'   => $nombre,
      ':ape'   => $apellido,
      ':rut'   => $rut,
      ':nac'   => $nacimiento,
      ':dom'   => $domicilio,
      ':tel'   => $telefono,
      ':mail'  => $correo,
      ':ec'    => $estado_civ,
      ':bco'   => $banco,
      ':tcta'  => $tipo_cta,
      ':cta'   => $cuenta,
      ':afp'   => $afp,
      ':sal'   => $salud,
      ':car'   => $cargo,
      ':hrs'   => $horas,
      ':fing'  => $fecha_ing,
      ':tprof' => $tipo_prof,
      ':idesc' => $escuela,
      ':idp'   => $id
    ]);

    // Auditoría de la tabla 'profesionales'
    $usuarioLog = $_SESSION['usuario']['id'];
    $datosNuevosProf = [
      'Nombre_profesional'       => $nombre,
      'Apellido_profesional'     => $apellido,
      'Rut_profesional'          => $rut,
      'Nacimiento_profesional'   => $nacimiento,
      'Domicilio_profesional'    => $domicilio,
      'Celular_profesional'      => $telefono,
      'Correo_profesional'       => $correo,
      'Estado_civil_profesional' => $estado_civ,
      'Banco_profesional'        => $banco,
      'Tipo_cuenta_profesional'  => $tipo_cta,
      'Cuenta_B_profesional'     => $cuenta,
      'AFP_profesional'          => $afp,
      'Salud_profesional'        => $salud,
      'Cargo_profesional'        => $cargo,
      'Horas_profesional'        => $horas,
      'Fecha_ingreso'            => $fecha_ing,
      'Tipo_profesional'         => $tipo_prof,
      'Id_escuela_prof'          => $escuela
    ];
    registrarAuditoria($conn, $usuarioLog, 'profesionales', $id, 'UPDATE', null, $datosNuevosProf);

    // 4b) Update usuarios asociado (permiso y estado)
    $sql2 = "
      UPDATE usuarios
         SET Permisos       = :perm,
             Estado_usuario = :est
       WHERE Id_profesional = :idp
    ";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->execute([
      ':perm' => $permiso,
      ':est'  => $estado_usr,
      ':idp'  => $id
    ]);

    // Auditoría de la tabla 'usuarios'
    $datosNuevosUsr = [
      'Permisos'       => $permiso,
      'Estado_usuario' => $estado_usr
    ];
    registrarAuditoria($conn, $usuarioLog, 'usuarios', $id, 'UPDATE', null, $datosNuevosUsr);

    $conn->commit();

    header("Location: index.php?seccion=usuarios");
    exit;

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "<p class='text-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
