<?php
session_start();
require 'config/db.php';

// 1. Obtener contador del carrito
$total_items = 0;
if (isset($_SESSION['user_id'])) {
    $stmt_cart = $pdo->prepare("SELECT SUM(cantidad) as total FROM carrito WHERE usuario_id = ?");
    $stmt_cart->execute([$_SESSION['user_id']]);
    $row_cart = $stmt_cart->fetch(PDO::FETCH_ASSOC);
    $total_items = $row_cart['total'] ?? 0;
}

// 2. Obtener categorías para el filtro
$stmt_categorias = $pdo->prepare("SELECT DISTINCT categoria FROM productos WHERE estado = 'activo' ORDER BY categoria");
$stmt_categorias->execute();
$categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);

// 3. Parámetros de búsqueda, categoría y paginación
$categoria_filtro = $_GET['categoria'] ?? '';
$busqueda = trim($_GET['q'] ?? '');
$pagina_actual = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$productos_por_pagina = 20;
$offset = ($pagina_actual - 1) * $productos_por_pagina;

// Construir condiciones de filtro
$condiciones = ["p.estado = 'activo'"];
$params = [$_SESSION['user_id'] ?? 0];

if (!empty($categoria_filtro)) {
    $condiciones[] = "p.categoria = ?";
    $params[] = $categoria_filtro;
}

if (!empty($busqueda)) {
    $condiciones[] = "(p.nombre LIKE ? OR p.descripcion LIKE ? OR p.marca LIKE ? OR p.modelo LIKE ?)";
    $term = "%$busqueda%";
    array_push($params, $term, $term, $term, $term);
}

$where_sql = " WHERE " . implode(" AND ", $condiciones);

// 4. Obtener TOTAL de productos (para paginación)
$count_sql = "SELECT COUNT(*) FROM productos p" . $where_sql;
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute(array_slice($params, 1));
$total_productos = $stmt_count->fetchColumn();
$total_paginas = ceil($total_productos / $productos_por_pagina);

// 5. Obtener productos de la página actual
$sql = "SELECT p.*, COALESCE(c.cantidad, 0) AS en_carrito
        FROM productos p
        LEFT JOIN carrito c ON p.id = c.producto_id AND c.usuario_id = ?
        $where_sql
        ORDER BY p.id DESC
        LIMIT $offset, $productos_por_pagina";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener imágenes para cada producto
    foreach ($productos as &$producto) {
        $stmt_img = $pdo->prepare("SELECT url_imagen, clasificacion FROM producto_imagenes WHERE producto_id = ? ORDER BY orden ASC");
        $stmt_img->execute([$producto['id']]);
        $producto['imagenes'] = $stmt_img->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($producto);
    
} catch (Exception $e) {
    $productos = [];
}

// Datos de sesión
$usuario_id = $_SESSION['user_id'] ?? null;
$nombre_usuario = $_SESSION['nombre'] ?? '';
$rol_actual = $_SESSION['user_rol'] ?? $_SESSION['rol'] ?? '';
$es_staff = in_array($rol_actual, ['admin', 'dueño', 'dueno']);

// Función para construir URLs con parámetros existentes
function buildUrl($params = []) {
    $query = $_GET;
    foreach ($params as $key => $value) {
        if ($value === null) {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }
    return '?' . http_build_query($query);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGS | NetGuard Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #061325;
            --bg-secondary: rgba(6, 19, 37, 0.95);
            --accent-primary: #2b6cb0;
            --accent-secondary: #3b82f6;
            --bg-body: #ffffff;
            --bg-card: #f8fafc;
            --text-main: #1e293b;
            --text-sec: #64748b;
            --border-color: #e2e8f0;
            --error: #ef4444;
            --success: #22c55e;
        }
        
        body { 
            background-color: var(--bg-body); 
            color: var(--text-main); 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            line-height: 1.6;
        }
        
        header { 
            background: var(--bg-secondary); 
            padding: 15px 0; 
            border-bottom: 1px solid var(--border-color); 
            position: sticky; 
            top: 0; 
            z-index: 100; 
            backdrop-filter: blur(10px); 
        }
        
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 0 20px; 
        }
        
        .navbar { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        
        .logo-text { 
            font-weight: 800; 
            font-size: 1.5rem; 
            color: white; 
            text-decoration: none; 
            letter-spacing: -1px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .nav-links { 
            display: flex; 
            align-items: center; 
            gap: 20px; 
        }
        
        .btn { 
            padding: 8px 16px; 
            border-radius: 6px; 
            text-decoration: none; 
            font-weight: 600; 
            font-size: 0.9rem; 
            transition: 0.2s; 
            border: none;
            cursor: pointer;
        }
        
        .btn-login { 
            background: transparent; 
            border: 1px solid var(--accent-secondary); 
            color: white; 
        }
        
        .btn-login:hover { 
            background: var(--accent-secondary); 
        }
        
        .btn-registro { 
            background: white; 
            color: var(--bg-primary); 
        }
        
        .btn-registro:hover { 
            background: #f1f5f9; 
        }
        
        .btn-admin { 
            background: var(--accent-primary); 
            color: white; 
        }
        
        .btn-admin:hover { 
            background: #3182ce; 
        }
        
        .cart-btn { 
            position: relative; 
            color: white; 
            text-decoration: none; 
            font-size: 1.2rem; 
            margin-right: 10px; 
            padding: 8px;
            border-radius: 6px;
            transition: 0.2s;
        }
        
        .cart-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .cart-badge { 
            position: absolute; 
            top: -8px; 
            right: -8px; 
            background: var(--error); 
            color: white; 
            font-size: 0.7rem; 
            font-weight: bold; 
            padding: 2px 6px; 
            border-radius: 10px; 
            border: 2px solid var(--bg-secondary);
            transition: transform 0.2s;
        }

        .cart-badge.pulse {
            animation: pulse 0.3s ease;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.3); }
            100% { transform: scale(1); }
        }

        .nombre-usuario {
            display: inline;
        }

        /* --- SECCIÓN HERO --- */
        .hero { 
            text-align: center; 
            padding: 140px 20px; 
            background: linear-gradient(rgba(6, 19, 37, 0.9), rgba(6, 19, 37, 0.95)), 
                        url('assets/img/banner-hero.webp'); 
            background-size: cover; 
            background-position: center; 
            background-attachment: fixed;
        }

        .hero h1 { 
            font-size: 3.5rem; 
            margin: 0; 
            font-weight: 800;
            color: white;
            text-shadow: 0 10px 20px rgba(0,0,0,0.4);
        }
        
        .hero p {
            color: #cbd5e1; 
            font-size: 1.2rem; 
            margin-top: 15px; 
            font-weight: 500;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* --- BARRA DE BÚSQUEDA --- */
        .search-section {
            background: linear-gradient(135deg, #0a1a2e, #061325);
            padding: 40px 0;
            border-bottom: 1px solid #1e3a5f;
        }

        .search-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .search-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            text-align: center;
        }

        .search-box {
            display: flex;
            width: 100%;
            max-width: 600px;
            background: white;
            border-radius: 50px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .search-box input {
            flex: 1;
            padding: 15px 25px;
            border: none;
            outline: none;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
        }

        .search-box button {
            background: var(--accent-primary);
            color: white;
            border: none;
            padding: 0 30px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-box button:hover {
            background: #1e4b8b;
        }

        .search-results-info {
            color: #94a3b8;
            font-size: 0.95rem;
        }

        .search-results-info strong {
            color: white;
        }

        .clear-search {
            color: var(--accent-secondary);
            text-decoration: none;
            margin-left: 15px;
            font-size: 0.9rem;
        }

        .clear-search:hover {
            text-decoration: underline;
        }

        /* --- FILTRO DE CATEGORÍAS --- */
        .filtro-categorias {
            background: var(--bg-card);
            padding: 20px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .categorias-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        
        .categoria-btn {
            padding: 8px 16px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-main);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .categoria-btn:hover {
            border-color: var(--accent-primary);
            color: var(--accent-primary);
            background: #ebf8ff;
        }
        
        .categoria-btn.active {
            background: var(--accent-primary);
            color: white;
            border-color: var(--accent-primary);
        }
        
        .categoria-titulo {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-main);
            margin-right: 15px;
        }
        
        .reset-filtro {
            margin-left: auto;
            color: var(--accent-primary);
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .reset-filtro:hover {
            text-decoration: underline;
        }

        /* --- GRID DE PRODUCTOS --- */
        .grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
            gap: 30px; 
            padding: 40px 0; 
        }
        
        .card { 
            background: var(--bg-card); 
            border: 1px solid var(--border-color); 
            border-radius: 12px; 
            overflow: hidden; 
            transition: 0.3s; 
            display: flex; 
            flex-direction: column;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .card:hover { 
            transform: translateY(-5px); 
            border-color: var(--accent-primary); 
            box-shadow: 0 10px 25px rgba(0,0,0,0.1); 
        }
        
        .card-img-container {
            width: 100%; 
            height: 220px; 
            background: white; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            overflow: hidden;
            border-bottom: 1px solid var(--border-color);
        }
        
        .card-img { 
            width: 100%; 
            height: 100%; 
            object-fit: contain; 
            padding: 15px; 
            box-sizing: border-box; 
        }
        
        .card-body { 
            padding: 20px; 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
        }
        
        .card-title { 
            margin: 0 0 10px 0; 
            font-size: 1.1rem; 
            font-weight: 600; 
            color: var(--text-main);
        }
        
        .card-categoria {
            color: var(--accent-primary);
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .price-container {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 15px;
        }
        
        .price-minorista {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .price-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-sec);
            white-space: nowrap;
        }
        
        .price-value {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--accent-primary);
        }
        
        .price-mayorista {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 3px;
        }
        
        .price-mayorista .price-value {
            color: var(--success);
        }
        
        .badge-precio {
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .badge-minorista {
            background: #e0f2fe;
            color: #0369a1;
        }
        
        .badge-mayorista {
            background: #dcfce7;
            color: #166534;
        }

        .card-actions {
            margin-top: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .btn-detail { 
            background: white; 
            border: 1px solid var(--accent-primary); 
            color: var(--accent-primary); 
            text-align: center; 
            padding: 12px; 
            border-radius: 8px; 
            text-decoration: none; 
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-detail:hover { 
            background: var(--accent-primary); 
            color: white; 
        }
        
        .btn-add-cart { 
            background: var(--accent-primary); 
            border: none; 
            color: white; 
            padding: 12px; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: 600;
            transition: 0.2s; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 8px;
            font-size: 0.95rem;
            width: 100%;
        }
        
        .btn-add-cart:hover { 
            background: #2c5282; 
        }
        
        .btn-add-cart:disabled { 
            background: #cbd5e1; 
            cursor: not-allowed; 
        }

        .btn-add-cart.loading {
            position: relative;
            color: transparent;
        }

        .btn-add-cart.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid white;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* --- PAGINACIÓN --- */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 40px 0;
            flex-wrap: wrap;
        }

        .page-link {
            padding: 8px 14px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-main);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s;
            background: white;
            min-width: 40px;
            text-align: center;
        }

        .page-link:hover {
            border-color: var(--accent-primary);
            color: var(--accent-primary);
            background: #ebf8ff;
        }

        .page-link.active {
            background: var(--accent-primary);
            color: white;
            border-color: var(--accent-primary);
        }

        .page-dots {
            padding: 8px 5px;
            color: var(--text-sec);
            font-weight: 600;
        }

        /* --- MENSAJE SIN PRODUCTOS --- */
        .sin-productos {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-sec);
        }
        
        .sin-productos h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: var(--text-main);
        }

        /* --- FOOTER --- */
        footer { 
            text-align: center; 
            padding: 40px; 
            color: var(--text-sec); 
            border-top: 1px solid var(--border-color); 
            margin-top: 50px; 
            background: var(--bg-card);
        }
        
        /* --- NOTIFICACIÓN FLOTANTE --- */
        .cart-notification {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--success);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(34, 197, 94, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .cart-notification.show {
            transform: translateY(0);
            opacity: 1;
        }

        .cart-notification i {
            font-size: 1.2rem;
        }

        /* --- RESPONSIVE --- */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .hero {
                padding: 100px 20px;
            }
            
            .grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
            }
            
            .categorias-container {
                justify-content: center;
            }
            
            .categoria-titulo {
                width: 100%;
                text-align: center;
                margin-bottom: 10px;
            }
            
            .reset-filtro {
                margin-left: 0;
                margin-top: 10px;
                width: 100%;
                text-align: center;
            }
            
            .nav-links {
                gap: 10px;
            }
            
            .logo-text {
                font-size: 1.2rem;
            }
            
            .nombre-usuario {
                display: none;
            }
            
            .btn-login {
                padding: 8px 12px;
            }
            
            .price-minorista,
            .price-mayorista {
                flex-wrap: wrap;
            }
            
            .price-label {
                font-size: 0.75rem;
            }
            
            .price-value {
                font-size: 1rem;
            }

            .pagination {
                gap: 5px;
            }

            .page-link {
                padding: 6px 10px;
                min-width: 35px;
                font-size: 0.85rem;
            }

            .search-title {
                font-size: 1.5rem;
            }

            .search-box {
                flex-direction: column;
                border-radius: 12px;
            }

            .search-box button {
                padding: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .hero p {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>

    <header>
        <div class="container navbar">
            <a href="index.php" class="logo-text">
                <i class="fa-solid fa-shield-alt"></i>
                NGS
            </a>
            
            <nav class="nav-links">
                <?php if ($usuario_id): ?>
                    <a href="account/perfil.php" class="btn btn-login" style="display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-user-circle"></i>
                        <span class="nombre-usuario"><?php echo htmlspecialchars($nombre_usuario); ?></span>
                    </a>
                    
                    <a href="ver_carrito.php" class="cart-btn" id="cartBtn">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <span class="cart-badge" id="cartBadge"><?php echo $total_items; ?></span>
                    </a>
                    
                    <?php if ($es_staff): ?>
                        <a href="admin/index.php" class="btn btn-admin">Administración</a>
                    <?php endif; ?>
                    
                    <a href="logout.php" style="color:#fecaca; text-decoration:none; font-size:0.9rem; display: flex; align-items: center; gap: 5px;">
                        <i class="fa-solid fa-sign-out-alt"></i> Salir
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-login">
                        <i class="fa-solid fa-sign-in-alt"></i> ENTRAR
                    </a>
                    <a href="registro.php" class="btn btn-registro">
                        <i class="fa-solid fa-user-plus"></i> REGISTRO
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <div class="hero">
        <div class="container">
            <h1>HARDWARE DE ALTO NIVEL</h1>
            <p>Potencia tu equipo con los mejores componentes.</p>
        </div>
    </div>

    <!-- Barra de búsqueda -->
    <div class="search-section">
        <div class="container search-container">
            <h2 class="search-title">Busca el producto que necesitas</h2>
            
            <form action="index.php" method="GET" class="search-box" id="searchForm">
                <input type="text" 
                       name="q" 
                       placeholder="Buscar por nombre, descripción, marca o modelo..." 
                       value="<?php echo htmlspecialchars($busqueda); ?>"
                       autocomplete="off">
                <button type="submit">
                    <i class="fa-solid fa-search"></i> Buscar
                </button>
            </form>
            
            <?php if (!empty($busqueda)): ?>
                <div class="search-results-info">
                    <strong><?php echo $total_productos; ?></strong> resultados para "<strong><?php echo htmlspecialchars($busqueda); ?></strong>"
                    <a href="index.php" class="clear-search">Limpiar búsqueda</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtro de categorías -->
    <?php if (!empty($categorias)): ?>
    <div class="filtro-categorias">
        <div class="container categorias-container">
            <span class="categoria-titulo">Categorías:</span>
            
            <a href="index.php<?php echo !empty($busqueda) ? '?q='.urlencode($busqueda) : ''; ?>" 
               class="categoria-btn <?php echo empty($categoria_filtro) ? 'active' : ''; ?>">
                Todas
            </a>
            
            <?php foreach($categorias as $categoria): 
                $url_params = [];
                if (!empty($busqueda)) $url_params['q'] = $busqueda;
                $url_params['categoria'] = $categoria['categoria'];
            ?>
                <a href="index.php?<?php echo http_build_query($url_params); ?>" 
                   class="categoria-btn <?php echo $categoria_filtro == $categoria['categoria'] ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($categoria['categoria']); ?>
                </a>
            <?php endforeach; ?>
            
            <?php if (!empty($categoria_filtro)): ?>
                <?php 
                $reset_params = [];
                if (!empty($busqueda)) $reset_params['q'] = $busqueda;
                ?>
                <a href="index.php<?php echo !empty($reset_params) ? '?'.http_build_query($reset_params) : ''; ?>" class="reset-filtro">
                    <i class="fa-solid fa-times"></i> Limpiar filtro
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <section class="container">
        <?php if (!empty($productos)): ?>
            <div class="grid">
                <?php foreach($productos as $p): 
                    $precio_minorista = $p['precio'];
                    $precio_mayorista = $p['precio_mayorista'] ?? $precio_minorista * 0.8;
                    
                    $img_final = 'https://via.placeholder.com/300?text=Sin+Imagen';
                    if (!empty($p['imagenes'])) {
                        foreach ($p['imagenes'] as $imagen) {
                            if ($imagen['clasificacion'] === 'principal') {
                                $img_final = $imagen['url_imagen'];
                                break;
                            }
                        }
                        if ($img_final === 'https://via.placeholder.com/300?text=Sin+Imagen') {
                            $img_final = $p['imagenes'][0]['url_imagen'];
                        }
                    } elseif (!empty($p['imagen'])) {
                        $img_db = $p['imagen'];
                        if (strpos($img_db, 'uploads/') === 0) {
                            $img_final = $img_db;
                        } else {
                            $img_final = 'uploads/productos/' . $img_db;
                        }
                    }
                ?>
                    <div class="card" data-producto-id="<?= $p['id'] ?>">
                        <div class="card-img-container">
                            <img src="<?php echo htmlspecialchars($img_final); ?>" class="card-img" alt="<?= htmlspecialchars($p['nombre']) ?>" onerror="this.src='https://via.placeholder.com/300?text=Imagen+No+Disponible'">
                        </div>

                        <div class="card-body">
                            <div>
                                <div class="card-categoria"><?= htmlspecialchars($p['categoria']) ?></div>
                                <h3 class="card-title"><?= htmlspecialchars($p['nombre']) ?></h3>
                                
                                <?php if ($usuario_id): ?>
                                    <div class="price-container">
                                        <div class="price-minorista">
                                            <span class="badge-precio badge-minorista">Minorista</span>
                                            <span class="price-label">Precio:</span>
                                            <span class="price-value">$<?= number_format($precio_minorista, 2) ?></span>
                                        </div>
                                        
                                        <div class="price-mayorista">
                                            <span class="badge-precio badge-mayorista">Mayorista</span>
                                            <span class="price-label">Precio:</span>
                                            <span class="price-value">$<?= number_format($precio_mayorista, 2) ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="card-actions">
                                <?php if ($usuario_id): ?>
                                    <div style="display: flex; gap: 10px;">
                                        <a href="producto.php?id=<?= $p['id'] ?>" class="btn-detail">
                                            Ver Detalles
                                        </a>
                                        
                                        <button type="button" 
                                                class="btn-add-cart add-to-cart" 
                                                data-producto-id="<?= $p['id'] ?>"
                                                data-precio="<?= $precio_minorista ?>">
                                            <i class="fa-solid fa-cart-plus"></i> Agregar
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <a href="producto.php?id=<?= $p['id'] ?>" class="btn-detail" style="width: 100%;">
                                        Ver Detalles
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- PAGINACIÓN -->
            <?php if ($total_paginas > 1): ?>
                <div class="pagination">
                    <?php if ($pagina_actual > 1): ?>
                        <a href="index.php?<?php echo http_build_query(array_merge($_GET, ['page' => $pagina_actual - 1])); ?>" class="page-link">
                            <i class="fa-solid fa-chevron-left"></i> Anterior
                        </a>
                    <?php endif; ?>

                    <?php
                    // Lógica para mostrar páginas
                    $rango = 2; // Cuántas páginas mostrar antes y después de la actual
                    $inicio = max(1, $pagina_actual - $rango);
                    $fin = min($total_paginas, $pagina_actual + $rango);

                    // Siempre mostrar la primera página
                    if ($inicio > 1) {
                        echo '<a href="index.php?' . http_build_query(array_merge($_GET, ['page' => 1])) . '" class="page-link">1</a>';
                        if ($inicio > 2) {
                            echo '<span class="page-dots">...</span>';
                        }
                    }

                    // Páginas intermedias
                    for ($i = $inicio; $i <= $fin; $i++) {
                        if ($i == $pagina_actual) {
                            echo '<span class="page-link active">' . $i . '</span>';
                        } else {
                            echo '<a href="index.php?' . http_build_query(array_merge($_GET, ['page' => $i])) . '" class="page-link">' . $i . '</a>';
                        }
                    }

                    // Siempre mostrar la última página
                    if ($fin < $total_paginas) {
                        if ($fin < $total_paginas - 1) {
                            echo '<span class="page-dots">...</span>';
                        }
                        echo '<a href="index.php?' . http_build_query(array_merge($_GET, ['page' => $total_paginas])) . '" class="page-link">' . $total_paginas . '</a>';
                    }
                    ?>

                    <?php if ($pagina_actual < $total_paginas): ?>
                        <a href="index.php?<?php echo http_build_query(array_merge($_GET, ['page' => $pagina_actual + 1])); ?>" class="page-link">
                            Siguiente <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="sin-productos">
                <h3>No hay productos disponibles</h3>
                <p>
                    <?php 
                    if (!empty($busqueda) && !empty($categoria_filtro)) {
                        echo "No se encontraron productos para la búsqueda '<strong>" . htmlspecialchars($busqueda) . "</strong>' en la categoría '<strong>" . htmlspecialchars($categoria_filtro) . "</strong>'";
                    } elseif (!empty($busqueda)) {
                        echo "No se encontraron productos para la búsqueda '<strong>" . htmlspecialchars($busqueda) . "</strong>'";
                    } elseif (!empty($categoria_filtro)) {
                        echo "No hay productos en la categoría '<strong>" . htmlspecialchars($categoria_filtro) . "</strong>'";
                    } else {
                        echo "No hay productos en stock";
                    }
                    ?>
                </p>
                <a href="index.php" class="btn-detail" style="display: inline-block; margin-top: 15px;">
                    Ver todos los productos
                </a>
            </div>
        <?php endif; ?>
    </section>

    <footer>
        <p>&copy; <?= date('Y') ?> NetGuard Systems. Todos los derechos reservados.</p>
        <p style="font-size: 0.9rem; margin-top: 10px;">Distribuidor autorizado de productos de seguridad y tecnología</p>
    </footer>

    <!-- Notificación flotante -->
    <div id="cartNotification" class="cart-notification">
        <i class="fa-solid fa-check-circle"></i>
        <span id="notificationMessage">Producto agregado al carrito</span>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Manejar errores de carga de imágenes
            const images = document.querySelectorAll('.card-img');
            images.forEach(img => {
                img.addEventListener('error', function() {
                    this.src = 'https://via.placeholder.com/300?text=Imagen+No+Disponible';
                    this.style.objectFit = 'cover';
                    this.style.padding = '0';
                });
            });

            // Si el usuario no está logueado, no hacer nada más
            <?php if (!$usuario_id): ?>
                return;
            <?php endif; ?>

            // Variables para el carrito
            const cartBadge = document.getElementById('cartBadge');
            const notification = document.getElementById('cartNotification');
            const notificationMessage = document.getElementById('notificationMessage');
            const addToCartButtons = document.querySelectorAll('.add-to-cart');

            // Función para mostrar notificación
            function showNotification(message, isSuccess = true) {
                notificationMessage.textContent = message;
                notification.style.background = isSuccess ? '#22c55e' : '#ef4444';
                notification.classList.add('show');
                
                setTimeout(() => {
                    notification.classList.remove('show');
                }, 3000);
            }

            // Función para actualizar el badge del carrito
            function updateCartBadge(callback) {
                fetch('api/obtener_carrito.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const total = data.total_items || 0;
                            cartBadge.textContent = total;
                            
                            if (total > 0) {
                                cartBadge.style.display = 'inline';
                                cartBadge.classList.add('pulse');
                                setTimeout(() => cartBadge.classList.remove('pulse'), 300);
                            } else {
                                cartBadge.style.display = 'none';
                            }
                            
                            if (callback) callback();
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }

            // Agregar al carrito con AJAX
            addToCartButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const productoId = this.dataset.productoId;
                    const precio = this.dataset.precio;
                    const originalText = this.innerHTML;
                    
                    // Deshabilitar botón y mostrar loading
                    this.disabled = true;
                    this.classList.add('loading');
                    
                    // Enviar petición AJAX
                    fetch('api/agregar_carrito_ajax.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            producto_id: productoId,
                            cantidad: 1,
                            precio: precio
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Actualizar badge
                            updateCartBadge();
                            
                            // Mostrar notificación de éxito
                            showNotification('✅ Producto agregado al carrito', true);
                        } else {
                            // Mostrar error
                            showNotification('❌ ' + (data.error || 'Error al agregar'), false);
                            
                            if (data.error === 'Sesión no iniciada') {
                                setTimeout(() => {
                                    window.location.href = 'login.php';
                                }, 2000);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('❌ Error de conexión', false);
                    })
                    .finally(() => {
                        // Restaurar botón
                        this.disabled = false;
                        this.classList.remove('loading');
                        this.innerHTML = originalText;
                    });
                });
            });

            // Opcional: Actualizar badge al cargar la página
            updateCartBadge();
        });
    </script>
</body>
</html>