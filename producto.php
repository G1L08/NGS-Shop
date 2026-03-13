<?php
session_start();
include __DIR__ . '/config/db.php';

// 1. VALIDACIÓN DE ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id = (int)$_GET['id'];

// 2. OBTENER DATOS DEL PRODUCTO
$stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
$stmt->execute([$id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    die("<div style='background:#0f172a; color:white; text-align:center; padding:50px; height:100vh; font-family:sans-serif;'>
            <h2>Producto no encontrado</h2>
            <p>El producto que buscas no existe o ha sido eliminado.</p>
            <a href='index.php' style='color:#3b82f6; text-decoration:none;'>Volver a la tienda</a>
         </div>");
}

// 3. OBTENER IMÁGENES DEL PRODUCTO
$stmt_imagenes = $pdo->prepare("SELECT * FROM producto_imagenes WHERE producto_id = ? ORDER BY 
    CASE clasificacion 
        WHEN 'principal' THEN 1 
        WHEN 'tecnica' THEN 2 
        WHEN 'contenido' THEN 3 
        WHEN 'adicional' THEN 4 
    END, orden");
$stmt_imagenes->execute([$id]);
$imagenes = $stmt_imagenes->fetchAll(PDO::FETCH_ASSOC);

// Organizar imágenes por clasificación
$imagenes_por_clasificacion = [
    'principal' => [],
    'tecnica' => [],
    'contenido' => [],
    'adicional' => []
];

foreach ($imagenes as $imagen) {
    $clasificacion = $imagen['clasificacion'];
    $imagenes_por_clasificacion[$clasificacion][] = $imagen;
}

// 4. VERIFICAR SI EL USUARIO ESTÁ LOGUEADO Y OBTENER DATOS
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

// 5. OBTENER PRODUCTOS RELACIONADOS (misma categoría)
$stmt_relacionados = $pdo->prepare("SELECT * FROM productos WHERE categoria = ? AND id != ? AND estado = 'activo' AND stock > 0 LIMIT 4");
$stmt_relacionados->execute([$producto['categoria'], $id]);
$productos_relacionados = $stmt_relacionados->fetchAll(PDO::FETCH_ASSOC);

// Obtener imagen principal para relacionados
foreach ($productos_relacionados as &$rel) {
    $stmt_img_rel = $pdo->prepare("SELECT url_imagen FROM producto_imagenes WHERE producto_id = ? AND clasificacion = 'principal' ORDER BY orden LIMIT 1");
    $stmt_img_rel->execute([$rel['id']]);
    $img_rel = $stmt_img_rel->fetch(PDO::FETCH_ASSOC);
    $rel['imagen_principal'] = $img_rel ? $img_rel['url_imagen'] : 'https://via.placeholder.com/300x300?text=Sin+Imagen';
}
unset($rel);

// 6. OBTENER IMAGEN PRINCIPAL
$imagen_principal = '';
if (!empty($imagenes_por_clasificacion['principal'])) {
    $imagen_principal = $imagenes_por_clasificacion['principal'][0]['url_imagen'];
} elseif (!empty($imagenes)) {
    $imagen_principal = $imagenes[0]['url_imagen'];
} else {
    $imagen_principal = "https://via.placeholder.com/600x600?text=Sin+Imagen";
}

// 7. CALCULAR PRECIOS
$precio_minorista = $producto['precio_minorista'] ?? $producto['precio'] ?? 0;
$precio_mayorista = $producto['precio_mayorista'] ?? $precio_minorista * 0.8;
$minimo_mayorista = $producto['minimo_mayorista'] ?? 2;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($producto['nombre']); ?> | NGS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== VARIABLES ===== */
        :root {
            --primary: #2b6cb0;
            --primary-dark: #1e4b8b;
            --primary-light: #4299e1;
            --secondary: #8b5cf6;
            --success: #22c55e;
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
            
            /* Header variables */
            --bg-primary: #061325;
            --bg-secondary: rgba(6, 19, 37, 0.95);
            --accent-primary: #2b6cb0;
            --accent-secondary: #3b82f6;
            --bg-body: #0f172a;
            --border-color: #334155;
            --error: #ef4444;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            margin: 0;
            line-height: 1.6;
        }

        /* ===== HEADER ===== */
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
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

        .logo-text i {
            color: var(--accent-primary);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 15px;
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
            display: flex;
            align-items: center;
            gap: 8px;
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
            background: var(--primary-dark);
        }

        .cart-btn {
            position: relative;
            color: white;
            text-decoration: none;
            font-size: 1.2rem;
            padding: 8px;
            border-radius: 6px;
            transition: 0.2s;
            display: flex;
            align-items: center;
        }

        .cart-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
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

        /* ===== ESTILOS PARA EL BUSCADOR CON MENÚ DESPLEGABLE ===== */
        .search-box {
            position: relative;
            display: flex;
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 50px;
            overflow: visible;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .search-box input {
            flex: 1;
            padding: 10px 20px;
            border: none;
            outline: none;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            background: white;
            color: #1e293b;
            border-radius: 50px 0 0 50px;
        }

        .search-box input::placeholder {
            color: #64748b;
        }

        .search-box button {
            background: var(--accent-primary);
            color: white;
            border: none;
            padding: 0 20px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            border-radius: 0 50px 50px 0;
            white-space: nowrap;
        }

        .search-box button:hover {
            background: var(--primary-dark);
        }

        /* Estilos para el menú desplegable de resultados */
        .search-results-container {
            position: absolute;
            top: calc(100% + 10px);
            left: 0;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            border: 1px solid #e2e8f0;
        }

        .search-results-container.show {
            display: block;
        }

        .search-result-item {
            display: flex;
            align-items: center;
            gap: 12px;
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
            background: var(--accent-primary);
            color: white;
            border-radius: 0 0 12px 12px;
        }

        .search-result-item.view-all:hover {
            background: var(--primary-dark);
        }

        .search-result-item.view-all i,
        .search-result-item.view-all span,
        .search-result-item.view-all strong {
            color: white;
        }

        .search-result-item.no-results {
            color: #64748b;
            justify-content: center;
            padding: 20px;
        }

        .result-image {
            width: 45px;
            height: 45px;
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
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .result-title strong {
            color: var(--accent-primary);
        }

        .result-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
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
            color: var(--accent-primary);
            white-space: nowrap;
        }

        /* ===== BREADCRUMB ===== */
        .breadcrumb {
            padding: 15px 0;
            color: var(--text-dim);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .breadcrumb a {
            color: var(--text-dim);
            text-decoration: none;
            transition: color 0.3s;
        }

        .breadcrumb a:hover {
            color: var(--accent-primary);
        }

        .breadcrumb-separator {
            opacity: 0.5;
        }

        /* ===== LAYOUT PRINCIPAL ===== */
        .main-container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px 20px 60px;
        }

        .product-main-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 60px;
        }

        /* Panel izquierdo - Galería */
        .gallery-panel {
            position: sticky;
            top: 100px;
            height: fit-content;
        }

        .main-image-container {
            background-color: var(--card-bg);
            padding: 40px;
            border-radius: 12px;
            border: 1px solid var(--card-border);
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            height: 450px;
            width: 100%;
            margin-bottom: 20px;
            cursor: pointer;
            position: relative;
        }

        .main-image-container::after {
            content: '🔍';
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: rgba(0,0,0,0.6);
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 1rem;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .main-image-container:hover::after {
            opacity: 1;
        }

        .main-image-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            transition: transform 0.3s;
        }

        .main-image-container:hover img {
            transform: scale(1.05);
        }

        /* Miniaturas */
        .thumbnail-carousel {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            padding: 10px 0;
            scrollbar-width: thin;
            scrollbar-color: var(--accent-primary) var(--card-border);
        }

        .thumbnail-carousel::-webkit-scrollbar {
            height: 6px;
        }

        .thumbnail-carousel::-webkit-scrollbar-track {
            background: var(--card-border);
            border-radius: 10px;
        }

        .thumbnail-carousel::-webkit-scrollbar-thumb {
            background: var(--accent-primary);
            border-radius: 10px;
        }

        .thumbnail-item {
            flex-shrink: 0;
            width: 80px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: var(--card-bg);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 5px;
        }

        .thumbnail-item:hover {
            border-color: var(--accent-primary);
            transform: translateY(-3px);
        }

        .thumbnail-item.active {
            border-color: var(--accent-primary);
            border-width: 3px;
        }

        .thumbnail-item img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        /* Panel derecho - Información */
        .info-panel {
            padding: 20px 0;
        }

        .product-header {
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 1px solid var(--card-border);
        }

        .product-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 10px 0;
            line-height: 1.2;
            color: white;
        }

        .product-subtitle {
            color: var(--text-dim);
            font-size: 1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .product-model {
            font-family: 'Monaco', 'Consolas', monospace;
            background: var(--bg-darker);
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            color: var(--text-light);
            border: 1px solid var(--card-border);
        }

        /* Precios duales */
        .price-section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid var(--card-border);
        }

        .price-container {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }

        .price-row {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .badge-precio {
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 700;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .badge-minorista {
            background: rgba(43, 108, 176, 0.2);
            color: var(--accent-primary);
            border: 1px solid rgba(43, 108, 176, 0.3);
        }

        .badge-mayorista {
            background: rgba(34, 197, 94, 0.2);
            color: var(--success);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .price-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-dim);
            min-width: 60px;
        }

        .price-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: white;
            letter-spacing: -0.5px;
        }

        .price-minorista .price-value {
            color: var(--accent-primary);
        }

        .price-mayorista .price-value {
            color: var(--success);
        }

        .price-tax {
            font-size: 0.9rem;
            color: var(--text-dim);
            font-weight: 500;
            margin-top: 5px;
        }

        .stock-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            background-color: rgba(34, 197, 94, 0.15);
            color: var(--success);
            border: 1px solid rgba(34, 197, 94, 0.3);
            margin-bottom: 25px;
        }

        .stock-status.out {
            background-color: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            border-color: rgba(239, 68, 68, 0.3);
        }

        .purchase-section {
            margin-top: 30px;
        }

        .qty-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--text-light);
            font-size: 1rem;
        }

        .qty-selector {
            width: 100px;
            background: var(--bg-darker);
            border: 2px solid var(--card-border);
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            margin-bottom: 20px;
        }

        .qty-selector:focus {
            outline: none;
            border-color: var(--accent-primary);
        }

        .btn-add {
            background: linear-gradient(135deg, var(--accent-primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            min-width: 200px;
        }

        .btn-add:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-add:disabled {
            background: #475569;
            cursor: not-allowed;
            transform: none;
        }

        /* Descripción */
        .description-section {
            color: var(--text-light);
            line-height: 1.8;
            font-size: 1rem;
            margin-bottom: 40px;
            padding-bottom: 40px;
            border-bottom: 1px solid var(--card-border);
        }

        /* Características */
        .features-section {
            margin-bottom: 50px;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin: 0 0 25px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .features-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
        }

        .feature-item {
            color: var(--text-light);
            padding: 12px 0;
            border-bottom: 1px dashed var(--card-border);
            position: relative;
            padding-left: 20px;
        }

        .feature-item:before {
            content: "•";
            color: var(--accent-primary);
            position: absolute;
            left: 0;
            font-size: 1.2rem;
        }

        /* Especificaciones técnicas */
        .specs-section {
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: 12px;
            border: 1px solid var(--card-border);
            margin-bottom: 30px;
        }

        .specs-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }

        .specs-images {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .spec-image-main {
            background-color: var(--bg-darker);
            padding: 30px;
            border-radius: 8px;
            border: 1px solid var(--card-border);
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            height: 280px;
            width: 100%;
            cursor: pointer;
            position: relative;
            transition: all 0.3s;
        }

        .spec-image-main:hover {
            border-color: var(--accent-primary);
        }

        .spec-image-main::after {
            content: '🔍';
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: rgba(0,0,0,0.6);
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 1rem;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .spec-image-main:hover::after {
            opacity: 1;
        }

        .spec-image-main img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .spec-image-thumbnails {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 5px 0;
        }

        .spec-thumbnail {
            flex-shrink: 0;
            width: 70px;
            height: 70px;
            border-radius: 6px;
            overflow: hidden;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
            background-color: var(--bg-darker);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 5px;
        }

        .spec-thumbnail:hover {
            border-color: var(--accent-primary);
        }

        .spec-thumbnail.active {
            border-color: var(--accent-primary);
            border-width: 3px;
        }

        .spec-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .specs-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .spec-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--card-border);
        }

        .spec-label {
            color: var(--text-dim);
            font-weight: 500;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .spec-value {
            color: var(--text-light);
            font-weight: 600;
            font-size: 0.95rem;
            text-align: right;
        }

        /* Contenido del paquete */
        .package-section {
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: 12px;
            border: 1px solid var(--card-border);
            margin-bottom: 30px;
        }

        .package-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }

        .package-images {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .package-image-main {
            background-color: var(--bg-darker);
            padding: 30px;
            border-radius: 8px;
            border: 1px solid var(--card-border);
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            height: 280px;
            width: 100%;
            cursor: pointer;
            position: relative;
            transition: all 0.3s;
        }

        .package-image-main:hover {
            border-color: var(--success);
        }

        .package-image-main::after {
            content: '🔍';
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: rgba(0,0,0,0.6);
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 1rem;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .package-image-main:hover::after {
            opacity: 1;
        }

        .package-image-main img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .package-image-thumbnails {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 5px 0;
        }

        .package-thumbnail {
            flex-shrink: 0;
            width: 70px;
            height: 70px;
            border-radius: 6px;
            overflow: hidden;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
            background-color: var(--bg-darker);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 5px;
        }

        .package-thumbnail:hover {
            border-color: var(--success);
        }

        .package-thumbnail.active {
            border-color: var(--success);
            border-width: 3px;
        }

        .package-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .package-list {
            color: var(--text-light);
            line-height: 1.8;
            padding-left: 20px;
        }

        .package-list li {
            margin-bottom: 12px;
            padding-left: 10px;
            position: relative;
        }

        .package-list li::before {
            content: "📦";
            position: absolute;
            left: -20px;
            color: var(--success);
        }

        /* Productos relacionados */
        .related-section {
            margin-top: 40px;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .related-product {
            background-color: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--card-border);
            overflow: hidden;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
        }

        .related-product:hover {
            border-color: var(--accent-primary);
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
        }

        .related-product-img {
            height: 180px;
            width: 100%;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: var(--bg-darker);
            position: relative;
        }

        .related-product-img img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            padding: 15px;
            transition: transform 0.3s;
        }

        .related-product:hover .related-product-img img {
            transform: scale(1.05);
        }

        .related-product-info {
            padding: 15px;
        }

        .related-product-title {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 8px;
            color: white;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 2.8em;
        }

        .related-product-brand {
            color: var(--text-dim);
            font-size: 0.85rem;
            margin-bottom: 10px;
        }

        .related-price-container {
            display: flex;
            flex-direction: column;
            gap: 3px;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid var(--card-border);
        }

        .related-price-minorista {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-dim);
            display: flex;
            justify-content: space-between;
        }

        .related-price-minorista .price-value {
            font-size: 0.95rem;
            color: var(--accent-primary);
            font-weight: 700;
        }

        .related-price-mayorista {
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
        }

        .related-price-mayorista .price-value {
            color: var(--success);
            font-weight: 800;
        }

        .badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--accent-primary);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            z-index: 1;
        }

        .badge.agotado {
            background: var(--danger);
        }

        /* Modal */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
            border: 2px solid var(--accent-primary);
            border-radius: 8px;
        }

        .close-modal {
            position: absolute;
            top: 30px;
            right: 40px;
            color: white;
            font-size: 3rem;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            z-index: 1002;
        }

        .close-modal:hover {
            color: var(--accent-secondary);
        }

        .modal-nav {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 20px;
            background: rgba(255,255,255,0.1);
            padding: 10px 20px;
            border-radius: 50px;
            backdrop-filter: blur(5px);
        }

        .modal-nav button {
            background: transparent;
            border: 1px solid white;
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            cursor: pointer;
            transition: 0.3s;
        }

        .modal-nav button:hover {
            background: var(--accent-primary);
            border-color: var(--accent-primary);
        }

        .click-to-view {
            position: absolute;
            bottom: 15px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.2);
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }

        .main-image-container:hover .click-to-view,
        .spec-image-main:hover .click-to-view,
        .package-image-main:hover .click-to-view {
            opacity: 1;
        }

        /* Notificación flotante */
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

        /* Footer */
        footer {
            text-align: center;
            padding: 40px;
            color: var(--text-dim);
            border-top: 1px solid var(--card-border);
            margin-top: 50px;
            background: var(--bg-darker);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .container {
                padding: 0 20px;
            }

            .product-main-layout {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .gallery-panel {
                position: static;
            }

            .specs-content,
            .package-content {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                align-items: stretch;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
                width: 100%;
            }

            .search-box {
                max-width: 100%;
            }

            .nombre-usuario {
                display: none;
            }

            .product-title {
                font-size: 1.6rem;
            }

            .main-image-container {
                height: 350px;
                padding: 20px;
            }

            .price-row {
                flex-wrap: wrap;
            }

            .price-value {
                font-size: 1.3rem;
            }

            .btn-add {
                width: 100%;
            }

            .specs-section,
            .package-section {
                padding: 20px;
            }

            .spec-image-main,
            .package-image-main {
                height: 220px;
            }
        }

        @media (max-width: 480px) {
            .product-title {
                font-size: 1.4rem;
            }

            .main-image-container {
                height: 280px;
            }

            .thumbnail-item {
                width: 70px;
                height: 70px;
            }

            .specs-list,
            .package-list {
                font-size: 0.9rem;
            }

            .modal-nav {
                flex-direction: column;
                gap: 10px;
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
            
            <!-- BUSCADOR CON MENÚ DESPLEGABLE -->
            <div class="search-box">
                <input type="text" 
                       id="searchInput"
                       placeholder="Buscar por nombre, descripción, marca o modelo..." 
                       autocomplete="off">
                <button type="button">
                    <i class="fa-solid fa-search"></i> Buscar
                </button>
            </div>
            
            <nav class="nav-links">
                <?php if ($usuario_id): ?>
                    <a href="account/perfil.php" class="btn btn-login">
                        <i class="fa-solid fa-user-circle"></i>
                        <span class="nombre-usuario"><?php echo htmlspecialchars($nombre_usuario); ?></span>
                    </a>

                    <a href="ver_carrito.php" class="cart-btn">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <span class="cart-badge"><?php echo $total_items; ?></span>
                    </a>

                    <?php if ($es_staff): ?>
                        <a href="admin/index.php" class="btn btn-admin">
                            <i class="fa-solid fa-cog"></i> Administración
                        </a>
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

    <div class="main-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="index.php">Inicio</a>
            <span class="breadcrumb-separator">/</span>
            <a href="categoria.php?cat=<?php echo urlencode($producto['categoria']); ?>">
                <?php echo htmlspecialchars($producto['categoria']); ?>
            </a>
            <span class="breadcrumb-separator">/</span>
            <span><?php echo htmlspecialchars($producto['nombre']); ?></span>
        </div>

        <!-- Layout principal -->
        <div class="product-main-layout">
            <!-- Panel izquierdo - Galería -->
            <div class="gallery-panel">
                <div class="main-image-container" onclick="openImageModal('<?php echo $imagen_principal; ?>')">
                    <img id="mainImage" src="<?php echo $imagen_principal; ?>" alt="<?php echo htmlspecialchars($producto['nombre']); ?>">
                    <div class="click-to-view">
                        <i class="fa-solid fa-magnifying-glass-plus"></i> Haz clic para ampliar
                    </div>
                </div>

                <?php if(!empty($imagenes_por_clasificacion['principal'])): ?>
                <div class="thumbnail-carousel">
                    <?php foreach($imagenes_por_clasificacion['principal'] as $index => $imagen): ?>
                    <div class="thumbnail-item <?php echo $index === 0 ? 'active' : ''; ?>"
                         onclick="changeMainImage('<?php echo $imagen['url_imagen']; ?>', this)">
                        <img src="<?php echo $imagen['url_imagen']; ?>"
                             alt="<?php echo htmlspecialchars($producto['nombre']); ?> - Imagen <?php echo $index + 1; ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Panel derecho - Información -->
            <div class="info-panel">
                <!-- Encabezado del producto -->
                <div class="product-header">
                    <h1 class="product-title"><?php echo htmlspecialchars($producto['nombre']); ?></h1>
                    <div class="product-subtitle">
                        <span><i class="fa-regular fa-building"></i> <?php echo htmlspecialchars($producto['marca']); ?></span>
                        <span class="product-model"><?php echo htmlspecialchars($producto['modelo']); ?></span>
                    </div>
                </div>

                <!-- Precios duales -->
                <?php if($usuario_id): ?>
                <div class="price-section">
                    <div class="price-container">
                        <!-- Precio Minorista -->
                        <div class="price-row price-minorista">
                            <span class="badge-precio badge-minorista"><i class="fa-solid fa-user"></i> Minorista</span>
                            <span class="price-label">Precio:</span>
                            <span class="price-value">$<?php echo number_format($precio_minorista, 2); ?></span>
                        </div>

                        <!-- Precio Mayorista -->
                        <div class="price-row price-mayorista">
                            <span class="badge-precio badge-mayorista"><i class="fa-solid fa-users"></i> Mayorista</span>
                            <span class="price-label">Precio:</span>
                            <span class="price-value">$<?php echo number_format($precio_mayorista, 2); ?></span>
                        </div>
                        
                        <?php if($minimo_mayorista > 1): ?>
                        <div class="price-tax">*Precio mayorista aplica desde <?php echo $minimo_mayorista; ?> unidades</div>
                        <?php endif; ?>
                    </div>

                    <div class="price-tax">IVA incluido</div>

                    <div class="stock-status <?php echo ($producto['stock'] > 0) ? '' : 'out'; ?>">
                        <i class="fa-solid <?php echo ($producto['stock'] > 0) ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                        <?php echo ($producto['stock'] > 0) ? 'Disponible' : 'Agotado'; ?>
                    </div>

                    <?php if($producto['stock'] > 0): ?>
                    <div class="purchase-section">
                        <form action="agregar_carrito.php" method="POST" id="addToCartForm">
                            <input type="hidden" name="id" value="<?php echo $producto['id']; ?>">

                            <label class="qty-label">Cantidad:</label>
                            <select name="cantidad" class="qty-selector">
                                <?php
                                $max_val = min($producto['stock'], 10);
                                for($i = 1; $i <= $max_val; $i++):
                                ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($i == 1) ? 'selected' : ''; ?>>
                                        <?php echo $i; ?> unidad<?php echo ($i > 1) ? 'es' : ''; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <br>
                            <button type="submit" class="btn-add" id="addToCartBtn">
                                <i class="fa-solid fa-cart-plus"></i> Agregar al Carrito
                            </button>
                        </form>
                    </div>
                    <?php else: ?>
                    <button type="button" disabled class="btn-add">
                        <i class="fa-solid fa-ban"></i> Producto Agotado
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Descripción -->
                <div class="description-section">
                    <?php echo nl2br(htmlspecialchars($producto['descripcion'])); ?>
                </div>

                <!-- Características principales -->
                <?php if(!empty($producto['caracteristica_especial'])): ?>
                <div class="features-section">
                    <h2 class="section-title">
                        <i class="fa-solid fa-star" style="color: var(--accent-primary);"></i> Características principales
                    </h2>

                    <div class="features-list">
                        <?php
                        $features = explode(',', $producto['caracteristica_especial']);
                        foreach($features as $feature):
                            $feature = trim($feature);
                            if(!empty($feature)):
                        ?>
                            <div class="feature-item"><?php echo htmlspecialchars($feature); ?></div>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Especificaciones técnicas -->
        <?php if(!empty($imagenes_por_clasificacion['tecnica']) || !empty($producto['especificaciones']) || !empty($producto['resolucion']) || !empty($producto['proteccion']) || !empty($producto['conexion'])): ?>
        <div class="specs-section">
            <h2 class="section-title">
                <i class="fa-solid fa-microchip"></i> Especificaciones técnicas
            </h2>

            <div class="specs-content">
                <!-- Imágenes técnicas -->
                <?php if(!empty($imagenes_por_clasificacion['tecnica'])): ?>
                <div class="specs-images">
                    <?php
                    $imagen_tecnica_principal = $imagenes_por_clasificacion['tecnica'][0]['url_imagen'];
                    ?>
                    <div class="spec-image-main" onclick="openImageModal('<?php echo $imagen_tecnica_principal; ?>')">
                        <img id="specMainImage" src="<?php echo $imagen_tecnica_principal; ?>" alt="Imagen técnica principal">
                        <div class="click-to-view">
                            <i class="fa-solid fa-magnifying-glass-plus"></i> Haz clic para ampliar
                        </div>
                    </div>

                    <?php if(count($imagenes_por_clasificacion['tecnica']) > 1): ?>
                    <div class="spec-image-thumbnails">
                        <?php foreach($imagenes_por_clasificacion['tecnica'] as $index => $imagen): ?>
                        <div class="spec-thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
                             onclick="changeSpecImage('<?php echo $imagen['url_imagen']; ?>', this)">
                            <img src="<?php echo $imagen['url_imagen']; ?>"
                                 alt="Imagen técnica <?php echo $index + 1; ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Lista de especificaciones -->
                <div class="specs-list">
                    <?php if(!empty($producto['resolucion'])): ?>
                    <div class="spec-item">
                        <span class="spec-label"><i class="fa-solid fa-eye"></i> Resolución</span>
                        <span class="spec-value"><?php echo htmlspecialchars($producto['resolucion']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($producto['proteccion'])): ?>
                    <div class="spec-item">
                        <span class="spec-label"><i class="fa-solid fa-shield-halved"></i> Protección</span>
                        <span class="spec-value"><?php echo htmlspecialchars($producto['proteccion']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($producto['conexion'])): ?>
                    <div class="spec-item">
                        <span class="spec-label"><i class="fa-solid fa-wifi"></i> Conexión</span>
                        <span class="spec-value"><?php echo htmlspecialchars($producto['conexion']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($producto['meses_garantia'])): ?>
                    <div class="spec-item">
                        <span class="spec-label"><i class="fa-solid fa-file-shield"></i> Garantía</span>
                        <span class="spec-value"><?php echo htmlspecialchars($producto['meses_garantia']); ?> meses</span>
                    </div>
                    <?php endif; ?>

                    <!-- Especificaciones detalladas -->
                    <?php if(!empty($producto['especificaciones'])): ?>
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--card-border);">
                        <div style="color: var(--text-light); line-height: 1.8; font-size: 0.95rem;">
                            <i class="fa-solid fa-file-lines" style="color: var(--accent-primary);"></i>
                            <?php echo nl2br(htmlspecialchars($producto['especificaciones'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Contenido del paquete -->
        <?php if(!empty($imagenes_por_clasificacion['contenido']) || !empty($producto['contenido_incluido'])): ?>
        <div class="package-section">
            <h2 class="section-title">
                <i class="fa-solid fa-box-open"></i> Contenido del paquete
            </h2>

            <div class="package-content">
                <!-- Imágenes de contenido -->
                <?php if(!empty($imagenes_por_clasificacion['contenido'])): ?>
                <div class="package-images">
                    <?php
                    $imagen_contenido_principal = $imagenes_por_clasificacion['contenido'][0]['url_imagen'];
                    ?>
                    <div class="package-image-main" onclick="openImageModal('<?php echo $imagen_contenido_principal; ?>')">
                        <img id="packageMainImage" src="<?php echo $imagen_contenido_principal; ?>" alt="Imagen de contenido principal">
                        <div class="click-to-view">
                            <i class="fa-solid fa-magnifying-glass-plus"></i> Haz clic para ampliar
                        </div>
                    </div>

                    <?php if(count($imagenes_por_clasificacion['contenido']) > 1): ?>
                    <div class="package-image-thumbnails">
                        <?php foreach($imagenes_por_clasificacion['contenido'] as $index => $imagen): ?>
                        <div class="package-thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
                             onclick="changePackageImage('<?php echo $imagen['url_imagen']; ?>', this)">
                            <img src="<?php echo $imagen['url_imagen']; ?>"
                                 alt="Imagen de contenido <?php echo $index + 1; ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Lista de contenido incluido -->
                <?php if(!empty($producto['contenido_incluido'])): ?>
                <ul class="package-list">
                    <?php
                    $content_items = explode("\n", $producto['contenido_incluido']);
                    foreach($content_items as $item):
                        $item = trim($item);
                        if(!empty($item)):
                    ?>
                        <li><?php echo htmlspecialchars($item); ?></li>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Productos relacionados -->
        <?php if(!empty($productos_relacionados)): ?>
        <div class="related-section">
            <h2 class="section-title">
                <i class="fa-solid fa-link"></i> Productos relacionados
            </h2>

            <div class="related-grid">
                <?php foreach($productos_relacionados as $relacionado):
                    // Calcular precios para producto relacionado
                    $precio_minorista_rel = $relacionado['precio_minorista'] ?? $relacionado['precio'] ?? 0;
                    $precio_mayorista_rel = $relacionado['precio_mayorista'] ?? $precio_minorista_rel * 0.8;
                ?>
                <a href="producto.php?id=<?php echo $relacionado['id']; ?>" class="related-product">
                    <?php if($relacionado['stock'] > 0): ?>
                        <span class="badge"><i class="fa-solid fa-check"></i> En stock</span>
                    <?php else: ?>
                        <span class="badge agotado"><i class="fa-solid fa-exclamation"></i> Agotado</span>
                    <?php endif; ?>
                    <div class="related-product-img">
                        <img src="<?php echo $relacionado['imagen_principal']; ?>" alt="<?php echo htmlspecialchars($relacionado['nombre']); ?>">
                    </div>
                    <div class="related-product-info">
                        <h3 class="related-product-title"><?php echo htmlspecialchars($relacionado['nombre']); ?></h3>
                        <div class="related-product-brand">
                            <i class="fa-regular fa-building"></i> <?php echo htmlspecialchars($relacionado['marca']); ?> - <?php echo htmlspecialchars($relacionado['modelo']); ?>
                        </div>
                        <?php if($usuario_id): ?>
                        <div class="related-price-container">
                            <div class="related-price-minorista">
                                <span><i class="fa-solid fa-user"></i> Minorista:</span>
                                <span class="price-value">$<?php echo number_format($precio_minorista_rel, 2); ?></span>
                            </div>
                            <div class="related-price-mayorista">
                                <span><i class="fa-solid fa-users"></i> Mayorista:</span>
                                <span class="price-value">$<?php echo number_format($precio_mayorista_rel, 2); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <footer>
        <p>&copy; <?= date('Y') ?> NetGuard Systems. Todos los derechos reservados.</p>
        <p style="font-size: 0.9rem; margin-top: 10px;">Distribuidor autorizado de productos de seguridad y tecnología</p>
    </footer>

    <!-- Modal para imagen grande -->
    <div id="imageModal" class="image-modal">
        <span class="close-modal" onclick="closeImageModal()">&times;</span>
        <img class="modal-content" id="modalImage">
        <div class="modal-nav">
            <button onclick="previousImage()"><i class="fa-solid fa-chevron-left"></i> Anterior</button>
            <button onclick="nextImage()">Siguiente <i class="fa-solid fa-chevron-right"></i></button>
        </div>
    </div>

    <!-- Notificación flotante -->
    <div id="cartNotification" class="cart-notification">
        <i class="fa-solid fa-check-circle"></i>
        <span id="notificationMessage">Producto agregado al carrito</span>
    </div>

    <!-- Incluir el JavaScript del buscador -->
    <script src="js/buscar.js"></script>

    <script>
        // Array con todas las imágenes para navegación en modal
        const allImages = [
            <?php
            $all_image_urls = [];
            foreach($imagenes as $img) {
                $all_image_urls[] = "'" . $img['url_imagen'] . "'";
            }
            echo implode(',', $all_image_urls);
            ?>
        ];
        let currentImageIndex = 0;

        // Función para cambiar la imagen principal
        function changeMainImage(src, element) {
            document.getElementById('mainImage').src = src;

            document.querySelectorAll('.thumbnail-item').forEach(item => {
                item.classList.remove('active');
            });

            element.classList.add('active');

            // Actualizar índice actual
            currentImageIndex = Array.from(document.querySelectorAll('.thumbnail-item')).indexOf(element);
        }

        // Función para cambiar imagen técnica
        function changeSpecImage(src, element) {
            document.getElementById('specMainImage').src = src;

            document.querySelectorAll('.spec-thumbnail').forEach(item => {
                item.classList.remove('active');
            });

            element.classList.add('active');
        }

        // Función para cambiar imagen de contenido
        function changePackageImage(src, element) {
            document.getElementById('packageMainImage').src = src;

            document.querySelectorAll('.package-thumbnail').forEach(item => {
                item.classList.remove('active');
            });

            element.classList.add('active');
        }

        // Función para abrir imagen en modal
        function openImageModal(src) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');

            // Encontrar índice de la imagen actual
            currentImageIndex = allImages.indexOf(src);
            if (currentImageIndex === -1) currentImageIndex = 0;

            modal.style.display = 'flex';
            modalImg.src = src;
            document.body.style.overflow = 'hidden';
        }

        // Función para imagen anterior
        function previousImage() {
            if (allImages.length === 0) return;
            currentImageIndex = (currentImageIndex - 1 + allImages.length) % allImages.length;
            document.getElementById('modalImage').src = allImages[currentImageIndex];
        }

        // Función para imagen siguiente
        function nextImage() {
            if (allImages.length === 0) return;
            currentImageIndex = (currentImageIndex + 1) % allImages.length;
            document.getElementById('modalImage').src = allImages[currentImageIndex];
        }

        // Función para cerrar modal
        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Cerrar modal con ESC y navegar con flechas
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            } else if (e.key === 'ArrowLeft' && document.getElementById('imageModal').style.display === 'flex') {
                previousImage();
            } else if (e.key === 'ArrowRight' && document.getElementById('imageModal').style.display === 'flex') {
                nextImage();
            }
        });

        // Cerrar modal haciendo clic fuera de la imagen
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });

        // Validación del formulario de carrito
        const addToCartForm = document.getElementById('addToCartForm');
        if (addToCartForm) {
            addToCartForm.addEventListener('submit', function(e) {
                const btn = document.getElementById('addToCartBtn');
                if (btn.disabled) {
                    e.preventDefault();
                    return false;
                }

                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Agregando...';
                btn.disabled = true;

                return true;
            });
        }

        // Carrusel automático de imágenes principales (si hay más de una)
        <?php if(!empty($imagenes_por_clasificacion['principal']) && count($imagenes_por_clasificacion['principal']) > 1): ?>
        let autoRotateInterval;
        const thumbnails = document.querySelectorAll('.thumbnail-item');

        function startAutoRotate() {
            autoRotateInterval = setInterval(function() {
                if (!document.getElementById('imageModal').style.display === 'flex') {
                    currentImageIndex = (currentImageIndex + 1) % thumbnails.length;
                    const thumbnail = thumbnails[currentImageIndex];
                    const imgSrc = thumbnail.querySelector('img').src;
                    changeMainImage(imgSrc, thumbnail);
                }
            }, 5000);
        }

        function stopAutoRotate() {
            clearInterval(autoRotateInterval);
        }

        // Iniciar rotación automática
        startAutoRotate();

        // Pausar al hacer hover sobre la galería
        document.querySelector('.gallery-panel').addEventListener('mouseenter', stopAutoRotate);
        document.querySelector('.gallery-panel').addEventListener('mouseleave', startAutoRotate);
        <?php endif; ?>

        // Función para mostrar notificación
        function showNotification(message, isSuccess = true) {
            const notification = document.getElementById('cartNotification');
            const notificationMessage = document.getElementById('notificationMessage');

            notificationMessage.textContent = message;
            notification.style.background = isSuccess ? '#22c55e' : '#ef4444';
            notification.classList.add('show');

            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }

        // Manejar errores de carga de imágenes
        document.querySelectorAll('img').forEach(img => {
            img.addEventListener('error', function() {
                if (!this.src.includes('placeholder')) {
                    this.src = 'https://via.placeholder.com/600x600?text=Error+de+Imagen';
                }
            });
        });
    </script>

</body>
</html>