<?php
session_start();
include __DIR__ . '/../config/db.php';

// 1. SEGURIDAD: Solo admin y dueño
$rol = $_SESSION['user_rol'] ?? '';
if ($rol !== 'admin' && $rol !== 'dueño' && $rol !== 'dueno') {
    header('Location: ../index.php'); exit;
}

// 2. LÓGICA DE FILTROS
$where = [];
$params = [];
$having = []; 

// A) Filtro Cliente
$filtro_cliente = $_GET['cliente'] ?? '';
if (!empty($filtro_cliente)) {
    $where[] = "u.nombre LIKE ?";
    $params[] = "%$filtro_cliente%";
}

// B) Filtro Fechas (Desde - Hasta)
$fecha_desde = $_GET['desde'] ?? '';
$fecha_hasta = $_GET['hasta'] ?? '';

if (!empty($fecha_desde)) {
    $where[] = "DATE(v.fecha) >= ?";
    $params[] = $fecha_desde;
}
if (!empty($fecha_hasta)) {
    $where[] = "DATE(v.fecha) <= ?";
    $params[] = $fecha_hasta;
}

// C) Filtro Producto (HAVING porque es un campo calculado con GROUP_CONCAT)
$filtro_producto = $_GET['producto'] ?? '';

// 3. CONSTRUCCIÓN DE LA CONSULTA - MODIFICADA para incluir precios
$query = "
    SELECT 
        v.id, 
        v.fecha, 
        v.total, 
        v.subtotal,
        v.iva,
        u.nombre AS cliente, 
        u.email,
        GROUP_CONCAT(
            CONCAT(
                d.cantidad, 'x ', 
                p.nombre, 
                ' ($', FORMAT(d.precio_unitario, 2), ' c/u)',
                ' = $', FORMAT(d.cantidad * d.precio_unitario, 2)
            ) 
            SEPARATOR '<br>'
        ) AS productos_detalle
    FROM ventas v
    JOIN usuarios u ON v.usuario_id = u.id
    JOIN detalle_ventas d ON v.id = d.venta_id
    JOIN productos p ON d.producto_id = p.id
";

if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}

$query .= " GROUP BY v.id";

if (!empty($filtro_producto)) {
    $query .= " HAVING productos_detalle LIKE ?";
    $params[] = "%$filtro_producto%";
}

$query .= " ORDER BY v.fecha DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas | NGS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- ESTILOS GENERALES --- */
        :root { 
            --primary: #007bff; 
            --primary-hover: #0056b3;
            --bg-body: #f3f4f6; 
            --text-dark: #1f2937; 
            --text-gray: #6b7280; 
            --white: #ffffff;
            --money-green: #059669;
            --money-bg: #d1fae5;
            --subtotal-color: #7c3aed;
            --subtotal-bg: #ede9fe;
            --iva-color: #dc2626;
            --iva-bg: #fee2e2;
            --neto-color: #0284c7;
            --neto-bg: #e0f2fe;
        }

        body { 
            margin: 0; 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg-body); 
            display: flex; 
            height: 100vh; 
            overflow: hidden; 
        }

        /* SIDEBAR */
        .sidebar { 
            width: 260px; 
            background: var(--white); 
            border-right: 1px solid #e5e7eb; 
            display: flex; 
            flex-direction: column; 
            padding: 25px 20px; 
            flex-shrink: 0; 
        }

        .sidebar h2 { 
            font-size: 1.3rem; 
            font-weight: 700;
            margin-bottom: 35px; 
            color: var(--text-dark); 
            display: flex; align-items: center; gap: 10px;
        }

        .menu-item { 
            display: flex; align-items: center; gap: 12px;
            padding: 12px 15px; margin-bottom: 8px; 
            color: var(--text-gray); text-decoration: none; 
            border-radius: 10px; font-weight: 500; 
            transition: all 0.3s ease; 
        }

        .menu-item:hover { background-color: #f9fafb; color: var(--primary); }
        .menu-item.active { background-color: #eef6ff; color: var(--primary); font-weight: 700; }

        .logout-btn { margin-top: auto; color: #ef4444; font-weight: 600; }
        .logout-btn:hover { background-color: #fef2f2; }

        /* MAIN CONTENT */
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }

        .header-section {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px;
        }

        .page-title { font-size: 1.8rem; font-weight: 700; color: var(--text-dark); margin: 0; }

        /* --- BARRA DE FILTROS --- */
        .filter-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
            border: 1px solid #e5e7eb;
        }

        .filter-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .form-control {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s;
            min-width: 160px;
        }

        .form-control:focus { border-color: var(--primary); }

        .btn-filter {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-filter:hover { background-color: var(--primary-hover); }

        .btn-reset {
            background-color: white;
            color: var(--text-gray);
            border: 1px solid #d1d5db;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: 0.2s;
        }

        .btn-reset:hover { background-color: #f9fafb; color: var(--text-dark); border-color: #9ca3af; }

        /* TABLA DE VENTAS */
        .table-container { 
            background: var(--white); 
            padding: 25px; 
            border-radius: 16px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
        }

        table { width: 100%; border-collapse: collapse; }

        th { 
            text-align: left; 
            padding: 15px; 
            border-bottom: 2px solid #e5e7eb; 
            color: var(--text-gray); 
            font-size: 0.85rem; 
            font-weight: 600;
            text-transform: uppercase;
        }

        td { 
            padding: 20px 15px; 
            border-bottom: 1px solid #f3f4f6; 
            vertical-align: top;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        tr:last-child td { border-bottom: none; }
        
        .badge-id { 
            background: #f3f4f6; color: var(--text-gray); 
            padding: 4px 8px; border-radius: 6px; font-family: monospace; font-size: 0.9rem;
        }

        .price-tag {
            font-weight: 700; color: var(--money-green);
            background: var(--money-bg);
            padding: 6px 12px; border-radius: 20px;
            display: inline-block;
            font-size: 1rem;
        }

        .price-tag-total {
            font-weight: 700; color: var(--money-green);
            background: var(--money-bg);
            padding: 8px 15px; border-radius: 20px;
            display: inline-block;
            font-size: 1.1rem;
            box-shadow: 0 2px 4px rgba(5, 150, 105, 0.1);
        }
        
        .price-tag-iva {
            font-weight: 600; color: var(--iva-color);
            background: var(--iva-bg);
            padding: 4px 10px; border-radius: 12px;
            display: inline-block;
            font-size: 0.9rem;
        }
        
        .price-tag-neto {
            font-weight: 600; color: var(--neto-color);
            background: var(--neto-bg);
            padding: 4px 10px; border-radius: 12px;
            display: inline-block;
            font-size: 0.9rem;
        }

        .client-info strong { display: block; color: var(--text-dark); }
        .client-info small { color: var(--text-gray); font-size: 0.85rem; }
        
        .product-list { 
            line-height: 1.8; 
            color: #4b5563;
            font-size: 0.9rem;
        }
        
        .product-item {
            margin-bottom: 8px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid var(--primary);
        }
        
        .product-name {
            font-weight: 600;
            color: var(--text-dark);
            display: block;
            margin-bottom: 3px;
        }
        
        .product-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 5px;
        }
        
        .unit-price {
            color: var(--text-gray);
            font-size: 0.85rem;
        }
        
        .subtotal {
            font-weight: 600;
            color: var(--subtotal-color);
            background: var(--subtotal-bg);
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.85rem;
        }
        
        /* ESTADÍSTICAS */
        .stats-container {
            background: white;
            padding: 25px;
            border-radius: 16px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border: 1px solid #e5e7eb;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #bae6fd;
        }
        
        .stat-card.total {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border-color: #6ee7b7;
        }
        
        .stat-card.iva {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border-color: #fca5a5;
        }
        
        .stat-card.neto {
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
            border-color: #7dd3fc;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--text-gray);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-dark);
        }
        
        .stat-value.total {
            color: var(--money-green);
        }
        
        .stat-value.iva {
            color: var(--iva-color);
        }
        
        .stat-value.neto {
            color: var(--neto-color);
        }
        
        /* DESGLOSE DE PRECIOS EN TABLA */
        .price-breakdown {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-top: 10px;
        }
        
        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            font-size: 0.85rem;
        }
        
        .price-label {
            color: var(--text-gray);
            font-weight: 500;
        }
        
        .price-amount {
            font-weight: 600;
        }
        
        .price-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 8px 0;
        }
        
        /* ICONOS ESPECÍFICOS */
        .fa-money-bill-wave { color: var(--money-green); }
        .fa-shopping-cart { color: var(--primary); }
        .fa-users { color: #8b5cf6; }
        .fa-receipt { color: var(--neto-color); }
        .fa-scale-balanced { color: var(--iva-color); }
        
        /* RESPONSIVE */
        @media (max-width: 1200px) {
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .form-group {
                width: 100%;
            }
            
            .form-control {
                min-width: 0;
            }
            
            .filter-form > div:last-child {
                flex-direction: row;
                justify-content: flex-start;
            }
            
            th:nth-child(3), td:nth-child(3) {
                max-width: 300px;
            }
        }
        
        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 800px;
            }
        }
        
    </style>
</head>
<body>

    <aside class="sidebar">
        <h2> ADMIN PANEL</h2>
        
        <nav>
            <a href="index.php" class="menu-item">
                <i class="fa-solid fa-house"></i> Inicio
            </a>
            <a href="productos.php" class="menu-item">
                <i class="fa-solid fa-box-open"></i> Productos
            </a>
            <a href="ventas.php" class="menu-item active">
                <i class="fa-solid fa-chart-line"></i> Ventas
            </a>
            <a href="pedidos.php" class="menu-item">
                <i class="fa-solid fa-truck"></i> Pedidos
            </a>
            <?php if($rol === 'dueño' || $rol === 'dueno'): ?>
                <a href="usuarios.php" class="menu-item">
                    <i class="fa-solid fa-users-gear"></i> Usuarios
                </a>
            <?php endif; ?>

            <a href="../index.php" class="menu-item" style="margin-top: 20px; border-top: 1px solid #f3f4f6; padding-top: 20px;">
                 Regresar a Tienda
            </a>
        </nav>

        <a href="../logout.php" class="menu-item logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
        </a>
    </aside>

    <main class="main-content">
        
        <div class="header-section">
            <h1 class="page-title">Historial de Ventas</h1>
        </div>

        <!-- ESTADÍSTICAS -->
        <?php
        // Calcular estadísticas con IVA
        $total_ventas = 0;
        $total_subtotal = 0;
        $total_iva = 0;
        $total_neto = 0;
        $num_ventas = count($ventas);
        $total_productos = 0;
        
        if ($num_ventas > 0) {
            foreach ($ventas as $venta) {
                $total_ventas += $venta['total'];
                $total_subtotal += $venta['subtotal'] ?? ($venta['total'] / 1.16); // Si no hay subtotal, calcular
                $total_iva += $venta['iva'] ?? ($venta['total'] * 0.16 / 1.16); // Si no hay IVA, calcular
                $total_neto += ($venta['subtotal'] ?? ($venta['total'] / 1.16));
            }
            
            // Consulta para contar productos totales vendidos
            $query_productos = "SELECT SUM(cantidad) as total_productos FROM detalle_ventas";
            if (!empty($where)) {
                $query_productos = "
                    SELECT SUM(d.cantidad) as total_productos 
                    FROM ventas v
                    JOIN detalle_ventas d ON v.id = d.venta_id
                    WHERE " . implode(" AND ", $where);
            }
            $stmt_productos = $pdo->prepare($query_productos);
            $stmt_productos->execute(array_slice($params, 0, count($where)));
            $result_productos = $stmt_productos->fetch(PDO::FETCH_ASSOC);
            $total_productos = $result_productos['total_productos'] ?? 0;
        }
        ?>
        
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-label">
                    <i class="fa-solid fa-shopping-cart"></i>
                    <span>VENTAS TOTALES</span>
                </div>
                <div class="stat-value"><?php echo $num_ventas; ?></div>
                <div style="font-size: 0.85rem; color: var(--text-gray); margin-top: 5px;">
                    <?php echo $total_productos; ?> productos vendidos
                </div>
            </div>
            
            <div class="stat-card neto">
                <div class="stat-label">
                    <i class="fa-solid fa-receipt"></i>
                    <span>SUBTOTAL</span>
                </div>
                <div class="stat-value neto">$<?php echo number_format($total_subtotal, 2); ?></div>
                <div style="font-size: 0.85rem; color: var(--text-gray); margin-top: 5px;">
                    Base gravable
                </div>
            </div>
            
            <div class="stat-card iva">
                <div class="stat-label">
                    <i class="fa-solid fa-scale-balanced"></i>
                    <span>IVA (16%)</span>
                </div>
                <div class="stat-value iva">$<?php echo number_format($total_iva, 2); ?></div>
                <div style="font-size: 0.85rem; color: var(--text-gray); margin-top: 5px;">
                    Impuesto por declarar
                </div>
            </div>
            
            <div class="stat-card total">
                <div class="stat-label">
                    <i class="fa-solid fa-money-bill-wave"></i>
                    <span>TOTAL FACTURADO</span>
                </div>
                <div class="stat-value total">$<?php echo number_format($total_ventas, 2); ?></div>
                <div style="font-size: 0.85rem; color: var(--text-gray); margin-top: 5px;">
                    Incluye IVA
                </div>
            </div>
        </div>

        <!-- FILTROS -->
        <div class="filter-container">
            <form method="GET" class="filter-form">
                
                <div class="form-group">
                    <label>Cliente</label>
                    <input type="text" name="cliente" class="form-control" placeholder="Nombre completo..." 
                           value="<?php echo htmlspecialchars($filtro_cliente); ?>">
                </div>

                <div class="form-group">
                    <label>Producto</label>
                    <input type="text" name="producto" class="form-control" placeholder="Ej: Cámara de vigilancia" 
                           value="<?php echo htmlspecialchars($filtro_producto); ?>">
                </div>

                <div class="form-group">
                    <label>Desde</label>
                    <input type="date" name="desde" id="fecha_desde" class="form-control" min="2025-01-01"
                           value="<?php echo htmlspecialchars($fecha_desde); ?>">
                </div>

                <div class="form-group">
                    <label>Hasta</label>
                    <input type="date" name="hasta" id="fecha_hasta" class="form-control" min="2025-01-01"
                           value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                </div>

                <div class="form-group" style="flex-direction: row; gap: 10px; align-self: flex-end;">
                    <button type="submit" class="btn-filter">
                        <i class="fa-solid fa-filter"></i> Filtrar
                    </button>
                    
                    <?php if(!empty($_GET)): ?>
                        <a href="ventas.php" class="btn-reset">
                            <i class="fa-solid fa-xmark"></i> Limpiar
                        </a>
                    <?php endif; ?>
                </div>

            </form>
        </div>

        <!-- TABLA DE VENTAS -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th width="8%">ID Venta</th>
                        <th width="17%">Cliente</th>
                        <th width="40%">Productos (Cantidad × Nombre + Precio)</th>
                        <th width="15%">Fecha</th>
                        <th width="20%" style="text-align: right;">Desglose de Pago</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($ventas)): ?>
                        <?php foreach($ventas as $venta): 
                            // Calcular valores si no existen en BD
                            $subtotal = $venta['subtotal'] ?? ($venta['total'] / 1.16);
                            $iva = $venta['iva'] ?? ($venta['total'] * 0.16 / 1.16);
                            $total = $venta['total'];
                        ?>
                        <tr>
                            <td>
                                <span class="badge-id">#<?php echo str_pad($venta['id'], 4, '0', STR_PAD_LEFT); ?></span>
                            </td>
                            
                            <td class="client-info">
                                <strong><?php echo htmlspecialchars($venta['cliente']); ?></strong>
                                <small><?php echo htmlspecialchars($venta['email']); ?></small>
                            </td>
                            
                            <td class="product-list">
                                <?php 
                                // Parsear la cadena de productos para mejor formato
                                $productos = explode('<br>', $venta['productos_detalle']);
                                $total_items = 0;
                                foreach ($productos as $producto):
                                    if (empty(trim($producto))) continue;
                                    
                                    // Extraer información del formato: "2x Nombre Producto ($50.00 c/u) = $100.00"
                                    preg_match('/^(\d+)x\s+(.+?)\s+\(\$(.+?)\s+c\/u\)\s+=\s+\$(.+?)$/', $producto, $matches);
                                    if (count($matches) === 5):
                                        $cantidad = $matches[1];
                                        $nombre = trim($matches[2]);
                                        $precio_unitario = $matches[3];
                                        $subtotal_producto = $matches[4];
                                        $total_items += (int)$cantidad;
                                ?>
                                    <div class="product-item">
                                        <span class="product-name">
                                            <?php echo $cantidad; ?> × <?php echo htmlspecialchars($nombre); ?>
                                        </span>
                                        <div class="product-details">
                                            <span class="unit-price">$<?php echo $precio_unitario; ?> c/u</span>
                                            <span class="subtotal">$<?php echo $subtotal_producto; ?></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="product-item">
                                        <?php echo htmlspecialchars($producto); ?>
                                    </div>
                                <?php endif; endforeach; ?>
                                
                                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #e5e7eb;">
                                    <small style="color: var(--text-gray); font-size: 0.85rem;">
                                        <i class="fa-solid fa-boxes-stacked"></i> Total: <?php echo $total_items; ?> producto<?php echo $total_items != 1 ? 's' : ''; ?>
                                    </small>
                                </div>
                            </td>
                            
                            <td style="color: var(--text-gray);">
                                <div style="display: flex; flex-direction: column; gap: 5px;">
                                    <div>
                                        <i class="fa-regular fa-calendar"></i> 
                                        <?php echo date('d/m/Y', strtotime($venta['fecha'])); ?>
                                    </div>
                                    <div>
                                        <i class="fa-regular fa-clock"></i>
                                        <?php echo date('H:i', strtotime($venta['fecha'])); ?> hrs
                                    </div>
                                </div>
                            </td>
                            
                            <td style="text-align: right;">
                                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                                    <!-- Desglose de precios -->
                                    <div class="price-breakdown">
                                        <div class="price-row">
                                            <span class="price-label">Subtotal:</span>
                                            <span class="price-amount">$<?php echo number_format($subtotal, 2); ?></span>
                                        </div>
                                        
                                        <div class="price-row">
                                            <span class="price-label">IVA (16%):</span>
                                            <span class="price-amount price-tag-iva">$<?php echo number_format($iva, 2); ?></span>
                                        </div>
                                        
                                        <div class="price-divider"></div>
                                        
                                        <div class="price-row" style="font-weight: 700; font-size: 1rem;">
                                            <span class="price-label">Total:</span>
                                            <span class="price-tag-total">$<?php echo number_format($total, 2); ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Información adicional -->
                                    <div style="text-align: left; width: 100%; margin-top: 10px;">
                                        <div style="font-size: 0.8rem; color: var(--text-gray); display: flex; align-items: center; gap: 5px;">
                                            <i class="fa-solid fa-calculator"></i>
                                            <span>IVA incluido en el total</span>
                                        </div>
                                        <div style="font-size: 0.8rem; color: var(--text-gray); display: flex; align-items: center; gap: 5px; margin-top: 3px;">
                                            <i class="fa-solid fa-percentage"></i>
                                            <span>16% tasa aplicada</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding: 60px; color: var(--text-gray);">
                                <i class="fa-solid fa-filter-circle-xmark fa-3x" style="opacity: 0.2; margin-bottom: 15px;"></i>
                                <p style="font-size: 1.1rem; margin-bottom: 10px;">No hay ventas que coincidan con estos filtros.</p>
                                <p style="font-size: 0.9rem; opacity: 0.8;">Intenta con otros criterios de búsqueda.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const desdeInput = document.getElementById('fecha_desde');
            const hastaInput = document.getElementById('fecha_hasta');

            // Función para actualizar el mínimo de "Hasta"
            function actualizarMinHasta() {
                if(desdeInput.value) {
                    hastaInput.min = desdeInput.value;
                    
                    // Si la fecha seleccionada en 'hasta' es menor que la nueva 'desde', la limpiamos
                    if(hastaInput.value && hastaInput.value < desdeInput.value) {
                        hastaInput.value = desdeInput.value;
                    }
                } else {
                    hastaInput.min = "2025-01-01"; // Default si borran 'desde'
                }
            }

            // Escuchar cambios en 'desde'
            desdeInput.addEventListener('change', actualizarMinHasta);
            
            // Ejecutar al cargar por si ya hay filtros aplicados (persistencia)
            actualizarMinHasta();
            
            // También establecer máximo para "Desde" basado en "Hasta"
            function actualizarMaxDesde() {
                if(hastaInput.value) {
                    desdeInput.max = hastaInput.value;
                    
                    // Si la fecha seleccionada en 'desde' es mayor que la nueva 'hasta', la limpiamos
                    if(desdeInput.value && desdeInput.value > hastaInput.value) {
                        desdeInput.value = hastaInput.value;
                    }
                } else {
                    desdeInput.max = "";
                }
            }
            
            hastaInput.addEventListener('change', actualizarMaxDesde);
            actualizarMaxDesde();
            
            // Resaltar las filas al pasar el mouse
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8fafc';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
        });
    </script>

</body>
</html>