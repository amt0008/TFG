<?php
// Iniciamos la sesión para poder guardar los datos del usuario logueado
session_start();

// Si el usuario ya está logueado, lo mandamos al index de esta misma carpeta
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: index.php");
    exit;
}

// Incluimos tu archivo de conexión a la base de datos (PDO)
require_once '../bd/bd_conn.php';

$email = $password = "";
$login_err = "";

// Procesamos los datos del formulario cuando se envía mediante POST
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    
    if(empty($email) || empty($password)){
        $login_err = "Por favor, introduce tu email y contraseña.";
    } else {
        $sql = "SELECT id_usuario, id_rol, nombre, password_hash, bloqueado FROM usuarios WHERE email = :email";
        
        try {
            if($stmt = $pdo->prepare($sql)){
                $stmt->execute(['email' => $email]);
                
                if($stmt->rowCount() == 1){                    
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if(password_verify($password, $row['password_hash'])){
                        if ($row['bloqueado'] == 1) {
                            $login_err = "Cuenta bloqueada. Contacta al administrador.";
                        } else {
                            session_regenerate_id();
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id_usuario"] = $row['id_usuario'];
                            $_SESSION["id_rol"] = $row['id_rol']; 
                            $_SESSION["nombre"] = $row['nombre'];
                            
                            // Redirigimos al index.php de la carpeta html
                            header("location: index.php");
                            exit;
                        }
                    } else {
                        $login_err = "El email o la contraseña no son correctos.";
                    }
                } else {
                    $login_err = "El email o la contraseña no son correctos.";
                }
            }
        } catch(PDOException $e) {
            $login_err = "Algo salió mal: " . $e->getMessage();
        }
        unset($stmt);
    }
    unset($pdo);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../imagenes/icono.png">
    <title>Iniciar Sesión | TechnoStore</title>
    <style>
        :root { 
            --primary-color: #0056b3; 
            --dark-bg: #1a1a1a; 
            --light-grey: #f4f4f4; 
        }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            color: #333; 
            background-color: var(--light-grey); 
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        header { 
            background: white; 
            border-bottom: 2px solid var(--primary-color); 
            padding: 0 5%; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            position: sticky; 
            top: 0; 
            z-index: 1000; 
            height: 80px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .logo { font-size: 28px; font-weight: bold; color: var(--primary-color); text-transform: uppercase; text-decoration: none; }

        .search-container { flex-grow: 1; max-width: 400px; margin: 0 20px; display: flex; }
        .search-container input { width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px 0 0 5px; outline: none; }
        .search-container button { padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 0 5px 5px 0; cursor: pointer; }

        .nav-container { display: flex; height: 100%; }
        .nav-item { position: relative; height: 100%; display: flex; align-items: center; }

        .nav-link { 
            text-decoration: none; color: #444; padding: 0 20px; 
            font-weight: 600; transition: 0.3s; height: 100%;
            display: flex; align-items: center;
        }

        .nav-link:hover { color: var(--primary-color); background: #f9f9f9; }

        .dropdown { 
            display: none; position: absolute; top: 100%; left: 0; 
            background-color: white; min-width: 220px; 
            box-shadow: 0 8px 16px rgba(0,0,0,0.1); 
            border-top: 3px solid var(--primary-color); z-index: 1001; 
        }

        .nav-item:hover .dropdown { display: block; animation: fadeIn 0.2s ease; }

        .dropdown a { color: #333; padding: 12px 20px; text-decoration: none; display: block; font-size: 14px; transition: 0.2s; }
        .dropdown a:hover { background-color: #f1f1f1; color: var(--primary-color); }
        .dropdown hr { border: 0; border-top: 1px solid #eee; margin: 5px 0; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* ----- ESTILOS PARA LOGIN ----- */
        .login-wrapper {
            flex-grow: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }

        h2 {
            text-align: center;
            color: var(--primary-color);
            margin-bottom: 30px;
            font-size: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #444;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 16px;
        }

        .form-group input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 86, 179, 0.2);
        }

        .btn-submit {
            width: 100%;
            background: var(--primary-color);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-submit:hover {
            background: #004494;
        }

        .error-message {
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }

        .register-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: bold;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

    <header>
        <a href="../index.html" class="logo">TechnoStore</a> 
        
        <form class="search-container" onsubmit="return handleSearch(event)">
            <input type="text" id="searchInput" placeholder="Buscar productos...">
            <button type="submit">Buscar</button>
        </form>

        <nav class="nav-container">
            <div class="nav-item">
                <a href="#" class="nav-link">Componentes</a>
                <div class="dropdown">
                    <a href="filtro.php?q=procesadores">Procesadores</a>
                    <a href="filtro.php?q=graficas">Tarjetas Gráficas</a>
                    <a href="filtro.php?q=ram">Memorias RAM</a>
                    <a href="filtro.php?q=ssd">Almacenamiento</a>
                </div>
            </div>

            <div class="nav-item">
                <a href="#" class="nav-link">Portátiles</a>
                <div class="dropdown">
                    <a href="filtro.php?q=gaming">Gaming</a>
                    <a href="filtro.php?q=trabajo">Trabajo/Oficina</a>
                </div>
            </div>

            <div class="nav-item">
                <a href="#" class="nav-link">Gaming</a>
                <div class="dropdown">
                    <a href="filtro.php?q=monitores">Monitores</a>
                    <a href="filtro.php?q=perifericos">Periféricos</a>
                </div>
            </div>

            <div class="nav-item">
                <a href="#" class="nav-link">Mi Cuenta</a>
                <div class="dropdown">
                    <a href="login.php">Iniciar sesión</a>
                    <a href="registro.php">Registrarse</a>
                </div>
            </div>
        </nav>
    </header>

    <div class="login-wrapper">
        <div class="login-container">
            <h2>Bienvenido de nuevo</h2>
            
            <?php 
            if(!empty($login_err)){
                echo '<div class="error-message">' . $login_err . '</div>';
            }        
            ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label>Correo Electrónico</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>    
                <div class="form-group">
                    <label>Contraseña</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn-submit">Iniciar Sesión</button>
                </div>
                <div class="register-link">
                    ¿No tienes una cuenta? <a href="registro.php">Regístrate aquí</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function handleSearch(event) {
            event.preventDefault();
            const query = document.getElementById('searchInput').value.trim();
            window.location.href = "filtro.php" + (query === "" ? "" : "?q=" + encodeURIComponent(query));
        }
    </script>
</body>
</html>