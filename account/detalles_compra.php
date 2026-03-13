<?php
session_start();
require '../config/db.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$usuario_id = $_SESSION['user_id'];

// Verificar que se proporcionó un ID de venta
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: mis_pedidos.php');
    exit();
}

$venta_id = (int)$_GET['id'];

// Obtener los detalles de la venta (sin teléfono)
$stmt = $pdo->prepare("
    SELECT v.*, 
           u.nombre as cliente_nombre,
           u.email as cliente_email
    FROM ventas v
    JOIN usuarios u ON v.usuario_id = u.id
    WHERE v.id = ? AND v.usuario_id = ?
");
$stmt->execute([$venta_id, $usuario_id]);
$venta = $stmt->fetch(PDO::FETCH_ASSOC);

// Verificar que la venta existe y pertenece al usuario
if (!$venta) {
    header('Location: mis_pedidos.php');
    exit();
}

// Obtener los detalles de los productos de la venta
$stmt_detalle = $pdo->prepare("
    SELECT dv.*, 
           p.nombre, 
           p.marca, 
           p.modelo, 
           p.descripcion,
           pi.url_imagen
    FROM detalle_ventas dv
    JOIN productos p ON dv.producto_id = p.id
    LEFT JOIN producto_imagenes pi ON p.id = pi.producto_id AND pi.clasificacion = 'principal'
    WHERE dv.venta_id = ?
    GROUP BY dv.id
");
$stmt_detalle->execute([$venta_id]);
$detalles = $stmt_detalle->fetchAll(PDO::FETCH_ASSOC);

// Calcular subtotal y IVA
$subtotal = $venta['subtotal'] ?? 0;
$iva = $venta['iva'] ?? 0;
$total = $venta['total'];

// Si no hay subtotal/iva en BD, calcularlo
if ($subtotal == 0 && $iva == 0) {
    $subtotal = $total / 1.16;
    $iva = $total - $subtotal;
}

// Mostrar mensajes de sesión
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Compra #<?php echo str_pad($venta_id, 6, '0', STR_PAD_LEFT); ?> | NGS Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --ngs-blue: rgb(6 19 37 / 95%);
            --ngs-accent: #0d6efd;
            --bg-light: #f8f9fa;
        }

        body {
            background-color: var(--bg-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Banner de detalles */
        .details-banner {
            background: linear-gradient(rgba(6, 19, 37, 0.9), rgba(6, 19, 37, 0.95));
            color: white;
            padding: 2.5rem 0;
            margin-bottom: 2rem;
        }

        .details-banner-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .banner-info h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .banner-info p {
            font-size: 1rem;
            opacity: 0.9;
            margin: 0;
        }

        /* Contenedor principal */
        .container-custom {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Navegación */
        .nav-buttons {
            margin-bottom: 2rem;
        }

        .btn-navigation {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            background-color: var(--ngs-blue);
            color: white;
        }

        .btn-navigation:hover {
            background-color: #051225;
            color: white;
            transform: translateY(-2px);
        }

        /* Tarjeta de resumen */
        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .summary-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--ngs-blue);
        }

        .summary-label {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.3rem;
        }

        .summary-value {
            font-size: 1.1rem;
            color: #333;
            font-weight: 500;
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
        }

        .status-pendiente {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-pagado {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-enviado {
            background-color: #d4edda;
            color: #155724;
        }

        .status-entregado {
            background-color: #c3e6cb;
            color: #155724;
        }

        .status-cancelado {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Información del cliente */
        .client-info-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            border-top: 4px solid var(--ngs-blue);
        }

        .client-info-card h5 {
            color: var(--ngs-blue);
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .client-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .client-detail-item {
            padding: 0.8rem;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .client-detail-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.2rem;
        }

        .client-detail-value {
            font-size: 1rem;
            color: #333;
            font-weight: 500;
        }

        /* Productos */
        .products-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .product-card {
            display: flex;
            gap: 1.5rem;
            padding: 1.5rem;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .product-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .product-img {
            width: 120px;
            height: 120px;
            object-fit: contain;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            border: 1px solid #eaeaea;
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .product-meta {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .product-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }

        .price-info {
            text-align: right;
        }

        .unit-price {
            font-size: 0.95rem;
            color: #666;
        }

        .subtotal {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--ngs-blue);
        }

        /* Resumen de pago */
        .payment-summary {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }

        .payment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .payment-item {
            padding: 1.5rem;
            border-radius: 8px;
            border: 2px solid #eaeaea;
        }

        .payment-item.subtotal {
            border-color: #e0f2fe;
            background: #f0f9ff;
        }

        .payment-item.iva {
            border-color: #fee2e2;
            background: #fef2f2;
        }

        .payment-item.total {
            border-color: #dcfce7;
            background: #f0fdf4;
            border-width: 3px;
        }

        .payment-label {
            font-size: 0.95rem;
            color: #666;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .payment-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
        }

        .payment-amount.subtotal {
            color: #0284c7;
        }

        .payment-amount.iva {
            color: #dc2626;
        }

        .payment-amount.total {
            color: #059669;
        }

        .iva-note {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.5rem;
            font-style: italic;
        }

        /* Botones de acción */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-print {
            background-color: #6c757d;
            color: white;
        }

        .btn-print:hover {
            background-color: #5a6268;
            color: white;
        }

        .btn-reorder {
            background-color: #28a745;
            color: white;
        }

        .btn-reorder:hover {
            background-color: #218838;
            color: white;
        }

        .btn-tracking {
            background-color: #17a2b8;
            color: white;
        }

        .btn-tracking:hover {
            background-color: #138496;
            color: white;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .details-banner-content {
                flex-direction: column;
                text-align: center;
            }
            
            .banner-info h1 {
                font-size: 1.8rem;
            }
            
            .product-card {
                flex-direction: column;
                text-align: center;
            }
            
            .product-img {
                width: 100%;
                max-width: 200px;
                margin: 0 auto;
            }
            
            .product-details {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .price-info {
                text-align: center;
            }
            
            .action-buttons {
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
            
            .payment-grid {
                grid-template-columns: 1fr;
            }
            
            .client-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<!-- Banner de detalles -->
<div class="details-banner">
    <div class="container container-custom">
        <div class="details-banner-content">
            <div class="banner-info">
                <h1>Detalles de Compra</h1>
                <p>Orden #<?php echo str_pad($venta_id, 6, '0', STR_PAD_LEFT); ?></p>
            </div>
            <div>
                <span class="status-badge status-<?php echo $venta['estatus']; ?>">
                    <?php 
                    $estatus_text = [
                        'pendiente' => 'Pendiente',
                        'pagado' => 'Pagado',
                        'enviado' => 'Enviado',
                        'entregado' => 'Entregado',
                        'cancelado' => 'Cancelado'
                    ];
                    echo $estatus_text[$venta['estatus']] ?? $venta['estatus'];
                    ?>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="container container-custom">
    <!-- Navegación -->
    <div class="nav-buttons">
        <a href="mis_pedidos.php" class="btn-navigation">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Volver a Mis Pedidos</span>
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

    <!-- Resumen de la compra -->
    <div class="summary-card">
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">Fecha de Compra</div>
                <div class="summary-value">
                    <?php echo date('d/m/Y H:i', strtotime($venta['fecha'])); ?>
                </div>
            </div>
            
            <div class="summary-item">
                <div class="summary-label">Número de Orden</div>
                <div class="summary-value">
                    #<?php echo str_pad($venta_id, 6, '0', STR_PAD_LEFT); ?>
                </div>
            </div>
            
            <div class="summary-item">
                <div class="summary-label">Método de Pago</div>
                <div class="summary-value">
                    <?php echo $venta['metodo_pago'] ?? 'No especificado'; ?>
                </div>
            </div>
            
            <div class="summary-item">
                <div class="summary-label">Productos</div>
                <div class="summary-value">
                    <?php echo count($detalles); ?> producto(s)
                </div>
            </div>
        </div>
        
        <?php if (!empty($venta['comentarios'])): ?>
        <div class="mt-3">
            <div class="summary-label">Comentarios del Pedido</div>
            <div class="summary-value" style="font-style: italic;">
                <?php echo htmlspecialchars($venta['comentarios']); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Información del cliente -->
    <div class="client-info-card">
        <h5><i class="fa-solid fa-user me-2"></i> Información del Cliente</h5>
        <div class="client-details">
            <div class="client-detail-item">
                <div class="client-detail-label">Nombre</div>
                <div class="client-detail-value"><?php echo htmlspecialchars($venta['cliente_nombre']); ?></div>
            </div>
            
            <div class="client-detail-item">
                <div class="client-detail-label">Correo Electrónico</div>
                <div class="client-detail-value"><?php echo htmlspecialchars($venta['cliente_email']); ?></div>
            </div>
            
            <!-- Si necesitas agregar teléfono en el futuro, deberás agregar la columna a la tabla usuarios -->
        </div>
    </div>

    <!-- Productos comprados -->
    <div class="products-section">
        <h5 class="mb-4"><i class="fa-solid fa-boxes-stacked me-2"></i> Productos Comprados</h5>
        
        <?php if (empty($detalles)): ?>
            <div class="alert alert-warning">
                <i class="fa-solid fa-exclamation-triangle me-2"></i>
                No se encontraron productos en esta compra.
            </div>
        <?php else: ?>
            <?php foreach($detalles as $detalle): 
                $img_producto = !empty($detalle['url_imagen']) ? $detalle['url_imagen'] : 'https://via.placeholder.com/120x120?text=Sin+Imagen';
                $subtotal_producto = $detalle['precio_unitario'] * $detalle['cantidad'];
            ?>
                <div class="product-card">
                    <img src="<?php echo htmlspecialchars($img_producto); ?>" 
                         class="product-img"
                         alt="<?php echo htmlspecialchars($detalle['nombre']); ?>"
                         onerror="this.src='https://via.placeholder.com/120x120?text=Sin+Imagen'">
                    
                    <div class="product-info">
                        <div class="product-name"><?php echo htmlspecialchars($detalle['nombre']); ?></div>
                        
                        <div class="product-meta">
                            <?php if (!empty($detalle['marca'])): ?>
                                <span class="me-3">
                                    <i class="fa-solid fa-tag me-1"></i>
                                    <?php echo htmlspecialchars($detalle['marca']); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($detalle['modelo'])): ?>
                                <span>
                                    <i class="fa-solid fa-cube me-1"></i>
                                    <?php echo htmlspecialchars($detalle['modelo']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($detalle['descripcion'])): ?>
                            <div class="product-description" style="color: #666; font-size: 0.9rem; line-height: 1.4;">
                                <?php echo nl2br(htmlspecialchars(substr($detalle['descripcion'], 0, 150))); ?>
                                <?php if (strlen($detalle['descripcion']) > 150): ?>...<?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="product-details">
                            <div>
                                <div class="unit-price">
                                    <strong>Cantidad:</strong> <?php echo $detalle['cantidad']; ?>
                                </div>
                                <div class="unit-price">
                                    <strong>Precio unitario:</strong> $<?php echo number_format($detalle['precio_unitario'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="price-info">
                                <div class="subtotal">
                                    $<?php echo number_format($subtotal_producto, 2); ?>
                                </div>
                                <div class="unit-price">
                                    Subtotal
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Resumen de pago -->
    <div class="payment-summary">
        <h5 class="mb-4"><i class="fa-solid fa-receipt me-2"></i> Resumen de Pago</h5>
        
        <div class="payment-grid">
            <div class="payment-item subtotal">
                <div class="payment-label">
                    <i class="fa-solid fa-calculator"></i>
                    Subtotal
                </div>
                <div class="payment-amount subtotal">
                    $<?php echo number_format($subtotal, 2); ?>
                </div>
                <div class="iva-note">Base gravable antes de impuestos</div>
            </div>
            
            <div class="payment-item iva">
                <div class="payment-label">
                    <i class="fa-solid fa-scale-balanced"></i>
                    IVA (16%)
                </div>
                <div class="payment-amount iva">
                    $<?php echo number_format($iva, 2); ?>
                </div>
                <div class="iva-note">Impuesto al Valor Agregado</div>
            </div>
            
            <div class="payment-item total">
                <div class="payment-label">
                    <i class="fa-solid fa-money-bill-wave"></i>
                    Total Pagado
                </div>
                <div class="payment-amount total">
                    $<?php echo number_format($total, 2); ?>
                </div>
                <div class="iva-note">Incluye IVA del 16%</div>
            </div>
        </div>
        
        <!-- Botones de acción -->
        <div class="action-buttons">
            <button onclick="window.print()" class="btn-action btn-print">
                <i class="fa-solid fa-print"></i>
                Imprimir Recibo
            </button>
            
            <?php if ($venta['estatus'] == 'entregado'): ?>
                <a href="volver_comprar.php?pedido_id=<?php echo $venta_id; ?>" class="btn-action btn-reorder">
                    <i class="fa-solid fa-redo"></i>
                    Volver a Comprar
                </a>
            <?php endif; ?>
            
            <?php if ($venta['estatus'] == 'enviado'): ?>
                <a href="rastreo_envio.php?id=<?php echo $venta_id; ?>" class="btn-action btn-tracking">
                    <i class="fa-solid fa-truck"></i>
                    Rastrear Envío
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Manejo de errores en imágenes
    document.addEventListener('DOMContentLoaded', function() {
        const images = document.querySelectorAll('.product-img');
        images.forEach(img => {
            img.addEventListener('error', function() {
                this.src = 'https://via.placeholder.com/120x120?text=Sin+Imagen';
            });
        });
        
        // Animación para imprimir recibo
        document.querySelector('.btn-print').addEventListener('click', function(e) {
            e.preventDefault();
            
            // Mostrar mensaje de impresión
            const printMessage = document.createElement('div');
            printMessage.innerHTML = `
                <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                          background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.3);
                          z-index: 10000; text-align: center;">
                    <i class="fa-solid fa-print fa-2x mb-3" style="color: #007bff;"></i>
                    <h5>Preparando para imprimir...</h5>
                    <p>El recibo se abrirá en una nueva ventana.</p>
                </div>
                <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                          background: rgba(0,0,0,0.5); z-index: 9999;"></div>
            `;
            document.body.appendChild(printMessage);
            
            setTimeout(() => {
                window.print();
                printMessage.remove();
            }, 1000);
        });
    });
</script>
</body>
</html>