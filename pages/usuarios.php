<?php
require_once 'includes/db.php';
session_start();

// Verificar si está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// Filtros
$escuela_filtro = $_GET['escuela'] ?? '';
$estado_filtro = $_GET['estado'] ?? '';
$buscar_usuario = trim($_GET['buscar'] ?? '');

// Formulario de búsqueda y filtros
echo "<h2 class='mb-4'>Visualización de Usuarios</h2>";
echo "<form method='GET' class='mb-3 d-flex flex-wrap gap-2'>
    <input type='hidden' name='seccion' value='usuarios'>
    <select name='escuela' class='form-select w-auto'>
        <option value=''>Todas las escuelas</option>
        <option value='1'" . ($escuela_filtro == '1' ? ' selected' : '') . ">Sendero</option>
        <option value='2'" . ($escuela_filtro == '2' ? ' selected' : '') . ">Multiverso</option>
        <option value='3'" . ($escuela_filtro == '3' ? ' selected' : '') . ">Luz de Luna</option>
    </select>
    <select name='estado' class='form-select w-auto'>
        <option value=''>Todos los estados</option>
        <option value='1'" . ($estado_filtro == '1' ? ' selected' : '') . ">Activo</option>
        <option value='0'" . ($estado_filtro == '0' ? ' selected' : '') . ">Inactivo</option>
    </select>
    <input type='text' name='buscar' class='form-control w-auto' placeholder='Buscar usuario o profesional' value='" . htmlspecialchars($buscar_usuario) . "'>
    <button class='btn btn-primary' type='submit'>Filtrar</button>
</form>";

// Consulta principal con joins y filtros
$sql = "SELECT u.*, p.*, e.Nombre_escuela
        FROM usuarios u
        LEFT JOIN profesionales p ON u.Id_profesional = p.Id_profesional
        LEFT JOIN escuelas e ON p.Id_escuela_prof = e.Id_escuela
        WHERE 1=1";

$params = [];
if ($escuela_filtro !== '') {
    $sql .= " AND p.Id_escuela_prof = ?";
    $params[] = $escuela_filtro;
}
if ($estado_filtro !== '') {
    $sql .= " AND u.Estado_usuario = ?";
    $params[] = $estado_filtro;
}
if ($buscar_usuario !== '') {
    $sql .= " AND (
        u.Nombre_usuario LIKE ? OR
        p.Nombre_profesional LIKE ? OR
        p.Apellido_profesional LIKE ? OR
        p.Rut_profesional LIKE ?
    )";
    $params = array_merge($params, array_fill(0, 4, "%$buscar_usuario%"));
}
$sql .= " ORDER BY u.Id_usuario DESC OFFSET 0 ROWS FETCH NEXT 50 ROWS ONLY";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

// Mostrar resultados
echo "<div style='max-height: 400px; overflow-y: auto; border-radius: 10px;'>
<table class='table table-striped table-bordered'>
<tr class='table-dark'>
    <th>Rut</th><th>Usuario</th><th>Nombres</th><th>Apellidos</th><th>Cargo</th>
    <th>Escuela</th><th>Permisos</th><th>Estado</th><th>Edición</th>
</tr>";

if ($usuarios) {
    foreach ($usuarios as $row) {
        echo "<tr>
            <td>" . htmlspecialchars($row['Rut_profesional'] ?? '-') . "</td>
            <td>" . htmlspecialchars($row['Nombre_usuario']) . "</td>
            <td>" . htmlspecialchars($row['Nombre_profesional'] ?? '-') . "</td>
            <td>" . htmlspecialchars($row['Apellido_profesional'] ?? '-') . "</td>
            <td>" . htmlspecialchars($row['Cargo_profesional'] ?? '-') . "</td>
            <td>" . htmlspecialchars($row['Nombre_escuela'] ?? 'Otra') . "</td>
            <td>" . htmlspecialchars($row['Permisos'] ?? 'user') . "</td>
            <td>" . ($row['Estado_usuario'] == 1 ? 'Activo' : 'Inactivo') . "</td>
            <td>
                <button class='btn btn-sm btn-warning' onclick='mostrarModalUsuario(" . htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') . ")'>Editar</button>
            </td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='9'>No se encontraron usuarios.</td></tr>";
}
echo "</table></div>";
?>

<!-- Modal de edición -->
<div id="modalUsuario" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); z-index:1000; align-items:center; justify-content:center;">
  <div style="background:white; border-radius:12px; padding:25px; max-width:800px; width:96%;">
    <div class="d-flex justify-content-between align-items-center">
      <h2>Editar Usuario</h2>
      <button onclick="cerrarModalUsuario()" style="font-size:24px; border:none; background:none;">&times;</button>
    </div>
    <form id="formUsuarioEditar" style="display:grid; grid-template-columns:repeat(3,1fr); gap:18px;">
      <input type="hidden" name="Id_usuario" id="edit_Id_usuario">
      <input type="hidden" name="Id_profesional" id="edit_Id_profesional">

      <div class="form-group"><label>Usuario</label><input name="Nombre_usuario" id="edit_Nombre_usuario" type="text" required></div>
      <div class="form-group"><label>Nombres</label><input name="Nombre_profesional" id="edit_Nombre_profesional" type="text"></div>
      <div class="form-group"><label>Apellidos</label><input name="Apellido_profesional" id="edit_Apellido_profesional" type="text"></div>
      <div class="form-group"><label>Correo</label><input name="Correo_profesional" id="edit_Correo_profesional" type="email"></div>
      <div class="form-group"><label>Número</label><input name="Celular_profesional" id="edit_Celular_profesional" type="text"></div>
      <div class="form-group"><label>RUT</label><input name="Rut_profesional" id="edit_Rut_profesional" type="text"></div>
      <div class="form-group"><label>Fecha de nacimiento</label><input name="Nacimiento_profesional" id="edit_Nacimiento_profesional" type="date"></div>
      <div class="form-group"><label>Cargo</label><input name="Cargo_profesional" id="edit_Cargo_profesional" type="text"></div>
      <div class="form-group"><label>Permisos</label>
        <select name="Permisos" id="edit_Permisos">
          <option value="user">Usuario</option>
          <option value="admin">Administrador</option>
        </select>
      </div>
      <div class="form-group"><label>Estado</label>
        <select name="Estado_usuario" id="edit_Estado_usuario">
          <option value="1">Activo</option>
          <option value="0">Inactivo</option>
        </select>
      </div>
      <div class="form-group" style="grid-column: 1/-1;">
        <button type="button" class="btn btn-success" onclick="guardarCambiosUsuario()">Guardar Cambios</button>
      </div>
    </form>
  </div>
</div>

<script>
function mostrarModalUsuario(datos) {
    document.getElementById('modalUsuario').style.display = 'flex';
    for (const key in datos) {
        let el = document.getElementById('edit_' + key);
        if (el) el.value = datos[key] ?? '';
    }
}
function cerrarModalUsuario() {
    document.getElementById('modalUsuario').style.display = 'none';
}
function guardarCambiosUsuario() {
    const formData = new FormData(document.getElementById('formUsuarioEditar'));
    fetch('editar_usuario.php', {
        method: 'POST',
        body: formData
    }).then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('¡Cambios guardados!');
            cerrarModalUsuario();
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    });
}
</script>
