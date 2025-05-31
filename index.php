<?php
// index.php

session_start();

// Verificar si se envió el formulario (botón)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Puedes procesar la lógica aquí
    // Por ejemplo: redirigir a la página "home"
    header('Location: index.php?page=home');
    exit;
}

// Obtener el valor de la página solicitada
$page = $_GET['page'] ?? null;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Index con Botón</title>
</head>
<body>

<h1>Bienvenido al index</h1>

<!-- Botón que envía el formulario -->
<form method="post">
    <button type="submit" name="goHome">Ir a la página Home</button>
</form>

<?php
// Mostrar la página solicitada
if ($page) {
    switch ($page) {
        case 'home':
            include 'pages/home.php';
            break;

        default:
            echo "<h2>Página no encontrada</h2>";
            break;
    }
}
?>

</body>
</html>
