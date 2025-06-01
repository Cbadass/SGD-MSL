<?php
require_once 'includes/db.php'; // Usa tu conexión PDO

$filtro_escuela = $_GET['escuela'] ?? '';

echo "<h2>Visualización de Cursos</h2>";
echo "<form method='GET' class='filters' style='margin-bottom:15px; display: flex; gap: 15px;'>
    <input type='hidden' name='seccion' value='cursos'>
    <select name='escuela'>
        <option value=''>Escuela</option>
        <option value='1'" . ($filtro_escuela == '1' ? ' selected' : '') . ">Sendero</option>
        <option value='2'" . ($filtro_escuela == '2' ? ' selected' : '') . ">Multiverso</option>
        <option value='3'" . ($filtro_escuela == '3' ? ' selected' : '') . ">Luz de Luna</option>
    </select>
    <button class='btn' type='submit'>Filtrar</button>
</form>";

echo "<div style='max-height: 400px; overflow-y: auto; border-radius: 10px;'>
<table>
    <tr>
        <th>Escuela</th>
        <th>Tipo Curso</th>
        <th>Grado</th>
        <th>Sección</th>
        <th>Docente</th>
        <th>Modificar Docente</th>
    </tr>";

try {
    $sql = "SELECT c.Id_curso, c.Tipo_curso, c.Grado_curso,
                   e.Nombre_escuela,
                   p.Nombre_profesional, p.Apellido_profesional
            FROM cursos c
            LEFT JOIN escuelas e ON c.Id_escuela = e.Id_escuela
            LEFT JOIN profesionales p ON c.Id_profesional = p.Id_profesional
            WHERE 1=1";

    $params = [];
    if ($filtro_escuela !== '') {
        $sql .= " AND c.Id_escuela = :id_escuela";
        $params[':id_escuela'] = $filtro_escuela;
    }

    $sql .= " ORDER BY c.Id_curso ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($cursos) {
        foreach ($cursos as $row) {
            $nombre_docente = ($row['Nombre_profesional'])
                ? "{$row['Nombre_profesional']} {$row['Apellido_profesional']}"
                : "<em>No asignado</em>";

            echo "<tr>
                <td>{$row['Nombre_escuela']}</td>
                <td>{$row['Tipo_curso']}</td>
                <td>{$row['Grado_curso']}</td>
                <td>-</td>
                <td>$nombre_docente</td>
                <td><button class='btn'>Modificar</button></td>
            </tr>";
        }
    } else {
        echo "<tr><td colspan='6'>No se encontraron cursos.</td></tr>";
    }
} catch (PDOException $e) {
    echo "<tr><td colspan='6' style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
}

echo "</table></div>";
?>
