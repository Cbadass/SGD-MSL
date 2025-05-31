<?php
// index.php

// Inicia sesión
session_start();

// Conexión a la base de datos
include("ConexionMultisenluz.php");

// Recuperar conexión de entorno
$connectionString = getenv("SQLAZURECONNSTR_ConexionBD");

// Parsear datos de la conexión
$connectionParams = [];
foreach (explode(";", $connectionString) as $part) {
    $kv = explode("=", $part, 2);
    if (count($kv) == 2) {
        $connectionParams[trim($kv[0])] = trim($kv[1]);
    }
}

$serverName = $connectionParams["Server"];
$database = $connectionParams["Database"];
$username = $connectionParams["User Id"];
$password = $connectionParams["Password"];

// Crear conexión
$conn = new mysqli($serverName, $username, $password, $database);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Obtener la sección a mostrar
$seccion = $_GET['seccion'] ?? 'usuarios'; // por defecto: usuarios

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SGD Multisenluz</title>
    <style>
        /* Aquí los estilos generales que tenías (sidebar, header, etc.) */
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background-color: #e8e8fc; }
        .container { display: flex; }
        .sidebar { width: 250px; background-color: #d3d2f3; height: 100vh; padding: 20px 0; display: flex; flex-direction: column; justify-content: space-between; }
        .sidebar h3 { margin: 10px 20px; color: #3b3b8c; font-size: 14px; text-transform: uppercase; }
        .sidebar a { display: block; padding: 10px 20px; color: #333; text-decoration: none; font-weight: 500; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { background-color: #bcbaf3; border-left: 5px solid #6e62f4; }
        .main { flex: 1; padding: 20px 40px; }
        .header { background-color: #875ff5; color: white; padding: 15px 30px; font-size: 22px; font-weight: bold; }
        .section { margin-top: 30px; background-color: white; padding: 30px; border-radius: 10px; }
    </style>
</head>
<body>
<div class="header">SGD Multisenluz</div>
<div class="container">
    <div class="sidebar">
        <div>
            <h3>Administrador</h3>
            <a href="?seccion=usuarios" class="<?= $seccion === 'usuarios' ? 'active' : '' ?>">Visualizar Usuarios</a>
            <a href="?seccion=registrar_usuario" class="<?= $seccion === 'registrar_usuario' ? 'active' : '' ?>">Registrar Usuario</a>
            <a href="?seccion=cursos" class="<?= $seccion === 'cursos' ? 'active' : '' ?>">Visualizar Cursos</a>
            <a href="?seccion=estudiantes" class="<?= $seccion === 'estudiantes' ? 'active' : '' ?>">Visualizar Estudiantes</a>
            <a href="?seccion=registrar_estudiante" class="<?= $seccion === 'registrar_estudiante' ? 'active' : '' ?>">Registrar Estudiante</a>
            <a href="?seccion=actividad" class="<?= $seccion === 'actividad' ? 'active' : '' ?>">Registro de Actividad</a>

            <h3>Documentos</h3>
            <a href="?seccion=documentos" class="<?= $seccion === 'documentos' ? 'active' : '' ?>">Tabla de Documentos</a>
            <a href="?seccion=subir_documento" class="<?= $seccion === 'subir_documento' ? 'active' : '' ?>">Subir Documentos</a>

            <h3>Asignaciones</h3>
            <a href="?seccion=asignaciones" class="<?= $seccion === 'asignaciones' ? 'active' : '' ?>">Gestión de Asignaciones</a>
        </div>
    </div>
    <div class="main">
        <div class="section">
            <?php
            // Según la sección, cargar el archivo correspondiente
            $allowedPages = [
                'usuarios', 'registrar_usuario', 'cursos', 'estudiantes',
                'registrar_estudiante', 'actividad', 'documentos', 'subir_documento', 'asignaciones'
            ];

            if (in_array($seccion, $allowedPages)) {
                include "pages/$seccion.php";
            } else {
                echo "<h2>Error 404 - Sección no encontrada</h2>";
            }
            ?>
        </div>
    </div>
</div>
</body>
</html>
