<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// 1) Protege la página
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// 2) Recoger filtros
$filtro_nombre   = trim($_GET['nombre_apoderado']   ?? '');
$filtro_apellido = trim($_GET['apellido_apoderado'] ?? '');
$filtro_rut      = trim($_GET['rut_apoderado']      ?? '');

// 3) Formulario de búsqueda
echo "<h2 class='mb-4'>Visualización de Apoderados</h2>";
echo "<form method='GET' class='mb-3 d-flex flex-wrap gap-2 align-items-end'>";
echo "  <input type='hidden' name='seccion' value='apoderados'>";

echo "  <div>
          <label>Nombre</label>
          <input type='text' name='nombre_apoderado' class='form-control' value='".htmlspecialchars($filtro_nombre)."'>
        </div>";

echo "  <div>
          <label>Apellido</label>
          <input type='text' name='apellido_apoderado' class='form-control' value='".htmlspecialchars($filtro_apellido)."'>
        </div>";

echo "  <div>
          <label>RUT</label>
          <input type='text' name='rut_apoderado' class='form-control' value='".htmlspecialchars($filtro_rut)."'>
        </div>";

echo "  <div class='d-flex gap-2'>
          <button type='submit' class='btn btn-primary mt-4'>Buscar</button>
          <button type='button' class='btn btn-secondary mt-4' onclick=\"window.location='?seccion=apoderados'\">Limpiar</button>
        </div>";
echo "</form>";

// 4) Construir consulta dinámica
$where  = "1=1";
$params = [];
if ($filtro_nombre !== '') {
    $where    .= " AND Nombre_apoderado LIKE ?";
    $params[] = "%{$filtro_nombre}%";
}
if ($filtro_apellido !== '') {
    $where    .= " AND Apellido_apoderado LIKE ?";
    $params[] = "%{$filtro_apellido}%";
}
if ($filtro_rut !== '') {
    $where    .= " AND Rut_apoderado LIKE ?";
    $params[] = "%{$filtro_rut}%";
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
