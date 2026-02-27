<?php
// 1. Iniciar sesión SIEMPRE al principio
session_start();

// 2. Conectar a la base de datos (subimos una carpeta con ../ para llegar a bd/)
require_once '../bd/bd_conn.php';

$productos_carrito = [];
$subtotal = 0;

// 3. Comprobar si el usuario realmente ha iniciado sesión
$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$id_usuario = $_SESSION['id_usuario'] ?? null;

// NUEVO: Comprobar si el usuario es administrador
$is_admin = false;
if ($is_logged_in && $id_usuario) {
    $stmt_admin = $pdo->prepare("SELECT id_rol FROM usuarios WHERE id_usuario = ?");
    $stmt_admin->execute([$id_usuario]);
    if ($stmt_admin->fetchColumn() == 1) {
        $is_admin = true;
    }
}

// ==================== PROCEDER AL PAGO (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pagar') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    if ($is_logged_in && $id_usuario) {
        try {
            // Iniciamos la transacción para asegurar que o se hace todo, o no se hace nada
            $pdo->beginTransaction();

            // 1. Obtener los productos actuales del carrito y calcular el total real (por seguridad, se hace en el servidor)
            $stmt = $pdo->prepare("
                SELECT p.id_producto, p.precio, dc.cantidad 
                FROM carrito c
                JOIN detalles_carrito dc ON c.id_carrito = dc.id_carrito
                JOIN productos p ON dc.id_producto = p.id_producto
                WHERE c.id_usuario = ?
            ");
            $stmt->execute([$id_usuario]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($items) === 0) {
                throw new Exception("El carrito está vacío.");
            }

            $total_pedido = 0;
            foreach ($items as $item) {
                $total_pedido += ($item['precio'] * $item['cantidad']);
            }

            // 2. Crear el registro principal en la tabla 'pedidos' (Estado 'Procesando' = Aceptado/Pagado)
            $stmt_pedido = $pdo->prepare("INSERT INTO pedidos (id_usuario, estado, total) VALUES (?, 'Procesando', ?)");
            $stmt_pedido->execute([$id_usuario, $total_pedido]);
            
            // Obtenemos el ID del pedido que se acaba de crear
            $id_pedido_nuevo = $pdo->lastInsertId();

            // 3. Insertar cada producto en 'detalles_pedido'
            $stmt_detalle = $pdo->prepare("INSERT INTO detalles_pedido (id_pedido, id_producto, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
            foreach ($items as $item) {
                $stmt_detalle->execute([$id_pedido_nuevo, $item['id_producto'], $item['cantidad'], $item['precio']]);
            }

            // 4. Vaciar el carrito de este usuario (borramos sus detalles_carrito)
            $stmt_vaciar = $pdo->prepare("DELETE dc FROM detalles_carrito dc JOIN carrito c ON dc.id_carrito = c.id_carrito WHERE c.id_usuario = ?");
            $stmt_vaciar->execute([$id_usuario]);

            // Confirmamos los cambios en la BD
            $pdo->commit();
            
            $response['success'] = true;
            $response['message'] = 'Pedido realizado con éxito';

        } catch (Exception $e) {
            // Si algo falla, deshacemos todos los cambios
            $pdo->rollBack();
            $response['message'] = 'Error al procesar el pago: ' . $e->getMessage();
        }
    } else {
        $response['message'] = 'Debes iniciar sesión para comprar.';
    }
    
    echo json_encode($response);
    exit;
}
// ================================================================

// ==================== ELIMINAR PRODUCTO (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    if ($is_logged_in && isset($_POST['id_detalle'])) {
        $id_detalle = (int)$_POST['id_detalle'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM detalles_carrito 
                                   WHERE id_detalle_carrito = ? 
                                   AND id_carrito IN (SELECT id_carrito FROM carrito WHERE id_usuario = ?)");
            $stmt->execute([$id_detalle, $id_usuario]);
            
            $response['success'] = true;
            $response['message'] = 'Producto eliminado correctamente';
        } catch (PDOException $e) {
            $response['message'] = 'Error al eliminar';
        }
    } else {
        $response['message'] = 'No tienes permiso';
    }
    echo json_encode($response);
    exit;
}
// ================================================================

// Si está logueado, buscamos su carrito en la BD para mostrarlo en pantalla
if ($is_logged_in && $id_usuario) {
    try {
        $sql = "SELECT dc.id_detalle_carrito, p.id_producto, p.nombre, p.precio, dc.cantidad 
                FROM carrito c
                JOIN detalles_carrito dc ON c.id_carrito = dc.id_carrito
                JOIN productos p ON dc.id_producto = p.id_producto
                WHERE c.id_usuario = :id_usuario";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id_usuario' => $id_usuario]);
        $productos_carrito = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($productos_carrito as $item) {
            $subtotal += ($item['precio'] * $item['cantidad']);
        }
    } catch (PDOException $e) {
        die("Error al cargar el carrito: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../imagenes/icono.png">
    <title>Mi Carrito | TechnoStore</title>
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

        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        
        .cart-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }

        .cart-items { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .cart-summary { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); height: fit-content; }

        .item { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding: 15px 0; }
        .item:last-child { border-bottom: none; }
        
        .item-info h4 { margin: 0 0 5px 0; color: #333; }
        .item-price { color: var(--primary-color); font-weight: bold; font-size: 18px; }
        
        .item-controls { display: flex; align-items: center; gap: 15px; }
        .item-controls input { width: 50px; padding: 5px; text-align: center; border: 1px solid #ddd; border-radius: 5px; }
        .btn-remove { color: #dc3545; background: none; border: none; cursor: pointer; text-decoration: underline; font-size: 14px;}

        .summary-row { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 16px; }
        .summary-total { font-size: 20px; font-weight: bold; border-top: 2px solid #eee; padding-top: 15px; margin-top: 10px; }

        .btn-cta { background: var(--primary-color); color: white; padding: 15px; text-align: center; text-decoration: none; border-radius: 5px; font-weight: bold; display: block; width: 100%; border: none; cursor: pointer; box-sizing: border-box; margin-top: 20px;}
        .btn-cta:hover { background: #004494; }
        .btn-cta:disabled { background: #ccc; cursor: not-allowed; }
        .empty-cart { text-align: center; padding: 40px; color: #666; }

        @media (max-width: 768px) {
            header { flex-wrap: wrap; height: auto; padding: 10px 5%; }
            .search-container { max-width: 100%; margin: 10px 0; }
            .nav-container { flex-wrap: wrap; }
            .nav-item { height: auto; }
            .nav-link { height: auto; padding: 10px 20px; }
            .cart-layout { grid-template-columns: 1fr; }
            .item { flex-direction: column; align-items: flex-start; }
            .item-controls { margin-top: 10px; justify-content: flex-end; width: 100%; }
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
                    <?php if($is_logged_in): ?>
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
        <h2 style="color: var(--primary-color); border-bottom: 2px solid #eee; padding-bottom: 10px;">Tu Carrito de la Compra</h2>
        
        <div class="cart-layout">
            <div class="cart-items">
                <?php if (!$is_logged_in): ?>
                    <div class="empty-cart">
                        <h3>¡Hola! Para usar el carrito necesitas identificarte.</h3>
                        <p>Inicia sesión o regístrate para guardar tus componentes.</p>
                        <br>
                        <a href="login.php" class="btn-cta" style="display:inline-block; width:auto; padding:10px 30px;">Iniciar Sesión</a>
                    </div>
                <?php elseif (empty($productos_carrito)): ?>
                    <div class="empty-cart">
                        <h3>Tu carrito está vacío</h3>
                        <p>¡Añade algunos componentes increíbles para empezar!</p>
                        <br>
                        <a href="filtro.php" class="btn-cta" style="display:inline-block; width:auto; padding:10px 30px;">Ir de compras</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($productos_carrito as $item): ?>
                        <div class="item">
                            <div class="item-info">
                                <h4><?php echo htmlspecialchars($item['nombre']); ?></h4>
                                <span class="item-price"><?php echo number_format($item['precio'], 2, ',', '.'); ?> €</span>
                            </div>
                            <div class="item-controls">
                                <input type="number" value="<?php echo htmlspecialchars($item['cantidad']); ?>" min="1" readonly>
                                <button class="btn-remove" data-id="<?php echo $item['id_detalle_carrito']; ?>">Eliminar</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="cart-summary">
                <h3>Resumen del Pedido</h3>
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span><?php echo number_format($subtotal, 2, ',', '.'); ?> €</span>
                </div>
                <div class="summary-row">
                    <span>Gastos de envío</span>
                    <span>Gratis</span>
                </div>
                <div class="summary-row summary-total">
                    <span>Total (IVA inc.)</span>
                    <span style="color: var(--primary-color);"><?php echo number_format($subtotal, 2, ',', '.'); ?> €</span>
                </div>
                
                <button id="btn-pagar" class="btn-cta" <?php echo (empty($productos_carrito) || !$is_logged_in) ? 'disabled' : ''; ?>>
                    Proceder al Pago
                </button>
            </div>
        </div>
    </div>

    <script>
        function handleSearch(event) {
            event.preventDefault();
            const query = document.getElementById('searchInput').value.trim();
            window.location.href = "filtro.php" + (query === "" ? "" : "?q=" + encodeURIComponent(query));
        }

        // ==================== PROCEDER AL PAGO ====================
        const btnPagar = document.getElementById('btn-pagar');
        if (btnPagar) {
            btnPagar.addEventListener('click', function() {
                this.textContent = 'Procesando pago...';
                this.disabled = true;

                fetch('carrito.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=pagar'
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'pedidos.php';
                    } else {
                        alert(data.message || 'Error al procesar el pago');
                        btnPagar.textContent = 'Proceder al Pago';
                        btnPagar.disabled = false;
                    }
                })
                .catch(error => {
                    alert('Error de conexión con el servidor.');
                    btnPagar.textContent = 'Proceder al Pago';
                    btnPagar.disabled = false;
                });
            });
        }

        // ==================== ELIMINAR PRODUCTO DEL CARRITO ====================
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-remove')) {
                const btn = e.target;
                const idDetalle = btn.getAttribute('data-id');

                btn.textContent = 'Eliminando...';
                btn.disabled = true;

                fetch('carrito.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=eliminar&id_detalle=${idDetalle}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        location.reload(); 
                    } else {
                        alert(data.message || 'Error al eliminar');
                        btn.textContent = 'Eliminar';
                        btn.disabled = false;
                    }
                })
                .catch(() => {
                    alert('Error de conexión');
                    btn.textContent = 'Eliminar';
                    btn.disabled = false;
                });
            }
        });
    </script>
</body>
</html>