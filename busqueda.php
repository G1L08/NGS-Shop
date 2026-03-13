<?php
session_start();
require 'config/db.php';

// 1. Obtener término de búsqueda
$busqueda = trim($_GET['q'] ?? '');
$categoria_filtro = $_GET['categoria'] ?? '';
$pagina_actual = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$productos_por_pagina = 20;
$offset = ($pagina_actual - 1) * $productos_por_pagina;

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

// 3. Construir condiciones de búsqueda
$condiciones = ["estado = 'activo'"];
$params = [];

if (!empty($busqueda)) {
    // Dividir búsqueda en palabras clave
    $palabras = explode(' ', $busqueda);
    $condiciones_busqueda = [];
    
    foreach ($palabras as $palabra) {
        $palabra = trim($palabra);
        if (!empty($palabra)) {
            $condiciones_busqueda[] = "(nombre LIKE ? OR descripcion LIKE ? OR marca LIKE ? OR modelo LIKE ? OR categoria LIKE ?)";
            $term = "%$palabra%";
            array_push($params, $term, $term, $term, $term, $term);
        }
    }
    
    if (!empty($condiciones_busqueda)) {
        $condiciones[] = "(" . implode(" AND ", $condiciones_busqueda) . ")";
    }
}

if (!empty($categoria_filtro)) {
    $condiciones[] = "categoria = ?";
    $params[] = $categoria_filtro;
}

$where_sql = " WHERE " . implode(" AND ", $condiciones);

// 4. Obtener categorías para el filtro
$stmt_categorias = $pdo->prepare("SELECT DISTINCT categoria FROM productos WHERE estado = 'activo' ORDER BY categoria");
$stmt_categorias->execute();
$categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);

// 5. Obtener TOTAL de productos
$count_sql = "SELECT COUNT(*) FROM productos" . $where_sql;
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$total_productos = $stmt_count->fetchColumn();
$total_paginas = ceil($total_productos / $productos_por_pagina);

// 6. Obtener productos de la página actual
$sql = "SELECT * FROM productos 
        $where_sql 
        ORDER BY 
            CASE 
                WHEN stock > 0 THEN 0 
                ELSE 1 
            END,
            CASE 
                WHEN nombre LIKE ? THEN 0
                ELSE 1
            END,
            id DESC";

// Añadir parámetro para ordenamiento por relevancia si hay búsqueda
$order_params = [];
if (!empty($busqueda)) {
    $term_principio = $busqueda . '%';
    array_unshift($params, $term_principio);
}

$sql .= " LIMIT $offset, $productos_por_pagina";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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

// Sugerencias de búsqueda relacionadas
$sugerencias = [];
if (!empty($busqueda) && $total_productos == 0) {
    $terminos_relacionados = [
        'teclado' => 'teclado lcd',
        'lcd' => 'teclado lcd',
        'alarma' => 'panel alarma',
        'panel' => 'panel control',
        'dsc' => 'dsc teclado',
        'power' => 'fuente poder',
        'bateria' => 'bateria respaldo',
        'camara' => 'camara seguridad',
        'sensor' => 'sensor movimiento',
        'contacto' => 'contacto magnetico',
        'magnético' => 'contacto magnetico'
    ];
    
    foreach ($terminos_relacionados as $key => $value) {
        if (stripos($busqueda, $key) !== false) {
            $sugerencias[$value] = $value;
        }
    }
    
    if (empty($sugerencias)) {
        $sugerencias = [
            'teclado lcd',
            'panel alarma',
            'sensor movimiento',
            'contacto magnetico',
            'dsc teclado',
            'camara seguridad',
            'fuente poder'
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar: <?php echo htmlspecialchars($busqueda ?: 'Todos los productos'); ?> | NGS</title>
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

        /* Search Header */
        .search-header {
            background: linear-gradient(135deg, var(--bg-darker), var(--bg-dark));
            padding: 40px 0;
            border-bottom: 1px solid var(--card-border);
        }
        
        .search-title {
            font-size: 2rem;
            font-weight: 700;
            color: white;
            margin: 0 0 10px 0;
        }
        
        .search-stats {
            color: var(--text-dim);
            font-size: 1rem;
            margin-bottom: 20px;
        }
        
        .search-stats strong {
            color: var(--primary);
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

        /* Filtros */
        .filters-section {
            background: var(--card-bg);
            padding: 20px 0;
            border-bottom: 1px solid var(--card-border);
        }
        
        .filters-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        
        .filter-label {
            color: var(--text-dim);
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .category-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .category-btn {
            padding: 8px 16px;
            background: var(--bg-darker);
            border: 1px solid var(--card-border);
            border-radius: 6px;
            color: var(--text-light);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .category-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(59, 130, 246, 0.1);
        }
        
        .category-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .clear-filters {
            color: var(--text-dim);
            text-decoration: none;
            font-size: 0.9rem;
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .clear-filters:hover {
            color: var(--primary);
        }

        /* Grid de productos */
        .results-section {
            padding: 40px 0;
        }
        
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
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
            height: 220px;
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

        /* Sugerencias */
        .suggestions-section {
            background: var(--card-bg-light);
            padding: 30px 0;
            border-bottom: 1px solid var(--card-border);
        }
        
        .suggestions-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: white;
            margin-bottom: 15px;
        }
        
        .suggestions-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .suggestion-item {
            background: var(--bg-darker);
            border: 1px solid var(--card-border);
            border-radius: 6px;
            padding: 8px 16px;
            color: var(--text-light);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .suggestion-item:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
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

        /* Sin resultados */
        .no-results {
            text-align: center;
            padding: 60px 20px;
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--card-border);
        }
        
        .no-results i {
            font-size: 4rem;
            color: var(--text-dim);
            margin-bottom: 20px;
        }
        
        .no-results h3 {
            font-size: 1.8rem;
            margin: 0 0 15px 0;
            color: white;
        }
        
        .no-results p {
            color: var(--text-dim);
            font-size: 1.1rem;
            max-width: 500px;
            margin: 0 auto 25px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
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
        }
        
        @media (max-width: 768px) {
            .search-title {
                font-size: 1.5rem;
            }
            
            .filters-container {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .clear-filters {
                margin-left: 0;
                margin-top: 10px;
            }
            
            .results-grid {
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
            .results-grid {
                grid-template-columns: 1fr;
            }
            
            .no-results h3 {
                font-size: 1.4rem;
            }
            
            .suggestions-list {
                justify-content: center;
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
                       value="<?php echo htmlspecialchars($busqueda); ?>"
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
            <span>Búsqueda</span>
            <?php if (!empty($busqueda)): ?>
                <span class="breadcrumb-separator">/</span>
                <span>"<?php echo htmlspecialchars($busqueda); ?>"</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search Header -->
    <div class="search-header">
        <div class="container">
            <h1 class="search-title">
                <?php if (!empty($busqueda)): ?>
                    Resultados para "<?php echo htmlspecialchars($busqueda); ?>"
                <?php else: ?>
                    Todos los productos
                <?php endif; ?>
            </h1>
            
            <div class="search-stats">
                <strong><?php echo $total_productos; ?></strong> producto<?php echo $total_productos != 1 ? 's' : ''; ?> encontrado<?php echo $total_productos != 1 ? 's' : ''; ?>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <?php if (!empty($categorias)): ?>
    <div class="filters-section">
        <div class="container filters-container">
            <span class="filter-label">
                <i class="fa-solid fa-filter"></i> Filtrar por:
            </span>
            
            <div class="category-filters">
                <a href="<?php echo buildUrl(['categoria' => null, 'page' => 1]); ?>" 
                   class="category-btn <?php echo empty($categoria_filtro) ? 'active' : ''; ?>">
                    Todas
                </a>
                
                <?php foreach($categorias as $categoria): ?>
                    <a href="<?php echo buildUrl(['categoria' => $categoria['categoria'], 'page' => 1]); ?>" 
                       class="category-btn <?php echo $categoria_filtro == $categoria['categoria'] ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($categoria['categoria']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <?php if (!empty($busqueda) || !empty($categoria_filtro)): ?>
                <a href="busqueda.php<?php echo !empty($busqueda) ? '?q='.urlencode($busqueda) : ''; ?>" class="clear-filters">
                    <i class="fa-solid fa-times"></i> Limpiar filtro
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sugerencias (si no hay resultados) -->
    <?php if (!empty($sugerencias) && $total_productos == 0): ?>
    <div class="suggestions-section">
        <div class="container">
            <h3 class="suggestions-title">¿Quizás quisiste buscar?</h3>
            <div class="suggestions-list">
                <?php foreach(array_slice($sugerencias, 0, 8) as $sugerencia): ?>
                    <a href="busqueda.php?q=<?php echo urlencode($sugerencia); ?>" class="suggestion-item">
                        <?php echo htmlspecialchars($sugerencia); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Resultados -->
    <section class="results-section">
        <div class="container">
            <?php if (!empty($productos)): ?>
                <div class="results-grid">
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
                <!-- Sin resultados -->
                <div class="no-results">
                    <i class="fa-solid fa-search"></i>
                    <h3>No encontramos resultados</h3>
                    <p>
                        <?php if (!empty($busqueda) && !empty($categoria_filtro)): ?>
                            No hay productos que coincidan con "<strong><?php echo htmlspecialchars($busqueda); ?></strong>" 
                            en la categoría "<strong><?php echo htmlspecialchars($categoria_filtro); ?></strong>"
                        <?php elseif (!empty($busqueda)): ?>
                            No hay productos que coincidan con "<strong><?php echo htmlspecialchars($busqueda); ?></strong>"
                        <?php elseif (!empty($categoria_filtro)): ?>
                            No hay productos en la categoría "<strong><?php echo htmlspecialchars($categoria_filtro); ?></strong>"
                        <?php else: ?>
                            No hay productos disponibles
                        <?php endif; ?>
                    </p>
                    <a href="index.php" class="btn-primary">
                        Ver todos los productos
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

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

        // Estilo para animación pulse si no existe
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