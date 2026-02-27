<?php
// 1. Iniciar sesión SIEMPRE al principio
session_start();

// 2. Conectar a la base de datos
require_once '../bd/bd_conn.php';

// 3. Comprobar si el usuario está logueado
$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
if (!$is_logged_in) {
    header("Location: login.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$success_message = $error_message = '';

// 4. Fetch datos actuales del usuario, incluyendo rol
$stmt = $pdo->prepare("SELECT u.nombre, u.email, u.fecha_registro, u.id_rol, r.nombre_rol 
                       FROM usuarios u JOIN roles r ON u.id_rol = r.id_rol 
                       WHERE u.id_usuario = ?");
$stmt->execute([$id_usuario]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Usuario no encontrado.");
}

$is_admin = ($user['id_rol'] == 1);

// 5. Procesar actualización de perfil personal (para todos)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validaciones básicas
    if (empty($nombre) || empty($email)) {
        $error_message = 'Nombre y email son obligatorios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Email inválido.';
    } else {
        $updates = [];
        $params = [];

        // Actualizar nombre si cambió
        if ($nombre !== $user['nombre']) {
            $updates[] = "nombre = ?";
            $params[] = $nombre;
        }

        // Actualizar email si cambió y verificar unicidad
        if ($email !== $user['email']) {
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ? AND id_usuario != ?");
            $check_stmt->execute([$email, $id_usuario]);
            if ($check_stmt->fetchColumn() > 0) {
                $error_message = 'El email ya está en uso.';
            } else {
                $updates[] = "email = ?";
                $params[] = $email;
            }
        }

        // Actualizar contraseña si se proporcionó
        if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
            if (!password_verify($current_password, $user['password_hash'])) {
                $error_message = 'Contraseña actual incorrecta.';
            } elseif ($new_password !== $confirm_password) {
                $error_message = 'Las nuevas contraseñas no coinciden.';
            } elseif (strlen($new_password) < 8) {
                $error_message = 'La nueva contraseña debe tener al menos 8 caracteres.';
            } else {
                $updates[] = "password_hash = ?";
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
            }
        }

        // Si hay actualizaciones y no hay error
        if (empty($error_message) && !empty($updates)) {
            $params[] = $id_usuario;
            $sql = "UPDATE usuarios SET " . implode(', ', $updates) . " WHERE id_usuario = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                $success_message = 'Perfil actualizado correctamente.';
                // Actualizar sesión si nombre cambió
                if (isset($_SESSION['nombre']) && $nombre !== $user['nombre']) {
                    $_SESSION['nombre'] = $nombre;
                }
                // Refrescar datos del usuario
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
                $stmt->execute([$id_usuario]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error_message = 'Error al actualizar el perfil.';
            }
        } elseif (empty($error_message)) {
            $success_message = 'No se realizaron cambios.';
        }
    }
}

// 6. Procesar gestión de usuarios (solo para admin)
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'manage_user') {
    $target_id = (int)$_POST['target_id'];
    $new_rol = (int)$_POST['new_rol'];
    $bloqueado = isset($_POST['bloqueado']) ? 1 : 0;

    if ($target_id == $id_usuario) {
        $error_message = 'No puedes modificar tu propio rol o bloquearte a ti mismo.';
    } else {
        // --- INICIO DE LA MODIFICACIÓN ---
        if ($new_rol == 1) {
            // Si es admin, le generamos el email interno cortando el email original
            $sql = "UPDATE usuarios SET id_rol = ?, bloqueado = ?, email_interno = CONCAT(SUBSTRING_INDEX(email, '@', 1), '@technostore.com') WHERE id_usuario = ?";
        } else {
            // Si vuelve a ser cliente, eliminamos el email interno
            $sql = "UPDATE usuarios SET id_rol = ?, bloqueado = ?, email_interno = NULL WHERE id_usuario = ?";
        }
        
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$new_rol, $bloqueado, $target_id])) {
            $success_message = 'Usuario actualizado correctamente.';
        } else {
            $error_message = 'Error al actualizar el usuario.';
        }
        // --- FIN DE LA MODIFICACIÓN ---
    }
}

// NUEVO: Procesar eliminación de usuarios (solo para admin)
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $target_id = (int)$_POST['target_id'];

    if ($target_id == $id_usuario) {
        $error_message = 'No puedes eliminar tu propia cuenta de administrador.';
    } else {
        // Ejecutar borrado
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
        try {
            if ($stmt->execute([$target_id])) {
                $success_message = 'Usuario eliminado de la base de datos de forma permanente.';
            } else {
                $error_message = 'No se pudo eliminar al usuario.';
            }
        } catch (PDOException $e) {
            // Captura el error si el usuario tiene pedidos u otras claves foráneas activas
            $error_message = 'Error al eliminar: Este usuario tiene datos asociados (como pedidos) en la base de datos.';
        }
    }
}

// 7. Fetch lista de usuarios para admin
$users_list = [];
if ($is_admin) {
    $stmt = $pdo->query("SELECT u.id_usuario, u.nombre, u.email, u.id_rol, u.bloqueado, u.fecha_registro 
                         FROM usuarios u ORDER BY u.id_usuario DESC");
    $users_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 8. Fetch roles para dropdown
$roles = $pdo->query("SELECT id_rol, nombre_rol FROM roles")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../imagenes/icono.png">
    <title>Mi Perfil | TechnoStore</title>
    <style>
        /* Variables y estilos base */
        * { box-sizing: border-box; }
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
            overflow-x: hidden;
        }

        /* ----- ESTILOS DEL HEADER (Importados del index) ----- */
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

        /* ----- ESTILOS PARA PERFIL ----- */
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .profile-card { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 40px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .btn-cta { background: var(--primary-color); color: white; padding: 15px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; width: 100%; margin-top: 10px; }
        .btn-cta:hover { background: #004494; }
        .message { padding: 10px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .section-title { margin-top: 40px; border-bottom: 2px solid #eee; padding-bottom: 10px; color: var(--primary-color); }

        /* Estilos para panel admin */
        .user-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .user-table th, .user-table td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        .user-table th { background: #f4f4f4; }
        .user-table form { display: inline; }
        .user-table button { padding: 8px 12px; background: var(--primary-color); color: white; border: none; border-radius: 5px; cursor: pointer; }
        .user-table button:hover { background: #004494; }
        .btn-danger { background-color: #dc3545 !important; margin-left: 5px; }
        .btn-danger:hover { background-color: #c82333 !important; }

        @media (max-width: 768px) {
            header { flex-wrap: wrap; height: auto; padding: 10px 5%; }
            .search-container { max-width: 100%; margin: 10px 0; }
            .nav-container { flex-wrap: wrap; }
            .nav-item { height: auto; }
            .nav-link { height: auto; padding: 10px 20px; }
            .container { padding: 0 10px; }
            .profile-card { padding: 20px; }
            .user-table { overflow-x: auto; display: block; }
            .user-table thead { display: none; }
            .user-table tr { display: block; margin-bottom: 15px; border: 1px solid #ddd; padding: 10px; }
            .user-table td { display: block; text-align: right; position: relative; padding-left: 50%; }
            .user-table td::before { content: attr(data-label); position: absolute; left: 10px; font-weight: bold; }
            .user-table td[data-label="Acciones"] { text-align: left; padding-left: 10px; }
        }
    </style>
</head>
<body>

    <header>
        <a href="../html/index.php" class="logo">TechnoStore</a> 
        
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
                    <a href="perfil.php">Mi Perfil (<?php echo htmlspecialchars($_SESSION["nombre"] ?? 'Usuario'); ?>)</a>
                    <a href="pedidos.php">Mis Pedidos</a>
                    <a href="carrito.php">Mi Carrito</a>
                    <?php if ($is_admin): ?>
                        <hr>
                        <a href="correo_interno.php">Correo Interno</a>
                    <?php endif; ?>
                    <hr>
                    <a href="logout.php">Cerrar Sesión</a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="profile-card">
            <h2>Mi Perfil</h2>
            <?php if ($success_message): ?>
                <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-group">
                    <label>Nombre</label>
                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($user['nombre']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Fecha de Registro</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['fecha_registro']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Rol</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['nombre_rol']); ?>" disabled>
                </div>
                <button type="submit" class="btn-cta">Guardar Cambios</button>
            </form>

            <h3 class="section-title">Cambiar Contraseña</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-group">
                    <label>Contraseña Actual</label>
                    <input type="password" name="current_password">
                </div>
                <div class="form-group">
                    <label>Nueva Contraseña</label>
                    <input type="password" name="new_password">
                </div>
                <div class="form-group">
                    <label>Confirmar Nueva Contraseña</label>
                    <input type="password" name="confirm_password">
                </div>
                <button type="submit" class="btn-cta">Cambiar Contraseña</button>
            </form>
        </div>

        <?php if ($is_admin): ?>
            <div class="profile-card">
                <h2>Panel de Administración - Gestión de Usuarios</h2>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th data-label="ID">ID</th>
                            <th data-label="Nombre">Nombre</th>
                            <th data-label="Email">Email</th>
                            <th data-label="Rol Actual">Rol Actual</th>
                            <th data-label="Bloqueado">Bloqueado</th>
                            <th data-label="Fecha Registro">Fecha Registro</th>
                            <th data-label="Acciones">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users_list as $u): ?>
                            <tr>
                                <td data-label="ID"><?php echo $u['id_usuario']; ?></td>
                                <td data-label="Nombre"><?php echo htmlspecialchars($u['nombre']); ?></td>
                                <td data-label="Email"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td data-label="Rol Actual"><?php echo $u['id_rol'] == 1 ? 'Admin' : 'Cliente'; ?></td>
                                <td data-label="Bloqueado"><?php echo $u['bloqueado'] ? 'Sí' : 'No'; ?></td>
                                <td data-label="Fecha Registro"><?php echo htmlspecialchars($u['fecha_registro']); ?></td>
                                <td data-label="Acciones">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="manage_user">
                                        <input type="hidden" name="target_id" value="<?php echo $u['id_usuario']; ?>">
                                        <select name="new_rol">
                                            <?php foreach ($roles as $rol): ?>
                                                <option value="<?php echo $rol['id_rol']; ?>" <?php echo ($rol['id_rol'] == $u['id_rol']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($rol['nombre_rol']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label><input type="checkbox" name="bloqueado" <?php echo $u['bloqueado'] ? 'checked' : ''; ?>> Bloquear</label>
                                        <button type="submit">Guardar</button>
                                    </form>

                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de que deseas eliminar permanentemente a este usuario? Esta acción no se puede deshacer.');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="target_id" value="<?php echo $u['id_usuario']; ?>">
                                        <button type="submit" class="btn-danger">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
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