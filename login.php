<?php
session_start();
require_once 'includes/db.php'; // Asegúrate de que tengas la conexión PDO aquí

// Si ya está logueado, redirigir al index
if (isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombreUsuario = trim($_POST['nombre_usuario'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');

    if (empty($nombreUsuario) || empty($contrasena)) {
        $error = "Por favor completa todos los campos.";
    } else {
        // Buscar al usuario en la BD
        $sql = "SELECT * FROM usuarios WHERE Nombre_usuario = :nombre AND Estado_usuario = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':nombre', $nombreUsuario);
        $stmt->execute();
        $usuario = $stmt->fetch();

        if ($usuario && $usuario['Contraseña'] === $contrasena) {
            // Guardar la sesión con permisos
            $_SESSION['usuario'] = [
                'id' => $usuario['Id_usuario'],
                'nombre' => $usuario['Nombre_usuario'],
                'permisos' => $usuario['Permisos'] ?? 'user', // default user si no hay permisos
                'id_profesional' => $usuario['Id_profesional'] ?? null
            ];

            header("Location: index.php");
            exit;
        } else {
            $error = "Usuario o contraseña incorrectos, o usuario inactivo.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Iniciar Sesión</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">

<h2 class="mb-4">Iniciar Sesión</h2>

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" class="mb-3">
    <div class="mb-3">
        <label for="nombre_usuario" class="form-label">Nombre de usuario</label>
        <input type="text" name="nombre_usuario" id="nombre_usuario" class="form-control" required>
    </div>
    <div class="mb-3">
        <label for="contrasena" class="form-label">Contraseña</label>
        <input type="password" name="contrasena" id="contrasena" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary">Ingresar</button>
</form>

</body>
</html>
