<?php
// inicializar la sección por defecto
$seccion = $_GET['seccion'] ?? 'usuarios'; // por defecto: 'usuarios'
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SGD Multisenluz</title>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background-color: #e8e8fc;
        }

        .container {
            display: flex;
        }

        .sidebar {
            width: 250px;
            background-color: #d3d2f3;
            height: 100vh;
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .sidebar h3 {
            margin: 10px 20px;
            color: #3b3b8c;
            font-size: 14px;
            text-transform: uppercase;
        }

        .sidebar a {
            display: block;
            padding: 10px 20px;
            color: #333;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background-color: #bcbaf3;
            border-left: 5px solid #6e62f4;
        }

        .sidebar .user-info {
            padding: 15px;
            border-top: 1px solid #bbb;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            background-color: #e3e3fb;
        }

        .sidebar .user-info img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
        }

        .main {
            flex: 1;
            padding: 20px 40px;
        }

        .header {
            background-color: #875ff5;
            color: white;
            padding: 15px 30px;
            font-size: 22px;
            font-weight: bold;
        }

        .section {
            margin-top: 30px;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
        }

        .filters {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        select, input[type="text"], input[type="number"], input[type="email"], input[type="date"] {
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 14px;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table th, table td {
            padding: 12px;
            border: 1px solid #ddd;
            font-size: 14px;
            text-align: left;
        }

        table th {
            background-color: #875ff5;
            color: white;
        }

        .btn {
            background-color: #6b6cfb;
            color: white;
            padding: 8px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .btn:hover {
            background-color: #574cf0;
        }

        .btn-green {
            background-color: #4cd964;
            margin-top: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }

        label {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 5px;
            display: block;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        h2 {
            margin-top: 0;
        }
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
                <a href="?seccion=actividad" class="<?= $seccion === 'actividad' ? 'active' : '' ?>">Registro de Actividad del sistema</a>

                <h3>Documentos</h3>
                <a href="?seccion=documentos" class="<?= $seccion === 'documentos' ? 'active' : '' ?>">Tabla de documentos</a>
                <a href="?seccion=subir_documento" class="<?= $seccion === 'subir_documento' ? 'active' : '' ?>">Subir Documentos </a>

                <h3>Asignaciones</h3>
                <a href="?seccion=asignaciones" class="<?= $seccion === 'asignaciones' ? 'active' : '' ?>">Gestión de Asignaciones</a>
            </div>
        </div>
        <div class="main">
            <div class="section">
                <!-- Aquí van las secciones dinámicas -->
            </div>
        </div>
    </div>
</body>
</html>
