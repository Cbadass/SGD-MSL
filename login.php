<?php
session_start();
require_once 'includes/db.php';

// Redirige si ya está logueado
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
        $sql = "SELECT * FROM usuarios WHERE Nombre_usuario = :nombre AND Estado_usuario = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':nombre', $nombreUsuario);
        $stmt->execute();
        $usuario = $stmt->fetch();

        if ($usuario && $usuario['Contraseña'] === $contrasena) {
            $_SESSION['usuario'] = [
                'id' => $usuario['Id_usuario'],
                'nombre' => $usuario['Nombre_usuario'],
                'permisos' => $usuario['Permisos'] ?? 'user',
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
  <style>
    body {
      background-color: #f0f0f5;
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
    }

    .login-container {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .login-box {
      display: flex;
      background: white;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      max-width: 900px;
      width: 100%;
    }

    .login-form {
      padding: 40px;
      width: 50%;
    }

    .login-form h2 {
      margin-bottom: 25px;
      color: #3b3b8c;
    }

    .login-info {
      text-align: center;

      background: #bdb3f6;
      color: white;
      width: 50%;
      padding: 40px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }

    .login-info img {
      width: 120px;
      margin-bottom: 20px;
    }

    .form-control {
      border-radius: 6px;
    }

    .btn-primary {
      background-color: #875ff5;
      border: none;
      padding: 10px;
    }

    .btn-primary:hover {
      background-color: #6b4fe0;
    }

    .error-msg {
      color: red;
      font-size: 14px;
      margin-bottom: 10px;
    }

    @media (max-width: 768px) {
      .login-box {
        flex-direction: column;
      }
      .login-form, .login-info {
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-box">
      <div class="login-form">
        <h2>Iniciar Sesión</h2>
        <?php if ($error): ?>
          <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
          <div class="mb-3">
            <label for="nombre_usuario" class="form-label">Nombre de usuario</label>
            <!-- ELIMINAR VALUE -->
            <input type="text" name="nombre_usuario" id="nombre_usuario" class="form-control" required value="alejandro.castillo@correo.cl">
          </div>
          <div class="mb-3">
            <label for="contrasena" class="form-label">Contraseña</label>
            <!-- ELIMINAR VALUE -->
            <input type="password" name="contrasena" id="contrasena" class="form-control" required value="Password123!">
          </div>
          <button type="submit" class="btn btn-primary w-100">Ingresar</button>
        </form>
      </div>
      <div class="login-info">
        <img src="source/LogoMultisenluz.png" alt="Logo Multisenluz">
        <h4>Corporación Educacional Multisenluz</h4>
        <p>Comprometidos con la formación integral y la innovación educativa.</p>
      </div>
    </div>
  </div>
</body>
</html>
