<?php
// 1. Iniciar la sesión siempre en la línea 1
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechnoStore | Hardware & High Performance</title>
    
    <link rel="stylesheet" href="CSS/cookies.css">
    <link rel="icon" type="image/png" href="../imagenes/icono.png">

    <style>
        /* --- ESTO ES LO ÚNICO AÑADIDO PARA EVITAR EL SCROLL HORIZONTAL --- */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        /* ----------------------------------------------------------------- */

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

        .hero { 
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), 
                        url('https://images.unsplash.com/photo-1550745165-9bc0b252726f?auto=format&fit=crop&q=80') center/cover no-repeat; 
            color: white; 
            text-align: center; 
            padding: 180px 5% 120px; 
            background-attachment: fixed;
        }
        .hero h1 { font-size: 3.8rem; margin-bottom: 1rem; text-shadow: 0 4px 12px rgba(0,0,0,0.7); }
        .hero p { font-size: 1.5rem; margin-bottom: 2.5rem; max-width: 800px; margin-left: auto; margin-right: auto; }
        
        .btn-cta { 
            background: var(--primary-color); 
            color: white; 
            padding: 18px 48px; 
            text-decoration: none; 
            border-radius: 50px; 
            font-weight: bold; 
            font-size: 1.3rem; 
            transition: all 0.3s; 
            box-shadow: 0 6px 20px rgba(0,86,179,0.4);
            border: none;
            cursor: pointer;
        }
        .btn-cta:hover { 
            background: #004494; 
            transform: translateY(-4px); 
            box-shadow: 0 10px 30px rgba(0,86,179,0.5);
        }

        .container { max-width: 1300px; margin: 60px auto; padding: 0 5%; }
        h2 { text-align: center; color: var(--primary-color); margin-bottom: 40px; font-size: 2.4rem; }

        .grid-productos { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
            gap: 30px; 
        }
        .card { 
            background: white; 
            border-radius: 12px; 
            overflow: hidden; 
            box-shadow: 0 6px 20px rgba(0,0,0,0.12); 
            transition: all 0.3s ease; 
            display: flex; 
            flex-direction: column;
        }
        .card:hover { 
            transform: translateY(-12px); 
            box-shadow: 0 16px 40px rgba(0,0,0,0.18); 
        }
        .card img { 
            width: 100%; 
            height: 220px; 
            object-fit: contain; 
            padding: 20px; 
            background: #f9f9f9; 
        }
        .card-body { padding: 20px; text-align: center; flex-grow: 1; display: flex; flex-direction: column; }
        .card h3 { margin: 0 0 12px; font-size: 1.3rem; height: 2.6em; overflow: hidden; }
        .precio { font-size: 1.6rem; color: var(--primary-color); font-weight: bold; margin: 12px 0; }
        .btn-comprar { 
            background: #28a745; 
            color: white; 
            padding: 12px 24px; 
            border-radius: 50px; 
            text-decoration: none; 
            font-weight: bold; 
            margin-top: auto; 
            transition: all 0.3s;
        }
        .btn-comprar:hover { background: #218838; transform: scale(1.05); }

        @media (max-width: 768px) {
            header { flex-wrap: wrap; height: auto; padding: 10px 5%; }
            .search-container { max-width: 100%; margin: 10px 0; }
            .nav-container { flex-wrap: wrap; justify-content: center; }
            .nav-item { height: auto; }
            .nav-link { padding: 10px 15px; }
            .hero { padding: 120px 5% 80px; }
            .hero h1 { font-size: 2.8rem; }
            .hero p { font-size: 1.3rem; }
            .grid-productos { grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }
        }

        @media (max-width: 480px) {
            .hero h1 { font-size: 2.2rem; }
            .hero p { font-size: 1.1rem; }
            .btn-cta { padding: 14px 32px; font-size: 1.1rem; }
            .grid-productos { grid-template-columns: 1fr; }
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
                <a href="#" class="nav-link">Mi Cuenta</a>
                <div class="dropdown">
                    <?php if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                        <a href="perfil.php">Mi Perfil (<?php echo htmlspecialchars($_SESSION["nombre"] ?? 'Usuario'); ?>)</a>
                        <a href="pedidos.php">Mis Pedidos</a>
                        <a href="carrito.php">Mi Carrito</a>
                        
                        <?php /* Validamos si el usuario es Administrador (id_rol = 1) */ ?>
                        <?php if(isset($_SESSION["id_rol"]) && $_SESSION["id_rol"] == 1): ?>
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

    <section class="hero">
        <h1>Tecnología de Vanguardia</h1>
        <p>Los mejores componentes para usuarios exigentes.</p>
        <button class="btn-cta" onclick="window.location.href='filtro.php'">Ver Catálogo</button>
    </section>

    <div class="container">
        <h2>Hardware Destacado</h2>
        <div class="grid-productos">

            <div class="card">
                <img src="../imagenes/GeForce_RTX_4090_Founders_Edition_24GB.png" alt="GeForce RTX 4090 Founders Edition 24GB">
                <div class="card-body">
                    <h3>GeForce RTX 4090 Founders Edition 24GB</h3>
                    <p class="precio">1.899,99 €</p>
                    <a href="filtro.php?q=RTX%204090" class="btn-comprar">Ver producto</a>
                </div>
            </div>

            <div class="card">
                <img src="../imagenes/Ryzen_9_9950X.png" alt="Ryzen 9 9950X 16 núcleos">
                <div class="card-body">
                    <h3>Ryzen 9 9950X 16 núcleos</h3>
                    <p class="precio">679,99 €</p>
                    <a href="filtro.php?q=Ryzen%209%209950X" class="btn-comprar">Ver producto</a>
                </div>
            </div>

            <div class="card">
                <img src="../imagenes/GeForce_RTX_5080_16GB.png" alt="GeForce RTX 5080 16GB">
                <div class="card-body">
                    <h3>GeForce RTX 5080 16GB</h3>
                    <p class="precio">1.299,99 €</p>
                    <a href="filtro.php?q=RTX%205080" class="btn-comprar">Ver producto</a>
                </div>
            </div>

            <div class="card">
                <img src="../imagenes/Core_i9-15900K_24_nucleos.png" alt="Core i9-15900K 24 núcleos">
                <div class="card-body">
                    <h3>Core i9-15900K 24 núcleos</h3>
                    <p class="precio">689,99 €</p>
                    <a href="filtro.php?q=Core%20i9-15900K" class="btn-comprar">Ver producto</a>
                </div>
            </div>

            <div class="card">
                <img src="../imagenes/ROG_Strix_Z890-E_Gaming_WiFi.png" alt="ROG Strix Z890-E Gaming WiFi">
                <div class="card-body">
                    <h3>ROG Strix Z890-E Gaming WiFi</h3>
                    <p class="precio">499,99 €</p>
                    <a href="filtro.php?q=ROG%20Strix%20Z890" class="btn-comprar">Ver producto</a>
                </div>
            </div>

            <div class="card">
                <img src="../imagenes/990_EVO.png" alt="990 EVO Plus 2TB PCIe 5.0 NVMe">
                <div class="card-body">
                    <h3>990 EVO Plus 2TB PCIe 5.0 NVMe</h3>
                    <p class="precio">229,99 €</p>
                    <a href="filtro.php?q=990%20EVO" class="btn-comprar">Ver producto</a>
                </div>
            </div>

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
