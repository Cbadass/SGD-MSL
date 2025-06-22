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
$filtro_usuario    = trim($_GET['usuario_id']   ?? '');
$filtro_tabla      = $_GET['tabla']            ?? '';
$filtro_accion     = $_GET['accion']           ?? '';
$filtro_registro   = trim($_GET['registro_id'] ?? '');
$filtro_fecha_desde = $_GET['fecha_desde']     ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta']     ?? '';

// 3) Formulario de búsqueda avanzada
echo "<h2 class='mb-4'>Registro de Auditoría</h2>";
echo "<form method='GET' class='mb-3 d-flex flex-wrap gap-3 align-items-end'>";
echo "  <input type='hidden' name='seccion' value='actividad'>";

// Usuario ID
echo "  <div>
          <label class='form-label'>Usuario ID</label>
          <input type='text' name='usuario_id' class='form-control' value='".htmlspecialchars($filtro_usuario)."'>
        </div>";

// Tabla
$tablas = ['usuarios','profesionales','estudiantes','cursos','apoderados','documentos','Auditoria'];
echo "  <div>
          <label class='form-label'>Tabla</label>
          <select name='tabla' class='form-select'>
            <option value=''>Todas</option>";
foreach ($tablas as $t) {
    $sel = $filtro_tabla === $t ? ' selected' : '';
    echo "<option value='$t'$sel>".htmlspecialchars($t)."</option>";
}
echo "    </select>
        </div>";

// Acción
$acciones = ['INSERT','UPDATE','DELETE'];
echo "  <div>
          <label class='form-label'>Acción</label>
          <select name='accion' class='form-select'>
            <option value=''>Todas</option>";
foreach ($acciones as $a) {
    $sel = $filtro_accion === $a ? ' selected' : '';
    echo "<option value='$a'$sel>$a</option>";
}
echo "    </select>
        </div>";

// Registro ID
echo "  <div>
          <label class='form-label'>ID Registro</label>
          <input type='text' name='registro_id' class='form-control' value='".htmlspecialchars($filtro_registro)."'>
        </div>";

// Fecha desde
echo "  <div>
          <label class='form-label'>Fecha desde</label>
          <input type='date' name='fecha_desde' class='form-control' value='".htmlspecialchars($filtro_fecha_desde)."'>
        </div>";

// Fecha hasta
echo "  <div>
          <label class='form-label'>Fecha hasta</label>
          <input type='date' name='fecha_hasta' class='form-control' value='".htmlspecialchars($filtro_fecha_hasta)."'>
        </div>";

// Botones
echo "  <div class='d-flex gap-2'>
          <button type='submit' class='btn btn-primary'>Filtrar</button>
          <button type='button' class='btn btn-secondary' onclick=\"window.location='?seccion=actividad'\">Limpiar</button>
        </div>";

echo "</form>";

// 4) Construir consulta dinámica
$where  = "1=1";
$params = [];

function filtrar(&$where,&$params,$campo,$valor,$exact = true) {
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

filtrar($where,$params,"Usuario_id",   $filtro_usuario);
filtrar($where,$params,"Tabla",         $filtro_tabla);
filtrar($where,$params,"Accion",        $filtro_accion);
filtrar($where,$params,"Registro_id",   $filtro_registro,false);

if ($filtro_fecha_desde !== '') {
    $where   .= " AND Fecha >= ?";
    $params[] = $filtro_fecha_desde . ' 00:00:00';
}
if ($filtro_fecha_hasta !== '') {
    $where   .= " AND Fecha <= ?";
    $params[] = $filtro_fecha_hasta . ' 23:59:59';
}

// 5) Ejecutar consulta
$sql = "
    SELECT
      Id_auditoria,
      Usuario_id,
      Tabla,
      Registro_id,
      Accion,
      Datos_anteriores,
      Datos_nuevos,
      Fecha
    FROM Auditoria
    WHERE $where
    ORDER BY Fecha DESC
    OFFSET 0 ROWS FETCH NEXT 200 ROWS ONLY
";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6) Mostrar tabla
echo "<div class='table-responsive'>";
echo "<table class='table table-striped table-bordered'>";
echo "<thead class='table-dark'><tr>
        <th>#</th>
        <th>Usuario ID</th>
        <th>Tabla</th>
        <th>ID Registro</th>
        <th>Acción</th>
        <th>Datos Anteriores</th>
        <th>Datos Nuevos</th>
        <th>Fecha</th>
      </tr></thead>";
echo "<tbody>";
if ($logs) {
    foreach ($logs as $row) {
        $antes = $row['Datos_anteriores']
               ? '<pre style="max-height:100px;overflow:auto;">'.htmlspecialchars($row['Datos_anteriores']).'</pre>'
               : '-';
        $nuevos = $row['Datos_nuevos']
                ? '<pre style="max-height:100px;overflow:auto;">'.htmlspecialchars($row['Datos_nuevos']).'</pre>'
                : '-';
        $fecha = date('Y-m-d H:i:s', strtotime($row['Fecha']));
        echo "<tr>
                <td>{$row['Id_auditoria']}</td>
                <td>{$row['Usuario_id']}</td>
                <td>".htmlspecialchars($row['Tabla'])."</td>
                <td>".htmlspecialchars($row['Registro_id'])."</td>
                <td>".htmlspecialchars($row['Accion'])."</td>
                <td>$antes</td>
                <td>$nuevos</td>
                <td>$fecha</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='8'>No se encontraron registros.</td></tr>";
}
echo "</tbody></table></div>";
?>
