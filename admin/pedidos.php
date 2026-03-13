<?php
session_start();
include __DIR__ . '/../config/db.php';

$rol = $_SESSION['user_rol'] ?? '';

if ($rol !== 'admin' && $rol !== 'dueño' && $rol !== 'dueno') {
    header('Location: ../login.php'); 
    exit;
}

$umbral_stock = 5; 

$stmt_notif = $pdo->prepare("SELECT id, nombre, stock, imagen FROM productos WHERE stock <= ? ORDER BY stock ASC");
$stmt_notif->execute([$umbral_stock]);
$alertas = $stmt_notif->fetchAll(PDO::FETCH_ASSOC);
$total_alertas = count($alertas);

$mes_actual = date('m');
$anio_actual = date('Y');

// Estadísticas de pedidos
$stmt_total_pedidos = $pdo->query("SELECT COUNT(*) FROM ventas");
$total_pedidos = $stmt_total_pedidos->fetchColumn();

$stmt_pendientes = $pdo->query("SELECT COUNT(*) FROM ventas WHERE estatus = 'pendiente'");
$pendientes = $stmt_pendientes->fetchColumn();

$stmt_enviados = $pdo->query("SELECT COUNT(*) FROM ventas WHERE estatus = 'enviado'");
$enviados = $stmt_enviados->fetchColumn();

$stmt_entregados = $pdo->query("SELECT COUNT(*) FROM ventas WHERE estatus = 'entregado'");
$entregados = $stmt_entregados->fetchColumn();

$stmt_ingresos = $pdo->query("SELECT SUM(total) FROM ventas WHERE MONTH(fecha) = $mes_actual AND YEAR(fecha) = $anio_actual");
$ingresos_mes = $stmt_ingresos->fetchColumn() ?: 0;

// Obtener todos los pedidos con información completa
$stmt_pedidos = $pdo->query("
    SELECT 
        v.id,
        v.fecha,
        v.total,
        v.estatus,
        v.tipo_venta,
        u.id as usuario_id,
        u.nombre,
        u.apellido_paterno,
        u.apellido_materno,
        u.email,
        u.telefono,
        u.calle,
        u.num_exterior,
        u.num_interior,
        u.colonia,
        u.ciudad,
        u.estado,
        u.cp,
        (SELECT GROUP_CONCAT(CONCAT(p.nombre, ' (', dv.cantidad, ')') SEPARATOR ' | ') 
         FROM detalle_ventas dv 
         JOIN productos p ON dv.producto_id = p.id 
         WHERE dv.venta_id = v.id) as productos,
        (SELECT COUNT(*) FROM detalle_ventas WHERE venta_id = v.id) as total_productos
    FROM ventas v
    JOIN usuarios u ON v.usuario_id = u.id
    ORDER BY v.fecha DESC
");

$pedidos = $stmt_pedidos->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas por estatus para la gráfica
$stmt_stats = $pdo->query("
    SELECT estatus, COUNT(*) as total 
    FROM ventas 
    GROUP BY estatus
");
$stats_estatus = $stmt_stats->fetchAll(PDO::FETCH_ASSOC);

$stats_data = [
    'pendiente' => 0,
    'pagado' => 0,
    'enviado' => 0,
    'entregado' => 0,
    'cancelado' => 0
];

foreach($stats_estatus as $stat) {
    $stats_data[$stat['estatus']] = $stat['total'];
}

$rol_label = ($rol === 'dueño' || $rol === 'dueno') ? 'DUEÑO' : 'ADMINISTRADOR';
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';

$meses = ["01"=>"Enero", "02"=>"Febrero", "03"=>"Marzo", "04"=>"Abril", "05"=>"Mayo", "06"=>"Junio", 
          "07"=>"Julio", "08"=>"Agosto", "09"=>"Septiembre", "10"=>"Octubre", "11"=>"Noviembre", "12"=>"Diciembre"];
$nombre_mes_actual = $meses[$mes_actual];

// Función para obtener badge de estatus
function getStatusBadge($estatus) {
    $badges = [
        'pendiente' => '<span style="background: #fef3c7; color: #d97706; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;"><i class="fa-regular fa-clock" style="margin-right: 5px;"></i> Pendiente</span>',
        'pagado' => '<span style="background: #dbeafe; color: #3b82f6; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;"><i class="fa-solid fa-check-circle" style="margin-right: 5px;"></i> Pagado</span>',
        'enviado' => '<span style="background: #e0f2fe; color: #0284c7; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;"><i class="fa-solid fa-truck" style="margin-right: 5px;"></i> Enviado</span>',
        'entregado' => '<span style="background: #d1fae5; color: #059669; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;"><i class="fa-solid fa-circle-check" style="margin-right: 5px;"></i> Entregado</span>',
        'cancelado' => '<span style="background: #fee2e2; color: #dc2626; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;"><i class="fa-solid fa-ban" style="margin-right: 5px;"></i> Cancelado</span>'
    ];
    return $badges[$estatus] ?? '<span style="background: #e5e7eb; color: #6b7280;">'.$estatus.'</span>';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos | NGS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root { 
            --primary: #007bff; 
            --primary-hover: #0056b3;
            --bg-body: #f3f4f6; 
            --text-dark: #1f2937; 
            --text-gray: #6b7280; 
            --white: #ffffff;
        }

        body { 
            margin: 0; 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg-body); 
            display: flex; 
            height: 100vh; 
            overflow: hidden; 
        }

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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .menu-item { 
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px; 
            margin-bottom: 8px; 
            color: var(--text-gray); 
            text-decoration: none; 
            border-radius: 10px; 
            font-weight: 500; 
            transition: all 0.3s ease; 
        }

        .menu-item i { width: 20px; font-size: 1.1rem; }

        .menu-item:hover { 
            background-color: #f9fafb; 
            color: var(--primary); 
        }

        .menu-item.active { 
            background-color: #eef6ff; 
            color: var(--primary); 
            font-weight: 700; 
        }

        .logout-btn { 
            margin-top: auto; 
            color: #ef4444; 
            font-weight: 600;
        }
        
        .logout-btn:hover { background-color: #fef2f2; }

        .main-content { 
            flex: 1; 
            padding: 40px; 
            overflow-y: auto; 
        }

        .notification-container { position: relative; cursor: pointer; }
        .bell-icon { font-size: 1.5rem; color: var(--white); transition: 0.3s; }
        
        .badge-alert {
            position: absolute; top: -8px; right: -8px;
            background-color: #ef4444; color: white;
            border-radius: 50%; padding: 2px 7px;
            font-size: 0.75rem; font-weight: 800; border: 2px solid var(--primary);
        }

        .notif-dropdown {
            display: none; position: absolute; right: 0; top: 40px;
            background-color: white; border: 1px solid #e5e7eb;
            width: 300px; border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15); z-index: 1000; overflow: hidden;
        }

        .notification-container:hover .notif-dropdown { display: block; }
        
        .notif-header { 
            background: #f9fafb; padding: 15px; 
            font-weight: 700; color: var(--text-dark); 
            border-bottom: 1px solid #eee; 
            font-size: 0.9rem;
        }

        .notif-item { 
            padding: 15px; border-bottom: 1px solid #f3f4f6; 
            display: flex; align-items: center; gap: 12px; 
            color: var(--text-dark); text-decoration: none; 
            font-size: 0.85rem; transition: 0.2s;
        }

        .notif-item:hover { background-color: #f9fafb; }
        
        .status-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .dot-red { background-color: #ef4444; box-shadow: 0 0 5px #ef4444; }
        .dot-orange { background-color: #f59e0b; box-shadow: 0 0 5px #f59e0b; }

        .welcome-card { 
            background: linear-gradient(135deg, #007bff, #0062cc); 
            color: white; padding: 35px; border-radius: 18px; 
            margin-bottom: 40px; box-shadow: 0 10px 20px rgba(0,123,255,0.2);
        }
        
        .welcome-card h1 { margin: 0; font-size: 2rem; font-weight: 700; }
        .welcome-card p { margin: 8px 0 0; opacity: 0.9; font-size: 1.1rem; }
        .welcome-card b { background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 5px; text-transform: uppercase; font-size: 0.85rem; margin-left: 5px; }

        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px; 
            margin-bottom: 30px;
        }

        .stat-card { 
            background: var(--white); 
            padding: 20px; 
            border-radius: 12px; 
            border: 1px solid #e5e7eb; 
            text-align: center;
            transition: all 0.3s ease; 
        }

        .stat-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        }

        .stat-number { 
            font-size: 2rem; 
            font-weight: 700; 
            color: var(--text-dark);
            margin: 10px 0 5px;
        }

        .stat-label { 
            color: var(--text-gray); 
            font-size: 0.9rem;
            font-weight: 500;
        }

        .stat-icon { 
            font-size: 2rem; 
            margin-bottom: 10px;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-select {
            padding: 10px 15px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            background: white;
            min-width: 150px;
        }

        .search-box {
            flex: 1;
            display: flex;
            gap: 10px;
        }

        .search-box input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
        }

        .search-box button {
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.3s;
        }

        .search-box button:hover {
            background: var(--primary-hover);
        }

        .pedidos-table-container {
            background: white;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        .pedidos-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }

        .pedidos-table th {
            background: #f9fafb;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .pedidos-table td {
            padding: 15px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }

        .pedidos-table tr:hover {
            background: #f9fafb;
        }

        .cliente-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .cliente-nombre {
            font-weight: 600;
            color: var(--text-dark);
        }

        .cliente-contacto {
            font-size: 0.85rem;
            color: var(--text-gray);
        }

        .direccion {
            font-size: 0.85rem;
            color: var(--text-gray);
            max-width: 200px;
        }

        .productos-list {
            font-size: 0.85rem;
            color: var(--text-gray);
            max-width: 250px;
        }

        .productos-list strong {
            color: var(--text-dark);
            display: block;
            margin-bottom: 5px;
        }

        .total-pedido {
            font-weight: 700;
            color: #059669;
            font-size: 1.1rem;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-view {
            background: #f3f4f6;
            color: var(--text-gray);
        }

        .btn-view:hover {
            background: #e5e7eb;
        }

        .btn-edit {
            background: #eef6ff;
            color: var(--primary);
        }

        .btn-edit:hover {
            background: #dbeafe;
        }

        .estatus-select {
            padding: 5px 10px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: 'Inter', sans-serif;
        }

        .section-title {
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 20px;
            font-size: 1.2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .export-btn {
            background: white;
            border: 1px solid #e5e7eb;
            padding: 8px 15px;
            border-radius: 8px;
            color: var(--text-dark);
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
        }

        .export-btn:hover {
            background: #f9fafb;
            border-color: var(--primary);
        }

        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 1px solid #e5e7eb;
        }

        .chart-bars {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .chart-bar-item {
            flex: 1;
            text-align: center;
        }

        .chart-bar {
            height: 100px;
            background: linear-gradient(to top, var(--primary), #60a5fa);
            border-radius: 8px 8px 0 0;
            transition: 0.3s;
            position: relative;
            margin-bottom: 8px;
        }

        .chart-bar:hover {
            opacity: 0.8;
        }

        .chart-label {
            font-size: 0.85rem;
            color: var(--text-gray);
            font-weight: 500;
        }

        .chart-value {
            font-weight: 700;
            color: var(--text-dark);
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
            
            <a href="ventas.php" class="menu-item">
                <i class="fa-solid fa-chart-line"></i> Ventas
            </a>
            <a href="pedidos.php" class="menu-item active">
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
        
        <div class="welcome-card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>Gestión de Pedidos</h1>
                    <p>Panel de Control | Rol: <b><?php echo $rol_label; ?></b></p>
                </div>
                
                <div class="notification-container">
                    <i class="fa-solid fa-bell bell-icon"></i>
                    <?php if ($total_alertas > 0): ?>
                        <span class="badge-alert"><?php echo $total_alertas; ?></span>
                    <?php endif; ?>

                    <div class="notif-dropdown">
                        <div class="notif-header">Alertas de Inventario</div>
                        <?php if ($total_alertas > 0): ?>
                            <?php foreach ($alertas as $item): ?>
                                <a href="editar_producto.php?id=<?php echo $item['id']; ?>" class="notif-item">
                                    <span class="status-dot <?php echo ($item['stock'] == 0) ? 'dot-red' : 'dot-orange'; ?>"></span>
                                    <div>
                                        <strong><?php echo htmlspecialchars($item['nombre']); ?></strong><br>
                                        <small style="color: var(--text-gray);">
                                            <?php echo ($item['stock'] == 0) ? 'Totalmente agotado' : 'Stock crítico: ' . $item['stock'] . ' unid.'; ?>
                                        </small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="notif-item" style="justify-content:center; color:var(--text-gray); padding: 20px;">
                                <i class="fa-solid fa-check-circle" style="color: #10b981;"></i> &nbsp; Inventario al día
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">

                <div class="stat-number"><?php echo $total_pedidos; ?></div>
                <div class="stat-label">Total Pedidos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pendientes; ?></div>
                <div class="stat-label">Pendientes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $enviados; ?></div>
                <div class="stat-label">Enviados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $entregados; ?></div>
                <div class="stat-label">Entregados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($ingresos_mes, 0); ?></div>
                <div class="stat-label">Ingresos <?php echo $nombre_mes_actual; ?></div>
            </div>
        </div>

        <!-- Gráfica de estatus -->
        <div class="chart-container">
            <h3 style="margin: 0 0 20px 0; color: var(--text-dark); font-weight: 600;">Distribución de Pedidos por Estatus</h3>
            <div class="chart-bars">
                <?php 
                $max_value = max($stats_data) ?: 1;
                foreach($stats_data as $estatus => $cantidad): 
                    $porcentaje = ($cantidad / $max_value) * 100;
                    $color = [
                        'pendiente' => '#d97706',
                        'pagado' => '#3b82f6',
                        'enviado' => '#0284c7',
                        'entregado' => '#059669',
                        'cancelado' => '#dc2626'
                    ][$estatus] ?? '#6b7280';
                ?>
                <div class="chart-bar-item">
                    <div class="chart-bar" style="height: <?php echo $porcentaje; ?>px; background: <?php echo $color; ?>;"></div>
                    <div class="chart-label"><?php echo ucfirst($estatus); ?></div>
                    <div class="chart-value"><?php echo $cantidad; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters">
            <select class="filter-select" id="estatusFilter">
                <option value="">Todos los estatus</option>
                <option value="pendiente">Pendiente</option>
                <option value="pagado">Pagado</option>
                <option value="enviado">Enviado</option>
                <option value="entregado">Entregado</option>
                <option value="cancelado">Cancelado</option>
            </select>
            
            <select class="filter-select" id="fechaFilter">
                <option value="">Todas las fechas</option>
                <option value="hoy">Hoy</option>
                <option value="semana">Esta semana</option>
                <option value="mes">Este mes</option>
            </select>

            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Buscar por cliente, ID o dirección...">
                <button onclick="filtrarPedidos()"><i class="fa-solid fa-search"></i></button>
            </div>

            <a href="exportar_pedidos.php" class="export-btn">
                <i class="fa-solid fa-download"></i> Exportar
            </a>
        </div>

        <!-- Tabla de pedidos -->
        <div class="section-title">
            <span>Listado de Pedidos</span>
            <span style="font-size: 0.9rem; color: var(--text-gray);"><?php echo count($pedidos); ?> registros</span>
        </div>

        <div class="pedidos-table-container">
            <table class="pedidos-table" id="pedidosTable">
                <thead>
                    <tr>
                        <th>ID Pedido</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Dirección de Envío</th>
                        <th>Productos</th>
                        <th>Total</th>
                        <th>Estatus</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pedidos as $pedido): 
                        $nombre_completo = $pedido['nombre'] . ' ' . $pedido['apellido_paterno'] . ' ' . $pedido['apellido_materno'];
                        $direccion = $pedido['calle'] . ' #' . $pedido['num_exterior'];
                        if($pedido['num_interior']) $direccion .= ' Int.' . $pedido['num_interior'];
                        $direccion .= ', ' . $pedido['colonia'] . ', ' . $pedido['ciudad'] . ', ' . $pedido['estado'] . ' CP ' . $pedido['cp'];
                    ?>
                    <tr>
                        <td>
                            <strong>#<?php echo str_pad($pedido['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                        </td>
                        <td>
                            <?php echo date('d/m/Y H:i', strtotime($pedido['fecha'])); ?>
                            <div style="font-size: 0.75rem; color: var(--text-gray);"><?php echo $pedido['tipo_venta']; ?></div>
                        </td>
                        <td>
                            <div class="cliente-info">
                                <span class="cliente-nombre"><?php echo htmlspecialchars($nombre_completo); ?></span>
                                <span class="cliente-contacto">
                                    <i class="fa-regular fa-envelope"></i> <?php echo htmlspecialchars($pedido['email']); ?>
                                </span>
                                <span class="cliente-contacto">
                                    <i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($pedido['telefono']); ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="direccion">
                                <i class="fa-solid fa-location-dot" style="color: var(--primary); margin-right: 5px;"></i>
                                <?php echo htmlspecialchars($direccion); ?>
                            </div>
                        </td>
                        <td>
                            <div class="productos-list">
                                <strong><?php echo $pedido['total_productos']; ?> producto(s)</strong>
                                <?php 
                                $productos_resumen = explode(' | ', $pedido['productos']);
                                $primeros = array_slice($productos_resumen, 0, 2);
                                foreach($primeros as $prod): ?>
                                    <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        • <?php echo htmlspecialchars($prod); ?>
                                    </div>
                                <?php endforeach; 
                                if(count($productos_resumen) > 2): ?>
                                    <div style="color: var(--primary);">+ <?php echo count($productos_resumen) - 2; ?> más</div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="total-pedido">$<?php echo number_format($pedido['total'], 2); ?></span>
                        </td>
                        <td>
                            <?php echo getStatusBadge($pedido['estatus']); ?>
                            <div style="margin-top: 5px;">
                                <select class="estatus-select" onchange="cambiarEstatus(<?php echo $pedido['id']; ?>, this.value)">
                                    <option value="">Cambiar</option>
                                    <option value="pendiente">Pendiente</option>
                                    <option value="pagado">Pagado</option>
                                    <option value="enviado">Enviado</option>
                                    <option value="entregado">Entregado</option>
                                    <option value="cancelado">Cancelado</option>
                                </select>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="ver_pedido.php?id=<?php echo $pedido['id']; ?>" class="btn-action btn-view">
                                    <i class="fa-regular fa-eye"></i>
                                </a>
                                <a href="editar_pedido.php?id=<?php echo $pedido['id']; ?>" class="btn-action btn-edit">
                                    <i class="fa-regular fa-pen-to-square"></i>
                                </a>
                                <a href="factura.php?id=<?php echo $pedido['id']; ?>" class="btn-action btn-view" target="_blank">
                                    <i class="fa-solid fa-file-invoice"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($pedidos)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 50px; color: var(--text-gray);">
                            <i class="fa-solid fa-box-open" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
                            No hay pedidos registrados
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Información adicional -->
        <div style="margin-top: 20px; display: flex; gap: 20px; flex-wrap: wrap;">
            <div style="background: white; padding: 15px; border-radius: 8px; flex: 1; border: 1px solid #e5e7eb;">
                <h4 style="margin: 0 0 10px 0; color: var(--text-dark); font-size: 0.95rem;">
                    <i class="fa-solid fa-circle-info" style="color: var(--primary);"></i> Leyenda de estatus
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; font-size: 0.85rem;">
                    <div><span style="background: #fef3c7; width: 12px; height: 12px; display: inline-block; border-radius: 3px;"></span> Pendiente: Esperando pago</div>
                    <div><span style="background: #dbeafe; width: 12px; height: 12px; display: inline-block; border-radius: 3px;"></span> Pagado: Pago confirmado</div>
                    <div><span style="background: #e0f2fe; width: 12px; height: 12px; display: inline-block; border-radius: 3px;"></span> Enviado: En ruta de entrega</div>
                    <div><span style="background: #d1fae5; width: 12px; height: 12px; display: inline-block; border-radius: 3px;"></span> Entregado: Cliente recibió</div>
                    <div><span style="background: #fee2e2; width: 12px; height: 12px; display: inline-block; border-radius: 3px;"></span> Cancelado: Pedido anulado</div>
                </div>
            </div>
            
            <div style="background: white; padding: 15px; border-radius: 8px; width: 300px; border: 1px solid #e5e7eb;">
                <h4 style="margin: 0 0 10px 0; color: var(--text-dark); font-size: 0.95rem;">
                    <i class="fa-solid fa-clock"></i> Última actualización
                </h4>
                <p style="margin: 0; color: var(--text-gray); font-size: 0.9rem;">
                    <?php echo date('d/m/Y H:i:s'); ?><br>
                    <small>Los datos se actualizan en tiempo real</small>
                </p>
            </div>
        </div>

    </main>

    <script>
        function filtrarPedidos() {
            const estatus = document.getElementById('estatusFilter').value;
            const fecha = document.getElementById('fechaFilter').value;
            const busqueda = document.getElementById('searchInput').value.toLowerCase();
            const filas = document.querySelectorAll('#pedidosTable tbody tr');
            
            filas.forEach(fila => {
                let mostrar = true;
                
                // Filtro por estatus
                if (estatus) {
                    const estatusCelda = fila.cells[6].innerHTML;
                    if (!estatusCelda.includes(estatus)) {
                        mostrar = false;
                    }
                }
                
                // Filtro por búsqueda
                if (busqueda) {
                    const texto = fila.innerText.toLowerCase();
                    if (!texto.includes(busqueda)) {
                        mostrar = false;
                    }
                }
                
                fila.style.display = mostrar ? '' : 'none';
            });
        }

        function cambiarEstatus(pedidoId, nuevoEstatus) {
            if (!nuevoEstatus) return;
            
            if (confirm('¿Estás seguro de cambiar el estatus de este pedido?')) {
                // Aquí iría la petición AJAX para actualizar el estatus
                // Por ahora simulamos con fetch
                fetch('actualizar_estatus_pedido.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        pedido_id: pedidoId,
                        estatus: nuevoEstatus
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload(); // Recargar para ver cambios
                    } else {
                        alert('Error al actualizar el estatus');
                    }
                });
            }
        }

        // Event listeners para filtros
        document.getElementById('estatusFilter').addEventListener('change', filtrarPedidos);
        document.getElementById('fechaFilter').addEventListener('change', filtrarPedidos);
        document.getElementById('searchInput').addEventListener('keyup', filtrarPedidos);

        // Tooltip para dirección completa
        const direcciones = document.querySelectorAll('.direccion');
        direcciones.forEach(dir => {
            dir.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f3f4f6';
                this.style.borderRadius = '4px';
            });
            dir.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });
    </script>

</body>
</html>