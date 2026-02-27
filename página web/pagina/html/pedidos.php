<?php
// Forzar a PHP a mostrar errores en pantalla por si acaso
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Validar sesión
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

// Requerir conexión a la base de datos
require_once "../bd/bd_conn.php";

// Obtenemos el ID del usuario
$id_usuario_actual = $_SESSION["id_usuario"] ?? 0; 

// Comprobamos que la variable $pdo existe (viene de bd_conn.php)
if (!isset($pdo)) {
    die("Error: No se ha encontrado la variable de conexión \$pdo. Revisa bd_conn.php.");
}

// NUEVO: Comprobar si el usuario es administrador
$is_admin = false;
if ($id_usuario_actual > 0) {
    $stmt_admin = $pdo->prepare("SELECT id_rol FROM usuarios WHERE id_usuario = ?");
    $stmt_admin->execute([$id_usuario_actual]);
    if ($stmt_admin->fetchColumn() == 1) {
        $is_admin = true;
    }
}

// --- NUEVO: Procesar cancelación de pedido ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $id_cancelar = (int)$_POST['id_pedido'];
    
    // Verificamos que el pedido sea de este usuario para evitar que cancelen pedidos de otros
    $stmt_check = $pdo->prepare("SELECT estado FROM pedidos WHERE id_pedido = :id_pedido AND id_usuario = :id_usuario");
    $stmt_check->execute([':id_pedido' => $id_cancelar, ':id_usuario' => $id_usuario_actual]);
    $estado_actual = $stmt_check->fetchColumn();

    // Solo permitimos cancelar si el pedido no está ya Cancelado, Entregado o Enviado
    if ($estado_actual && !in_array($estado_actual, ['Cancelado', 'Entregado', 'Enviado'])) {
        $stmt_cancel = $pdo->prepare("UPDATE pedidos SET estado = 'Cancelado' WHERE id_pedido = :id_pedido");
        $stmt_cancel->execute([':id_pedido' => $id_cancelar]);
        
        // Recargamos la página para reflejar el cambio y evitar reenvío del formulario
        header("Location: pedidos.php");
        exit;
    }
}
// ---------------------------------------------

// Preparar consulta usando PDO
$sql = "SELECT p.id_pedido, p.fecha_pedido, p.estado, p.total, SUM(dp.cantidad) as total_articulos 
        FROM pedidos p 
        LEFT JOIN detalles_pedido dp ON p.id_pedido = dp.id_pedido 
        WHERE p.id_usuario = :id_usuario 
        GROUP BY p.id_pedido 
        ORDER BY p.fecha_pedido DESC";

try {
    $stmt = $pdo->prepare($sql);
    // Vinculamos el parámetro de forma segura con PDO
    $stmt->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_INT);
    $stmt->execute();
    
    // Obtenemos todos los resultados como un array asociativo
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error en la consulta SQL: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Pedidos | TechnoStore</title>
    
    <link rel="stylesheet" href="../CSS/cookies.css">
    <link rel="icon" type="image/png" href="../imagenes/icono.png">

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root { 
            --primary-color: #0056b3; 
            --dark-bg: #1a1a1a; 
            --light-grey: #f4f4f4; 
        }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            color: #333; 
            background-color: var(--light-grey); 
            overflow-x: hidden; 
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

        .container { max-width: 1100px; margin: 60px auto; padding: 0 5%; min-height: 60vh; }
        
        .pedidos-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid #ddd; padding-bottom: 15px; }
        .pedidos-header h1 { color: var(--primary-color); font-size: 2.2rem; }

        .table-container { background: white; border-radius: 12px; box-shadow: 0 6px 20px rgba(0,0,0,0.08); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background-color: #f8f9fa; color: #555; font-weight: 600; padding: 18px 20px; border-bottom: 2px solid #eee; }
        td { padding: 18px 20px; border-bottom: 1px solid #eee; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #fdfdfd; }

        .order-id { font-weight: bold; color: var(--primary-color); }
        .order-total { font-weight: bold; font-size: 1.1rem; }

        .badge { padding: 6px 14px; border-radius: 50px; font-size: 0.85rem; font-weight: bold; color: white; display: inline-block; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-Entregado { background-color: #28a745; }
        .badge-Enviado { background-color: #007bff; }
        .badge-Pendiente { background-color: #ffc107; color: #333; }
        .badge-Procesando { background-color: #17a2b8; }
        .badge-Cancelado { background-color: #dc3545; }

        .btn-outline { display: inline-block; padding: 8px 16px; border: 1px solid var(--primary-color); color: var(--primary-color); text-decoration: none; border-radius: 5px; font-weight: 600; transition: all 0.3s; font-size: 0.9rem; }
        .btn-outline:hover { background-color: var(--primary-color); color: white; }
        
        /* NUEVO: Estilo para el botón de cancelar */
        .btn-cancel { display: inline-block; padding: 8px 16px; border: 1px solid #dc3545; color: #dc3545; background: transparent; text-decoration: none; border-radius: 5px; font-weight: 600; transition: all 0.3s; font-size: 0.9rem; cursor: pointer; margin-left: 5px; }
        .btn-cancel:hover { background-color: #dc3545; color: white; }

        .text-center { text-align: center; }

        @media (max-width: 768px) {
            header { flex-wrap: wrap; height: auto; padding: 10px 5%; }
            .search-container { max-width: 100%; margin: 10px 0; }
            .nav-container { flex-wrap: wrap; justify-content: center; }
            .nav-item { height: auto; }
            .nav-link { padding: 10px 15px; }
            .pedidos-header { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
    </style>
</head>
<body>

    <header>
        <a href="index.php" class="logo">TechnoStore</a> 
        
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
                    <?php if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                        <a href="perfil.php">Mi Perfil (<?php echo htmlspecialchars($_SESSION["nombre"] ?? 'Usuario'); ?>)</a>
                        <a href="pedidos.php">Mis Pedidos</a>
                        <a href="carrito.php">Mi Carrito</a>
                        <?php if ($is_admin): ?>
                            <hr>
                            <a href="https://webmail.technostore.com">Correo Interno</a>
                        <?php endif; ?>
                        <hr>
                        <a href="logout.php">Cerrar Sesión</a>
                    <?php else: ?>
                        <a href="login.php">Iniciar sesión</a>
                        <a href="registro.php">Registrarse</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="pedidos-header">
            <h1>Mis Pedidos</h1>
            <p>Revisa el estado y el historial de tus compras.</p>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nº Pedido</th>
                        <th>Fecha</th>
                        <th>Artículos Totales</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($pedidos) > 0): ?>
                        <?php foreach ($pedidos as $pedido): ?>
                            <tr>
                                <td class="order-id">#TS-<?php echo str_pad($pedido['id_pedido'], 5, "0", STR_PAD_LEFT); ?></td>
                                <td><?php echo date("d/m/Y H:i", strtotime($pedido['fecha_pedido'])); ?></td>
                                <td><?php echo $pedido['total_articulos'] ?? 0; ?> ud(s).</td>
                                <td class="order-total"><?php echo number_format($pedido['total'], 2, ',', '.'); ?> €</td>
                                <td>
                                    <span class="badge badge-<?php echo htmlspecialchars($pedido['estado']); ?>">
                                        <?php echo htmlspecialchars($pedido['estado']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="detalle_pedido.php?id=<?php echo $pedido['id_pedido']; ?>" class="btn-outline">Ver detalle</a>
                                    
                                    <?php if (!in_array($pedido['estado'], ['Cancelado', 'Entregado', 'Enviado'])): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de que deseas cancelar este pedido?');">
                                            <input type="hidden" name="action" value="cancel_order">
                                            <input type="hidden" name="id_pedido" value="<?php echo $pedido['id_pedido']; ?>">
                                            <button type="submit" class="btn-cancel">Cancelar</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center" style="padding: 40px;">
                                No has realizado ningún pedido todavía. <br><br>
                                <a href="index.php" class="btn-outline">Ir a la tienda</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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