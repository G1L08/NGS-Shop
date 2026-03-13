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

$stmt_prod = $pdo->query("SELECT COUNT(*) FROM productos");
$total_productos = $stmt_prod->fetchColumn();

$total_usuarios = 0;
if ($rol === 'dueño' || $rol === 'dueno') {
    $stmt_user = $pdo->query("SELECT COUNT(*) FROM usuarios");
    $total_usuarios = $stmt_user->fetchColumn();
}

$mes_actual = date('m');
$anio_actual = date('Y');

// Calcular ganancias brutas (antes de impuestos)
$stmt_ganancias_brutas = $pdo->prepare("SELECT SUM(total) FROM ventas WHERE MONTH(fecha) = ? AND YEAR(fecha) = ?");
$stmt_ganancias_brutas->execute([$mes_actual, $anio_actual]);
$ingresos_brutos_mes = $stmt_ganancias_brutas->fetchColumn() ?: 0;

// Calcular IVA cobrado (16% de las ventas)
$tasa_iva = 0.16;
$iva_cobrado = $ingresos_brutos_mes * $tasa_iva;

// Calcular ganancias netas (después de restar el IVA)
$ganancias_netas = $ingresos_brutos_mes - $iva_cobrado;

$stmt_recent = $pdo->query("
    SELECT v.id, v.total, v.fecha, u.nombre 
    FROM ventas v 
    JOIN usuarios u ON v.usuario_id = u.id 
    ORDER BY v.fecha DESC LIMIT 5
");
$ultimas_ventas = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);


$rol_label = ($rol === 'dueño' || $rol === 'dueno') ? 'DUEÑO' : 'ADMINISTRADOR';
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';

$meses = ["01"=>"Enero", "02"=>"Febrero", "03"=>"Marzo", "04"=>"Abril", "05"=>"Mayo", "06"=>"Junio", 
          "07"=>"Julio", "08"=>"Agosto", "09"=>"Septiembre", "10"=>"Octubre", "11"=>"Noviembre", "12"=>"Diciembre"];
$nombre_mes_actual = $meses[$mes_actual];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | NGS</title>
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
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); 
            gap: 25px; 
        }

        .stat-card-link { text-decoration: none; color: inherit; }

        .stat-card { 
            background: var(--white); 
            padding: 30px; 
            border-radius: 16px; 
            border: 1px solid #e5e7eb; 
            display: flex; 
            align-items: center; 
            gap: 20px; 
            transition: all 0.3s ease; 
        }

        .stat-card:hover { 
            transform: translateY(-8px); 
            box-shadow: 0 12px 25px rgba(0,0,0,0.05);
            border-color: var(--primary);
        }

        .icon-box { 
            width: 60px; height: 60px; 
            border-radius: 14px; 
            display: flex; align-items: center; 
            justify-content: center; font-size: 1.8rem; 
        }

        .blue { background: #eff6ff; color: #3b82f6; }
        .green { background: #f0fdf4; color: #16a34a; }
        .money { background: #d1fae5; color: #059669; }
        .net { background: #f0f9ff; color: #0284c7; }
        .tax { background: #fef3c7; color: #d97706; }

        .stat-info h3 { 
            margin: 0; font-size: 2rem; 
            color: var(--text-dark); 
            font-weight: 700;
        }

        .stat-info span { 
            color: var(--text-gray); 
            font-weight: 500; 
            font-size: 1rem;
        }

        .section-title {
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 25px;
            font-size: 1.2rem;
        }

        /* NUEVO: Estilos para desglose de ganancias */
        .ganancias-detalle {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 15px;
        }

        .ganancia-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 12px;
            background: #f8fafc;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .ganancia-label {
            color: #64748b;
            font-weight: 500;
        }

        .ganancia-valor {
            font-weight: 600;
        }

        .bruto { color: #059669; }
        .iva { color: #d97706; }
        .neto { color: #0284c7; }

        /* Tooltip para ganancias */
        .ganancias-tooltip {
            position: relative;
            cursor: help;
        }

        .ganancias-tooltip:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #1f2937;
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.85rem;
            width: 250px;
            z-index: 100;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            white-space: normal;
            line-height: 1.4;
        }

        /* Gráfica simple de ganancias */
        .ganancias-chart {
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            margin-top: 10px;
            overflow: hidden;
        }

        .chart-segment {
            height: 100%;
            display: inline-block;
        }

        .segment-bruto {
            background: #059669;
            width: 100%;
        }

        .segment-iva {
            background: #d97706;
            width: 16%;
        }

        .segment-neto {
            background: #0284c7;
            width: 84%;
        }

    </style>
</head>
<body>

    <aside class="sidebar">
        <h2> ADMIN PANEL</h2>
        
        <nav>
            <a href="index.php" class="menu-item active">
                <i class="fa-solid fa-house"></i> Inicio
            </a>
            <a href="productos.php" class="menu-item">
                <i class="fa-solid fa-box-open"></i> Productos
            </a>
            
            <a href="ventas.php" class="menu-item">
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
        
        <div class="welcome-card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>¡Bienvenido, <?php echo htmlspecialchars($nombre_usuario); ?>!</h1>
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

        <h3 class="section-title">Resumen de Actividad</h3>
        
        <div class="stats-grid">
            <a href="productos.php" class="stat-card-link">
                <div class="stat-card">
                    <div class="icon-box blue">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_productos; ?></h3>
                        <span>Productos</span>
                    </div>
                </div>
            </a>

            <!-- CARD DE GANANCIAS NETAS -->
            <a href="ventas.php" class="stat-card-link">
                <div class="stat-card ganancias-tooltip" data-tooltip="Ganancias netas después de impuestos (IVA 16%)">
                    <div class="icon-box net">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3>$<?php echo number_format($ganancias_netas, 2); ?></h3>
                        <span>Ganancias netas de <?php echo $nombre_mes_actual; ?></span>
                        
                        <!-- Gráfica simple de ganancias -->
                        <div class="ganancias-chart">
                            <div class="chart-segment segment-neto" title="Ganancias Netas: $<?php echo number_format($ganancias_netas, 2); ?>"></div>
                        </div>
                        
                        <!-- Desglose detallado al hacer hover -->
                        <div class="ganancias-detalle">
                            <div class="ganancia-item">
                                <span class="ganancia-label">Ventas brutas:</span>
                                <span class="ganancia-valor bruto">$<?php echo number_format($ingresos_brutos_mes, 2); ?></span>
                            </div>
                            <div class="ganancia-item">
                                <span class="ganancia-label">IVA (16%):</span>
                                <span class="ganancia-valor iva">$<?php echo number_format($iva_cobrado, 2); ?></span>
                            </div>
                            <div class="ganancia-item">
                                <span class="ganancia-label">Ganancias netas:</span>
                                <span class="ganancia-valor neto">$<?php echo number_format($ganancias_netas, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </a>

            <!-- CARD DE IVA COBRADO -->
            <div class="stat-card">
                <div class="icon-box tax">
                    <i class="fa-solid fa-scale-balanced"></i>
                </div>
                <div class="stat-info">
                    <h3>$<?php echo number_format($iva_cobrado, 2); ?></h3>
                    <span>IVA cobrado en <?php echo $nombre_mes_actual; ?></span>
                    <div style="margin-top: 8px; font-size: 0.85rem; color: #64748b;">
                        <i class="fa-solid fa-percentage"></i> 16% sobre ventas brutas
                    </div>
                </div>
            </div>

            <?php if($rol === 'dueño' || $rol === 'dueno'): ?>
            <a href="usuarios.php" class="stat-card-link">
                <div class="stat-card">
                    <div class="icon-box green">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_usuarios; ?></h3>
                        <span>Usuarios</span>
                    </div>
                </div>
            </a>
            <?php endif; ?>
        </div>

        <!-- RESUMEN DE GANANCIAS -->
        <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-top: 30px; border: 1px solid #e5e7eb;">
            <h3 style="margin: 0 0 20px 0; color: var(--text-dark); font-weight: 600; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-chart-pie"></i> Desglose de Ganancias - <?php echo $nombre_mes_actual; ?>
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div style="text-align: center; padding: 20px; background: #f0f9ff; border-radius: 10px;">
                    <div style="font-size: 1.5rem; font-weight: 700; color: #059669;">$<?php echo number_format($ingresos_brutos_mes, 2); ?></div>
                    <div style="color: #64748b; font-size: 0.9rem; margin-top: 5px;">Ventas Brutas</div>
                    <div style="font-size: 0.8rem; color: #94a3b8; margin-top: 5px;">
                        <i class="fa-solid fa-receipt"></i> Total facturado
                    </div>
                </div>
                
                <div style="text-align: center; padding: 20px; background: #fef3c7; border-radius: 10px;">
                    <div style="font-size: 1.5rem; font-weight: 700; color: #d97706;">$<?php echo number_format($iva_cobrado, 2); ?></div>
                    <div style="color: #64748b; font-size: 0.9rem; margin-top: 5px;">IVA (16%)</div>
                    <div style="font-size: 0.8rem; color: #94a3b8; margin-top: 5px;">
                        <i class="fa-solid fa-scale-balanced"></i> Impuesto por pagar
                    </div>
                </div>
                
                <div style="text-align: center; padding: 20px; background: #f0fdf4; border-radius: 10px; border: 2px solid #bbf7d0;">
                    <div style="font-size: 1.8rem; font-weight: 800; color: #16a34a;">$<?php echo number_format($ganancias_netas, 2); ?></div>
                    <div style="color: #16a34a; font-size: 1rem; font-weight: 600; margin-top: 5px;">GANANCIAS NETAS</div>
                    <div style="font-size: 0.8rem; color: #65a30d; margin-top: 5px;">
                        <i class="fa-solid fa-piggy-bank"></i> Disponible después de impuestos
                    </div>
                </div>
            </div>
            
            <!-- Gráfica de distribución -->
            <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <span style="color: var(--text-gray); font-weight: 500;">Distribución:</span>
                    <span style="font-size: 0.9rem; color: #64748b;">
                        <span style="color: #059669;">Ventas: <?php echo number_format(($ingresos_brutos_mes > 0) ? 100 : 0, 1); ?>%</span> | 
                        <span style="color: #d97706;">IVA: <?php echo number_format(($ingresos_brutos_mes > 0) ? 16 : 0, 1); ?>%</span> | 
                        <span style="color: #16a34a;">Neto: <?php echo number_format(($ingresos_brutos_mes > 0) ? 84 : 0, 1); ?>%</span>
                    </span>
                </div>
                <div style="height: 12px; background: #e5e7eb; border-radius: 6px; overflow: hidden; display: flex;">
                    <div style="flex: <?php echo ($ingresos_brutos_mes > 0) ? 84 : 0; ?>; background: #16a34a;" title="Ganancias Netas (84%)"></div>
                    <div style="flex: <?php echo ($ingresos_brutos_mes > 0) ? 16 : 0; ?>; background: #d97706;" title="IVA (16%)"></div>
                </div>
            </div>
        </div>

        <h3 class="section-title" style="margin-top: 40px;">Últimas Ventas</h3>
        <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 10px;">ID</th>
                        <th style="padding: 10px;">Cliente</th>
                        <th style="padding: 10px;">Fecha</th>
                        <th style="padding: 10px;">Monto</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($ultimas_ventas as $venta): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 12px; color: var(--text-gray);">#<?php echo str_pad($venta['id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td style="padding: 12px; font-weight: 500;"><?php echo htmlspecialchars($venta['nombre']); ?></td>
                        <td style="padding: 12px; color: #64748b;"><?php echo date('d/m/Y', strtotime($venta['fecha'])); ?></td>
                        <td style="padding: 12px; font-weight: bold; color: #059669;">$<?php echo number_format($venta['total'], 2); ?></td>
                        <td style="padding: 12px; text-align: right;">
                            <a href="ventas.php" style="text-decoration: none; color: #007bff; font-size: 0.9rem; font-weight: 600;">Ver detalles</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($ultimas_ventas)): ?>
                        <tr><td colspan="5" style="padding:30px; text-align:center; color:#94a3b8;">No se han registrado ventas recientemente.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

    <script>
        // Script para mostrar/ocultar detalles de ganancias
        document.addEventListener('DOMContentLoaded', function() {
            const gananciasCard = document.querySelector('.ganancias-tooltip');
            const gananciasDetalle = gananciasCard.querySelector('.ganancias-detalle');
            
            // Ocultar detalles inicialmente
            gananciasDetalle.style.display = 'none';
            
            // Mostrar detalles al hacer hover
            gananciasCard.addEventListener('mouseenter', function() {
                gananciasDetalle.style.display = 'flex';
            });
            
            gananciasCard.addEventListener('mouseleave', function() {
                gananciasDetalle.style.display = 'none';
            });
        });
    </script>

</body>
</html>