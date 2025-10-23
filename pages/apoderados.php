<?php
// pages/apoderados.php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/roles.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// ========== Obtener alcance ==========
$alcance = getAlcanceUsuario($conn, $_SESSION['usuario']);
$idsEstudiantesPermitidos = $alcance['estudiantes'];
// ====================================

$filtro_id = intval($_GET['Id_apoderado'] ?? 0);

echo "<h2 class='mb-4'>Visualización de Apoderados</h2>";
echo "<form method='GET' style='display:flex; gap:8rem ; margin: 2rem 0; align-items: flex-end;'>";
echo "  <input type='hidden' name='seccion' value='apoderados'>";
echo "  <div style='flex:1; position:relative;'>";
echo "    <label>Apoderado</label>";
echo "    <input type='text' id='buscar_apoderado' class='form-control' placeholder='RUT o Nombre'>";
echo "    <input type='hidden' name='Id_apoderado' id='Id_apoderado' value='".htmlspecialchars($filtro_id)."'>";
echo "    <div id='resultados_apoderado' class='border mt-1' style='position:absolute; width:100%; z-index:10; background:#fff;'></div>";
echo "  </div>";
echo "  <button type='submit' class='btn btn-primary btn-height mt-4'>Buscar</button>";
echo "  <button type='button' class='btn btn-secondary btn-height mt-4' onclick=\"window.location='?seccion=apoderados'\">Limpiar</button>";
echo "</form>";

// Construir consulta: apoderados solo de estudiantes permitidos
$where = "1=1";
$params = [];

if ($filtro_id > 0) {
    $where .= " AND Id_apoderado = ?";
    $params[] = $filtro_id;
}

// ========== Filtrar por estudiantes permitidos ==========
if ($idsEstudiantesPermitidos !== null) {
    if (empty($idsEstudiantesPermitidos) || $idsEstudiantesPermitidos === [0]) {
        $where .= " AND 0=1 "; // Sin estudiantes = sin apoderados
    } else {
        $placeholders = implode(',', array_fill(0, count($idsEstudiantesPermitidos), '?'));
        $where .= " AND Id_apoderado IN (
            SELECT DISTINCT Id_apoderado 
            FROM estudiantes 
            WHERE Id_estudiante IN ($placeholders) AND Id_apoderado IS NOT NULL
        ) ";
        $params = array_merge($params, $idsEstudiantesPermitidos);
    }
}
// =======================================================

$sql = "
    SELECT
      Id_apoderado,
      Nombre_apoderado,
      Apellido_apoderado,
      Rut_apoderado,
      Numero_apoderado,
      Escolaridad_padre,
      Escolaridad_madre,
      Ocupacion_padre,
      Ocupacion_madre,
      Correo_apoderado
    FROM apoderados
    WHERE $where
    ORDER BY Id_apoderado ASC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$apoderados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mostrar tabla
echo "<div style='max-height:400px; overflow-y:auto; border-radius:10px;'>";
echo "  <table class='table table-striped table-bordered'>";
echo "    <thead class='table-dark'>
            <tr>
              <th>Nombre</th>
              <th>Apellido</th>
              <th>RUT</th>
              <th>Teléfono</th>
              <th>Escolaridad Padre</th>
              <th>Escolaridad Madre</th>
              <th>Ocupación Padre</th>
              <th>Ocupación Madre</th>
              <th>Correo</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>";

if ($apoderados) {
    foreach ($apoderados as $row) {
        echo "<tr>
                <td>".htmlspecialchars($row['Nombre_apoderado'])."</td>
                <td>".htmlspecialchars($row['Apellido_apoderado'])."</td>
                <td>".htmlspecialchars($row['Rut_apoderado'])."</td>
                <td>".htmlspecialchars($row['Numero_apoderado'])."</td>
                <td>".htmlspecialchars($row['Escolaridad_padre'])."</td>
                <td>".htmlspecialchars($row['Escolaridad_madre'])."</td>
                <td>".htmlspecialchars($row['Ocupacion_padre'])."</td>
                <td>".htmlspecialchars($row['Ocupacion_madre'])."</td>
                <td>".htmlspecialchars($row['Correo_apoderado'])."</td>
                <td>";
        
        // Solo ADMIN y DIRECTOR pueden editar
        if (in_array($alcance['rol'], ['ADMIN', 'DIRECTOR'], true)) {
            echo "<a href='index.php?seccion=modificar_apoderado&Id_apoderado={$row['Id_apoderado']}' 
                    class='btn btn-sm btn-warning link-text'>Editar</a>";
        }
        
        echo "<a href=\"index.php?seccion=perfil&Id_apoderado={$row['Id_apoderado']}\"
                class=\"btn btn-sm btn-primary link-text\">Ver perfil</a> 
                </td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='10'>No se encontraron apoderados.</td></tr>";
}

echo "    </tbody>
        </table>
      </div>";
?>

<script>
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
        div.textContent = `${item.rut} — ${item.nombre} ${item.apellido}`;
        div.onclick = () => {
          document.getElementById(idInput).value = item.id;
          cont.innerHTML = `<div class="resultado seleccionado">${div.textContent} (Seleccionado)</div>`;
        };
        cont.appendChild(div);
      });
    });
}

const input = document.getElementById('buscar_apoderado');
const resultados = document.getElementById('resultados_apoderado');
input.addEventListener('input', e => {
  buscar('buscar_apoderados.php', e.target.value.trim(), resultados, 'Id_apoderado');
});
</script>