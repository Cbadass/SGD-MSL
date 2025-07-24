<?php
// pages/actividad.php
session_start();
require_once __DIR__ . '/../includes/db.php';

// 1) Protege la página
if (!isset($_SESSION['usuario'])) {
    header("Location: ../login.php");
    exit;
}

// 2) Recoger filtros
$filtro_usuario     = trim($_GET['usuario_id']   ?? '');
$filtro_tabla       = $_GET['tabla']            ?? '';
$filtro_accion      = $_GET['accion']           ?? '';
$filtro_registro    = trim($_GET['registro_id'] ?? '');
$filtro_fecha_desde = $_GET['fecha_desde']      ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta']      ?? '';

// 3) Formulario de búsqueda avanzada
echo "<h2 class='mb-4'>Registro de Actividad</h2>";
echo "<form method='GET'>
      <input type='hidden' name='seccion' value='actividad'>";

// Fila 1
echo "<div style='display:flex; gap:8rem; margin: 2rem 0; align-items: flex-end;'>
        <div style='min-width:240px;'>
          <label class='form-label'>Usuario</label>
          <input type='text' id='buscar_usuario' class='form-control' placeholder='Nombre o RUT'>
          <input type='hidden' name='usuario_id' id='usuario_id' value='".htmlspecialchars($filtro_usuario)."'>
          <div id='resultados_usuario' class='border mt-1'></div>
        </div>
        <div>
          <label class='form-label'>Tabla</label>
          <select name='tabla' class='form-select'>
            <option value=''>Todas</option>";
$tablas = ['usuarios','profesionales','estudiantes','cursos','apoderados','documentos','Auditoria'];
foreach ($tablas as $t) {
    $sel = $filtro_tabla === $t ? ' selected' : '';
    echo "<option value='$t'$sel>".htmlspecialchars($t)."</option>";
}
echo "    </select>
        </div>
        <div>
          <label class='form-label'>Acción</label>
          <select name='accion' class='form-select'>
            <option value=''>Todas</option>";
$acciones = ['INSERT','UPDATE','DELETE'];
foreach ($acciones as $a) {
    $sel = $filtro_accion === $a ? ' selected' : '';
    echo "<option value='$a'$sel>$a</option>";
}
echo "    </select>
        </div>
        <div>
          <label class='form-label'>ID Registro</label>
          <input type='text' name='registro_id' class='form-control' value='".htmlspecialchars($filtro_registro)."'>
        </div>
      </div>";

// Fila 2
echo "<div style='display:flex; gap:8rem; margin: 2rem 0; align-items: flex-end;'>
        <div>
          <label class='form-label'>Fecha desde</label>
          <input type='date' name='fecha_desde' class='form-control' value='".htmlspecialchars($filtro_fecha_desde)."'>
        </div>
        <div>
          <label class='form-label'>Fecha hasta</label>
          <input type='date' name='fecha_hasta' class='form-control' value='".htmlspecialchars($filtro_fecha_hasta)."'>
        </div>
        <div style='display:flex; gap:1rem;'>
          <button type='submit' class='btn btn-primary btn-height'>Buscar</button>
          <button type='button' class='btn btn-secondary btn-height' onclick=\"window.location='?seccion=actividad'\">Limpiar</button>
        </div>
      </div>
      </form>";

// 4) Construir consulta dinámica
$where  = "1=1";
$params = [];

function filtrar(&$where, &$params, $campo, $valor, $exact = true) {
    if ($valor !== '') {
        if ($exact) {
            $where   .= " AND $campo = ?";
            $params[] = $valor;
        } else {
            $where   .= " AND $campo LIKE ?";
            $params[] = "%$valor%";
        }
    }
}

filtrar($where, $params, "a.Usuario_id",   $filtro_usuario);
filtrar($where, $params, "a.Tabla",        $filtro_tabla);
filtrar($where, $params, "a.Accion",       $filtro_accion);
filtrar($where, $params, "a.Registro_id",  $filtro_registro, false);

if ($filtro_fecha_desde !== '') {
    $where   .= " AND a.Fecha >= ?";
    $params[] = $filtro_fecha_desde . ' 00:00:00';
}
if ($filtro_fecha_hasta !== '') {
    $where   .= " AND a.Fecha <= ?";
    $params[] = $filtro_fecha_hasta . ' 23:59:59';
}

// 5) Ejecutar consulta con JOIN para mostrar nombre completo
$sql = "
    SELECT
      a.Id_auditoria,
      a.Tabla,
      a.Registro_id,
      a.Accion,
      a.Datos_nuevos,
      a.Fecha,
      u.Nombre_usuario,
      p.Nombre_profesional,
      p.Apellido_profesional
    FROM Auditoria a
    LEFT JOIN usuarios      u ON a.Usuario_id   = u.Id_usuario
    LEFT JOIN profesionales p ON u.Id_profesional = p.Id_profesional
    WHERE $where
    ORDER BY a.Fecha DESC
    OFFSET 0 ROWS FETCH NEXT 200 ROWS ONLY
";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6) Mostrar tabla sin columna "Datos Anteriores"
echo "<div class='table-responsive' style='max-height: 400px; overflow-y:auto; border-radius:10px;'>
        <table class='table table-striped table-bordered'>
          <thead class='table-dark'>
            <tr>
              <th>#</th>
              <th>Usuario</th>
              <th>Tabla</th>
              <th>ID Registro</th>
              <th>Acción</th>
              <th>Datos Nuevos</th>
              <th>Fecha</th>
            </tr>
          </thead>
          <tbody>";

$accionMap = [
  'INSERT' => 'Creación',
  'UPDATE' => 'Actualización',
  'DELETE' => 'Eliminación'
];

if ($logs) {
    foreach ($logs as $row) {
        // Usuario completo
        $userFull = $row['Nombre_profesional'] && $row['Apellido_profesional']
                  ? "{$row['Nombre_profesional']} {$row['Apellido_profesional']}"
                  : $row['Nombre_usuario'];

        // Acción descriptiva
        $accionDesc = $accionMap[$row['Accion']] ?? $row['Accion'];

        // Datos nuevos legibles
        $datosArr = json_decode($row['Datos_nuevos'], true) ?: [];
        $nuevosHtml = '<div style="max-height:150px;overflow:auto;">';
        foreach ($datosArr as $k => $v) {
            $nuevosHtml .= "<div><strong>".htmlspecialchars($k)."</strong>: ".htmlspecialchars((string)$v)."</div>";
        }
        $nuevosHtml .= '</div>';

        $fecha = date('Y-m-d H:i:s', strtotime($row['Fecha']));

        echo "<tr>
                <td>{$row['Id_auditoria']}</td>
                <td>".htmlspecialchars($userFull)."</td>
                <td>".htmlspecialchars($row['Tabla'])."</td>
                <td>".htmlspecialchars($row['Registro_id'])."</td>
                <td>{$accionDesc}</td>
                <td>{$nuevosHtml}</td>
                <td>{$fecha}</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='7'>No se encontraron registros.</td></tr>";
}

echo "    </tbody>
        </table>
      </div>";
?>

<script>
// Función genérica de búsqueda tipo autocomplete
function buscar(endpoint, query, cont, idInput) {
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
        div.textContent = `${item.rut} - ${item.nombre} ${item.apellido}`;
        div.onclick = () => {
          document.getElementById(idInput).value = item.id;
          cont.innerHTML = `<div class="resultado seleccionado">${div.textContent} (Seleccionado)</div>`;
        };
        cont.appendChild(div);
      });
    });
}

// Conecta el buscador de usuario al endpoint correspondiente
document.getElementById('buscar_usuario')
  .addEventListener('input', e => {
    buscar('buscar_usuarios.php', e.target.value.trim(),
           document.getElementById('resultados_usuario'),
           'usuario_id');
  });
</script>
