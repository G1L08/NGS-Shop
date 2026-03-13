<?php
session_start();
require '../config/db.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$usuario_id = $_SESSION['user_id'];
$nombre_usuario = $_SESSION['nombre'] ?? '';

// Mostrar mensajes de sesión
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['warning'])) {
    $warning_message = $_SESSION['warning'];
    unset($_SESSION['warning']);
}

// Obtener todos los pedidos del usuario
$stmt = $pdo->prepare("
    SELECT v.*, 
           (SELECT COUNT(*) FROM detalle_ventas dv WHERE dv.venta_id = v.id) as total_productos
    FROM ventas v
    WHERE v.usuario_id = ?
    ORDER BY v.fecha DESC
");
$stmt->execute([$usuario_id]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Para cada pedido, obtener sus detalles
foreach ($pedidos as &$pedido) {
    $stmt_detalle = $pdo->prepare("
        SELECT dv.*, p.nombre, p.marca, p.modelo, p.precio, p.stock, pi.url_imagen
        FROM detalle_ventas dv
        JOIN productos p ON dv.producto_id = p.id
        LEFT JOIN producto_imagenes pi ON p.id = pi.producto_id AND pi.clasificacion = 'principal'
        WHERE dv.venta_id = ?
        GROUP BY dv.id
    ");
    $stmt_detalle->execute([$pedido['id']]);
    $pedido['detalles'] = $stmt_detalle->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar si todos los productos están disponibles para re-compra
    $pedido['todos_disponibles'] = true;
    foreach ($pedido['detalles'] as $detalle) {
        if ($detalle['stock'] < $detalle['cantidad']) {
            $pedido['todos_disponibles'] = false;
            break;
        }
    }
}
unset($pedido);

// Función para obtener el color según el estatus
function getStatusColor($estatus) {
    switch($estatus) {
        case 'pendiente': return ['bg' => '#fff3cd', 'text' => '#856404', 'icon' => 'fa-clock'];
        case 'pagado': return ['bg' => '#d1ecf1', 'text' => '#0c5460', 'icon' => 'fa-check-circle'];
        case 'enviado': return ['bg' => '#cce5ff', 'text' => '#004085', 'icon' => 'fa-truck'];
        case 'entregado': return ['bg' => '#d4edda', 'text' => '#155724', 'icon' => 'fa-circle-check'];
        case 'cancelado': return ['bg' => '#f8d7da', 'text' => '#721c24', 'icon' => 'fa-ban'];
        default: return ['bg' => '#e2e3e5', 'text' => '#383d41', 'icon' => 'fa-question'];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Compras | NGS Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --ngs-blue: rgb(6 19 37 / 95%);
            --ngs-accent: #0d6efd;
            --bg-light: #f8f9fa;
            --text-dark: #1e293b;
            --text-gray: #64748b;
            --border-color: #e2e8f0;
        }

        body {
            background-color: var(--bg-light);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            color: var(--text-dark);
        }

        /* Banner de perfil */
        .profile-banner {
            background: linear-gradient(rgba(6, 19, 37, 0.95), rgba(6, 19, 37, 0.98)), 
                        url('../assets/img/banner-hero.webp');
            background-size: cover;
            background-position: center;
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-bottom: 3px solid var(--ngs-accent);
        }

        .profile-banner-content {
            color: white;
            text-align: center;
        }

        .profile-banner h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .profile-banner p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Contenedor principal */
        .container-custom {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Navegación */
        .nav-buttons {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn-navigation {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-back {
            background-color: var(--ngs-blue);
            color: white;
        }

        .btn-back:hover {
            background-color: #0a1a2e;
            color: white;
            transform: translateY(-2px);
        }

        .btn-store {
            background-color: #28a745;
            color: white;
        }

        .btn-store:hover {
            background-color: #218838;
            color: white;
            transform: translateY(-2px);
        }

        /* Resumen de compras */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease;
        }

        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .summary-icon {
            font-size: 2rem;
            color: var(--ngs-accent);
            margin-bottom: 10px;
        }

        .summary-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .summary-label {
            color: var(--text-gray);
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Listado de pedidos */
        .pedidos-grid {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .pedido-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .pedido-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .pedido-header {
            background: #f8fafc;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .pedido-info {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.8rem;
            color: var(--text-gray);
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .info-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .info-value.total {
            color: var(--ngs-accent);
            font-size: 1.2rem;
        }
        
        .estatus-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .pedido-body {
            padding: 1.5rem;
        }
        
        .productos-grid {
            display: grid;
            gap: 1rem;
        }
        
        .producto-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.5rem;
            border-radius: 8px;
            transition: background 0.2s;
        }
        
        .producto-item:hover {
            background: #f8fafc;
        }
        
        .producto-img {
            width: 70px;
            height: 70px;
            object-fit: contain;
            background: white;
            border-radius: 8px;
            padding: 8px;
            border: 1px solid var(--border-color);
        }
        
        .producto-detalle {
            flex: 1;
        }
        
        .producto-nombre {
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .producto-marca {
            font-size: 0.85rem;
            color: var(--text-gray);
            margin-bottom: 4px;
        }
        
        .producto-precio {
            display: flex;
            gap: 1rem;
            font-size: 0.9rem;
        }
        
        .precio-unitario {
            color: var(--text-gray);
        }
        
        .precio-total {
            font-weight: 600;
            color: var(--ngs-accent);
        }
        
        .pedido-footer {
            padding: 1rem 1.5rem;
            background: #f8fafc;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .pedido-actions {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-detail {
            background-color: var(--ngs-accent);
            color: white;
        }
        
        .btn-detail:hover {
            background-color: #0b5ed7;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-rebuy {
            background-color: #28a745;
            color: white;
        }
        
        .btn-rebuy:hover {
            background-color: #218838;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-rebuy.disabled {
            background-color: #6c757d;
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-track {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-track:hover {
            background-color: #138496;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-invoice {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-invoice:hover {
            background-color: #5a6268;
            color: white;
            transform: translateY(-2px);
        }
        
        /* Sin pedidos */
        .sin-pedidos {
            text-align: center;
            padding: 5rem 2rem;
            background: white;
            border-radius: 12px;
            border: 2px dashed var(--border-color);
        }
        
        .sin-pedidos i {
            font-size: 5rem;
            color: var(--border-color);
            margin-bottom: 1.5rem;
        }
        
        .sin-pedidos h3 {
            margin-bottom: 1rem;
            color: var(--text-dark);
        }
        
        .sin-pedidos p {
            color: var(--text-gray);
            margin-bottom: 2rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .profile-banner {
                padding: 2rem 0;
            }
            
            .profile-banner h1 {
                font-size: 2rem;
            }
            
            .nav-buttons {
                flex-direction: column;
            }
            
            .btn-navigation {
                width: 100%;
                justify-content: center;
            }
            
            .pedido-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .pedido-info {
                gap: 1rem;
                width: 100%;
                justify-content: space-between;
            }
            
            .producto-item {
                flex-wrap: wrap;
            }
            
            .pedido-actions {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<!-- Banner de perfil -->
<div class="profile-banner">
    <div class="container container-custom">
        <div class="profile-banner-content">
            <h1>Historial de Compras</h1>
            <p>Consulta el detalle completo de todas tus compras</p>
        </div>
    </div>
</div>

<div class="container container-custom">
    <!-- Navegación -->
    <div class="nav-buttons">
        <a href="perfil.php" class="btn-navigation btn-back">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Volver al Perfil</span>
        </a>
        
        <a href="../index.php" class="btn-navigation btn-store">
            <span>Seguir Comprando</span>
        </a>
    </div>

    <!-- Mensajes de notificación -->
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($warning_message)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            <?php echo htmlspecialchars($warning_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Calcular estadísticas -->
    <?php
    $total_compras = count($pedidos);
    $total_gastado = 0;
    $compras_entregadas = 0;
    $compras_pendientes = 0;
    
    foreach($pedidos as $p) {
        $total_gastado += $p['total'];
        if($p['estatus'] == 'entregado') $compras_entregadas++;
        if($p['estatus'] == 'pendiente') $compras_pendientes++;
    }
    ?>

    <!-- Tarjetas de resumen -->
    <?php if (!empty($pedidos)): ?>
    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-icon"><i class="fa-solid fa-bag-shopping"></i></div>
            <div class="summary-number"><?php echo $total_compras; ?></div>
            <div class="summary-label">Total de compras</div>
        </div>
        
       <!-- <div class="summary-card">
            <div class="summary-icon"><i class="fa-solid fa-dollar-sign"></i></div>
            <div class="summary-number">$<?php echo number_format($total_gastado, 2); ?></div>
            <div class="summary-label">Total gastado</div>
        </div>
    -->
        <div class="summary-card">
            <div class="summary-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div class="summary-number"><?php echo $compras_entregadas; ?></div>
            <div class="summary-label">Entregadas</div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon"><i class="fa-solid fa-clock"></i></div>
            <div class="summary-number"><?php echo $compras_pendientes; ?></div>
            <div class="summary-label">En proceso</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Listado de pedidos -->
    <?php if (empty($pedidos)): ?>
        <div class="sin-pedidos">
            
            <h3>No tienes historial de compras</h3>
            <p class="text-muted">Cuando realices tu primera compra, aparecerá aquí todo el historial.</p>
            <a href="../index.php" class="btn btn-primary btn-lg">
                Ir a la tienda
            </a>
        </div>
    <?php else: ?>
        <div class="pedidos-grid">
            <?php foreach($pedidos as $pedido): 
                $fecha = date('d/m/Y', strtotime($pedido['fecha']));
                $hora = date('H:i', strtotime($pedido['fecha']));
                $status = getStatusColor($pedido['estatus']);
            ?>
                <div class="pedido-card">
                    <!-- Encabezado -->
                    <div class="pedido-header">
                        <div class="pedido-info">
                            <div class="info-item">
                                <span class="info-label">Pedido #</span>
                                <span class="info-value"><?php echo str_pad($pedido['id'], 8, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Fecha</span>
                                <span class="info-value"><?php echo $fecha; ?> <small style="color: #666;"><?php echo $hora; ?></small></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Productos</span>
                                <span class="info-value"><?php echo $pedido['total_productos']; ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Total</span>
                                <span class="info-value total">$<?php echo number_format($pedido['total'], 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="estatus-badge" style="background: <?php echo $status['bg']; ?>; color: <?php echo $status['text']; ?>">
                            <i class="fa-regular <?php echo $status['icon']; ?>"></i>
                            <?php 
                            $estatus_text = [
                                'pendiente' => 'Pendiente',
                                'pagado' => 'Pagado',
                                'enviado' => 'Enviado',
                                'entregado' => 'Entregado',
                                'cancelado' => 'Cancelado'
                            ];
                            echo $estatus_text[$pedido['estatus']] ?? $pedido['estatus'];
                            ?>
                        </div>
                    </div>
                    
                    <!-- Cuerpo con productos -->
                    <div class="pedido-body">
                        <div class="productos-grid">
                            <?php foreach($pedido['detalles'] as $index => $detalle): ?>
                                <?php if($index < 3): // Mostrar máximo 3 productos ?>
                                    <div class="producto-item">
                                        <img src="<?php echo htmlspecialchars($detalle['url_imagen'] ?? 'https://via.placeholder.com/70x70?text=Sin+Imagen'); ?>" 
                                             class="producto-img"
                                             alt="<?php echo htmlspecialchars($detalle['nombre']); ?>"
                                             onerror="this.src='https://via.placeholder.com/70x70?text=Sin+Imagen'">
                                        <div class="producto-detalle">
                                            <div class="producto-nombre"><?php echo htmlspecialchars($detalle['nombre']); ?></div>
                                            <div class="producto-marca"><?php echo htmlspecialchars($detalle['marca']); ?></div>
                                            <div class="producto-precio">
                                                <span class="precio-unitario">$<?php echo number_format($detalle['precio_unitario'], 2); ?> c/u</span>
                                                <span class="precio-total">x<?php echo $detalle['cantidad']; ?> = $<?php echo number_format($detalle['cantidad'] * $detalle['precio_unitario'], 2); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <?php if($pedido['total_productos'] > 3): ?>
                                <div style="text-align: center; padding: 0.5rem; background: #f8fafc; border-radius: 6px;">
                                    <i class="fa-solid fa-ellipsis"></i> 
                                    <?php echo $pedido['total_productos'] - 3; ?> producto(s) más...
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Footer con acciones -->
                    <div class="pedido-footer">
                        <div>
                            <small style="color: var(--text-gray);">
                                <i class="fa-regular fa-credit-card"></i> 
                                Pago: <?php echo ucfirst($pedido['tipo_venta'] ?? 'Minorista'); ?>
                            </small>
                        </div>
                        
                        <div class="pedido-actions">
                            <?php if ($pedido['estatus'] == 'pendiente'): ?>
                                <a href="../ver_carrito.php" class="btn-action btn-rebuy">
                                    <i class="fa-regular fa-credit-card"></i> Completar pago
                                </a>
                            <?php elseif ($pedido['estatus'] == 'enviado'): ?>
                                <a href="rastreo_envio.php?id=<?php echo $pedido['id']; ?>" class="btn-action btn-track">
                                    <i class="fa-solid fa-truck"></i> Rastrear envío
                                </a>
                            <?php elseif ($pedido['estatus'] == 'entregado'): ?>
                                <?php if($pedido['todos_disponibles']): ?>
                                    <a href="volver_comprar.php?pedido_id=<?php echo $pedido['id']; ?>" class="btn-action btn-rebuy">
                                        <i class="fa-solid fa-cart-plus"></i> Comprar de nuevo
                                    </a>
                                <?php else: ?>
                                    <span class="btn-action btn-rebuy disabled" onclick="return false;">
                                        <i class="fa-solid fa-cart-plus"></i> No disponible
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <a href="detalles_compra.php?id=<?php echo $pedido['id']; ?>" class="btn-action btn-detail">
                                <i class="fa-regular fa-eye"></i> Ver detalles
                            </a>
                            
                            <a href="factura.php?id=<?php echo $pedido['id']; ?>" class="btn-action btn-invoice" target="_blank">
                                <i class="fa-solid fa-file-invoice"></i> Factura
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const images = document.querySelectorAll('.producto-img');
        images.forEach(img => {
            img.addEventListener('error', function() {
                this.src = 'https://via.placeholder.com/70x70?text=Sin+Imagen';
            });
        });
    });
</script>
</body>
</html>