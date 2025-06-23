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
$filtro_tipo       = $_GET['tipo_curso']     ?? '';
$filtro_grado      = $_GET['grado_curso']    ?? '';
$filtro_seccion    = $_GET['seccion_curso']  ?? '';
$filtro_docente    = $_GET['docente']        ?? '';

// 3) Cargar listas para los selects
$escuelas = $conn->query("SELECT Id_escuela, Nombre_escuela FROM escuelas ORDER BY Nombre_escuela")
                 ->fetchAll(PDO::FETCH_ASSOC);

$tipos = $conn->query("SELECT DISTINCT Tipo_curso FROM cursos WHERE Tipo_curso IS NOT NULL ORDER BY Tipo_curso")
             ->fetchAll(PDO::FETCH_COLUMN);

$grados = $conn->query("SELECT DISTINCT Grado_curso FROM cursos WHERE Grado_curso IS NOT NULL ORDER BY Grado_curso")
              ->fetchAll(PDO::FETCH_COLUMN);

$secciones = $conn->query("SELECT DISTINCT seccion_curso FROM cursos WHERE seccion_curso IS NOT NULL ORDER BY seccion_curso")
                  ->fetchAll(PDO::FETCH_COLUMN);

$docentes = $conn->query("
    SELECT Id_profesional, Nombre_profesional, Apellido_profesional
      FROM profesionales
     ORDER BY Nombre_profesional, Apellido_profesional
")->fetchAll(PDO::FETCH_ASSOC);

// 4) Formulario de búsqueda avanzada
echo "<h2 class='mb-4'>Visualización de Cursos</h2>";
echo "<form method='GET' style='display:flex; gap:8rem ; margin: 2rem 0; align-items: flex-end;'";
echo "<input type='hidden' name='seccion' value='cursos'>";

// Escuela
echo "<div><label>Escuela</label><select name='escuela' class='form-select'>";
echo "<option value=''>Todas</option>";
foreach ($escuelas as $e) {
    $sel = $filtro_escuela == $e['Id_escuela'] ? ' selected' : '';
    echo "<option value='{$e['Id_escuela']}'{$sel}>{$e['Nombre_escuela']}</option>";
}
echo "</select></div>";

// Tipo
echo "<div><label>Tipo</label><select name='tipo_curso' class='form-select'>";
echo "<option value=''>Todos</option>";
foreach ($tipos as $t) {
    $sel = $filtro_tipo === $t ? ' selected' : '';
    echo "<option value=\"".htmlspecialchars($t)."\"{$sel}>".htmlspecialchars($t)."</option>";
}
echo "</select></div>";

// Grado
echo "<div><label>Grado</label><select name='grado_curso' class='form-select'>";
echo "<option value=''>Todos</option>";
foreach ($grados as $g) {
    $sel = $filtro_grado === $g ? ' selected' : '';
    echo "<option value=\"".htmlspecialchars($g)."\"{$sel}>".htmlspecialchars($g)."</option>";
}
echo "</select></div>";

// Sección
echo "<div><label>Sección</label><select name='seccion_curso' class='form-select'>";
echo "<option value=''>Todas</option>";
foreach ($secciones as $s) {
    $sel = $filtro_seccion === $s ? ' selected' : '';
    echo "<option value=\"".htmlspecialchars($s)."\"{$sel}>".htmlspecialchars($s)."</option>";
}
echo "</select></div>";

// Docente
echo "<div><label>Docente</label><select name='docente' class='form-select'>";
echo "<option value=''>Todos</option>";
foreach ($docentes as $d) {
    $nombre = "{$d['Nombre_profesional']} {$d['Apellido_profesional']}";
    $sel = $filtro_docente == $d['Id_profesional'] ? ' selected' : '';
    echo "<option value='{$d['Id_profesional']}'{$sel}>".htmlspecialchars($nombre)."</option>";
}
echo "</select></div>";

// Botones
echo "  <button type='submit' class='btn btn-primary btn-height'>Buscar</button>";
echo "  <button type='button' class='btn btn-secondary btn-height' onclick=\"window.location='?seccion=cursos'\">Limpiar</button>";

echo "</form>";

// 5) Construir consulta dinámica
$where  = "1=1";
$params = [];
function filtrar(&$where,&$params,$campo,$valor) {
    if ($valor !== '') {
        $where   .= " AND $campo = ?";
        $params[] = $valor;
    }
}
filtrar($where,$params,"c.Id_escuela",$filtro_escuela);
filtrar($where,$params,"c.Tipo_curso",$filtro_tipo);
filtrar($where,$params,"c.Grado_curso",$filtro_grado);
filtrar($where,$params,"c.seccion_curso",$filtro_seccion);
if ($filtro_docente!=='') {
    $where .= " AND c.Id_profesional = ?";
    $params[] = $filtro_docente;
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
  LEFT JOIN escuelas     e ON c.Id_escuela     = e.Id_escuela
  LEFT JOIN profesionales p ON c.Id_profesional = p.Id_profesional
  WHERE $where
  ORDER BY c.Id_curso ASC
";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6) Mostrar tabla
echo "<div style='max-height:400px;overflow-y:auto;border-radius:10px;'>";
echo "<table class='table table-striped table-bordered'>";
echo "<thead class='table-dark'><tr>
        <th>Escuela</th><th>Tipo</th><th>Grado</th><th>Sección</th>
        <th>Docente Encargado</th><th>Acciones</th>
      </tr></thead><tbody>";

if ($cursos) {
    foreach ($cursos as $r) {
        $doc = $r['Id_profesional']
             ? htmlspecialchars("{$r['Nombre_profesional']} {$r['Apellido_profesional']}")
             : "<em>No asignado</em>";
        echo "<tr>
                <td>".htmlspecialchars($r['Nombre_escuela'])."</td>
                <td>".htmlspecialchars($r['Tipo_curso'])."</td>
                <td>".htmlspecialchars($r['Grado_curso'])."</td>
                <td>".htmlspecialchars($r['seccion_curso'])."</td>
                <td>{$doc}</td>
                <td>
                  <a href='index.php?seccion=modificar_curso&Id_curso={$r['Id_curso']}' 
                     class='btn btn-sm btn-warning'>Editar</a>
                </td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='6'>No se encontraron cursos.</td></tr>";
}
echo "</tbody></table></div>";
