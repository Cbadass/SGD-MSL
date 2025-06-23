<?php
// pages/apoderados.php
session_start();
require_once __DIR__ . '/../includes/db.php';

// 1) Protege la página
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// 2) Recoger filtro único por selección
$filtro_id = intval($_GET['Id_apoderado'] ?? 0);

// 3) Formulario de búsqueda con autocomplete
echo "<h2 class='mb-4'>Visualización de Apoderados</h2>";
echo "<form method='GET' style='display:flex; gap:8rem ; margin: 2rem 0; align-items: flex-end;'";
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

// 4) Construir consulta dinámica
$where  = "1=1";
$params = [];
if ($filtro_id > 0) {
    $where   .= " AND Id_apoderado = ?";
    $params[] = $filtro_id;
}

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

// 5) Mostrar tabla
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
                <td>
                  <a href='index.php?seccion=modificar_apoderado&Id_apoderado={$row['Id_apoderado']}' 
                     class='btn btn-sm btn-warning'>Editar</a>
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
// Función de autocomplete usando buscar_apoderados.php
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
