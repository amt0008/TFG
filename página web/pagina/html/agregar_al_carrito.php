<?php
session_start();
require_once '../bd/bd_conn.php';   // ← Esto está correcto porque estás en html/

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    $response['message'] = 'Debes iniciar sesión para añadir productos al carrito.';
    echo json_encode($response);
    exit;
}

$id_producto = isset($_POST['id_producto']) ? (int)$_POST['id_producto'] : 0;
$cantidad    = isset($_POST['cantidad']) ? max(1, (int)$_POST['cantidad']) : 1;
$id_usuario  = $_SESSION['id_usuario'];

if ($id_producto <= 0) {
    $response['message'] = 'Producto inválido.';
    echo json_encode($response);
    exit;
}

// Obtener o crear carrito del usuario
$stmt = $pdo->prepare("SELECT id_carrito FROM carrito WHERE id_usuario = ? LIMIT 1");
$stmt->execute([$id_usuario]);
$carrito = $stmt->fetch(PDO::FETCH_ASSOC);

if ($carrito) {
    $id_carrito = $carrito['id_carrito'];
} else {
    $stmt = $pdo->prepare("INSERT INTO carrito (id_usuario) VALUES (?)");
    $stmt->execute([$id_usuario]);
    $id_carrito = $pdo->lastInsertId();
}

// Verificar producto y stock
$stmt = $pdo->prepare("SELECT stock_actual, nombre FROM productos WHERE id_producto = ?");
$stmt->execute([$id_producto]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    $response['message'] = 'El producto no existe.';
    echo json_encode($response);
    exit;
}
if ($producto['stock_actual'] < $cantidad) {
    $response['message'] = 'Stock insuficiente para ' . htmlspecialchars($producto['nombre']);
    echo json_encode($response);
    exit;
}

// Añadir / actualizar en detalles_carrito
$stmt = $pdo->prepare("INSERT INTO detalles_carrito (id_carrito, id_producto, cantidad)
                       VALUES (?, ?, ?)
                       ON DUPLICATE KEY UPDATE cantidad = cantidad + VALUES(cantidad)");
$stmt->execute([$id_carrito, $id_producto, $cantidad]);

$response['success'] = true;
$response['message'] = '✓ Producto añadido al carrito correctamente';
echo json_encode($response);
?>