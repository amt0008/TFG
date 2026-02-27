<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Validar sesión
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

require_once "../bd/bd_conn.php";

$id_usuario_actual = $_SESSION["id_usuario"] ?? 0; 
$id_pedido = $_GET['id'] ?? null;

if (!$id_pedido) {
    die("Error: No se ha especificado un número de pedido.");
}

// 1. Obtener la información general del pedido y validar que sea de este usuario
$sql_pedido = "SELECT id_pedido, fecha_pedido, estado, total FROM pedidos WHERE id_pedido = :id_pedido AND id_usuario = :id_usuario";
$stmt_pedido = $pdo->prepare($sql_pedido);
$stmt_pedido->execute([':id_pedido' => $id_pedido, ':id_usuario' => $id_usuario_actual]);
$pedido = $stmt_pedido->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    die("Error: El pedido no existe o no tienes permiso para verlo.");
}

// 2. Obtener los productos exactos de este pedido cruzando con la tabla productos
$sql_detalles = "SELECT dp.cantidad, dp.precio_unitario, p.nombre 
                 FROM detalles_pedido dp 
                 JOIN productos p ON dp.id_producto = p.id_producto 
                 WHERE dp.id_pedido = :id_pedido";
$stmt_detalles = $pdo->prepare($sql_detalles);
$stmt_detalles->execute([':id_pedido' => $id_pedido]);
$detalles = $stmt_detalles->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Pedido #TS-<?php echo str_pad($pedido['id_pedido'], 5, "0", STR_PAD_LEFT); ?> | TechnoStore</title>
    
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

        .nav-container { display: flex; height: 100%; }
        .nav-item { position: relative; height: 100%; display: flex; align-items: center; }
        .nav-link { text-decoration: none; color: #444; padding: 0 20px; font-weight: 600; transition: 0.3s; height: 100%; display: flex; align-items: center; }
        .nav-link:hover { color: var(--primary-color); background: #f9f9f9; }

        .container { max-width: 1000px; margin: 60px auto; padding: 0 5%; min-height: 60vh; }
        
        .pedidos-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid #ddd; padding-bottom: 15px; }
        .pedidos-header h1 { color: var(--primary-color); font-size: 2.2rem; margin-bottom: 5px; }
        
        .info-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 20px; }
        .info-item span { display: block; font-size: 0.9rem; color: #666; margin-bottom: 5px; }
        .info-item strong { font-size: 1.1rem; color: #333; }

        .table-container { background: white; border-radius: 12px; box-shadow: 0 6px 20px rgba(0,0,0,0.08); overflow-x: auto; margin-bottom: 30px;}
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background-color: #f8f9fa; color: #555; font-weight: 600; padding: 18px 20px; border-bottom: 2px solid #eee; }
        td { padding: 18px 20px; border-bottom: 1px solid #eee; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        
        .product-name { font-weight: bold; color: var(--primary-color); }
        .order-total-row { background-color: #f8f9fa; font-size: 1.2rem; }
        
        .btn-outline { display: inline-block; padding: 10px 20px; border: 1px solid var(--primary-color); color: var(--primary-color); text-decoration: none; border-radius: 5px; font-weight: 600; transition: all 0.3s; }
        .btn-outline:hover { background-color: var(--primary-color); color: white; }

        /* Etiqueta de estado */
        .badge { padding: 6px 14px; border-radius: 50px; font-size: 0.85rem; font-weight: bold; color: white; display: inline-block; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-Entregado { background-color: #28a745; }
        .badge-Enviado { background-color: #007bff; }
        .badge-Pendiente { background-color: #ffc107; color: #333; }
        .badge-Procesando { background-color: #17a2b8; }
        .badge-Cancelado { background-color: #dc3545; }

        @media (max-width: 768px) {
            header { flex-wrap: wrap; height: auto; padding: 10px 5%; justify-content: center; }
            .pedidos-header { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
    </style>
</head>
<body>

    <header>
        <a href="index.php" class="logo">TechnoStore</a> 
        <nav class="nav-container">
            <div class="nav-item"><a href="pedidos.php" class="nav-link">← Volver a Mis Pedidos</a></div>
        </nav>
    </header>

    <div class="container">
        <div class="pedidos-header">
            <div>
                <h1>Pedido #TS-<?php echo str_pad($pedido['id_pedido'], 5, "0", STR_PAD_LEFT); ?></h1>
                <p>Realizado el <?php echo date("d/m/Y a las H:i", strtotime($pedido['fecha_pedido'])); ?></p>
            </div>
            <div>
                <span class="badge badge-<?php echo htmlspecialchars($pedido['estado']); ?>">
                    <?php echo ($pedido['estado'] === 'Procesando') ? 'ACEPTADO' : htmlspecialchars($pedido['estado']); ?>
                </span>
            </div>
        </div>

        <div class="info-box">
            <div class="info-item">
                <span>Método de envío</span>
                <strong>Envío Estándar (Gratis)</strong>
            </div>
            <div class="info-item">
                <span>Total pagado</span>
                <strong><?php echo number_format($pedido['total'], 2, ',', '.'); ?> €</strong>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Precio Unitario</th>
                        <th>Cantidad</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $item): ?>
                        <tr>
                            <td class="product-name"><?php echo htmlspecialchars($item['nombre']); ?></td>
                            <td><?php echo number_format($item['precio_unitario'], 2, ',', '.'); ?> €</td>
                            <td><?php echo $item['cantidad']; ?> ud(s).</td>
                            <td><strong><?php echo number_format($item['precio_unitario'] * $item['cantidad'], 2, ',', '.'); ?> €</strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="order-total-row">
                        <td colspan="3" style="text-align: right;"><strong>Total del Pedido:</strong></td>
                        <td><strong style="color: var(--primary-color);"><?php echo number_format($pedido['total'], 2, ',', '.'); ?> €</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="text-align: center;">
            <a href="pedidos.php" class="btn-outline">Volver al historial</a>
        </div>
    </div>

</body>
</html>