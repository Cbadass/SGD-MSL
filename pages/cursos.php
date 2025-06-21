<?php
// pages/cursos.php
session_start();
require_once __DIR__ . '/../includes/db.php';

// 1) Protege la página
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// 2) Recoger filtros
$filtro_escuela    = $_GET['escuela']        ?? '';
$filtro_tipo       = trim($_GET['tipo_curso'] ?? '');
$filtro_grado      = trim($_GET['grado_curso']?? '');
$filtro_seccion    = trim($_GET['seccion_curso'] ?? '');
$filtro_docente    = trim($_GET['docente']    ?? '');

// 3) Formulario de búsqueda avanzada
echo "<h2 class='mb-4'>Visualización de Cursos</h2>";
echo "<form method='GET' class='mb-3 d-flex flex-wrap gap-2 align-items-end'>";
echo "  <input type='hidden' name='seccion' value='cursos'>";

echo "  <div>
          <label>Escuela</label>
          <select name='escuela' class='form-select'>
            <option value=''>Todas</option>
            <option value='1'".($filtro_escuela=='1'?' selected':'').">Sendero</option>
            <option value='2'".($filtro_escuela=='2'?' selected':'').">Multiverso</option>
            <option value='3'".($filtro_escuela=='3'?' selected':'').">Luz de Luna</option>
          </select>
        </div>";

echo "  <div>
          <label>Tipo</label>
          <input type='text' name='tipo_curso' class='form-control' value='".htmlspecialchars($filtro_tipo)."'>
        </div>";

echo "  <div>
          <label>Grado</label>
          <input type='text' name='grado_curso' class='form-control' value='".htmlspecialchars($filtro_grado)."'>
        </div>";

echo "  <div>
          <label>Sección</label>
          <input type='text' name='seccion_curso' class='form-control' value='".htmlspecialchars($filtro_seccion)."'>
        </div>";

echo "  <div style='flex:1'>
          <label>Docente</label>
          <input type='text' name='docente' class='form-control' placeholder='Nombre o RUT' value='".htmlspecialchars($filtro_docente)."'>
        </div>";

echo "  <div class='d-flex gap-2'>
          <button type='submit' class='btn btn-primary mt-4'>Buscar</button>
          <button type='button' class='btn btn-secondary mt-4' onclick=\"window.location='?seccion=cursos'\">Limpiar filtros</button>
        </div>";

echo "</form>";

// 4) Construir consulta dinámica
$where  = "1=1";
$params = [];
function filtrar(&$where,&$params,$campo,$valor) {
    if ($valor!=='') {
        $where   .= " AND $campo LIKE ?";
        $params[] = "%$valor%";
    }
}
filtrar($where,$params,"c.Id_escuela",$filtro_escuela);
filtrar($where,$params,"c.Tipo_curso",$filtro_tipo);
filtrar($where,$params,"c.Grado_curso",$filtro_grado);
filtrar($where,$params,"c.seccion_curso",$filtro_seccion);
if ($filtro_docente!=='') {
    $where .= " AND (
        p.Nombre_profesional LIKE ? OR
        p.Apellido_profesional LIKE ? OR
        p.Rut_profesional LIKE ?
    )";
    $params[] = "%{$filtro_docente}%";
    $params[] = "%{$filtro_docente}%";
    $params[] = "%{$filtro_docente}%";
}

$sql = "
  SELECT
    c.Id_curso,
    c.Tipo_curso,
    c.Grado_curso,
    c.seccion_curso,
    e.Nombre_escuela,
    p.Id_profesional,
    p.Nombre_profesional,
    p.Apellido_profesional
  FROM cursos c
  LEFT JOIN escuelas    e ON c.Id_escuela    = e.Id_escuela
  LEFT JOIN profesionales p ON c.Id_profesional = p.Id_profesional
  WHERE $where
  ORDER BY c.Id_curso ASC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5) Mostrar tabla
echo "<div style='max-height:400px; overflow-y:auto; border-radius:10px;'>
        <table class='table table-striped table-bordered'>
          <thead class='table-dark'>
            <tr>
              <th>Escuela</th>
              <th>Tipo</th>
              <th>Grado</th>
              <th>Sección</th>
              <th>Docente Encargado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>";

if ($cursos) {
    foreach ($cursos as $row) {
        $nombre_docente = $row['Id_profesional']
            ? htmlspecialchars("{$row['Nombre_profesional']} {$row['Apellido_profesional']}")
            : "<em>No asignado</em>";
        echo "<tr>
                <td>".htmlspecialchars($row['Nombre_escuela'])."</td>
                <td>".htmlspecialchars($row['Tipo_curso'])."</td>
                <td>".htmlspecialchars($row['Grado_curso'])."</td>
                <td>".htmlspecialchars($row['seccion_curso'])."</td>
                <td>{$nombre_docente}</td>
                <td>
                  <a href='index.php?seccion=modificar_curso&Id_curso={$row['Id_curso']}' 
                     class='btn btn-sm btn-warning'>Editar</a>
                </td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='6'>No se encontraron cursos.</td></tr>";
}

echo "    </tbody>
        </table>
      </div>";
?>
