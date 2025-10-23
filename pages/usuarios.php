<?php
// pages/usuarios.php
require_once 'includes/db.php';
require_once 'includes/roles.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// ========== Obtener alcance ==========
$alcance = getAlcanceUsuario($conn, $_SESSION['usuario']);
$idsProfesionalesPermitidos = $alcance['profesionales'];
// ====================================

// 1) Recoger filtros
$escuela_filtro = $_GET['escuela'] ?? '';
$estado_filtro = $_GET['estado'] ?? '';
$cargo_filtro = $_GET['cargo'] ?? '';
$filtro_prof_id = intval($_GET['Id_profesional'] ?? 0);

$allowed_cargos = [
    'Administradora','Directora',
    'Profesor(a) Diferencial','Profesor(a)',
    'Asistentes de la educación','Especialistas',
    'Docente','Psicologa','Fonoaudiologo',
    'Kinesiologo','Terapeuta Ocupacional'
];

// 2) Formulario de búsqueda
echo "<h2 class='mb-4'>Visualización de Profesionales</h2>";
echo "<form method='GET' style='display:flex; gap:8rem ; margin: 2rem 0; align-items: flex-end;'>
        <input type='hidden' name='seccion' value='usuarios'>";

// Escuela (solo ADMIN)
if ($alcance['rol'] === 'ADMIN') {
    echo "<div>
            <label>Escuela</label>
            <select name='escuela' class='form-select'>
              <option value=''>Todas</option>
              <option value='1'".($escuela_filtro=='1'?' selected':'').">Sendero</option>
              <option value='2'".($escuela_filtro=='2'?' selected':'').">Multiverso</option>
              <option value='3'".($escuela_filtro=='3'?' selected':'').">Luz de Luna</option>
            </select>
          </div>";
}

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
        <select name='cargo' class='form-select'>
          <option value=''>Todos</option>";
foreach ($allowed_cargos as $c) {
    $s = $cargo_filtro === $c ? ' selected' : '';
    echo "<option value=\"".htmlspecialchars($c)."\"{$s}>".htmlspecialchars($c)."</option>";
}
echo "  </select>
      </div>";

// Autocomplete Profesional
echo "<div style='flex:1; position:relative;'>
        <label>Profesional</label>
        <input type='text' id='buscar_profesional' class='form-control' placeholder='RUT o Nombre'>
        <input type='hidden' name='Id_profesional' id='Id_profesional' value='".htmlspecialchars($filtro_prof_id)."'>
        <div id='resultados_profesional' class='border mt-1' style='position:absolute; width:100%; z-index:10; background:#fff;'></div>
      </div>";

echo "<button type='submit' class='btn btn-primary btn-height mt-4'>Buscar</button>
      <button type='button' class='btn btn-secondary btn-height mt-4' onclick=\"window.location='?seccion=usuarios'\">Limpiar filtros</button>
      </form>";

// 3) Construir consulta con filtro de alcance
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
    p.Cargo_profesional,
    e.Nombre_escuela
  FROM usuarios u
  LEFT JOIN profesionales p ON u.Id_profesional = p.Id_profesional
  LEFT JOIN escuelas      e ON p.Id_escuela_prof = e.Id_escuela
  WHERE " . filtrarPorIDs($idsProfesionalesPermitidos, 'p.Id_profesional');

$params = [];
agregarParametrosFiltro($params, $idsProfesionalesPermitidos);

if ($escuela_filtro !== '' && $alcance['rol'] === 'ADMIN') {
    $sql .= " AND p.Id_escuela_prof = ?";
    $params[] = $escuela_filtro;
}
if ($estado_filtro !== '') {
    $sql .= " AND u.Estado_usuario = ?";
    $params[] = $estado_filtro;
}
if ($cargo_filtro !== '') {
    $sql .= " AND p.Cargo_profesional = ?";
    $params[] = $cargo_filtro;
}
if ($filtro_prof_id > 0) {
    $sql .= " AND p.Id_profesional = ?";
    $params[] = $filtro_prof_id;
}

$sql .= " ORDER BY u.Id_usuario DESC
          OFFSET 0 ROWS FETCH NEXT 50 ROWS ONLY";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ... resto del código de la tabla sin cambios ...
// 5) Mostrar tabla
echo "<div style='max-height:400px; overflow-y:auto; border-radius:10px;'>
        <table class='table table-striped table-bordered'>
          <thead class='table-dark'>
            <tr>
              <th>RUT</th>
              <th>Usuario</th>
              <th>Nombres</th>
              <th>Apellidos</th>
              <th>Cargo</th>
              <th>Escuela</th>
              <th>Permisos</th>
              <th>Estado</th>
              <th>Edición</th>
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
                <td>".htmlspecialchars($row['Permisos']           ?? 'USER')."</td>
                <td>".($row['Estado_usuario']==1 ? 'Activo':'Inactivo')."</td>
                <td style='text-align:center;'>
                    <a href='index.php?seccion=modificar_profesional&Id_profesional=" . htmlspecialchars($row['Id_profesional']) . "' class='btn btn-sm btn-warning me-1 link-text'>Editar</a>
                    <a href='index.php?seccion=documentos&id_prof=" . htmlspecialchars($row['Id_profesional']) . "&sin_estudiante=1' class='btn btn-sm btn-info link-text'>Docs libres</a>
                    <a href=\"index.php?seccion=perfil&Id_profesional={$row['Id_profesional']}\"
                    class=\"btn btn-sm btn-primary link-text\">Ver perfil</a> 
                </td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='9'>No se encontraron usuarios.</td></tr>";
}

echo "    </tbody>
        </table>
      </div>";
?>

<script>
// Autocomplete para Profesionales
function buscarProfesional(endpoint, query, cont, idInput) {
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
document.getElementById('buscar_profesional')
  .addEventListener('input', e => {
    buscarProfesional('buscar_profesionales.php', e.target.value.trim(),
                      document.getElementById('resultados_profesional'),
                      'Id_profesional');
  });
</script>
