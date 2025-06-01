<?php
require_once 'includes/db.php'; // Archivo con tu conexión PDO configurada en Azure

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

    try {
        // Iniciar transacción
        $conn->beginTransaction();

        // Insertar profesional
        $stmt = $conn->prepare("INSERT INTO profesionales (
            Nombre_profesional, Apellido_profesional, Rut_profesional, Nacimiento_profesional,
            Domicilio_profesional, Celular_profesional, Correo_profesional, Estado_civil_profesional,
            Banco_profesional, Tipo_cuenta_profesional, Cuenta_B_profesional, AFP_profesional,
            Salud_profesional, Cargo_profesional, Horas_profesional, Fecha_ingreso,
            Tipo_profesional, Id_escuela_prof
        ) VALUES (
            :nombre, :apellido, :rut, :nacimiento,
            :domicilio, :telefono, :correo, :estado_civil,
            :banco, :tipo_cta, :cuenta, :afp,
            :salud, :cargo, :horas, :fecha_ing,
            :tipo_prof, :id_escuela
        )");
        $stmt->execute([
            ':nombre' => $nombre,
            ':apellido' => $apellido,
            ':rut' => $rut,
            ':nacimiento' => $nacimiento,
            ':domicilio' => $domicilio,
            ':telefono' => $telefono,
            ':correo' => $correo,
            ':estado_civil' => $estado_civil,
            ':banco' => $banco,
            ':tipo_cta' => $tipo_cta,
            ':cuenta' => $cuenta,
            ':afp' => $afp,
            ':salud' => $salud,
            ':cargo' => $cargo,
            ':horas' => $horas,
            ':fecha_ing' => $fecha_ing,
            ':tipo_prof' => $tipo_prof,
            ':id_escuela' => $id_escuela
        ]);
        $id_profesional = $conn->lastInsertId();

        // Insertar usuario
        $stmt_user = $conn->prepare("INSERT INTO usuarios (
            Nombre_usuario, Contraseña, Estado_usuario, Id_profesional
        ) VALUES (
            :nombre_usuario, :contrasena, 1, :id_profesional
        )");
        $stmt_user->execute([
            ':nombre_usuario' => $nombre_usuario,
            ':contrasena' => $contrasena,
            ':id_profesional' => $id_profesional
        ]);

        $conn->commit();

        echo "<p style='color:green;'>✅ Usuario registrado correctamente.<br>Nombre de usuario: <strong>$nombre_usuario</strong> | Contraseña: <strong>$contrasena</strong></p>";
    } catch (PDOException $e) {
        $conn->rollBack();
        echo "<p style='color:red;'>Error al registrar usuario: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// FORMULARIO HTML
echo '<h2>Registrar nuevo usuario</h2>
<form method="POST" class="form-grid">
    <div class="form-group"><label>Nombres</label><input name="nombre" required></div>
    <div class="form-group"><label>Apellidos</label><input name="apellido" type="text" required></div>
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
?>
