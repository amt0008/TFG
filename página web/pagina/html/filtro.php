<?php
// 1. Añadimos session_start() IMPRESCINDIBLE para saber si el usuario está logueado
session_start(); 
require_once '../bd/bd_conn.php';

// NUEVO: Comprobar si el usuario es administrador
$is_admin = false;
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && isset($_SESSION['id_usuario'])) {
    $stmt_admin = $pdo->prepare("SELECT id_rol FROM usuarios WHERE id_usuario = ?");
    $stmt_admin->execute([$_SESSION['id_usuario']]);
    if ($stmt_admin->fetchColumn() == 1) {
        $is_admin = true;
    }
}

// 2. Parámetros de filtrado con valores por defecto 
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';

// Capturamos precios mínimo y máximo
$min_price = isset($_GET['min']) ? (float)$_GET['min'] : 0;
// Buscamos el precio máximo real en la BD para que el slider sea dinámico
$max_db = $pdo->query("SELECT MAX(precio) FROM productos")->fetchColumn();
$max_limit = $max_db ? ceil($max_db) : 3000; 
$max_price = isset($_GET['max']) ? (float)$_GET['max'] : $max_limit;

// Capturamos el proveedor y el stock
$id_proveedor = isset($_GET['proveedor']) ? (int)$_GET['proveedor'] : 0;
$solo_stock = isset($_GET['stock']) ? true : false;

// 3. Obtener proveedores para el select del menú lateral
$stmt_prov = $pdo->query("SELECT id_proveedor, nombre_empresa FROM proveedores");
$proveedores = $stmt_prov->fetchAll(PDO::FETCH_ASSOC);

// 4. Construcción de la Consulta dinámica
$sql = "SELECT * FROM productos WHERE precio BETWEEN :min AND :max";
$params = [':min' => $min_price, ':max' => $max_price];

// Búsqueda por texto (caja de búsqueda superior)
if (!empty($search)) { 
    $sql .= " AND (nombre LIKE :query OR descripcion LIKE :query)"; 
    $params[':query'] = "%$search%";
}

// Búsqueda por categoría EXCELENTE y SEGURA (ahora usa tu nueva columna)
if (!empty($categoria)) { 
    $sql .= " AND categoria = :cat"; 
    $params[':cat'] = $categoria;
}

// Búsqueda por proveedor
if ($id_proveedor > 0) { 
    $sql .= " AND id_proveedor = :prov"; 
    $params[':prov'] = $id_proveedor;
}

// Filtrar solo artículos en stock
if ($solo_stock) { 
    $sql .= " AND stock_actual > 0"; 
}

// Ordenar resultados alfabéticamente
$sql .= " ORDER BY nombre ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../imagenes/icono.png">
    <title>TechnoStore | Catálogo de Productos</title>
    <style>
        * { box-sizing: border-box; }
        :root { --primary-color: #0056b3; --light-grey: #f4f4f4; }
        body { font-family: 'Segoe UI', sans-serif; margin: 0; background-color: var(--light-grey); overflow-x: hidden; }
        
        header { 
            background: white; border-bottom: 2px solid var(--primary-color); padding: 0 5%; 
            display: flex; justify-content: space-between; align-items: center; 
            height: 80px; position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        .logo { font-size: 28px; font-weight: bold; color: var(--primary-color); text-transform: uppercase; text-decoration: none; }
        
        .search-container { flex-grow: 1; max-width: 400px; margin: 0 20px; display: flex; }
        .search-container input { width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px 0 0 5px; outline: none; }
        .search-container button { padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 0 5px 5px 0; cursor: pointer; }

        .nav-container { display: flex; height: 100%; }
        .nav-item { position: relative; height: 100%; display: flex; align-items: center; }
        .nav-link { text-decoration: none; color: #444; padding: 0 20px; font-weight: 600; transition: 0.3s; height: 100%; display: flex; align-items: center; }
        .nav-link:hover { color: var(--primary-color); background: #f9f9f9; }

        .dropdown { display: none; position: absolute; top: 100%; left: 0; background-color: white; min-width: 220px; box-shadow: 0 8px 16px rgba(0,0,0,0.1); border-top: 3px solid var(--primary-color); z-index: 1001; }
        .nav-item:hover .dropdown { display: block; animation: fadeIn 0.2s ease; }
        .dropdown a { color: #333; padding: 12px 20px; text-decoration: none; display: block; font-size: 14px; }
        .dropdown a:hover { background-color: #f1f1f1; color: var(--primary-color); }

        .main-layout { display: flex; max-width: 1400px; margin: 30px auto; padding: 0 20px; gap: 30px; }
        
        /* Sidebar de Filtros */
        .sidebar { width: 320px; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); height: fit-content; }
        
        .range-slider { position: relative; width: 100%; height: 35px; margin-top: 20px; }
        .range-slider input[type="range"] { position: absolute; left: 0; bottom: 0; width: 100%; -webkit-appearance: none; background: none; pointer-events: none; }
        .range-slider input[type="range"]::-webkit-slider-thumb { height: 18px; width: 18px; border-radius: 50%; background: var(--primary-color); pointer-events: auto; -webkit-appearance: none; cursor: pointer; border: 2px solid white; box-shadow: 0 0 2px rgba(0,0,0,0.5); }
        .slider-track { width: 100%; height: 5px; position: absolute; background: #ddd; top: 50%; transform: translateY(-50%); border-radius: 5px; }

        .price-input-container { display: flex; justify-content: space-between; margin-bottom: 15px; }
        .price-input-container input { width: 80px; padding: 5px; border: 1px solid #ddd; border-radius: 4px; }

        .grid-productos { flex-grow: 1; display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 25px; }
        .card { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: 0.3s; display: flex; flex-direction: column; }
        .card img { width: 100%; height: 180px; object-fit: contain; padding: 10px; box-sizing: border-box; }
        .card-body { padding: 15px; flex-grow: 1; display: flex; flex-direction: column; }
        .precio { font-size: 22px; color: var(--primary-color); font-weight: bold; }
        .btn-cta { background: var(--primary-color); color: white; padding: 10px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 768px) {
            .main-layout { flex-direction: column; }
            .sidebar { width: 100%; }
            .grid-productos { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
            header { flex-wrap: wrap; height: auto; padding: 10px 5%; }
            .search-container { max-width: 100%; margin: 10px 0; }
            .nav-container { flex-wrap: wrap; }
            .nav-item { height: auto; }
            .nav-link { height: auto; padding: 10px 20px; }
        }
    </style>
</head>
<body>

<header>
    <a href="../html/index.php" class="logo">TechnoStore</a>
    
    <form class="search-container" onsubmit="return handleSearch(event)">
        <input type="text" id="searchInput" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar productos por texto...">
        <button type="submit">Buscar</button>
    </form>

    <nav class="nav-container">
        <div class="nav-item">
            <a href="#" class="nav-link">Componentes</a>
            <div class="dropdown">
                <a href="filtro.php?categoria=procesador">Procesadores</a>
                <a href="filtro.php?categoria=grafica">Tarjetas Gráficas</a>
                <a href="filtro.php?categoria=placa base">Placas Base</a> 
                <a href="filtro.php?categoria=ram">Memorias RAM</a>
                <a href="filtro.php?categoria=ssd">Almacenamiento</a>
            </div>
        </div>

        <div class="nav-item">
            <a href="#" class="nav-link">Portátiles</a>
            <div class="dropdown">
                <a href="filtro.php?categoria=portatil gaming">Gaming</a>
                <a href="filtro.php?categoria=portatil">Trabajo/Oficina</a>
            </div>
        </div>

        <div class="nav-item">
            <a href="#" class="nav-link">Gaming</a>
            <div class="dropdown">
                <a href="filtro.php?categoria=monitor">Monitores</a>
                <a href="filtro.php?categoria=periferico">Periféricos</a>
            </div>
        </div>

        <div class="nav-item">
            <a href="carrito.php" class="nav-link">🛒 Carrito</a>
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

<div class="main-layout">
    <aside class="sidebar">
        <h2>Filtros</h2>
        <form action="filtro.php" method="GET">
            
            <?php if(!empty($search)): ?>
                <input type="hidden" name="q" value="<?php echo htmlspecialchars($search); ?>">
            <?php endif; ?>

            <div>
                <label style="font-weight: 600;">Categoría</label>
                <select name="categoria" style="width:100%; padding:8px; margin-top:5px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Todas las categorías</option>
                    <optgroup label="Componentes">
                        <option value="procesador" <?php echo ($categoria == 'procesador') ? 'selected' : ''; ?>>Procesadores</option>
                        <option value="grafica" <?php echo ($categoria == 'grafica') ? 'selected' : ''; ?>>Tarjetas Gráficas</option>
                        <option value="placa base" <?php echo ($categoria == 'placa base') ? 'selected' : ''; ?>>Placas Base</option> 
                        <option value="ram" <?php echo ($categoria == 'ram') ? 'selected' : ''; ?>>Memorias RAM</option>
                        <option value="ssd" <?php echo ($categoria == 'ssd') ? 'selected' : ''; ?>>Almacenamiento (SSD/HDD)</option>
                    </optgroup>
                    <optgroup label="Portátiles">
                        <option value="portatil gaming" <?php echo ($categoria == 'portatil gaming') ? 'selected' : ''; ?>>Gaming</option>
                        <option value="portatil" <?php echo ($categoria == 'portatil') ? 'selected' : ''; ?>>Trabajo/Oficina</option>
                    </optgroup>
                    <optgroup label="Gaming">
                        <option value="monitor" <?php echo ($categoria == 'monitor') ? 'selected' : ''; ?>>Monitores</option>
                        <option value="periferico" <?php echo ($categoria == 'periferico') ? 'selected' : ''; ?>>Periféricos</option>
                    </optgroup>
                </select>
            </div>

            <div style="margin-top:20px;">
                <label style="font-weight: 600;">Proveedor</label>
                <select name="proveedor" style="width:100%; padding:8px; margin-top:5px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="0">Todos los proveedores</option>
                    <?php foreach($proveedores as $prov): ?>
                        <option value="<?php echo $prov['id_proveedor']; ?>" <?php echo ($id_proveedor == $prov['id_proveedor']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($prov['nombre_empresa']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group" style="margin-top:20px;">
                <label style="font-weight: 600;">Precio (€)</label>
                <div class="range-slider">
                    <div class="slider-track" id="track"></div>
                    <input type="range" min="0" max="<?php echo $max_limit; ?>" value="<?php echo $min_price; ?>" id="slider-1" oninput="slideOne()">
                    <input type="range" min="0" max="<?php echo $max_limit; ?>" value="<?php echo $max_price; ?>" id="slider-2" oninput="slideTwo()">
                </div>
                <div class="price-input-container">
                    <input type="number" name="min" id="input-min" value="<?php echo $min_price; ?>" oninput="updateSliders()">
                    <input type="number" name="max" id="input-max" value="<?php echo $max_price; ?>" oninput="updateSliders()">
                </div>
            </div>

            <div style="margin-top:10px;">
                <label style="cursor: pointer;">
                    <input type="checkbox" name="stock" <?php echo $solo_stock ? 'checked' : ''; ?>> 
                    Solo mostrar artículos en Stock
                </label>
            </div>

            <button type="submit" class="btn-cta" style="width:100%; margin-top:20px;">Aplicar Filtros</button>
            <a href="filtro.php" style="display:block; text-align:center; margin-top:10px; color:#666; font-size:13px; text-decoration:underline;">Limpiar todos los filtros</a>
        </form>
    </aside>

    <main style="flex-grow:1;">
        <h1>
            <?php 
                if (!empty($search)) echo "Búsqueda: '" . htmlspecialchars($search) . "'";
                elseif (!empty($categoria)) echo "Categoría: " . ucfirst(htmlspecialchars($categoria));
                else echo "Todos los productos"; 
            ?>
        </h1>
        <div class="grid-productos">
            <?php if (count($productos) > 0): ?>
                <?php foreach ($productos as $p): ?>
                    <div class="card">
                        <img src="../<?php echo !empty($p['ruta_imagen']) ? htmlspecialchars($p['ruta_imagen']) : 'imagenes/sin-foto.png'; ?>" 
                             alt="<?php echo htmlspecialchars($p['nombre']); ?>"
                             title="Ruta que está cargando: ../<?php echo htmlspecialchars($p['ruta_imagen'] ?? 'NO HAY RUTA'); ?>">
                        <div class="card-body">
                            <h3 style="font-size:16px; margin:0 0 10px 0;"><?php echo htmlspecialchars($p['nombre']); ?></h3>
                            <p class="precio"><?php echo number_format($p['precio'], 2, ',', '.'); ?> €</p>
                            <p style="color: <?php echo ($p['stock_actual'] > 0) ? '#28a745' : '#dc3545'; ?>; font-size:12px; font-weight: bold;">
                                <?php echo ($p['stock_actual'] > 0) ? 'En Stock: ' . $p['stock_actual'] . ' uds' : 'Agotado'; ?>
                            </p>
                            <button class="btn-cta add-to-cart" 
                                    data-id="<?php echo $p['id_producto']; ?>"
                                    style="margin-top:10px;"
                                    <?php echo ($p['stock_actual'] == 0) ? 'disabled style="background-color:#ccc; cursor:not-allowed;"' : ''; ?>>
                                Añadir al carrito
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; background:white; padding:40px; border-radius:10px; text-align:center;">
                    <p>No se han encontrado productos que coincidan con los filtros seleccionados.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
    function handleSearch(event) {
        event.preventDefault();
        const query = document.getElementById('searchInput').value.trim();
        window.location.href = "filtro.php" + (query === "" ? "" : "?q=" + encodeURIComponent(query));
    }

    let sliderOne = document.getElementById("slider-1");
    let sliderTwo = document.getElementById("slider-2");
    let inputMin = document.getElementById("input-min");
    let inputMax = document.getElementById("input-max");
    let sliderTrack = document.getElementById("track");
    let sliderMaxValue = sliderOne.max;

    function slideOne() {
        if (parseInt(sliderTwo.value) - parseInt(sliderOne.value) <= 50) sliderOne.value = parseInt(sliderTwo.value) - 50;
        inputMin.value = sliderOne.value;
        fillColor();
    }
    function slideTwo() {
        if (parseInt(sliderTwo.value) - parseInt(sliderOne.value) <= 50) sliderTwo.value = parseInt(sliderOne.value) + 50;
        inputMax.value = sliderTwo.value;
        fillColor();
    }
    function updateSliders() {
        sliderOne.value = inputMin.value;
        sliderTwo.value = inputMax.value;
        fillColor();
    }
    function fillColor() {
        let percent1 = (sliderOne.value / sliderMaxValue) * 100;
        let percent2 = (sliderTwo.value / sliderMaxValue) * 100;
        sliderTrack.style.background = `linear-gradient(to right, #ddd ${percent1}% , var(--primary-color) ${percent1}%, var(--primary-color) ${percent2}%, #ddd ${percent2}%)`;
    }
    window.onload = fillColor;
</script>

<script>
// === AÑADIR AL CARRITO CON AJAX + TOAST ===
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('add-to-cart')) {
        const btn = e.target;
        const idProducto = btn.getAttribute('data-id');
        const textoOriginal = btn.textContent;

        btn.disabled = true;
        btn.textContent = 'Añadiendo...';

        fetch('agregar_al_carrito.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id_producto=${idProducto}&cantidad=1`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                btn.textContent = '✓ Añadido';
                btn.style.backgroundColor = '#28a745';
                showToast(data.message);
                setTimeout(() => {
                    btn.textContent = textoOriginal;
                    btn.style.backgroundColor = '';
                    btn.disabled = false;
                }, 1800);
            } else {
                showToast(data.message, 'error');
                btn.textContent = 'Error';
                setTimeout(() => {
                    btn.textContent = textoOriginal;
                    btn.disabled = false;
                }, 2500);
            }
        })
        .catch(() => {
            showToast('Error de conexión', 'error');
            btn.textContent = textoOriginal;
            btn.disabled = false;
        });
    }
});

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position:fixed; bottom:30px; left:50%; transform:translateX(-50%);
        padding:14px 28px; border-radius:8px; color:white; font-weight:600;
        box-shadow:0 6px 20px rgba(0,0,0,0.25); z-index:10000; transition:all 0.3s;
    `;
    toast.style.backgroundColor = (type === 'error') ? '#dc3545' : '#28a745';
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 400);
    }, 2800);
}
</script>
</body>
</html>