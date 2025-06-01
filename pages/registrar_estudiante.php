<?php
require_once 'includes/db.php'; // Tu conexión PDO

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

    try {
        // Iniciar transacción
        $conn->beginTransaction();

        // Insertar estudiante
        $stmt_est = $conn->prepare("INSERT INTO estudiantes (
            Nombre_estudiante, Apellido_estudiante, Rut_estudiante,
            Fecha_nacimiento, Fecha_ingreso, Estado_estudiante
        ) VALUES (
            :nombre_est, :apellido_est, :rut_est, :fecha_nac, :fecha_ing, 1
        )");

        $stmt_est->execute([
            ':nombre_est' => $nombre_est,
            ':apellido_est' => $apellido_est,
            ':rut_est' => $rut_est,
            ':fecha_nac' => $fecha_nac,
            ':fecha_ing' => $fecha_ing
        ]);

        // Obtener el ID del estudiante insertado
        $id_estudiante = $conn->lastInsertId();

        // Insertar apoderado
        $stmt_apo = $conn->prepare("INSERT INTO apoderados (
            Nombre_apoderado, Apellido_apoderado, Rut_apoderado, Numero_apoderado,
            Escolaridad_padre, Escolaridad_madre, Ocupacion_padre, Ocupacion_madre,
            Correo_apoderado, Id_estudiante, Domicilio_apoderado
        ) VALUES (
            :nombre_apo, :apellido_apo, :rut_apo, :numero,
            :escolaridad_padre, :escolaridad_madre, :ocupacion_padre, :ocupacion_madre,
            :correo_apo, :id_estudiante, :domicilio
        )");

        $stmt_apo->execute([
            ':nombre_apo' => $nombre_apo,
            ':apellido_apo' => $apellido_apo,
            ':rut_apo' => $rut_apo,
            ':numero' => $numero,
            ':escolaridad_padre' => $escolaridad_padre,
            ':escolaridad_madre' => $escolaridad_madre,
            ':ocupacion_padre' => $ocupacion_padre,
            ':ocupacion_madre' => $ocupacion_madre,
            ':correo_apo' => $correo_apo,
            ':id_estudiante' => $id_estudiante,
            ':domicilio' => $domicilio
        ]);

        $conn->commit();

        echo "<p style='color:green;'>✅ Estudiante y apoderado registrados correctamente.</p>";
    } catch (PDOException $e) {
        $conn->rollBack();
        echo "<p style='color:red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
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
?>
