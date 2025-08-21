<?php
try {

    // Lee las variables de entorno (ya configuradas en Azure)
    $serverName = getenv("DB_SERVER");  
    $database   = getenv("DB_DATABASE"); 
    $username   = getenv("DB_USERNAME"); 
    $password   = getenv("DB_PASSWORD"); 

    // Construir DSN (Data Source Name)
    $dsn = "sqlsrv:Server=$serverName;Database=$database";

    // Crear conexión PDO
    $conn = new PDO($dsn, $username, $password);

    // Opciones seguras
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
