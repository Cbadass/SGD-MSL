<?php
require_once 'includes/db.php'; // Aquí va tu conexión PDO con Azure

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

$sql = "SELECT 
            e.Id_estudiante, e.Rut_estudiante, e.Nombre_estudiante, e.Apellido_estudiante, 
            e.Fecha_nacimiento, e.Fecha_ingreso, e.Id_curso, e.Estado_estudiante, e.Id_apoderado, e.Id_escuela, 
            a.Nombre_apoderado, a.Apellido_apoderado, a.Rut_apoderado, a.Numero_apoderado, a.Escolaridad_padre, 
            a.Escolaridad_madre, a.Ocupacion_padre, a.Ocupacion_madre, a.Correo_apoderado,
            esc.Nombre_escuela
        FROM estudiantes e
        LEFT JOIN apoderados a ON e.Id_apoderado = a.Id_apoderado
        LEFT JOIN escuelas esc ON e.Id_escuela = esc.Id_escuela
        WHERE 1=1";

$params = [];
if ($filtro_escuela !== '') {
    $sql .= " AND e.Id_escuela = ?";
    $params[] = $filtro_escuela;
}
if ($filtro_estado !== '') {
    $sql .= " AND e.Estado_estudiante = ?";
    $params[] = $filtro_estado;
}
$sql .= " ORDER BY e.Id_estudiante ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$estudiantes = $stmt->fetchAll();

echo "<div style='max-height: 350px; overflow-y: auto; border-radius: 10px;'>
<table>
<tr>
    <th>RUT</th><th>Nombres</th><th>Apellidos</th><th>Apoderado</th><th>Escuela</th><th>Edición</th>
</tr>";

if ($estudiantes) {
    foreach ($estudiantes as $row) {
        $apoderado = trim($row['Nombre_apoderado'] . " " . $row['Apellido_apoderado']);
        echo "<tr>
            <td>{$row['Rut_estudiante']}</td>
            <td>{$row['Nombre_estudiante']}</td>
            <td>{$row['Apellido_estudiante']}</td>
            <td>" . ($apoderado ?: '-') . "</td>
            <td>" . ($row['Nombre_escuela'] ?? '-') . "</td>
            <td>
                <button class='btn' onclick='mostrarModalEstudiante(" . htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') . ")'>Editar</button>
            </td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='6'>No se encontraron estudiantes registrados.</td></tr>";
}
echo "</table></div>";
?>

<div id="modalEstudiante" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); z-index:1000; align-items:center; justify-content:center;">
  <div style="background:white; border-radius:12px; padding:25px; max-width:700px; width:95%;">
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <h3 id="tituloModalEstudiante">Modificar Detalles del Estudiante</h3>
      <button onclick="cerrarModalEstudiante()" style="font-size:20px; border:none; background:none;">&times;</button>
    </div>
    <form id="formEstudianteEditar">
      <input type="hidden" name="Id_estudiante" id="Id_estudiante">
      <input type="hidden" name="Id_apoderado" id="Id_apoderado">
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
        <div><label>Nombres</label><input type="text" name="Nombre_estudiante" id="Nombre_estudiante"></div>
        <div><label>Apellidos</label><input type="text" name="Apellido_estudiante" id="Apellido_estudiante"></div>
        <div><label>Fecha de Ingreso</label><input type="date" name="Fecha_ingreso" id="Fecha_ingreso"></div>
        <div><label>RUT</label><input type="text" name="Rut_estudiante" id="Rut_estudiante"></div>
        <div><label>Fecha de nacimiento</label><input type="date" name="Fecha_nacimiento" id="Fecha_nacimiento"></div>
        <div></div>
      </div>
      <h4 style="margin-top:18px;">Datos de apoderado</h4>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
        <div><label>Nombres Apoderado</label><input type="text" name="Nombre_apoderado" id="Nombre_apoderado"></div>
        <div><label>Apellidos Apoderado</label><input type="text" name="Apellido_apoderado" id="Apellido_apoderado"></div>
        <div><label>Domicilio</label><input type="text" name="Domicilio_apoderado" id="Domicilio_apoderado"></div>
        <div><label>Escolaridad Padre</label><input type="text" name="Escolaridad_padre" id="Escolaridad_padre"></div>
        <div><label>Escolaridad Madre</label><input type="text" name="Escolaridad_madre" id="Escolaridad_madre"></div>
        <div><label>Correo Electronico</label><input type="email" name="Correo_apoderado" id="Correo_apoderado"></div>
        <div><label>Ocupación Padre</label><input type="text" name="Ocupacion_padre" id="Ocupacion_padre"></div>
        <div><label>Ocupación Madre</label><input type="text" name="Ocupacion_madre" id="Ocupacion_madre"></div>
        <div><label>Número de contacto</label><input type="text" name="Numero_apoderado" id="Numero_apoderado"></div>
      </div>
      <button type="button" class="btn btn-green" style="margin-top:15px;" onclick="guardarCambiosEstudiante()">Guardar Cambios</button>
    </form>
  </div>
</div>

<script>
function mostrarModalEstudiante(datos) {
    document.getElementById('modalEstudiante').style.display = 'flex';
    for (const key in datos) {
      let el = document.getElementById(key);
      if (el) el.value = datos[key] ?? '';
    }
}
function cerrarModalEstudiante() {
    document.getElementById('modalEstudiante').style.display = 'none';
}
function guardarCambiosEstudiante() {
    const formData = new FormData(document.getElementById('formEstudianteEditar'));
    fetch('editar_estudiante.php', {
        method: 'POST',
        body: formData
    }).then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('¡Cambios guardados!');
            location.reload();
        } else {
            alert('Error al guardar: ' + data.error);
        }
    });
}
</script>
