<?php
// pages/usuarios.php

$escuela_filtro = $_GET['escuela'] ?? '';
$estado_filtro = $_GET['estado'] ?? '';

echo "<h2>Visualización de Usuarios</h2>";
echo "<form method='GET' class='filters' style='margin-bottom:15px; display: flex; gap: 15px;'>
    <input type='hidden' name='seccion' value='usuarios'>
    <select name='escuela'>
        <option value=''>Escuela</option>
        <option value='1'" . ($escuela_filtro == '1' ? ' selected' : '') . ">Sendero</option>
        <option value='2'" . ($escuela_filtro == '2' ? ' selected' : '') . ">Multiverso</option>
        <option value='3'" . ($escuela_filtro == '3' ? ' selected' : '') . ">Luz de Luna</option>
    </select>
    <select name='estado'>
        <option value=''>Estado</option>
        <option value='1'" . ($estado_filtro == '1' ? ' selected' : '') . ">Activo</option>
        <option value='0'" . ($estado_filtro == '0' ? ' selected' : '') . ">Inactivo</option>
    </select>
    <button class='btn' type='submit'>Filtrar</button>
</form>";

// Ejemplo de tabla (puedes copiar la lógica completa de la que tenías antes)
echo "<p>Aquí va la tabla de usuarios filtrada con SQL, igual que antes.</p>";
