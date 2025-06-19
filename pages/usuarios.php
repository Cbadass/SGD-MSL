<?php
require_once 'includes/db.php';
session_start();

// /* Descomenta en producción
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}
// */

// 1) Recoger filtros
$escuela_filtro          = $_GET['escuela']               ?? '';
$estado_filtro           = $_GET['estado']                ?? '';
$cargo_filtro            = $_GET['cargo']                 ?? '';
$buscar_usuario          = trim($_GET['buscar']           ?? '');
$nombre_prof_filtro      = trim($_GET['nombre_profesional'] ?? '');
$apellido_prof_filtro    = trim($_GET['apellido_profesional']?? '');
$rut_prof_filtro         = trim($_GET['rut_profesional']    ?? '');

// 2) Cargos permitidos
$allowed_cargos = [
    'Administradora',
    'Directora',
    'Profesor(a) Diferencial',
    'Profesor(a)',
    'Asistentes de la educación',
    'Especialistas',
    'Docente',
    'Psicologa',
    'Fonoaudiologo',
    'Kinesiologo',
    'Terapeuta Ocupacional'
];

// 3) Formulario de búsqueda avanzada
echo "<h2 class='mb-4'>Visualización de Profesionales</h2>";
echo "<form id='formFiltros' method='GET' class='mb-3 d-flex flex-wrap gap-2 align-items-end'>";
// seccion oculta
echo "<input type='hidden' name='seccion' value='usuarios'>";
// Escuelas
echo "<div>
        <label>Escuela</label>
        <select name='escuela' class='form-select'>
          <option value=''>Todas</option>
          <option value='1'".($escuela_filtro=='1'?' selected':'').">Sendero</option>
          <option value='2'".($escuela_filtro=='2'?' selected':'').">Multiverso</option>
          <option value='3'".($escuela_filtro=='3'?' selected':'').">Luz de Luna</option>
        </select>
      </div>";
// Estado
echo "<div>
        <label>Estado</label>
        <select name='estado' class='form-select'>
          <option value=''>Todos</option>
          <option value='1'".($estado_filtro=='1'?' selected':'').">Activo</option>
          <option value='0'".($estado_filtro=='0'?' selected':'').">Inactivo</option>
        </select>
      </div>";
// Cargo
echo "<div>
        <label>Cargo</label>
        <select name='cargo' class='form-select'>";
echo   "<option value=''>Todos</option>";
foreach($allowed_cargos as $c) {
    $s = $cargo_filtro === $c ? ' selected' : '';
    echo "<option value=\"".htmlspecialchars($c)."\"{$s}>".htmlspecialchars($c)."</option>";
}
echo   "</select>
      </div>";
// Campos avanzados
echo "<div>
        <label>Nombre prof.</label>
        <input type='text' name='nombre_profesional' class='form-control' value='".htmlspecialchars($nombre_prof_filtro)."'>
      </div>";
echo "<div>
        <label>Apellido prof.</label>
        <input type='text' name='apellido_profesional' class='form-control' value='".htmlspecialchars($apellido_prof_filtro)."'>
      </div>";
echo "<div>
        <label>RUT prof.</label>
        <input type='text' name='rut_profesional' class='form-control' value='".htmlspecialchars($rut_prof_filtro)."'>
      </div>";
// Buscar usuario (username)
echo "<div style='flex:1'>
        <label>Usuario</label>
        <input type='text' name='buscar' class='form-control' placeholder='usuario o nombre' value='".htmlspecialchars($buscar_usuario)."'>
      </div>";
// Botones
echo "<div class='d-flex gap-2'>
        <button type='submit' class='btn btn-primary mt-4'>Buscar</button>
        <button type='button' class='btn btn-secondary mt-4' onclick='limpiarFiltros()'>Limpiar filtros</button>
      </div>";
echo "</form>";

// 4) Construir consulta con columnas explícitas
$sql = "
  SELECT
    u.Id_usuario,
    u.Nombre_usuario,
    u.Permisos,
    u.Estado_usuario,
    p.Id_profesional,
    p.Nombre_profesional,
    p.Apellido_profesional,
    p.Rut_profesional,
    p.Nacimiento_profesional,
    p.Celular_profesional,
    p.Correo_profesional,
    p.Cargo_profesional,
    e.Nombre_escuela
  FROM usuarios u
  LEFT JOIN profesionales p ON u.Id_profesional = p.Id_profesional
  LEFT JOIN escuelas      e ON p.Id_escuela_prof = e.Id_escuela
  WHERE 1=1
";
$params = [];
if ($escuela_filtro!=='') {
    $sql    .= " AND p.Id_escuela_prof = ?";
    $params[] = $escuela_filtro;
}
if ($estado_filtro!=='') {
    $sql    .= " AND u.Estado_usuario = ?";
    $params[] = $estado_filtro;
}
if ($cargo_filtro!=='') {
    $sql    .= " AND p.Cargo_profesional = ?";
    $params[] = $cargo_filtro;
}
if ($nombre_prof_filtro!=='') {
    $sql    .= " AND p.Nombre_profesional LIKE ?";
    $params[] = "%{$nombre_prof_filtro}%";
}
if ($apellido_prof_filtro!=='') {
    $sql    .= " AND p.Apellido_profesional LIKE ?";
    $params[] = "%{$apellido_prof_filtro}%";
}
if ($rut_prof_filtro!=='') {
    $sql    .= " AND p.Rut_profesional LIKE ?";
    $params[] = "%{$rut_prof_filtro}%";
}
if ($buscar_usuario!=='') {
    $sql    .= " AND u.Nombre_usuario LIKE ?";
    $params[] = "%{$buscar_usuario}%";
}
$sql .= " ORDER BY u.Id_usuario DESC
          OFFSET 0 ROWS FETCH NEXT 50 ROWS ONLY";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5) Mostrar tabla
echo "<div style='max-height:400px; overflow-y:auto; border-radius:10px;'>
        <table class='table table-striped table-bordered'>
          <thead class='table-dark'>
            <tr>
              <th>RUT</th><th>Usuario</th><th>Nombres</th><th>Apellidos</th><th>Cargo</th>
              <th>Escuela</th><th>Permisos</th><th>Estado</th><th>Edición</th>
            </tr>
          </thead>
          <tbody>";
if ($usuarios) {
    foreach ($usuarios as $row) {
        echo "<tr>
                <td>".htmlspecialchars($row['Rut_profesional']   ?? '-')."</td>
                <td>".htmlspecialchars($row['Nombre_usuario'])."</td>
                <td>".htmlspecialchars($row['Nombre_profesional']?? '-')."</td>
                <td>".htmlspecialchars($row['Apellido_profesional']?? '-')."</td>
                <td>".htmlspecialchars($row['Cargo_profesional']  ?? '-')."</td>
                <td>".htmlspecialchars($row['Nombre_escuela']     ?? 'Otra')."</td>
                <td>".htmlspecialchars($row['Permisos']           ?? 'user')."</td>
                <td>".($row['Estado_usuario']==1 ? 'Activo':'Inactivo')."</td>
                <td>
                  <a 
                    href=\"modificar_profesional.php?Id_profesional={$row['Id_profesional']}\" 
                    class=\"btn btn-sm btn-warning\"
                  >Editar</a>
                </td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='9'>No se encontraron usuarios.</td></tr>";
}
echo "  </tbody>
        </table>
      </div>";
?>

<script>
function limpiarFiltros() {
  window.location.href = window.location.pathname + '?seccion=usuarios';
}
</script>
