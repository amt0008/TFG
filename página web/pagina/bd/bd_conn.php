<?php
// bd/bd_conn.php
$servidor = "192.168.10.4"; // Dirección IP de tu servidor MariaDB
$usuario = "aplicaciones";  // Usuario de la base de datos
$contrasena = "changeme";   // Contraseña
$base_datos = "technostore"; // Nombre de la base de datos

try {
    // Creamos el objeto $pdo que necesitan tus otros archivos
    $pdo = new PDO("mysql:host=$servidor;dbname=$base_datos;charset=utf8mb4", $usuario, $contrasena);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>