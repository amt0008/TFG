<?php

class AuthCookieManager {
    private $pdo;

    // Inyectamos la conexión PDO al instanciar la clase
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Crea una cookie de "Recuérdame" segura y la guarda en la BD.
     * Se debe llamar a esta función SOLO si el usuario marcó la casilla "Recuérdame" en el login.
     */
    public function crearCookieRecuerdame($id_usuario) {
        // 1. Generar un token criptográficamente seguro (64 caracteres en hexadecimal)
        // Esto encaja perfectamente con el campo token VARCHAR(64) de tu BD.
        $token = bin2hex(random_bytes(32)); 
        
        // 2. Definir el tiempo de expiración (ej. 30 días)
        $dias = 30;
        $tiempo_expiracion = time() + ($dias * 24 * 60 * 60);
        $fecha_expiracion_db = date('Y-m-d H:i:s', $tiempo_expiracion);

        // 3. Guardar en la base de datos
        // Insertamos en la tabla sesiones_cookies usando consultas preparadas para evitar Inyección SQL
        $stmt = $this->pdo->prepare("INSERT INTO sesiones_cookies (id_usuario, token, fecha_expiracion) VALUES (:id_usuario, :token, :fecha_expiracion)");
        $stmt->execute([
            ':id_usuario' => $id_usuario,
            ':token' => $token,
            ':fecha_expiracion' => $fecha_expiracion_db
        ]);

        // 4. Configurar y enviar la cookie al navegador del usuario con máxima seguridad
        setcookie(
            'technostore_remember', // Nombre de la cookie
            $token,                 // Valor de la cookie
            [
                'expires' => $tiempo_expiracion,
                'path' => '/',          // Disponible en toda la web
                'domain' => '',         // Déjalo vacío para el dominio actual
                'secure' => true,       // OBLIGATORIO: Solo se envía por HTTPS
                'httponly' => true,     // OBLIGATORIO: Inaccesible desde JavaScript (previene ataques XSS)
                'samesite' => 'Strict'  // OBLIGATORIO: Previene ataques CSRF
            ]
        );
    }

    /**
     * Valida la cookie cuando el usuario vuelve a la web.
     */
    public function validarCookieRecuerdame() {
        if (!isset($_COOKIE['technostore_remember'])) {
            return false; // No hay cookie
        }

        $token = $_COOKIE['technostore_remember'];

        // Buscar el token en la base de datos asegurando que no haya expirado
        $stmt = $this->pdo->prepare("SELECT id_usuario FROM sesiones_cookies WHERE token = :token AND fecha_expiracion > NOW()");
        $stmt->execute([':token' => $token]);
        
        $sesion = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($sesion) {
            // El token es válido. Retornamos el id_usuario para iniciar su sesión en PHP.
            return $sesion['id_usuario'];
        } else {
            // Si el token es inválido o expiró, borramos la cookie por seguridad
            $this->borrarCookie();
            return false;
        }
    }

    /**
     * Borra la cookie del navegador y de la base de datos (Ej: al hacer Logout)
     */
    public function borrarCookie() {
        if (isset($_COOKIE['technostore_remember'])) {
            $token = $_COOKIE['technostore_remember'];
            
            // Borrar de la base de datos
            $stmt = $this->pdo->prepare("DELETE FROM sesiones_cookies WHERE token = :token");
            $stmt->execute([':token' => $token]);

            // Borrar del navegador invalidando la fecha
            setcookie('technostore_remember', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
    }
}
?>