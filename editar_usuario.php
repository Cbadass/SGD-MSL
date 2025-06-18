<?php
require_once 'includes/db.php';
session_start();
header('Content-Type: application/json');

// 1) Validar sesión
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success'=>false,'error'=>'No autenticado']);
    exit;
}

// 2) Capturar IDs
$Id_usuario     = intval($_POST['Id_usuario']     ?? 0);
if ($Id_usuario <= 0) {
    echo json_encode(['success'=>false,'error'=>'ID de usuario inválido']);
    exit;
}
$Id_profesional = intval($_POST['Id_profesional'] ?? 0);

// 3) Campos
$Nombre_usuario        = trim($_POST['Nombre_usuario']        ?? '');
$Permisos              = in_array($_POST['Permisos'] ?? '', ['user','admin'])
                           ? $_POST['Permisos'] : 'user';
$Estado_usuario        = ($_POST['Estado_usuario'] ?? '1') === '1' ? 1 : 0;

$Nombre_profesional    = trim($_POST['Nombre_profesional']    ?? '');
$Apellido_profesional  = trim($_POST['Apellido_profesional']  ?? '');
$Rut_profesional       = trim($_POST['Rut_profesional']       ?? '');
$Nacimiento_profesional= trim($_POST['Nacimiento_profesional']?? '');
$Celular_profesional   = trim($_POST['Celular_profesional']   ?? '');
$Correo_profesional    = trim($_POST['Correo_profesional']    ?? '');
$Cargo_profesional     = trim($_POST['Cargo_profesional']     ?? '');

// 4) Validar cargo
$allowed_cargos = [
    'Administradora',
    'Directora',
    'Profesor(a) Diferencial',
    'Profesor(a)',
    'Asistentes de la educación',
    'Especialistas',
    'Docente',
    'Psicologa',
    'Fonoaudiologo',
    'Kinesiologo',
    'Terapeuta Ocupacional'
];
if ($Cargo_profesional !== '' && !in_array($Cargo_profesional, $allowed_cargos)) {
    echo json_encode(['success'=>false,'error'=>'Cargo inválido']);
    exit;
}

try {
    $conn->beginTransaction();

    // UPDATE usuarios
    $stmt1 = $conn->prepare("
      UPDATE usuarios
      SET Nombre_usuario = :nomu,
          Permisos       = :perm,
          Estado_usuario = :est
      WHERE Id_usuario = :idu
    ");
    $stmt1->execute([
        ':nomu'=>$Nombre_usuario,
        ':perm'=>$Permisos,
        ':est' =>$Estado_usuario,
        ':idu' =>$Id_usuario
    ]);

    // UPDATE profesionales si aplica
    if ($Id_profesional>0) {
        $stmt2 = $conn->prepare("
          UPDATE profesionales
          SET Nombre_profesional     = :nomp,
              Apellido_profesional   = :app,
              Rut_profesional        = :rut,
              Nacimiento_profesional = :nac,
              Celular_profesional    = :cel,
              Correo_profesional     = :mail,
              Cargo_profesional      = :cargo
          WHERE Id_profesional = :idp
        ");
        $stmt2->execute([
            ':nomp'  =>$Nombre_profesional,
            ':app'   =>$Apellido_profesional,
            ':rut'   =>$Rut_profesional,
            ':nac'   =>$Nacimiento_profesional,
            ':cel'   =>$Celular_profesional,
            ':mail'  =>$Correo_profesional,
            ':cargo' =>$Cargo_profesional,
            ':idp'   =>$Id_profesional
        ]);
    }

    $conn->commit();
    echo json_encode(['success'=>true]);

} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['success'=>false,'error'=>'Error BD: '.$e->getMessage()]);
}
