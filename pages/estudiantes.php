<?php
// pages/estudiantes.php
require_once 'includes/db.php';
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// 1) Recoger filtros
$filtro_escuela      = $_GET['escuela']                 ?? '';
$filtro_estado       = $_GET['estado']                  ?? '';
$filtro_estudiante   = intval($_GET['Id_estudiante']    ?? 0);

// 2) Formulario de búsqueda avanzada
echo "<h2 class='mb-4'>Visualización de Estudiantes</h2>";
echo "<form method='GET' style='display:flex; gap:8rem ; margin: 2rem 0; align-items: flex-end;'";
echo "  <input type='hidden' name='seccion' value='estudiantes'>";

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

// Autocomplete Estudiante
echo "<div style='flex:1; position:relative;'>
        <label>Estudiante</label>
        <input type='text' id='buscar_estudiante' class='form-control' placeholder='RUT o Nombre'>
        <input type='hidden' name='Id_estudiante' id='Id_estudiante' value='".htmlspecialchars($filtro_estudiante)."'>
        <div id='resultados_estudiante' class='border mt-1' style='position:absolute; width:100%; z-index:10; background:#fff;'></div>
      </div>";

// Botones
echo "<button type='submit' class='btn btn-primary btn-height mt-4'>Buscar</button>
      <button type='button' class='btn btn-secondary btn-height mt-4' onclick=\"window.location='?seccion=estudiantes'\">Limpiar filtros</button>";
echo "</form>";

// 3) Construir consulta dinámica
$where  = "1=1";
$params = [];

if ($filtro_escuela !== '') {
    $where   .= " AND e.Id_escuela = ?";
    $params[] = $filtro_escuela;
}
if ($filtro_estado !== '') {
    $where   .= " AND e.Estado_estudiante = ?";
    $params[] = $filtro_estado;
}
if ($filtro_estudiante > 0) {
    $where   .= " AND e.Id_estudiante = ?";
    $params[] = $filtro_estudiante;
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
  LEFT JOIN cursos      c   ON e.Id_curso     = c.Id_curso
  LEFT JOIN escuelas    esc ON e.Id_escuela   = esc.Id_escuela
  LEFT JOIN apoderados  a   ON e.Id_apoderado = a.Id_apoderado
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
    $nac          = new DateTime($row['Fecha_nacimiento']);
    $edad         = $hoy->diff($nac)->y;
    $nombreComp   = "{$row['Nombre_estudiante']} {$row['Apellido_estudiante']}";
    $cursoComp    = "{$row['Tipo_curso']}-{$row['Grado_curso']}-{$row['seccion_curso']}";
    $apoderadoFull = trim("{$row['Nombre_apoderado']} {$row['Apellido_apoderado']}");

    echo "<tr>
            <td>".htmlspecialchars($nombreComp)."</td>
            <td>".htmlspecialchars($row['Rut_estudiante'])."</td>
            <td>{$edad}</td>
            <td>".htmlspecialchars($cursoComp)."</td>
            <td>".htmlspecialchars($row['Nombre_escuela'])."</td>
            <td>".($apoderadoFull?:'-')."</td>
            <td>".htmlspecialchars($row['Numero_apoderado'] ?? '-')."</td>
            <td>".htmlspecialchars($row['Correo_apoderado'] ?? '-')."</td>
            <td>
              <a href=\"index.php?seccion=modificar_estudiante&Id_estudiante={$row['Id_estudiante']}\" 
                class=\"btn btn-sm btn-warning\">Editar</a>
              <a href=\"index.php?seccion=documentos&id_estudiante={$row['Id_estudiante']}&sin_profesional=1\" 
                 class=\"btn btn-sm btn-info\">Documentos</a>
              <a href=\"index.php?seccion=perfil&Id_estudiante={$row['Id_estudiante']}\"
                 class=\"btn btn-sm btn-primary\">Ver perfil</a> 
            </td>
          </tr>";
}
if (empty($estudiantes)) {
    echo "<tr><td colspan='9'>No se encontraron estudiantes.</td></tr>";
}

echo "    </tbody>
        </table>
      </div>";
?>

<script>
// Autocomplete para Estudiantes
function buscarEstudiante(endpoint, query, cont, idInput) {
  if (query.length < 3) {
    cont.innerHTML = '';
    return;
  }
  fetch(endpoint + '?q=' + encodeURIComponent(query))
    .then(res => res.json())
    .then(data => {
      cont.innerHTML = '';
      if (!data.length) {
        cont.innerHTML = '<div class="p-2 text-muted">Sin resultados</div>';
        return;
      }
      data.forEach(item => {
        const div = document.createElement('div');
        div.className = 'resultado';
        div.textContent = `${item.rut} — ${item.nombre} ${item.apellido}`;
        div.onclick = () => {
          document.getElementById(idInput).value = item.id;
          cont.innerHTML = `<div class="resultado seleccionado">${div.textContent} (Seleccionado)</div>`;
        };
        cont.appendChild(div);
      });
    });
}

const inp = document.getElementById('buscar_estudiante');
const panel = document.getElementById('resultados_estudiante');
inp.addEventListener('input', e => {
  buscarEstudiante('buscar_estudiantes.php', e.target.value.trim(), panel, 'Id_estudiante');
});
</script>
