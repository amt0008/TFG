<?php
// Iniciamos la sesión
session_start();

// Si el usuario ya está logueado, lo mandamos a index.html
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: ../index.html");
    exit;
}

// Incluimos el archivo de conexión a la base de datos (PDO)
require_once '../bd/bd_conn.php';

// Definimos e inicializamos las variables
$nombre = $email = $password = $confirm_password = "";
$nombre_err = $email_err = $password_err = $confirm_password_err = "";

// Procesamos el formulario cuando se envía mediante POST
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // Validar nombre
    if(empty(trim($_POST["nombre"]))){
        $nombre_err = "Por favor, ingresa tu nombre.";
    } else {
        $nombre = trim($_POST["nombre"]);
    }

    // Validar email
    if(empty(trim($_POST["email"]))){
        $email_err = "Por favor, ingresa un correo electrónico.";
    } elseif(!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Por favor, ingresa un correo electrónico válido.";
    } else {
        // Verificamos si el email ya existe
        $sql = "SELECT id_usuario FROM usuarios WHERE email = :email";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['email' => trim($_POST["email"])]);
            
            if($stmt->rowCount() == 1){
                $email_err = "Este correo electrónico ya está registrado.";
            } else {
                $email = trim($_POST["email"]);
            }
        } catch(PDOException $e) {
            echo "<div style='color:red; text-align:center; padding:10px;'>Error de base de datos: " . $e->getMessage() . "</div>";
        }
        unset($stmt);
    }
    
    // Validar contraseña
    if(empty(trim($_POST["password"]))){
        $password_err = "Por favor, ingresa una contraseña.";     
    } elseif(strlen(trim($_POST["password"])) < 6){
        $password_err = "La contraseña debe tener al menos 6 caracteres.";
    } else{
        $password = trim($_POST["password"]);
    }
    
    // Validar confirmación de contraseña
    if(empty(trim($_POST["confirm_password"]))){
        $confirm_password_err = "Por favor, confirma tu contraseña.";     
    } else{
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($password_err) && ($password != $confirm_password)){
            $confirm_password_err = "Las contraseñas no coinciden.";
        }
    }
    
    // Si no hay errores, insertamos en la base de datos
    if(empty($nombre_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)){
        
        // NOMBRES DE COLUMNA CORRECTOS: id_rol y password_hash
        $sql = "INSERT INTO usuarios (id_rol, nombre, email, password_hash) VALUES (:id_rol, :nombre, :email, :password_hash)";
         
        try {
            if($stmt = $pdo->prepare($sql)){
                $stmt->execute([
                    'id_rol' => 2, // 2 = cliente
                    'nombre' => $nombre,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT)
                ]);
                
                // Iniciamos sesión automáticamente
                session_regenerate_id();
                $_SESSION["loggedin"] = true;
                $_SESSION["id_usuario"] = $pdo->lastInsertId();
                $_SESSION["id_rol"] = 2;
                $_SESSION["nombre"] = $nombre;
                
                // REDIRECCIÓN CORRECTA AL REGISTRARSE
                header("location: ../index.html");
                exit;
            }
        } catch(PDOException $e) {
             echo "<div style='color:red; text-align:center; padding:10px;'>Error al registrar: " . $e->getMessage() . "</div>";
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
    <title>Registrarse | TechnoStore</title>
    <link rel="icon" type="image/png" href="../imagenes/icono.png">
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

        .auth-wrapper {
            flex-grow: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .auth-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
            border-top: 4px solid var(--primary-color);
        }

        .auth-container h2 {
            margin-top: 0;
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box; 
            outline: none;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            border-color: var(--primary-color);
        }

        .form-group .error-text {
            color: #dc3545;
            font-size: 13px;
            margin-top: 5px;
            display: block;
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
            margin-top: 10px;
        }

        .btn-submit:hover {
            background: #004494;
        }

        .auth-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }

        .auth-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: bold;
        }

        .auth-link a:hover {
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

    <div class="auth-wrapper">
        <div class="auth-container">
            <h2>Crear una Cuenta</h2>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label>Nombre Completo</label>
                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>">
                    <span class="error-text"><?php echo $nombre_err; ?></span>
                </div>    
                <div class="form-group">
                    <label>Correo Electrónico</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <span class="error-text"><?php echo $email_err; ?></span>
                </div>    
                <div class="form-group">
                    <label>Contraseña</label>
                    <input type="password" name="password">
                    <span class="error-text"><?php echo $password_err; ?></span>
                </div>
                <div class="form-group">
                    <label>Confirmar Contraseña</label>
                    <input type="password" name="confirm_password">
                    <span class="error-text"><?php echo $confirm_password_err; ?></span>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn-submit">Registrarse</button>
                </div>
                <div class="auth-link">
                    ¿Ya tienes una cuenta? <a href="login.php">Inicia sesión aquí</a>
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