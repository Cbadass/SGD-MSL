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
                <?php
                switch ($seccion) {
                        case 'usuarios':
                            $escuela_filtro = $_GET['escuela'] ?? '';
                            $estado_filtro = $_GET['estado'] ?? '';

                            echo "<h2>Visualización de Usuarios</h2>";
                            echo "<form method='GET' class='filters' style='margin-bottom:15px; display: flex; gap: 15px;'>
                                <input type='hidden' name='seccion' value='usuarios'>
                                <select name='escuela'>
                                    <option value=''>Escuela</option>
                                    <option value='1'" . ($escuela_filtro == '1' ? ' selected' : '') . ">Sendero</option>
                                    <option value='2'" . ($escuela_filtro == '2' ? ' selected' : '') . ">Multiverso</option>
                                    <option value='3'" . ($escuela_filtro == '3' ? ' selected' : '') . ">Luz de Luna</option>
                                </select>
                                <select name='estado'>
                                    <option value=''>Estado</option>
                                    <option value='1'" . ($estado_filtro == '1' ? ' selected' : '') . ">Activo</option>
                                    <option value='0'" . ($estado_filtro == '0' ? ' selected' : '') . ">Inactivo</option>
                                </select>
                                <button class='btn' type='submit'>Filtrar</button>
                            </form>";

                            echo "<div style='max-height: 350px; overflow-y: auto; border-radius: 10px;'>
                            <table>
                                <tr>
                                    <th>Rut</th><th>Usuario</th><th>Nombres</th><th>Apellidos</th><th>Cargo</th>
                                    <th>Escuela</th><th>Permisos</th><th>Estado</th><th>Edición</th>
                                </tr>";

                            $sql = "SELECT u.Id_usuario, u.Nombre_usuario, u.Estado_usuario, 
                                           p.Id_profesional, p.Rut_profesional, p.Nombre_profesional, 
                                           p.Apellido_profesional, p.Cargo_profesional, p.Id_escuela_prof,
                                           e.Nombre_escuela
                                    FROM usuarios u
                                    LEFT JOIN profesionales p ON u.Id_profesional = p.Id_profesional
                                    LEFT JOIN escuelas e ON p.Id_escuela_prof = e.Id_escuela
                                    WHERE 1=1";

                            if ($escuela_filtro !== '') {
                                $sql .= " AND p.Id_escuela_prof = " . intval($escuela_filtro);
                            }
                            if ($estado_filtro !== '') {
                                $sql .= " AND u.Estado_usuario = " . intval($estado_filtro);
                            }

                            $sql .= " ORDER BY u.Id_usuario DESC LIMIT 50";
                            $res = $conn->query($sql);

                            if ($res && $res->num_rows > 0) {
                                while ($row = $res->fetch_assoc()) {
                                    echo "<tr>
                                        <td>{$row['Rut_profesional']}</td>
                                        <td>{$row['Nombre_usuario']}</td>
                                        <td>{$row['Nombre_profesional']}</td>
                                        <td>{$row['Apellido_profesional']}</td>
                                        <td>{$row['Cargo_profesional']}</td>
                                        <td>" . ($row['Nombre_escuela'] ?? 'Otra') . "</td>
                                        <td>Usuario</td>
                                        <td>" . ($row['Estado_usuario'] == 1 ? 'Activo' : 'Inactivo') . "</td>
                                        <td>
                                            <button class='btn' onclick='mostrarModal(" . json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS) . ")'>Editar</button>
                                        </td>
                                    </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='9'>No se encontraron usuarios.</td></tr>";
                            }

                            echo "</table></div>";

                            // Aquí el modal y script ↓↓↓
                            break;


                    case 'registrar_usuario':
                        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                            // Captura de datos del formulario
                            $nombre      = $_POST['nombre'];
                            $apellido    = $_POST['apellido'];
                            $correo      = $_POST['correo'];
                            $telefono    = $_POST['telefono'];
                            $rut         = $_POST['rut'];
                            $nacimiento  = $_POST['fecha_nacimiento'];
                            $tipo_prof   = $_POST['tipo_profesional'];
                            $cargo       = $_POST['cargo'];
                            $horas       = $_POST['horas'];
                            $fecha_ing   = $_POST['fecha_ingreso'];
                            $domicilio   = $_POST['domicilio'];
                            $estado_civil= $_POST['estado_civil'];
                            $banco       = $_POST['banco'];
                            $tipo_cta    = $_POST['tipo_cuenta'];
                            $cuenta      = $_POST['cuenta'];
                            $afp         = $_POST['afp'];
                            $salud       = $_POST['salud'];
                            $permiso     = $_POST['permiso'];
                            $escuela     = $_POST['escuela'];

                            // Asociar ID de escuela
                            $escuelas = ['Multiverso' => 2, 'Sendero' => 1, 'Luz de luna' => 3];
                            $id_escuela = $escuelas[$escuela] ?? null;

                            // Generar nombre_usuario
                            $n1 = explode(" ", trim($nombre))[0];
                            $a1 = explode(" ", trim($apellido))[0];
                            $nombre_usuario = strtolower($n1 . "." . $a1);

                            // Contraseña por defecto: nombre de la escuela
                            $contrasena = $escuela;

                            // 1. Insertar profesional
                            $sql_pro = "INSERT INTO profesionales (
                                Nombre_profesional, Apellido_profesional, Rut_profesional, Nacimiento_profesional,
                                Domicilio_profesional, Celular_profesional, Correo_profesional, Estado_civil_profesional,
                                Banco_profesional, Tipo_cuenta_profesional, Cuenta_B_profesional, AFP_profesional,
                                Salud_profesional, Cargo_profesional, Horas_profesional, Fecha_ingreso,
                                Tipo_profesional, Id_escuela_prof
                            ) VALUES (
                                '$nombre', '$apellido', '$rut', '$nacimiento',
                                '$domicilio', '$telefono', '$correo', '$estado_civil',
                                '$banco', '$tipo_cta', '$cuenta', '$afp',
                                '$salud', '$cargo', $horas, '$fecha_ing',
                                '$tipo_prof', $id_escuela
                            )";

                            if ($conn->query($sql_pro) === TRUE) {
                                $id_profesional = $conn->insert_id;

                                // 2. Insertar usuario
                                $sql_user = "INSERT INTO usuarios (
                                    Nombre_usuario, Contraseña, Estado_usuario, Id_profesional
                                ) VALUES (
                                    '$nombre_usuario', '$contrasena', 1, $id_profesional
                                )";

                                if ($conn->query($sql_user)) {
                                    echo "<p style='color:green;'>✅ Usuario registrado correctamente.<br>Nombre de usuario: <strong>$nombre_usuario</strong> | Contraseña: <strong>$contrasena</strong></p>";
                                } else {
                                    echo "<p style='color:red;'>Error al insertar usuario: " . $conn->error . "</p>";
                                }
                            } else {
                                echo "<p style='color:red;'>Error al insertar profesional: " . $conn->error . "</p>";
                            }
                        }

                        // FORMULARIO HTML
                        echo '<h2>Registrar nuevo usuario</h2>
                        <form method="POST" class="form-grid">
                            <div class="form-group"><label>Nombres</label><input name="nombre" required></div>
                            <div class="form-group"><label>Apellidos</label><input name="apellido" required></div>
                            <div class="form-group"><label>Correo Electrónico</label><input name="correo" type="email" required></div>
                            <div class="form-group"><label>Número</label><input name="telefono" required></div>
                            <div class="form-group"><label>RUT</label><input name="rut" required></div>
                            <div class="form-group"><label>Fecha de nacimiento</label><input name="fecha_nacimiento" type="date" required></div>
                            <div class="form-group"><label>Tipo de profesional</label>
                                <select name="tipo_profesional">
                                    <option>Docente</option><option>Administrativo</option><option>Asistente</option>
                                </select>
                            </div>
                            <div class="form-group"><label>Cargo</label><input name="cargo"></div>
                            <div class="form-group"><label>Horas laborales</label><input name="horas" type="number"></div>
                            <div class="form-group"><label>Fecha de inicio</label><input name="fecha_ingreso" type="date"></div>
                            <div class="form-group"><label>Domicilio</label><input name="domicilio"></div>
                            <div class="form-group"><label>Estado Civil</label><input name="estado_civil"></div>
                            <div class="form-group"><label>Banco</label>
                                <select name="banco">
                                    <option>Banco Estado</option><option>Santander</option><option>Banco Falabella</option>
                                </select>
                            </div>
                            <div class="form-group"><label>Tipo de cuenta</label><select name="tipo_cuenta">
                                <option>Corriente</option><option>Vista</option><option>Ahorro</option>
                            </select></div>
                            <div class="form-group"><label>N° de cuenta</label><input name="cuenta"></div>
                            <div class="form-group"><label>AFP</label><select name="afp">
                                <option>AFP Modelo</option><option>Habitat</option>
                            </select></div>
                            <div class="form-group"><label>Salud</label><select name="salud">
                                <option>FONASA</option><option>ISAPRE</option>
                            </select></div>
                            <div class="form-group"><label>Permisos</label><select name="permiso">
                                <option>Usuario</option><option>Administrador</option>
                            </select></div>
                            <div class="form-group"><label>Escuela</label><select name="escuela">
                                <option>Multiverso</option><option>Sendero</option><option>Luz de luna</option>
                            </select></div>
                            <button class="btn btn-green" type="submit">Guardar Datos</button>
                        </form>';
                        break;

                    case 'cursos':
                        $filtro_escuela = $_GET['escuela'] ?? '';

                        echo "<h2>Visualización de Cursos</h2>";
                        echo "<form method='GET' class='filters' style='margin-bottom:15px; display: flex; gap: 15px;'>
                            <input type='hidden' name='seccion' value='cursos'>
                            <select name='escuela'>
                                <option value=''>Escuela</option>
                                <option value='1'" . ($filtro_escuela == '1' ? ' selected' : '') . ">Sendero</option>
                                <option value='2'" . ($filtro_escuela == '2' ? ' selected' : '') . ">Multiverso</option>
                                <option value='3'" . ($filtro_escuela == '3' ? ' selected' : '') . ">Luz de Luna</option>
                            </select>
                            <button class='btn' type='submit'>Filtrar</button>
                        </form>";

                        echo "<div style='max-height: 400px; overflow-y: auto; border-radius: 10px;'>
                        <table>
                            <tr>
                                <th>Escuela</th>
                                <th>Tipo Curso</th>
                                <th>Grado</th>
                                <th>Sección</th>
                                <th>Docente</th>
                                <th>Modificar Docente</th>
                            </tr>";

                        $sql = "SELECT c.Id_curso, c.Tipo_curso, c.Grado_curso,
                                       e.Nombre_escuela,
                                       p.Nombre_profesional, p.Apellido_profesional
                                FROM cursos c
                                LEFT JOIN escuelas e ON c.Id_escuela = e.Id_escuela
                                LEFT JOIN profesionales p ON c.Id_profesional = p.Id_profesional
                                WHERE 1=1";

                        if ($filtro_escuela !== '') {
                            $sql .= " AND c.Id_escuela = " . intval($filtro_escuela);
                        }

                        $sql .= " ORDER BY c.Id_curso ASC";

                        $res = $conn->query($sql);

                        if ($res && $res->num_rows > 0) {
                            while ($row = $res->fetch_assoc()) {
                                $nombre_docente = ($row['Nombre_profesional']) 
                                    ? "{$row['Nombre_profesional']} {$row['Apellido_profesional']}"
                                    : "<em>No asignado</em>";

                                echo "<tr>
                                    <td>{$row['Nombre_escuela']}</td>
                                    <td>{$row['Tipo_curso']}</td>
                                    <td>{$row['Grado_curso']}</td>
                                    <td>-</td>
                                    <td>$nombre_docente</td>
                                    <td><button class='btn'>Modificar</button></td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6'>No se encontraron cursos.</td></tr>";
                        }

                        echo "</table></div>";
                        break;

                    case 'estudiantes':
                        $filtro_escuela = $_GET['escuela'] ?? '';
                        $filtro_estado = $_GET['estado'] ?? '';

                        echo "<h2>Visualización de Estudiantes</h2>";
                        echo "<form method='GET' class='filters' style='margin-bottom:15px; display: flex; gap: 15px;'>
                            <input type='hidden' name='seccion' value='estudiantes'>
                            <select name='escuela'>
                                <option value=''>Escuela</option>
                                <option value='1'" . ($filtro_escuela == '1' ? ' selected' : '') . ">Sendero</option>
                                <option value='2'" . ($filtro_escuela == '2' ? ' selected' : '') . ">Multiverso</option>
                                <option value='3'" . ($filtro_escuela == '3' ? ' selected' : '') . ">Luz de Luna</option>
                            </select>
                            <select name='estado'>
                                <option value=''>Estado</option>
                                <option value='1'" . ($filtro_estado == '1' ? ' selected' : '') . ">Activo</option>
                                <option value='0'" . ($filtro_estado == '0' ? ' selected' : '') . ">Inactivo</option>
                            </select>
                            <button class='btn' type='submit'>Filtrar</button>
                        </form>";

                        echo "<div style='max-height: 350px; overflow-y: auto; border-radius: 10px;'>
                        <table>
                            <tr>
                                <th>RUT</th><th>Nombres</th><th>Apellidos</th><th>Apoderado</th><th>Escuela</th>
                            </tr>";

                        $sql = "SELECT e.Rut_estudiante, e.Nombre_estudiante, e.Apellido_estudiante,
                                       a.Nombre_apoderado, a.Apellido_apoderado,
                                       esc.Nombre_escuela, e.Estado_estudiante
                                FROM estudiantes e
                                LEFT JOIN apoderados a ON e.Id_apoderado = a.Id_apoderado
                                LEFT JOIN escuelas esc ON e.Id_escuela = esc.Id_escuela
                                WHERE 1=1";

                        if ($filtro_escuela !== '') {
                            $sql .= " AND e.Id_escuela = " . intval($filtro_escuela);
                        }

                        if ($filtro_estado !== '') {
                            $sql .= " AND e.Estado_estudiante = " . intval($filtro_estado);
                        }

                        $sql .= " ORDER BY e.Id_estudiante ASC";

                        $res = $conn->query($sql);

                        if ($res && $res->num_rows > 0) {
                            while ($row = $res->fetch_assoc()) {
                                $apoderado = trim($row['Nombre_apoderado'] . " " . $row['Apellido_apoderado']);

                                echo "<tr>
                                    <td>{$row['Rut_estudiante']}</td>
                                    <td>{$row['Nombre_estudiante']}</td>
                                    <td>{$row['Apellido_estudiante']}</td>
                                    <td>" . ($apoderado ?: '-') . "</td>
                                    <td>" . ($row['Nombre_escuela'] ?? '-') . "</td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5'>No se encontraron estudiantes registrados.</td></tr>";
                        }

                        echo "</table></div>";
                        break;





                    case 'registrar_estudiante':
                        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                            // Captura de datos estudiante
                            $nombre_est  = $_POST['nombre_estudiante'];
                            $apellido_est = $_POST['apellido_estudiante'];
                            $rut_est = $_POST['rut_estudiante'];
                            $fecha_nac = $_POST['fecha_nacimiento'];
                            $fecha_ing = $_POST['fecha_ingreso'];

                            // Captura de datos apoderado
                            $nombre_apo  = $_POST['nombre_apoderado'];
                            $apellido_apo = $_POST['apellido_apoderado'];
                            $rut_apo = $_POST['rut_apoderado'];
                            $numero = $_POST['numero_apoderado'];
                            $escolaridad_padre = $_POST['escolaridad_padre'];
                            $escolaridad_madre = $_POST['escolaridad_madre'];
                            $ocupacion_padre = $_POST['ocupacion_padre'];
                            $ocupacion_madre = $_POST['ocupacion_madre'];
                            $correo_apo = $_POST['correo_apoderado'];
                            $domicilio = $_POST['domicilio_apoderado'];

                            $sql_est = "INSERT INTO estudiantes (
                                Nombre_estudiante, Apellido_estudiante, Rut_estudiante,
                                Fecha_nacimiento, Fecha_ingreso, Estado_estudiante
                            ) VALUES (
                                '$nombre_est', '$apellido_est', '$rut_est',
                                '$fecha_nac', '$fecha_ing', 1
                            )";

                            if ($conn->query($sql_est)) {
                                $id_estudiante = $conn->insert_id;

                                $sql_apo = "INSERT INTO apoderados (
                                    Nombre_apoderado, Apellido_apoderado, Rut_apoderado, Numero_apoderado,
                                    Escolaridad_padre, Escolaridad_madre, Ocupacion_padre, Ocupacion_madre,
                                    Correo_apoderado, Id_estudiante
                                ) VALUES (
                                    '$nombre_apo', '$apellido_apo', '$rut_apo', '$numero',
                                    '$escolaridad_padre', '$escolaridad_madre', '$ocupacion_padre', '$ocupacion_madre',
                                    '$correo_apo', $id_estudiante
                                )";

                                if ($conn->query($sql_apo)) {
                                    echo "<p style='color:green;'>✅ Estudiante y apoderado registrados correctamente.</p>";
                                } else {
                                    echo "<p style='color:red;'>❌ Error al registrar apoderado: " . $conn->error . "</p>";
                                }
                            } else {
                                echo "<p style='color:red;'>❌ Error al registrar estudiante: " . $conn->error . "</p>";
                            }
                        }

                        // FORMULARIO VISUAL
                        echo '
                        <h2>Registrar nuevo estudiante</h2>
                        <form method="POST" style="background:white; padding:25px; border-radius:12px; width:100%; max-width:900px;">
                            <fieldset style="border:none; margin-bottom:20px;">
                                <legend><strong>Datos personales</strong></legend>
                                <div class="form-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:15px;">
                                    <div class="form-group"><label>Nombres</label><input name="nombre_estudiante" required></div>
                                    <div class="form-group"><label>Apellidos</label><input name="apellido_estudiante" required></div>
                                    <div class="form-group"><label>Fecha de Ingreso</label><input name="fecha_ingreso" type="date" required></div>
                                    <div class="form-group"><label>RUT</label><input name="rut_estudiante" required></div>
                                    <div class="form-group"><label>Fecha de nacimiento</label><input name="fecha_nacimiento" type="date" required></div>
                                </div>
                            </fieldset>

                            <fieldset style="border:none;">
                                <legend><strong>Datos de apoderados</strong></legend>
                                <div class="form-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:15px;">
                                    <div class="form-group"><label>Nombres Apoderado</label><input name="nombre_apoderado" required></div>
                                    <div class="form-group"><label>Apellidos Apoderado</label><input name="apellido_apoderado" required></div>
                                    <div class="form-group"><label>RUT Apoderado</label><input name="rut_apoderado"></div>
                                    <div class="form-group"><label>Domicilio</label><input name="domicilio_apoderado"></div>
                                    <div class="form-group"><label>Escolaridad Padre</label>
                                        <select name="escolaridad_padre">
                                            <option>S/A</option><option>8° Básico</option><option>Media Completa</option>
                                        </select>
                                    </div>
                                    <div class="form-group"><label>Escolaridad Madre</label>
                                        <select name="escolaridad_madre">
                                            <option>S/A</option><option>8° Básico</option><option>Media Completa</option>
                                        </select>
                                    </div>
                                    <div class="form-group"><label>Ocupación Padre</label><input name="ocupacion_padre"></div>
                                    <div class="form-group"><label>Ocupación Madre</label><input name="ocupacion_madre"></div>
                                    <div class="form-group"><label>Correo Electrónico</label><input name="correo_apoderado" type="email"></div>
                                    <div class="form-group"><label>Número de contacto</label><input name="numero_apoderado"></div>
                                </div>
                            </fieldset>

                            <div style="margin-top:20px; text-align:right;">
                                <button class="btn btn-green" type="submit">📁 Guardar Datos</button>
                            </div>
                        </form>';
                        break;


                    case 'actividad':
                        echo "<h2>Registro de Actividad del Sistema</h2>
                        <ul>
                            <li>📅 Fecha de creación: 01 enero, 2022</li>
                            <li>🕓 Última modificación: 28 enero, 2022</li>
                        </ul>";
                        break;

                    case 'documentos':
                        $filtro_tipo = $_GET['tipo_documento'] ?? '';

                        echo "<h2>Visualización de Documentos</h2>";
                        echo "<form method='GET' class='filters' style='margin-bottom:15px; display: flex; gap: 15px; align-items:center;'>
                            <input type='hidden' name='seccion' value='documentos_visualizar'>
                            <label for='tipo_documento'>Filtrar por Tipo:</label>
                            <select name='tipo_documento' id='tipo_documento'>
                                <option value=''>Todos</option>
                                <option value='Informe'" . ($filtro_tipo == 'Informe' ? ' selected' : '') . ">Informe</option>
                                <option value='Autorización'" . ($filtro_tipo == 'Autorización' ? ' selected' : '') . ">Autorización</option>
                                <option value='Acta'" . ($filtro_tipo == 'Acta' ? ' selected' : '') . ">Acta</option>
                                <option value='Otro'" . ($filtro_tipo == 'Otro' ? ' selected' : '') . ">Otro</option>
                            </select>
                            <button class='btn' type='submit'>Filtrar</button>
                        </form>";

                        echo "<div style='max-height: 350px; overflow-y: auto; border-radius: 10px;'>
                        <table>
                            <tr>
                                <th>Nombre del Documento</th>
                                <th>Tipo de Documento</th>
                                <th>Fecha Subido</th>
                                <th>Entidad Asociada</th>
                                <th>Ver Documento</th>
                            </tr>";

                        $sql = "SELECT d.Id_documento, d.Nombre_documento, d.Tipo_documento, d.Fecha_subido, d.Ruta_documento,
                                       e.Nombre_estudiante, e.Apellido_estudiante,
                                       p.Nombre_profesional, p.Apellido_profesional
                                FROM documentos d
                                LEFT JOIN estudiantes e ON d.Id_estudiante_doc = e.Id_estudiante
                                LEFT JOIN profesionales p ON d.Id_prof_doc = p.Id_profesional
                                WHERE 1=1";

                        if ($filtro_tipo !== '') {
                            $sql .= " AND d.Tipo_documento = '" . $conn->real_escape_string($filtro_tipo) . "'";
                        }

                        $sql .= " ORDER BY d.Fecha_subido DESC";

                        $res = $conn->query($sql);

                        if ($res && $res->num_rows > 0) {
                            while ($row = $res->fetch_assoc()) {
                                $entidad = '-';
                                if (!empty($row['Nombre_estudiante'])) {
                                    $entidad = $row['Nombre_estudiante'] . " " . $row['Apellido_estudiante'] . " (Estudiante)";
                                } elseif (!empty($row['Nombre_profesional'])) {
                                    $entidad = $row['Nombre_profesional'] . " " . $row['Apellido_profesional'] . " (Profesional)";
                                }

                                $ruta = htmlspecialchars($row['Ruta_documento']);
                                $nombreDoc = htmlspecialchars($row['Nombre_documento']);

                                echo "<tr>
                                    <td>$nombreDoc</td>
                                    <td>{$row['Tipo_documento']}</td>
                                    <td>{$row['Fecha_subido']}</td>
                                    <td>$entidad</td>
                                    <td><a href='$ruta' target='_blank' class='btn'>Ver</a></td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5'>No se encontraron documentos.</td></tr>";
                        }

                        echo "</table></div>";
                        break;


                    case 'subir_documento':
                        echo "<div class='header'>Agregar nuevo documento</div>";
                    
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'])) {
                            $nombre = $conn->real_escape_string($_POST['nombre']);
                            $rutEstudiante = !empty($_POST['rut_estudiante']) ? $conn->real_escape_string($_POST['rut_estudiante']) : null;
                            $rutProfesional = !empty($_POST['rut_profesional']) ? $conn->real_escape_string($_POST['rut_profesional']) : null;
                            $tipoDocumento = $conn->real_escape_string($_POST['tipo_documento']);
                            $descripcion = $conn->real_escape_string($_POST['descripcion']);
                    
                            // Archivo subido
                            $archivoNombre = basename($_FILES['documento']['name']);
                            $archivoTmp = $_FILES['documento']['tmp_name'];
                    
                            // Datos de Azure Storage
                            $accountName = "<nombre_cuenta>";
                            $accountKey = "<clave_de_acceso>";
                            $containerName = "<nombre_contenedor>";
                            $blobName = $archivoNombre;
                            $blobUrl = "https://$accountName.blob.core.windows.net/$containerName/$blobName";
                    
                            // Preparar firma
                            $currentDate = gmdate("D, d M Y H:i:s T", time());
                            $contentLength = filesize($archivoTmp);
                            $blobType = "BlockBlob";
                    
                            $canonicalizedHeaders = "x-ms-blob-type:$blobType\nx-ms-date:$currentDate\nx-ms-version:2020-10-02";
                            $canonicalizedResource = "/$accountName/$containerName/$blobName";
                            $stringToSign = "PUT\n\n\n$contentLength\n\n\n\n\n\n\n\n$canonicalizedHeaders\n$canonicalizedResource";
                            $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($accountKey), true));
                    
                            $headers = [
                                "x-ms-blob-type: $blobType",
                                "x-ms-date: $currentDate",
                                "x-ms-version: 2020-10-02",
                                "Authorization: SharedKey $accountName:$signature",
                                "Content-Length: $contentLength"
                            ];
                    
                            // Subir archivo con cURL
                            $ch = curl_init($blobUrl);
                            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            curl_setopt($ch, CURLOPT_INFILE, fopen($archivoTmp, 'r'));
                            curl_setopt($ch, CURLOPT_INFILESIZE, $contentLength);
                            $response = curl_exec($ch);
                            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                    
                            if ($statusCode == 201) {
                                echo "<p style='color:green; font-weight:bold;'>Archivo subido a Azure Blob Storage.</p>";
                    
                                // Buscar IDs
                                $idEstudiante = null;
                                $idProfesional = null;
                    
                                if ($rutEstudiante) {
                                    $resEst = $conn->query("SELECT Id_estudiante FROM estudiantes WHERE Rut_estudiante='$rutEstudiante'");
                                    if ($resEst && $resEst->num_rows > 0) {
                                        $rowEst = $resEst->fetch_assoc();
                                        $idEstudiante = $rowEst['Id_estudiante'];
                                    }
                                }
                    
                                if ($rutProfesional) {
                                    $resProf = $conn->query("SELECT Id_profesional FROM profesionales WHERE Rut_profesional='$rutProfesional'");
                                    if ($resProf && $resProf->num_rows > 0) {
                                        $rowProf = $resProf->fetch_assoc();
                                        $idProfesional = $rowProf['Id_profesional'];
                                    }
                                }
                    
                                // Insertar en la tabla documentos
                                $sql = "INSERT INTO documentos 
                                    (Nombre_documento, Tipo_documento, Fecha_subido, Url_documento, Descripcion, Id_estudiante_doc, Id_prof_doc)
                                    VALUES ('$nombre', '$tipoDocumento', GETDATE(), '$blobUrl', '$descripcion', " .
                                    ($idEstudiante !== null ? "'$idEstudiante'" : "NULL") . ", " .
                                    ($idProfesional !== null ? "'$idProfesional'" : "NULL") . ")";
                    
                                if ($conn->query($sql)) {
                                    echo "<p style='color:green; font-weight:bold;'>Documento registrado en la base de datos.</p>";
                                } else {
                                    echo "<p style='color:red;'>Error al registrar en la base de datos: {$conn->error}</p>";
                                }
                            } else {
                                echo "<p style='color:red;'>Error al subir a Azure Blob Storage. Código HTTP: $statusCode</p>";
                            }
                        }
                    
                        // Formulario
                        echo "
                        <div class='section'>
                            <form method='POST' enctype='multipart/form-data'>
                                <div class='form-grid'>
                                    <div class='form-group'>
                                        <label for='nombre'>Nombre del Documento</label>
                                        <input type='text' name='nombre' id='nombre' required>
                                    </div>
                    
                                    <div class='form-group'>
                                        <label for='rut_estudiante'>RUT del estudiante enlazado</label>
                                        <input type='text' name='rut_estudiante' id='rut_estudiante' placeholder='Opcional'>
                                    </div>
                    
                                    <div class='form-group'>
                                        <label for='rut_profesional'>RUT del profesional enlazado</label>
                                        <input type='text' name='rut_profesional' id='rut_profesional' placeholder='Opcional'>
                                    </div>
                    
                                    <div class='form-group'>
                                        <label for='tipo_documento'>Tipo de documento</label>
                                        <select name='tipo_documento' id='tipo_documento' required>
                                            <option value=''>Seleccione</option>

                                            <!-- Documentos de estudiantes -->
                                            <optgroup label='Documentos de estudiantes'>
                                                <option value='Certificado de Nacimiento'>Certificado de Nacimiento</option>
                                                <option value='Ficha de Matrícula'>Ficha de Matrícula</option>
                                                <option value='Certificado alumno prioritario'>Certificado alumno prioritario</option>
                                                <option value='Antecedentes en caso de emergencia'>Antecedentes en caso de emergencia</option>
                                                <option value='Autorización para evaluar y reevaluar'>Autorización para evaluar y reevaluar</option>
                                                <option value='Autorización de la muda'>Autorización de la muda</option>
                                                <option value='Informe Psicológico'>Informe Psicológico</option>
                                                <option value='Protocolos de prueba aplicada'>Protocolos de prueba aplicada</option>
                                                <option value='Prueba de conducta adaptativa ICAAP'>Prueba de conducta adaptativa ICAAP</option>
                                                <option value='Formulario de ingreso FUDEI'>Formulario de ingreso FUDEI</option>
                                                <option value='Formulario NEEP'>Formulario NEEP</option>
                                                <option value='Plan de Apoyo Individual PAI'>Plan de Apoyo Individual PAI</option>
                                                <option value='Formulario NEET'>Formulario NEET</option>
                                                <option value='Plan de Adecuaciones Curriculares Individualizado PACI'>Plan de Adecuaciones Curriculares Individualizado PACI</option>
                                                <option value='Informe pedagógico curricular'>Informe pedagógico curricular</option>
                                                <option value='Informe a la Familia'>Informe a la Familia</option>
                                                <option value='Informe Pedagógico 1er semestre'>Informe Pedagógico 1er semestre</option>
                                                <option value='Informe Pedagógico 2do semestre'>Informe Pedagógico 2do semestre</option>
                                                <option value='Informe Personalidad 1er semestre'>Informe Personalidad 1er semestre</option>
                                                <option value='Informe Personalidad 2do semestre'>Informe Personalidad 2do semestre</option>
                                                <option value='Informe Vocacional 1er semestre'>Informe Vocacional 1er semestre</option>
                                                <option value='Informe vocacional 2do semestre'>Informe vocacional 2do semestre</option>
                                                <option value='Informe de Notas 1er semestre'>Informe de Notas 1er semestre</option>
                                                <option value='Informe de notas 2do semestre'>Informe de notas 2do semestre</option>
                                                <option value='Certificado de estudios MINEDUC'>Certificado de estudios MINEDUC</option>
                                                <option value='Valoración de salud'>Valoración de salud</option>
                                                <option value='Informe fonoaudiológico'>Informe fonoaudiológico</option>
                                                <option value='Informe kinesiológico'>Informe kinesiológico</option>
                                                <option value='Informe Terapeuta Ocupacional'>Informe Terapeuta Ocupacional</option>
                                                <option value='Derivaciones a especialistas'>Derivaciones a especialistas</option>
                                                <option value='Informes médicos'>Informes médicos</option>
                                                <option value='Recetas médicas'>Recetas médicas</option>
                                                <option value='Antecedentes judiciales'>Antecedentes judiciales</option>
                                                <option value='Pruebas diagnósticas'>Pruebas diagnósticas</option>
                                                <option value='Hoja de vida del estudiante'>Hoja de vida del estudiante</option>
                                                <option value='Ficha desregulación emocional y conductual DEC'>Ficha desregulación emocional y conductual DEC</option>
                                                <option value='Otros'>Otros</option>
                                                <option value='Declaración de matrícula'>Declaración de matrícula</option>
                                                <option value='Screening'>Screening</option>
                                                <option value='Test Comprensión auditiva del Lenguaje TECAL'>Test Comprensión auditiva del Lenguaje TECAL</option>
                                                <option value='Test para evaluar procesos de simplificación fonológica TEPROSIF'>Test para evaluar procesos de simplificación fonológica TEPROSIF</option>
                                                <option value='Test de la articulación a la repetición TAR'>Test de la articulación a la repetición TAR</option>
                                                <option value='Habilidades pragmáticas'>Habilidades pragmáticas</option>
                                                <option value='Órganos fonoarticulatorios'>Órganos fonoarticulatorios</option>
                                                <option value='Formulario NEEP reevaluación (diciembre)'>Formulario NEEP reevaluación (diciembre)</option>
                                                <option value='Informe a la Familia Marzo'>Informe a la Familia Marzo</option>
                                                <option value='Estado de avance a la Familia Junio'>Estado de avance a la Familia Junio</option>
                                            </optgroup>

                                                <!-- Documentos de docentes -->
                                            <optgroup label='Documentos de docentes'>
                                                <option value='Curriculum'>Curriculum</option>
                                                <option value='Certificado de título'>Certificado de título</option>
                                                <option value='Certificado de registro MINEDUC'>Certificado de registro MINEDUC</option>
                                                <option value='Certificado de antecedentes para fines especiales'>Certificado de antecedentes para fines especiales</option>
                                                <option value='Certificado de consulta de inhabilidades para trabajar con menores de edad'>Certificado de consulta de inhabilidades para trabajar con menores de edad</option>
                                                <option value='Certificado de consulta de inhabilidades por maltrato relevante'>Certificado de consulta de inhabilidades por maltrato relevante</option>
                                                <option value='Ficha personal'>Ficha personal</option>
                                                <option value='Contrato de trabajo'>Contrato de trabajo</option>
                                                <option value='Recepción del Reglamento Interno de Higiene y Seguridad'>Recepción del Reglamento Interno de Higiene y Seguridad</option>
                                                <option value='Anexos de contratos'>Anexos de contratos</option>
                                                <option value='Certificado de afiliación AFP'>Certificado de afiliación AFP</option>
                                                <option value='Certificado de afiliación al sistema de salud'>Certificado de afiliación al sistema de salud</option>
                                                <option value='Certificados de perfeccionamientos'>Certificados de perfeccionamientos</option>
                                                <option value='Carta aviso de cese de funciones'>Carta aviso de cese de funciones</option>
                                                <option value='Finiquito'>Finiquito</option>
                                                <option value='Certificado de estudios para fines laborales'>Certificado de estudios para fines laborales</option>
                                                <option value='Licencia de Educación Media'>Licencia de Educación Media</option>
                                                <option value='Certificado de inscripción en el Registro Nacional de Prestadores Individuales de Salud'>Certificado de inscripción en el Registro Nacional de Prestadores Individuales de Salud</option>
                                                <option value='Hoja de vida conductor'>Hoja de vida conductor</option>
                                                <option value='Licencia conducir legalizada'>Licencia conducir legalizada</option>
                                            </optgroup>

                                        </select>
                                    </div>
                    
                                    <div class='form-group'>
                                        <label for='descripcion'>Descripción</label>
                                        <textarea name='descripcion' id='descripcion' rows='3'></textarea>
                                    </div>
                    
                                    <div class='form-group'>
                                        <label for='documento'>Seleccionar Documento</label>
                                        <input type='file' name='documento' id='documento' required>
                                    </div>
                                </div>
                    
                                <button type='submit' class='btn btn-green'>Subir documento</button>
                            </form>
                        </div>";
                        break;
                    case 'asignaciones':
                        echo "<h2>Gestión de Asignaciones</h2>";
                        break;
                }
                ?>
            </div>
        </div>
    </div>
</body>
</html>
