<?php
require_once 'includes/db.php';
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// 1) Recoger filtros
$filtro_escuela    = $_GET['escuela']              ?? '';
$filtro_estado     = $_GET['estado']               ?? '';
$filtro_nombre     = trim($_GET['nombre_estudiante'] ?? '');
$filtro_apellido   = trim($_GET['apellido_estudiante'] ?? '');
$filtro_rut        = trim($_GET['rut_estudiante']    ?? '');
$buscar_libre      = trim($_GET['buscar']            ?? '');

// 2) Formulario de búsqueda avanzada
echo "<h2 class='mb-4'>Visualización de Estudiantes</h2>";
echo "<form method='GET' class='mb-3 d-flex flex-wrap gap-2 align-items-end'>";
echo "<input type='hidden' name='seccion' value='estudiantes'>";

// Escuela
echo "<div>
        <label>Escuela</label>
        <select name='escuela' class='form-select'>
          <option value=''>Todas</option>
          <option value='1'".($filtro_escuela=='1'?' selected':'').">Sendero</option>
          <option value='2'".($filtro_escuela=='2'?' selected':'').">Multiverso</option>
          <option value='3'".($filtro_escuela=='3'?' selected':'').">Luz de Luna</option>
        </select>
      </div>";

// Estado
echo "<div>
        <label>Estado</label>
        <select name='estado' class='form-select'>
          <option value=''>Todos</option>
          <option value='1'".($filtro_estado=='1'?' selected':'').">Activo</option>
          <option value='0'".($filtro_estado=='0'?' selected':'').">Inactivo</option>
        </select>
      </div>";

// Nombre
echo "<div>
        <label>Nombre</label>
        <input type='text' name='nombre_estudiante' class='form-control' value='".htmlspecialchars($filtro_nombre)."'>
      </div>";

// Apellido
echo "<div>
        <label>Apellido</label>
        <input type='text' name='apellido_estudiante' class='form-control' value='".htmlspecialchars($filtro_apellido)."'>
      </div>";

// RUT
echo "<div>
        <label>RUT</label>
        <input type='text' name='rut_estudiante' class='form-control' value='".htmlspecialchars($filtro_rut)."'>
      </div>";

// Libre
echo "<div style='flex:1'>
        <label>Buscar</label>
        <input type='text' name='buscar' class='form-control' placeholder='cualquier término' value='".htmlspecialchars($buscar_libre)."'>
      </div>";

// Botones
echo "<div class='d-flex gap-2'>
        <button type='submit' class='btn btn-primary mt-4'>Buscar</button>
        <button type='button' class='btn btn-secondary mt-4' onclick=\"window.location='?seccion=estudiantes'\">Limpiar filtros</button>
      </div>";

echo "</form>";

// 3) Construir consulta dinámica
$where  = "1=1";
$params = [];

// Helper
function filtrar(&$where,&$params,$campo,$valor){
  if($valor!==''){
    $where   .= " AND $campo LIKE ?";
    $params[] = "%$valor%";
  }
}

filtrar($where,$params,"e.Id_escuela",$filtro_escuela);
filtrar($where,$params,"e.Estado_estudiante",$filtro_estado);
filtrar($where,$params,"e.Nombre_estudiante",$filtro_nombre);
filtrar($where,$params,"e.Apellido_estudiante",$filtro_apellido);
filtrar($where,$params,"e.Rut_estudiante",$filtro_rut);
if($buscar_libre!==''){
  // busca en nombre, apellido o rut
  $where .= " AND (e.Nombre_estudiante LIKE ? OR e.Apellido_estudiante LIKE ? OR e.Rut_estudiante LIKE ?)";
  $params[]= "%$buscar_libre%";
  $params[]= "%$buscar_libre%";
  $params[]= "%$buscar_libre%";
}

$sql = "
  SELECT
    e.Id_estudiante,
    e.Nombre_estudiante,
    e.Apellido_estudiante,
    e.Rut_estudiante,
    e.Fecha_nacimiento,
    c.Tipo_curso,
    c.Grado_curso,
    c.seccion_curso,
    esc.Nombre_escuela,
    a.Nombre_apoderado,
    a.Apellido_apoderado,
    a.Numero_apoderado,
    a.Correo_apoderado
  FROM estudiantes e
  LEFT JOIN cursos      c   ON e.Id_curso    = c.Id_curso
  LEFT JOIN escuelas    esc ON e.Id_escuela  = esc.Id_escuela
  LEFT JOIN apoderados  a   ON e.Id_apoderado= a.Id_apoderado
  WHERE $where
  ORDER BY e.Id_estudiante ASC
";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4) Mostrar tabla
echo "<div style='max-height:400px; overflow-y:auto; border-radius:10px;'>
        <table class='table table-striped table-bordered'>
          <thead class='table-dark'>
            <tr>
              <th>Nombre completo</th>
              <th>RUT</th>
              <th>Edad</th>
              <th>Curso</th>
              <th>Escuela</th>
              <th>Apoderado</th>
              <th>Número</th>
              <th>Correo apoderado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>";

$hoy = new DateTime();
foreach($estudiantes as $row){
    // calcular edad
    $nac = new DateTime($row['Fecha_nacimiento']);
    $edad= $hoy->diff($nac)->y;

    $nombreCompleto  = "{$row['Nombre_estudiante']} {$row['Apellido_estudiante']}";
    $cursoCompleto   = "{$row['Tipo_curso']}-{$row['Grado_curso']}-{$row['seccion_curso']}";
    $apoderadoFull   = trim("{$row['Nombre_apoderado']} {$row['Apellido_apoderado']}");

    echo "<tr>
            <td>".htmlspecialchars($nombreCompleto)."</td>
            <td>".htmlspecialchars($row['Rut_estudiante'])."</td>
            <td>{$edad}</td>
            <td>".htmlspecialchars($cursoCompleto)."</td>
            <td>".htmlspecialchars($row['Nombre_escuela'])."</td>
            <td>".($apoderadoFull?:'-')."</td>
            <td>".htmlspecialchars($row['Numero_apoderado'] ?? '-')."</td>
            <td>".htmlspecialchars($row['Correo_apoderado'] ?? '-')."</td>
            <td>
              <a href=\"index.php?seccion=modificar_estudiante&Id_estudiante={$row['Id_estudiante']}\" 
                 class=\"btn btn-sm btn-warning\">Editar</a>
              <a href=\"index.php?seccion=documentos&estudiante=".urlencode($row['Rut_estudiante'])."\" 
                 class=\"btn btn-sm btn-info\">Documentos</a>
            </td>
          </tr>";
}
if(empty($estudiantes)){
    echo "<tr><td colspan='9'>No se encontraron estudiantes.</td></tr>";
}

echo "    </tbody>
        </table>
      </div>";
?>

<script>
function limpiarFiltros(){
  window.location.href = window.location.pathname + '?seccion=estudiantes';
}
</script>
