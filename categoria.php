<?php
session_start();
require 'config/db.php';

// 1. Obtener categoría de la URL
$categoria = $_GET['cat'] ?? '';
if (empty($categoria)) {
    header('Location: index.php');
    exit;
}

// 2. Verificar usuario
$usuario_id = $_SESSION['user_id'] ?? null;
$nombre_usuario = $_SESSION['nombre'] ?? '';
$rol_actual = $_SESSION['user_rol'] ?? $_SESSION['rol'] ?? '';
$es_staff = in_array($rol_actual, ['admin', 'dueño', 'dueno']);
$total_items = 0;

if ($usuario_id) {
    $stmt_cart = $pdo->prepare("SELECT SUM(cantidad) as total FROM carrito WHERE usuario_id = ?");
    $stmt_cart->execute([$usuario_id]);
    $row_cart = $stmt_cart->fetch(PDO::FETCH_ASSOC);
    $total_items = $row_cart['total'] ?: 0;
}

// 3. Parámetros de paginación
$pagina_actual = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$productos_por_pagina = 20;
$offset = ($pagina_actual - 1) * $productos_por_pagina;

// 4. Obtener TOTAL de productos en esta categoría
$count_sql = "SELECT COUNT(*) FROM productos WHERE categoria = ? AND estado = 'activo'";
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute([$categoria]);
$total_productos = $stmt_count->fetchColumn();
$total_paginas = ceil($total_productos / $productos_por_pagina);

// 5. Obtener productos de la página actual
$sql = "SELECT * FROM productos 
        WHERE categoria = ? AND estado = 'activo' 
        ORDER BY 
            CASE 
                WHEN stock > 0 THEN 0 
                ELSE 1 
            END,
            id DESC
        LIMIT $offset, $productos_por_pagina";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$categoria]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener imágenes para cada producto
    foreach ($productos as &$producto) {
        $stmt_img = $pdo->prepare("SELECT url_imagen, clasificacion FROM producto_imagenes WHERE producto_id = ? ORDER BY 
            CASE clasificacion 
                WHEN 'principal' THEN 1 
                WHEN 'tecnica' THEN 2 
                WHEN 'contenido' THEN 3 
                WHEN 'adicional' THEN 4 
            END, orden LIMIT 1");
        $stmt_img->execute([$producto['id']]);
        $imagen = $stmt_img->fetch(PDO::FETCH_ASSOC);
        $producto['imagen_principal'] = $imagen ? $imagen['url_imagen'] : 'https://via.placeholder.com/300x300?text=Sin+Imagen';
    }
    unset($producto);
    
} catch (Exception $e) {
    $productos = [];
}

// 6. Obtener todas las categorías para el menú lateral
$stmt_categorias = $pdo->prepare("SELECT DISTINCT categoria FROM productos WHERE estado = 'activo' ORDER BY categoria");
$stmt_categorias->execute();
$categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);

// Función para construir URLs
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
    <title><?php echo htmlspecialchars($categoria); ?> | NGS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --primary-light: #60a5fa;
            --secondary: #8b5cf6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg-dark: #0f172a;
            --bg-darker: #0a1122;
            --card-bg: #1e293b;
            --card-bg-light: #26334d;
            --card-border: #334155;
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --text-light: #cbd5e1;
            --text-lighter: #e2e8f0;
            
            /* Header variables */
            --bg-primary: #061325;
            --bg-secondary: rgba(6, 19, 37, 0.95);
            --accent-primary: #2b6cb0;
            --accent-secondary: #3b82f6;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            line-height: 1.6;
        }

        /* Header */
        header {
            background: var(--bg-secondary);
            padding: 15px 0;
            border-bottom: 1px solid var(--card-border);
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 40px;
        }
        
        .navbar {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 30px;
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
            background: var(--danger);
            color: white;
            font-size: 0.7rem;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 10px;
            border: 2px solid var(--bg-secondary);
        }
        
        .nombre-usuario {
            display: inline;
        }

        /* Estilos para el buscador */
        .search-box {
            position: relative;
            display: flex;
            width: 100%;
            max-width: 600px;
            background: white;
            border-radius: 50px;
            overflow: visible;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .search-box input {
            flex: 1;
            padding: 12px 20px;
            border: none;
            outline: none;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            background: white;
            color: #1e293b;
        }

        .search-box input::placeholder {
            color: #94a3b8;
        }

        .search-box button {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0 25px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .search-box button:hover {
            background: var(--primary-dark);
        }

        /* Estilos para resultados de búsqueda */
        .search-results-container {
            position: absolute;
            top: calc(100% + 10px);
            left: 0;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            border: 1px solid var(--card-border);
        }

        .search-results-container.show {
            display: block;
        }

        .search-result-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            text-decoration: none;
            color: #1e293b;
            transition: background 0.2s;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-item:hover {
            background: #f1f5f9;
        }

        .search-result-item.view-all {
            background: var(--primary);
            color: white;
            border-radius: 0 0 12px 12px;
        }

        .search-result-item.view-all:hover {
            background: var(--primary-dark);
        }

        .search-result-item.view-all i,
        .search-result-item.view-all span {
            color: white;
        }

        .search-result-item.no-results {
            color: #64748b;
            justify-content: center;
        }

        .result-image {
            width: 50px;
            height: 50px;
            background: #f8fafc;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            border: 1px solid #e2e8f0;
            flex-shrink: 0;
        }

        .result-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .result-info {
            flex: 1;
            min-width: 0;
        }

        .result-title {
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 4px;
            font-size: 0.95rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .result-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
        }

        .result-brand {
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
        }

        .result-price {
            font-weight: 700;
            color: var(--primary);
            white-space: nowrap;
        }

        /* Breadcrumb */
        .breadcrumb {
            padding: 20px 0;
            color: var(--text-dim);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .breadcrumb a {
            color: var(--text-dim);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .breadcrumb a:hover {
            color: var(--primary);
        }
        
        .breadcrumb-separator {
            opacity: 0.5;
        }

        /* Layout principal con sidebar */
        .category-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
            margin-top: 20px;
        }

        /* Sidebar de categorías */
        .category-sidebar {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--card-border);
            padding: 20px;
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .sidebar-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: white;
            margin: 0 0 20px 0;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-title i {
            color: var(--primary);
        }

        .category-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .category-item {
            margin-bottom: 5px;
        }

        .category-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 15px;
            color: var(--text-light);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
            font-size: 0.95rem;
        }

        .category-link:hover {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary);
        }

        .category-link.active {
            background: var(--primary);
            color: white;
        }

        .category-link.active .category-count {
            color: white;
            background: rgba(255, 255, 255, 0.2);
        }

        .category-count {
            background: var(--bg-darker);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            color: var(--text-dim);
        }

        /* Header de categoría */
        .category-header {
            margin-bottom: 30px;
        }

        .category-title {
            font-size: 2rem;
            font-weight: 700;
            color: white;
            margin: 0 0 10px 0;
        }

        .category-stats {
            color: var(--text-dim);
            font-size: 1rem;
        }

        .category-stats strong {
            color: var(--primary);
        }

        /* Grid de productos */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }
        
        .product-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        
        .product-image {
            height: 200px;
            background: var(--bg-darker);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid var(--card-border);
        }
        
        .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-category {
            color: var(--primary);
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .product-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            margin: 0 0 10px 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 2.8em;
        }
        
        .product-brand {
            color: var(--text-dim);
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .product-price {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 15px;
        }
        
        .price-minorista {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }
        
        .price-minorista .price-value {
            color: var(--primary);
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .price-mayorista {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }
        
        .price-mayorista .price-value {
            color: var(--success);
            font-weight: 800;
            font-size: 1.2rem;
        }
        
        .badge-precio {
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .badge-minorista {
            background: rgba(59, 130, 246, 0.2);
            color: var(--primary);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        .badge-mayorista {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .stock-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .stock-badge.agotado {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            border-color: rgba(239, 68, 68, 0.3);
        }

        /* Paginación */
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
            border: 1px solid var(--card-border);
            border-radius: 6px;
            color: var(--text-light);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s;
            background: var(--card-bg);
            min-width: 40px;
            text-align: center;
        }
        
        .page-link:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(59, 130, 246, 0.1);
        }
        
        .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .page-dots {
            padding: 8px 5px;
            color: var(--text-dim);
        }

        /* Sin productos */
        .no-products {
            text-align: center;
            padding: 60px 20px;
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--card-border);
            grid-column: 1 / -1;
        }
        
        .no-products i {
            font-size: 4rem;
            color: var(--text-dim);
            margin-bottom: 20px;
        }
        
        .no-products h3 {
            font-size: 1.8rem;
            margin: 0 0 15px 0;
            color: white;
        }
        
        .no-products p {
            color: var(--text-dim);
            font-size: 1.1rem;
            max-width: 500px;
            margin: 0 auto 25px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .container {
                padding: 0 20px;
            }
            
            .navbar {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .search-box {
                max-width: 100%;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .nombre-usuario {
                display: none;
            }
            
            .category-layout {
                grid-template-columns: 1fr;
            }
            
            .category-sidebar {
                position: static;
                margin-bottom: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .category-title {
                font-size: 1.8rem;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
            }
            
            .pagination {
                gap: 5px;
            }
            
            .page-link {
                padding: 6px 10px;
                min-width: 35px;
                font-size: 0.85rem;
            }
            
            .search-box {
                flex-direction: column;
                border-radius: 12px;
            }

            .search-box button {
                padding: 12px;
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .category-title {
                font-size: 1.5rem;
            }
            
            .no-products h3 {
                font-size: 1.4rem;
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
            
            <!-- BUSCADOR -->
            <div class="search-box">
                <input type="text" 
                       id="searchInput"
                       placeholder="Buscar por nombre, descripción, marca o modelo..." 
                       autocomplete="off">
                <button type="button" onclick="window.location.href='busqueda.php?q='+encodeURIComponent(document.getElementById('searchInput').value)">
                    <i class="fa-solid fa-search"></i> Buscar
                </button>
            </div>
            
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

    <!-- Breadcrumb -->
    <div class="container">
        <div class="breadcrumb">
            <a href="index.php">Inicio</a>
            <span class="breadcrumb-separator">/</span>
            <span><?php echo htmlspecialchars($categoria); ?></span>
        </div>
    </div>

    <div class="container">
        <div class="category-layout">
            <!-- Sidebar de categorías -->
            <aside class="category-sidebar">
                <h3 class="sidebar-title">
                    <i class="fa-solid fa-layer-group"></i>
                    Categorías
                </h3>
                
                <ul class="category-list">
                    <?php foreach($categorias as $cat): 
                        // Obtener conteo de productos por categoría
                        $stmt_count_cat = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE categoria = ? AND estado = 'activo'");
                        $stmt_count_cat->execute([$cat['categoria']]);
                        $count_cat = $stmt_count_cat->fetchColumn();
                    ?>
                        <li class="category-item">
                            <a href="categoria.php?cat=<?php echo urlencode($cat['categoria']); ?>" 
                               class="category-link <?php echo $categoria == $cat['categoria'] ? 'active' : ''; ?>">
                                <span><?php echo htmlspecialchars($cat['categoria']); ?></span>
                                <span class="category-count"><?php echo $count_cat; ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </aside>

            <!-- Contenido principal -->
            <main>
                <!-- Header de categoría -->
                <div class="category-header">
                    <h1 class="category-title"><?php echo htmlspecialchars($categoria); ?></h1>
                    <div class="category-stats">
                        <strong><?php echo $total_productos; ?></strong> producto<?php echo $total_productos != 1 ? 's' : ''; ?> disponible<?php echo $total_productos != 1 ? 's' : ''; ?>
                    </div>
                </div>

                <!-- Grid de productos -->
                <?php if (!empty($productos)): ?>
                    <div class="products-grid">
                        <?php foreach($productos as $producto): 
                            $precio_minorista = $producto['precio'];
                            $precio_mayorista = $producto['precio_mayorista'] ?? $precio_minorista * 0.8;
                        ?>
                            <a href="producto.php?id=<?php echo $producto['id']; ?>" class="product-card">
                                <div class="product-image">
                                    <img src="<?php echo htmlspecialchars($producto['imagen_principal']); ?>" 
                                         alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                         onerror="this.src='https://via.placeholder.com/300x300?text=Sin+Imagen'">
                                </div>
                                
                                <div class="product-info">
                                    <div class="product-category">
                                        <?php echo htmlspecialchars($producto['categoria']); ?>
                                    </div>
                                    
                                    <h3 class="product-name">
                                        <?php echo htmlspecialchars($producto['nombre']); ?>
                                    </h3>
                                    
                                    <div class="product-brand">
                                        <?php echo htmlspecialchars($producto['marca']); ?> - <?php echo htmlspecialchars($producto['modelo']); ?>
                                    </div>
                                    
                                    <?php if ($usuario_id): ?>
                                        <div class="product-price">
                                            <div class="price-minorista">
                                                <span class="badge-precio badge-minorista">Minorista</span>
                                                <span class="price-value">$<?php echo number_format($precio_minorista, 2); ?></span>
                                            </div>
                                            <div class="price-mayorista">
                                                <span class="badge-precio badge-mayorista">Mayorista</span>
                                                <span class="price-value">$<?php echo number_format($precio_mayorista, 2); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <span class="stock-badge <?php echo ($producto['stock'] > 0) ? '' : 'agotado'; ?>">
                                        <?php echo ($producto['stock'] > 0) ? 'Disponible' : 'Agotado'; ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- Paginación -->
                    <?php if ($total_paginas > 1): ?>
                        <div class="pagination">
                            <?php if ($pagina_actual > 1): ?>
                                <a href="<?php echo buildUrl(['page' => $pagina_actual - 1]); ?>" class="page-link">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php
                            $rango = 2;
                            $inicio = max(1, $pagina_actual - $rango);
                            $fin = min($total_paginas, $pagina_actual + $rango);

                            if ($inicio > 1) {
                                echo '<a href="' . buildUrl(['page' => 1]) . '" class="page-link">1</a>';
                                if ($inicio > 2) {
                                    echo '<span class="page-dots">...</span>';
                                }
                            }

                            for ($i = $inicio; $i <= $fin; $i++) {
                                if ($i == $pagina_actual) {
                                    echo '<span class="page-link active">' . $i . '</span>';
                                } else {
                                    echo '<a href="' . buildUrl(['page' => $i]) . '" class="page-link">' . $i . '</a>';
                                }
                            }

                            if ($fin < $total_paginas) {
                                if ($fin < $total_paginas - 1) {
                                    echo '<span class="page-dots">...</span>';
                                }
                                echo '<a href="' . buildUrl(['page' => $total_paginas]) . '" class="page-link">' . $total_paginas . '</a>';
                            }
                            ?>

                            <?php if ($pagina_actual < $total_paginas): ?>
                                <a href="<?php echo buildUrl(['page' => $pagina_actual + 1]); ?>" class="page-link">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Sin productos -->
                    <div class="no-products">
                        <i class="fa-solid fa-box-open"></i>
                        <h3>No hay productos en esta categoría</h3>
                        <p>Actualmente no tenemos productos disponibles en <?php echo htmlspecialchars($categoria); ?>.</p>
                        <a href="index.php" class="btn-primary" style="background: var(--primary); color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; display: inline-block;">
                            Ver todas las categorías
                        </a>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Incluir el JavaScript del buscador -->
    <script src="js/buscar.js"></script>

    <script>
        // Función para actualizar badge del carrito
        function updateCartBadge() {
            <?php if($usuario_id): ?>
            fetch('api/obtener_carrito.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const cartBadge = document.getElementById('cartBadge');
                        const total = data.total_items || 0;
                        cartBadge.textContent = total;
                        
                        if (total > 0) {
                            cartBadge.style.display = 'inline';
                            cartBadge.classList.add('pulse');
                            setTimeout(() => cartBadge.classList.remove('pulse'), 300);
                        } else {
                            cartBadge.style.display = 'none';
                        }
                    }
                })
                .catch(error => console.error('Error:', error));
            <?php endif; ?>
        }

        // Estilo para animación pulse
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.3); }
                100% { transform: scale(1); }
            }
        `;
        document.head.appendChild(style);
    </script>

</body>
</html>