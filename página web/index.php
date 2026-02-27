<?php
// Activar el reporte de errores
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Reemplazamos la configuración manual por el require
require_once 'bd/bd_conn.php';

// Comprobamos si la variable $pdo existe (creada en bd_conn.php)
if (!$pdo) {
    die("<h1>Aviso de Error</h1><p>No se pudo establecer la conexión.</p>");
} else {
    // Si funciona, redirigimos
    header("Location: /pagina/index.html");
    exit();
}
?>