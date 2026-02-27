<?php
// 1. Recuperamos la sesión actual
session_start();
 
// 2. Vaciamos absolutamente todas las variables de sesión
$_SESSION = array();
 
// 3. ¡LA CLAVE ESTÁ AQUÍ! Forzamos al navegador a borrar la cookie de la sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
 
// 4. Destruimos la sesión en el servidor
session_destroy();
 
// 5. Redirigimos a la página principal (index.html) como me has pedido
header("location: ../index.html");
exit;
?>