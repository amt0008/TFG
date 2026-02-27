<?php
// Iniciamos sesión para capturar el ID si el usuario está logueado
session_start();

// 1. Conexión a la base de datos (PDO)
require_once '../bd/bd_conn.php';

// Configurar la cabecera para devolver JSON
header('Content-Type: application/json');

// Leemos los datos enviados por el banner (JavaScript)
$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);

if (is_array($data)) {
    try {
        // 2. Preparar los datos obligatorios para la tabla sesiones_cookies
        
        // Generamos un token aleatorio de 64 caracteres (hexadecimal)
        $token = bin2hex(random_bytes(32)); 
        
        // Definimos una fecha de expiración (ej. 1 año para el consentimiento)
        $fecha_expiracion = date('Y-m-d H:i:s', strtotime('+1 year'));
        
        // Capturamos y hasheamos la IP del equipo (SHA-256 genera 64 caracteres)
        $ip_real = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ip_hasheada = hash('sha256', $ip_real);
        
        // Capturamos el ID de usuario si ha iniciado sesión, de lo contrario NULL
        $id_usuario = isset($_SESSION['id_usuario']) ? $_SESSION['id_usuario'] : null;

        // 3. Extraemos las preferencias del JSON y las convertimos a 1 o 0 para MariaDB
        // El JS envía: { tecnicas: true/false, analiticas: true/false, marketing: true/false }
        $val_tecnicas = (isset($data['tecnicas']) && $data['tecnicas']) ? 1 : 1; // Las técnicas siempre suelen ser 1
        $val_analiticas = (isset($data['analiticas']) && $data['analiticas']) ? 1 : 0;
        $val_marketing = (isset($data['marketing']) && $data['marketing']) ? 1 : 0;

        // 4. Consulta SQL adaptada EXACTAMENTE a las columnas de tu tabla
        $sql = "INSERT INTO sesiones_cookies (id_usuario, token, fecha_expiracion, ip_hasheada, cookies_tecnicas, cookies_analytics, cookies_marketing) 
                VALUES (:id_usr, :token, :fecha, :ip, :tecnicas, :analiticas, :marketing)";
        
        $stmt = $pdo->prepare($sql);
        
        // 5. Ejecutar la inserción
        $stmt->execute([
            ':id_usr'     => $id_usuario,
            ':token'      => $token,
            ':fecha'      => $fecha_expiracion,
            ':ip'         => $ip_hasheada,
            ':tecnicas'   => $val_tecnicas,
            ':analiticas' => $val_analiticas,
            ':marketing'  => $val_marketing
        ]);

        // Respuesta de éxito para el JavaScript
        echo json_encode(['status' => 'success', 'message' => 'Consentimiento guardado en BD']);

    } catch (PDOException $e) {
        http_response_code(500);
        // Descomenta la siguiente línea temporalmente si quieres ver el error exacto en la consola de red del navegador
        // echo json_encode(['status' => 'error', 'message' => 'Error de BD: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No se recibieron datos válidos']);
}
?>