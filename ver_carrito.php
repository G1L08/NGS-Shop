<?php
session_start();
require 'config/db.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['user_id'];
$carrito_items = [];
$subtotal_final = 0;
$tasa_iva = 0.16; // 16% de IVA

// Obtener contador del carrito para el badge
$stmt_cart = $pdo->prepare("SELECT SUM(cantidad) as total FROM carrito WHERE usuario_id = ?");
$stmt_cart->execute([$usuario_id]);
$row_cart = $stmt_cart->fetch(PDO::FETCH_ASSOC);
$total_items = $row_cart['total'] ?? 0;

// Consultar productos del carrito incluyendo el STOCK actual
$sql = "SELECT c.id AS carrito_id, c.cantidad, p.id AS producto_id, p.nombre, p.precio, p.precio_mayorista, p.minimo_mayorista, p.imagen, p.stock 
        FROM carrito c 
        JOIN productos p ON c.producto_id = p.id 
        WHERE c.usuario_id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$usuario_id]);
$carrito_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener rol del usuario para mostrar información adicional
$stmt_rol = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ?");
$stmt_rol->execute([$usuario_id]);
$usuario_info = $stmt_rol->fetch(PDO::FETCH_ASSOC);
$rol_usuario = $usuario_info['rol'] ?? 'cliente';
$nombre_usuario = $_SESSION['nombre'] ?? '';
$rol_actual = $_SESSION['user_rol'] ?? $_SESSION['rol'] ?? '';
$es_staff = in_array($rol_actual, ['admin', 'dueño', 'dueno']);

// Calcular subtotales y determinar precio aplicado
foreach ($carrito_items as &$item) {
    $precio_minorista = $item['precio'];
    $precio_mayorista = $item['precio_mayorista'] ?? $precio_minorista * 0.8;
    $minimo_mayorista = $item['minimo_mayorista'] ?? 5;
    
    $es_precio_mayorista = ($item['cantidad'] >= $minimo_mayorista);
    $precio_aplicar = $es_precio_mayorista ? $precio_mayorista : $precio_minorista;
    
    $item['precio_aplicado'] = $precio_aplicar;
    $item['es_mayorista'] = $es_precio_mayorista;
    $item['minimo_mayorista'] = $minimo_mayorista;
    $item['precio_minorista'] = $precio_minorista;
    $item['precio_mayorista'] = $precio_mayorista;
    
    $item['subtotal_item'] = $precio_aplicar * $item['cantidad'];
    $subtotal_final += $item['subtotal_item'];
}
unset($item);

// Calcular IVA y Total
$monto_iva = $subtotal_final * $tasa_iva;
$total_final = $subtotal_final + $monto_iva;
$stripeConfig = require_once __DIR__ . '/config/stripe.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tu Carrito | NGS STORE</title>
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

        .page-title { 
            border-bottom: 1px solid var(--border-color); 
            padding-bottom: 20px; 
            margin-bottom: 30px; 
            font-size: 2rem;
            color: var(--text-main);
        }

        /* Indicador del tipo de precio aplicado */
        .precio-tipo-indicator {
            background: var(--bg-card);
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }
        
        .tipo-precio-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            color: var(--text-main);
        }
        
        .precio-badge {
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .badge-minorista {
            background: rgba(59, 130, 246, 0.2);
            color: var(--accent-primary);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        .badge-mayorista {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .precio-explicacion {
            font-size: 0.9rem;
            color: var(--text-sec);
            padding-left: 30px;
            line-height: 1.5;
        }

        /* Tabla */
        .cart-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        .cart-table th { text-align: left; padding: 15px; color: var(--text-sec); border-bottom: 1px solid var(--border-color); }
        .cart-table td { padding: 15px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        
        .product-info { display: flex; align-items: center; gap: 15px; }
        
        .product-img-container { 
            width: 60px; height: 60px; min-width: 60px; 
            background: white; border-radius: 8px; 
            display: flex; justify-content: center; align-items: center; 
            overflow: hidden; 
            border: 1px solid var(--border-color);
        }
        .product-img { width: 100%; height: 100%; object-fit: contain; padding: 2px; }
        
        .price-info-container {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .price-label {
            font-size: 0.8rem;
            color: var(--text-sec);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .price-minorista {
            font-size: 0.9rem;
            color: var(--text-sec);
        }
        
        .price-mayorista {
            font-size: 1rem;
            color: var(--success);
            font-weight: 600;
        }
        
        .price-applied {
            font-size: 1.05rem;
            color: var(--accent-primary);
            font-weight: 600;
        }
        
        .price-applied.mayorista {
            color: var(--success);
        }
        
        .minimo-info {
            font-size: 0.75rem;
            color: #f59e0b;
            background: rgba(245, 158, 11, 0.1);
            padding: 3px 8px;
            border-radius: 4px;
            margin-top: 3px;
            display: inline-block;
        }
        
        .minimo-info.cumplido {
            color: var(--success);
            background: rgba(16, 185, 129, 0.1);
        }
        
        .qty-control { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            background: var(--bg-card); 
            width: fit-content; 
            padding: 5px; 
            border-radius: 8px; 
            border: 1px solid var(--border-color); 
        }
        
        .btn-qty { 
            background: var(--border-color); 
            color: var(--text-main); 
            border: none; 
            width: 30px; 
            height: 30px; 
            border-radius: 4px; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            text-decoration: none; 
            transition: 0.2s; 
        }
        .btn-qty:hover:not(.disabled) { background: #cbd5e1; }
        .btn-qty.disabled { opacity: 0.3; cursor: not-allowed; pointer-events: none; }
        .qty-number { 
            font-weight: bold; 
            width: 20px; 
            text-align: center;
            transition: color 0.3s;
        }

        .item-total {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-main);
        }
        
        .item-total.mayorista {
            color: var(--success);
        }
        
        .ahorro-item {
            font-size: 0.75rem;
            color: var(--success);
            display: block;
            margin-top: 3px;
        }
        
        .btn-delete { 
            color: var(--error); 
            cursor: pointer; 
            text-decoration: none;
            padding: 8px;
            border-radius: 6px;
            transition: 0.2s;
            background: transparent;
            border: none;
            font-size: 1.1rem;
        }
        .btn-delete:hover {
            background: rgba(239, 68, 68, 0.1);
        }
        
        .summary-box {
            background: var(--bg-card);
            padding: 30px;
            border-radius: 16px;
            width: 100%;
            max-width: 400px;
            margin-left: auto;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 1.1rem;
            color: var(--text-main);
        }

        .summary-row.iva-row {
            color: var(--text-sec);
            font-size: 1rem;
            border-top: 1px dashed var(--border-color);
            padding-top: 15px;
            margin-top: 15px;
        }

        .summary-row.total {
            border-top: 1px solid var(--border-color);
            padding-top: 20px;
            margin-top: 20px;
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-main);
        }
        
        .ahorro-total-info {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            display: none;
        }
        
        .ahorro-total-info.show {
            display: block;
        }
        
        .ahorro-total-text {
            color: var(--success);
            font-size: 0.95rem;
            font-weight: 600;
        }

        .btn-checkout { 
            background: var(--accent-primary); 
            color: white; 
            padding: 15px; 
            border-radius: 12px; 
            text-decoration: none; 
            font-size: 1.1rem; 
            font-weight: 600;
            display: block; 
            text-align: center;
            margin-top: 25px; 
            transition: 0.3s;
            border: none;
            width: 100%;
            cursor: pointer;
        }
        .btn-checkout:hover { 
            background: #2c5282; 
            transform: translateY(-2px);
        }

        .stock-warning { 
            font-size: 0.75rem; 
            color: #f59e0b; 
            display: block; 
            margin-top: 5px; 
        }
        
        .sugerencia-aumento {
            font-size: 0.75rem;
            color: var(--success);
            background: rgba(16, 185, 129, 0.1);
            padding: 3px 8px;
            border-radius: 4px;
            margin-top: 5px;
            display: inline-block;
        }
        
        .btn-qty.loading {
            position: relative;
            color: transparent;
        }
        
        .btn-qty.loading::after {
            content: '';
            position: absolute;
            width: 14px;
            height: 14px;
            border: 2px solid var(--text-main);
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
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
        
        .cart-notification.error {
            background: var(--error);
        }
        
        .cart-notification.show {
            transform: translateY(0);
            opacity: 1;
        }

        .empty-cart {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-sec);
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .empty-cart i {
            font-size: 4rem;
            opacity: 0.3;
            margin-bottom: 20px;
            color: var(--text-main);
        }

        .empty-cart h2 {
            color: var(--text-main);
            margin-bottom: 10px;
        }

        .btn-home {
            color: var(--accent-primary);
            text-decoration: none;
            padding: 10px 20px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 8px;
            display: inline-block;
            margin-top: 15px;
            transition: 0.2s;
        }

        .btn-home:hover {
            background: rgba(59, 130, 246, 0.2);
        }

        /* Footer */
        footer { 
            text-align: center; 
            padding: 40px; 
            color: var(--text-sec); 
            border-top: 1px solid var(--border-color); 
            margin-top: 50px; 
            background: var(--bg-card);
        }

        @media (max-width: 768px) {
            .cart-table {
                font-size: 0.9rem;
            }
            
            .cart-table td {
                padding: 10px;
            }
            
            .product-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .product-img-container {
                width: 50px;
                height: 50px;
                min-width: 50px;
            }
            
            .summary-box {
                max-width: 100%;
                margin-left: 0;
                margin-top: 30px;
            }
            
            .qty-control {
                flex-direction: column;
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
                    <a href="index.php" class="btn-home">
            <i class="fa-solid fa-arrow-left"></i> Seguir Comprando
        </a>
            </nav>
        </div>
    </header>

    <div class="container">
        <h1 class="page-title">Tu Carrito de Compras</h1>
        
        <div class="precio-tipo-indicator">
            <div class="tipo-precio-info">
                <span>Sistema de precios por cantidad:</span>
            </div>
            <div class="precio-explicacion">
                • <strong>Precio Minorista:</strong> Para compras menores a la cantidad mínima<br>
                • <strong>Precio Mayorista:</strong> Se aplica automáticamente cuando alcanzas la cantidad mínima
            </div>
        </div>

        <?php if (count($carrito_items) > 0): 
            $ahorro_total = 0;
            $productos_mayorista = 0;
            foreach ($carrito_items as $item) {
                if ($item['es_mayorista']) {
                    $productos_mayorista++;
                    $subtotal_minorista_item = $item['precio_minorista'] * $item['cantidad'];
                    $ahorro_total += ($subtotal_minorista_item - $item['subtotal_item']);
                }
            }
        ?>
        
            <div id="ahorroTotalInfo" class="ahorro-total-info <?php echo ($ahorro_total > 0) ? 'show' : ''; ?>">
                <div class="ahorro-total-text">
                    <i class="fa-solid fa-piggy-bank"></i> 
                    ¡Estás ahorrando $<?php echo number_format($ahorro_total, 2); ?> 
                    con precios mayoristas en <?php echo $productos_mayorista; ?> producto(s)!
                </div>
            </div>

            <table class="cart-table" id="cartTable">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Precio</th>
                        <th>Cantidad</th>
                        <th>Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="cartBody">
                    <?php foreach ($carrito_items as $item): 
                        $img_db = $item['imagen'];
                        $img_final = 'https://via.placeholder.com/60?text=IMG';

                        if (!empty($img_db)) {
                            if (strpos($img_db, 'uploads/') === 0) {
                                $img_final = $img_db;
                            } else {
                                $img_final = 'uploads/productos/' . $img_db;
                            }
                        }
                        
                        $es_mayorista = $item['es_mayorista'];
                        $precio_aplicado = $item['precio_aplicado'];
                        $precio_minorista = $item['precio_minorista'];
                        $precio_mayorista = $item['precio_mayorista'];
                        $minimo_mayorista = $item['minimo_mayorista'];
                        $cantidad_actual = $item['cantidad'];
                        $alcanzo_limite = ($cantidad_actual >= $item['stock']);
                        $falta_para_mayorista = max(0, $minimo_mayorista - $cantidad_actual);
                        
                        $ahorro_item = 0;
                        if($es_mayorista) {
                            $subtotal_minorista_item = $precio_minorista * $cantidad_actual;
                            $ahorro_item = $subtotal_minorista_item - $item['subtotal_item'];
                        }
                    ?>
                        <tr data-carrito-id="<?php echo $item['carrito_id']; ?>" data-producto-id="<?php echo $item['producto_id']; ?>">
                            <td>
                                <div class="product-info">
                                    <div class="product-img-container">
                                        <img src="<?php echo htmlspecialchars($img_final); ?>" class="product-img" alt="Foto" onerror="this.src='https://via.placeholder.com/60?text=Error'">
                                    </div>
                                    <div>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($item['nombre']); ?></div>
                                        <?php if($alcanzo_limite): ?>
                                            <span class="stock-warning">
                                                <i class="fa-solid fa-exclamation-triangle"></i> 
                                                Máx. disponible: <?php echo $item['stock']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <div class="price-info-container">
                                    <div class="price-label">
                                        <span>Minorista:</span>
                                        <span class="price-minorista">$<?php echo number_format($precio_minorista, 2); ?></span>
                                    </div>
                                    <div class="price-label">
                                        <span>Mayorista:</span>
                                        <span class="price-mayorista">$<?php echo number_format($precio_mayorista, 2); ?></span>
                                    </div>
                                    <div style="margin-top: 8px;">
                                        <span class="price-applied <?php echo $es_mayorista ? 'mayorista' : ''; ?>">
                                            <i class="fa-solid fa-tag"></i> 
                                            Aplicado: $<?php echo number_format($precio_aplicado, 2); ?>
                                        </span>
                                        <div class="minimo-info <?php echo $es_mayorista ? 'cumplido' : ''; ?>">
                                            <?php if($es_mayorista): ?>
                                                <i class="fa-solid fa-check-circle"></i> 
                                                Mayorista (mín. <?php echo $minimo_mayorista; ?> unidades)
                                            <?php else: ?>
                                                <i class="fa-solid fa-info-circle"></i> 
                                                Agrega <?php echo $falta_para_mayorista; ?> más para precio mayorista
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <div class="qty-control">
                                    <button class="btn-qty btn-restar" data-accion="restar">
                                        <i class="fa-solid fa-minus" style="font-size: 0.8rem;"></i>
                                    </button>

                                    <span class="qty-number"><?php echo $cantidad_actual; ?></span>

                                    <button class="btn-qty btn-sumar <?php echo $alcanzo_limite ? 'disabled' : ''; ?>" 
                                            data-accion="sumar"
                                            <?php echo $alcanzo_limite ? 'disabled' : ''; ?>>
                                        <i class="fa-solid fa-plus" style="font-size: 0.8rem;"></i>
                                    </button>
                                </div>
                                
                                <?php if(!$es_mayorista && $falta_para_mayorista > 0 && $falta_para_mayorista <= ($item['stock'] - $cantidad_actual)): ?>
                                <div class="sugerencia-aumento">
                                    <i class="fa-solid fa-lightbulb"></i> 
                                    Agrega <?php echo $falta_para_mayorista; ?> más y ahorra 
                                    $<?php echo number_format((($precio_minorista - $precio_mayorista) * ($cantidad_actual + $falta_para_mayorista)), 2); ?>
                                </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="item-total <?php echo $es_mayorista ? 'mayorista' : ''; ?>">
                                    $<?php echo number_format($item['subtotal_item'], 2); ?>
                                </div>
                                <?php if($es_mayorista && $ahorro_item > 0): ?>
                                <div class="ahorro-item">
                                    <i class="fa-solid fa-arrow-down"></i> 
                                    Ahorro: $<?php echo number_format($ahorro_item, 2); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn-delete" onclick="eliminarProducto(<?php echo $item['carrito_id']; ?>)">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div id="summaryBox" class="summary-box">
                <?php if($ahorro_total > 0): ?>
                <div style="background: rgba(16, 185, 129, 0.1); padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                    <div style="color: var(--success); font-weight: 600; font-size: 0.95rem;">
                        <i class="fa-solid fa-trophy"></i> 
                        ¡Felicidades! Estás obteniendo precios mayoristas
                    </div>
                </div>
                <?php endif; ?>

                <div class="summary-row">
                    <span>Subtotal</span>
                    <span id="subtotalDisplay" style="font-weight: 600;">$<?php echo number_format($subtotal_final, 2); ?></span>
                </div>
                
                <div class="summary-row iva-row">
                    <span>IVA (16%)</span>
                    <span id="ivaDisplay">$<?php echo number_format($monto_iva, 2); ?></span>
                </div>

                <div class="summary-row total">
                    <span>Total a Pagar</span>
                    <span id="totalDisplay" style="color: var(--success);">$<?php echo number_format($total_final, 2); ?></span>
                </div>

                <button id="checkout-button" class="btn-checkout">
                    <i class="fa-solid fa-credit-card" style="margin-right: 8px;"></i> 
                    Proceder al Pago
                </button>
                
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px dashed var(--border-color); font-size: 0.85rem; color: var(--text-sec);">
                    <i class="fa-solid fa-receipt"></i> 
                    Precios incluyen IVA del 16%. Total calculado automáticamente según cantidades.
                </div>
            </div>

        <?php else: ?>
            <div class="empty-cart">
                <i class="fa-solid fa-cart-arrow-down"></i>
                <h2>Tu carrito está vacío</h2>
                <p>Parece que no has añadido productos aún.</p>
                <a href="index.php" class="btn-home">
                    <i class="fa-solid fa-arrow-left"></i> Volver a la tienda
                </a>
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <p>&copy; <?= date('Y') ?> NetGuard Systems. Todos los derechos reservados.</p>
        <p style="font-size: 0.9rem; margin-top: 10px;">Distribuidor autorizado de productos de seguridad y tecnología</p>
    </footer>

    <div id="cartNotification" class="cart-notification">
        <i class="fa-solid fa-check-circle"></i>
        <span id="notificationMessage"></span>
    </div>

    <script>
        function showNotification(message, isSuccess = true) {
            const notification = document.getElementById('cartNotification');
            const messageSpan = document.getElementById('notificationMessage');
            
            messageSpan.textContent = message;
            notification.className = 'cart-notification' + (isSuccess ? '' : ' error');
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }

        function actualizarColoresCantidad() {
            document.querySelectorAll('.qty-number').forEach(function(element) {
                const row = element.closest('tr');
                const precioAplicado = row.querySelector('.price-applied');
                
                if (precioAplicado && precioAplicado.classList.contains('mayorista')) {
                    element.style.color = 'var(--success)';
                } else {
                    element.style.color = 'var(--accent-primary)';
                }
            });
        }

        function actualizarResumen(data) {
            document.getElementById('subtotalDisplay').textContent = '$' + data.subtotal.toFixed(2);
            document.getElementById('ivaDisplay').textContent = '$' + data.iva.toFixed(2);
            document.getElementById('totalDisplay').textContent = '$' + data.total.toFixed(2);
            
            const ahorroDiv = document.querySelector('.ahorro-total-info');
            if (data.ahorro_total > 0) {
                if (ahorroDiv) {
                    ahorroDiv.classList.add('show');
                    ahorroDiv.querySelector('.ahorro-total-text').innerHTML = 
                        '<i class="fa-solid fa-piggy-bank"></i> ¡Estás ahorrando $' + 
                        data.ahorro_total.toFixed(2) + ' con precios mayoristas en ' + 
                        data.productos_mayorista + ' producto(s)!';
                }
            } else {
                if (ahorroDiv) ahorroDiv.classList.remove('show');
            }
            
            // Actualizar badge del carrito
            if (data.total_items !== undefined) {
                const cartBadge = document.getElementById('cartBadge');
                if (cartBadge) {
                    cartBadge.textContent = data.total_items;
                    if (data.total_items > 0) {
                        cartBadge.style.display = 'inline';
                        cartBadge.classList.add('pulse');
                        setTimeout(() => cartBadge.classList.remove('pulse'), 300);
                    } else {
                        cartBadge.style.display = 'none';
                    }
                }
            }
        }

        function actualizarFila(row, data) {
            // Actualizar precio aplicado
            const precioApplied = row.querySelector('.price-applied');
            const minimoInfo = row.querySelector('.minimo-info');
            const itemTotal = row.querySelector('.item-total');
            const ahorroItem = row.querySelector('.ahorro-item');
            const qtyNumber = row.querySelector('.qty-number');
            const btnSumar = row.querySelector('.btn-sumar');
            
            // Actualizar cantidad
            qtyNumber.textContent = data.cantidad;
            
            // Actualizar precio aplicado
            precioApplied.textContent = 'Aplicado: $' + data.precio_aplicado.toFixed(2);
            if (data.es_mayorista) {
                precioApplied.classList.add('mayorista');
                qtyNumber.style.color = 'var(--success)';
            } else {
                precioApplied.classList.remove('mayorista');
                qtyNumber.style.color = 'var(--accent-primary)';
            }
            
            // Actualizar info de mínimo
            if (data.es_mayorista) {
                minimoInfo.innerHTML = '<i class="fa-solid fa-check-circle"></i> Mayorista (mín. ' + data.minimo + ' unidades)';
                minimoInfo.classList.add('cumplido');
            } else {
                minimoInfo.innerHTML = '<i class="fa-solid fa-info-circle"></i> Agrega ' + data.faltan + ' más para precio mayorista';
                minimoInfo.classList.remove('cumplido');
            }
            
            // Actualizar total del item
            itemTotal.textContent = '$' + data.subtotal_item.toFixed(2);
            if (data.es_mayorista) {
                itemTotal.classList.add('mayorista');
            } else {
                itemTotal.classList.remove('mayorista');
            }
            
            // Actualizar ahorro del item
            if (data.ahorro_item > 0) {
                if (!ahorroItem) {
                    const newAhorro = document.createElement('div');
                    newAhorro.className = 'ahorro-item';
                    newAhorro.innerHTML = '<i class="fa-solid fa-arrow-down"></i> Ahorro: $' + data.ahorro_item.toFixed(2);
                    itemTotal.parentNode.appendChild(newAhorro);
                } else {
                    ahorroItem.innerHTML = '<i class="fa-solid fa-arrow-down"></i> Ahorro: $' + data.ahorro_item.toFixed(2);
                }
            } else {
                if (ahorroItem) ahorroItem.remove();
            }
            
            // Actualizar botón sumar
            if (data.alcanzo_limite) {
                btnSumar.classList.add('disabled');
                btnSumar.disabled = true;
            } else {
                btnSumar.classList.remove('disabled');
                btnSumar.disabled = false;
            }
            
            // Actualizar sugerencia de aumento
            const sugerencia = row.querySelector('.sugerencia-aumento');
            if (!data.es_mayorista && data.faltan > 0 && data.faltan <= (data.stock_disponible - data.cantidad)) {
                if (!sugerencia) {
                    const newSugerencia = document.createElement('div');
                    newSugerencia.className = 'sugerencia-aumento';
                    newSugerencia.innerHTML = '<i class="fa-solid fa-lightbulb"></i> Agrega ' + data.faltan + ' más y ahorra $' + data.ahorro_posible.toFixed(2);
                    row.querySelector('td:nth-child(3)').appendChild(newSugerencia);
                } else {
                    sugerencia.innerHTML = '<i class="fa-solid fa-lightbulb"></i> Agrega ' + data.faltan + ' más y ahorra $' + data.ahorro_posible.toFixed(2);
                }
            } else {
                if (sugerencia) sugerencia.remove();
            }
        }

        function eliminarProducto(carritoId) {
            if (!confirm('¿Eliminar este producto del carrito?')) return;
            
            fetch('api/eliminar_carrito_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ carrito_id: carritoId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const row = document.querySelector(`tr[data-carrito-id="${carritoId}"]`);
                    row.remove();
                    
                    actualizarResumen(data);
                    showNotification('Producto eliminado del carrito');
                    
                    if (document.querySelectorAll('#cartBody tr').length === 0) {
                        location.reload(); // Recargar para mostrar carrito vacío
                    }
                } else {
                    showNotification('Error: ' + data.error, false);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error de conexión', false);
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            actualizarColoresCantidad();
            
            // Botones de sumar/restar
            document.querySelectorAll('.btn-restar, .btn-sumar').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    if (this.classList.contains('disabled') || this.disabled) return;
                    
                    const row = this.closest('tr');
                    const carritoId = row.dataset.carritoId;
                    const accion = this.dataset.accion;
                    
                    // Deshabilitar botones temporalmente
                    const originalHtml = this.innerHTML;
                    this.classList.add('loading');
                    this.disabled = true;
                    
                    fetch('api/actualizar_carrito_ajax.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            carrito_id: carritoId,
                            accion: accion
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            actualizarFila(row, data);
                            actualizarResumen(data);
                            showNotification('Carrito actualizado');
                        } else {
                            showNotification('Error: ' + data.error, false);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('Error de conexión', false);
                    })
                    .finally(() => {
                        this.classList.remove('loading');
                        this.innerHTML = originalHtml;
                        this.disabled = false;
                    });
                });
            });
        });
    </script>

   <!-- Stripe.js -->
    <script src="https://js.stripe.com/v3/"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const checkoutButton = document.getElementById('checkout-button');
        
        if (checkoutButton) {
            checkoutButton.addEventListener('click', function() {
                // Deshabilitar botón
                checkoutButton.disabled = true;
                checkoutButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Procesando...';
                
                // Crear la sesión de pago
                fetch('api/crear_sesion_stripe.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => { throw err; });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    // Usar la clave PÚBLICA para el frontend
                    const stripe = Stripe('<?php echo $stripeConfig['public_key']; ?>');
                    return stripe.redirectToCheckout({ sessionId: data.id });
                })
                .then(result => {
                    if (result.error) {
                        alert(result.error.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al procesar el pago: ' + (error.message || 'Intenta de nuevo'));
                    checkoutButton.disabled = false;
                    checkoutButton.innerHTML = '<i class="fa-solid fa-credit-card"></i> Proceder al Pago';
                });
            });
        }
    });
    </script>

</body>
</html>