<?php
session_start();
require '../config/db.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$usuario_id = $_SESSION['user_id'];
$venta_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$venta_id) {
    header('Location: historial_compras.php');
    exit();
}

// Obtener información de la venta
$stmt_venta = $pdo->prepare("
    SELECT v.*, u.nombre, u.apellido_paterno, u.apellido_materno, u.email, 
           u.telefono_celular, u.rfc, u.calle, u.num_exterior, u.num_interior,
           u.colonia, u.ciudad, u.estado, u.cp, u.razon_social, u.regimen_sat
    FROM ventas v
    JOIN usuarios u ON v.usuario_id = u.id
    WHERE v.id = ? AND v.usuario_id = ?
");
$stmt_venta->execute([$venta_id, $usuario_id]);
$venta = $stmt_venta->fetch(PDO::FETCH_ASSOC);

if (!$venta) {
    header('Location: historial_compras.php');
    exit();
}

// Obtener detalles de la venta
$stmt_detalle = $pdo->prepare("
    SELECT dv.*, p.nombre, p.marca, p.modelo, p.categoria
    FROM detalle_ventas dv
    JOIN productos p ON dv.producto_id = p.id
    WHERE dv.venta_id = ?
");
$stmt_detalle->execute([$venta_id]);
$detalles = $stmt_detalle->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales si no están en la venta
$subtotal = $venta['subtotal'] > 0 ? $venta['subtotal'] : $venta['total'] / 1.16;
$iva = $venta['iva'] > 0 ? $venta['iva'] : $venta['total'] - $subtotal;
$total = $venta['total'];

// Número de factura formateado
$num_factura = 'FAC-' . date('Y') . '-' . str_pad($venta_id, 6, '0', STR_PAD_LEFT);

// Fecha formateada
setlocale(LC_TIME, 'spanish');
$fecha_factura = strftime('%d de %B de %Y', strtotime($venta['fecha']));
$hora_factura = date('H:i', strtotime($venta['fecha']));

// Determinar estatus en español
$estatus_text = [
    'pendiente' => 'Pendiente',
    'pagado' => 'Pagado',
    'enviado' => 'Enviado',
    'entregado' => 'Entregado',
    'cancelado' => 'Cancelado'
];
$estatus = $estatus_text[$venta['estatus']] ?? $venta['estatus'];

// Nombre completo del cliente
$nombre_cliente = trim($venta['nombre'] . ' ' . $venta['apellido_paterno'] . ' ' . $venta['apellido_materno']);
$razon_social = $venta['razon_social'] ?? $nombre_cliente;

// Dirección completa
$direccion = trim($venta['calle'] . ' #' . $venta['num_exterior']);
if (!empty($venta['num_interior'])) $direccion .= ' Int. ' . $venta['num_interior'];
$direccion .= ', ' . $venta['colonia'] . ', ' . $venta['ciudad'] . ', ' . $venta['estado'] . ', CP ' . $venta['cp'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura #<?php echo $num_factura; ?> | NGS Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background-color: white;
                padding: 0.5in;
            }
            .factura-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            min-height: 100vh;
        }

        .factura-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .factura-header {
            background: linear-gradient(135deg, rgb(6 19 37 / 95%), #0a1a2e);
            color: white;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }

        .factura-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .factura-titulo {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            position: relative;
            display: inline-block;
        }

        .factura-titulo::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 60px;
            height: 3px;
            background: #ffc107;
            border-radius: 2px;
        }

        .factura-subtitulo {
            color: rgba(255,255,255,0.8);
            margin-top: 10px;
            font-size: 1rem;
        }

        .factura-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            display: inline-block;
            margin-top: 10px;
        }

        .info-section {
            padding: 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgb(6 19 37 / 95%);
            font-size: 1.2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 2px;
        }

        .info-value {
            font-weight: 600;
            color: #2d3748;
        }

        .table-container {
            padding: 30px;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: rgb(6 19 37 / 95%);
            color: white;
            font-weight: 500;
            border: none;
            padding: 15px;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid #edf2f7;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .totales-section {
            padding: 30px;
            background: #f8f9fa;
            border-top: 2px dashed #dee2e6;
        }

        .totales-grid {
            max-width: 400px;
            margin-left: auto;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .total-row:last-child {
            border-bottom: none;
        }

        .total-row.grand-total {
            font-size: 1.3rem;
            font-weight: 700;
            color: rgb(6 19 37 / 95%);
            padding-top: 15px;
            margin-top: 10px;
            border-top: 2px solid #dee2e6;
        }

        .footer-section {
            padding: 20px 30px;
            background: white;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .btn-imprimir {
            background: rgb(6 19 37 / 95%);
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-imprimir:hover {
            background: #0a1a2e;
            transform: translateY(-2px);
            color: white;
        }

        .btn-volver {
            background: #6c757d;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .btn-volver:hover {
            background: #5a6268;
            transform: translateY(-2px);
            color: white;
        }

        .estatus-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .estatus-pagado { background: #d4edda; color: #155724; }
        .estatus-pendiente { background: #fff3cd; color: #856404; }
        .estatus-enviado { background: #cce5ff; color: #004085; }
        .estatus-entregado { background: #d1e7dd; color: #0f5132; }
        .estatus-cancelado { background: #f8d7da; color: #721c24; }

        .qr-code {
            text-align: right;
        }

        .qr-code img {
            width: 100px;
            height: 100px;
        }

        @media (max-width: 768px) {
            .factura-titulo {
                font-size: 2rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .footer-section {
                flex-direction: column;
            }
            
            .btn-imprimir, .btn-volver {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<div class="factura-container">
    <!-- Header -->
    <div class="factura-header">
        <div style="display: flex; justify-content: space-between; align-items: center; position: relative;">
            <div>
                <h1 class="factura-titulo">
                    <i class="fa-solid fa-file-invoice me-2"></i> FACTURA
                </h1>
                <div class="factura-subtitulo">Comprobante fiscal digital</div>
                <div class="factura-badge">
                    <i class="fa-regular fa-credit-card me-2"></i>
                    <?php echo $num_factura; ?>
                </div>
            </div>
            <div style="text-align: right;">
                <img src="../assets/img/logo-ngs-white.png" alt="NGS Logo" style="height: 50px; opacity: 0.9;" onerror="this.style.display='none'">
                <div style="font-size: 0.8rem; margin-top: 5px;">NGS STORE</div>
            </div>
        </div>
    </div>

    <!-- Información de la factura -->
    <div class="info-section">
        <div class="info-grid">
            <div class="info-item">
                <div class="info-icon">
                    <i class="fa-regular fa-building"></i>
                </div>
                <div class="info-content">
                    <div class="info-label">EMPRESA</div>
                    <div class="info-value">NetGuard Systems</div>
                    <div style="font-size: 0.9rem; color: #4a5568;">RFC: NGS880515XXX</div>
                    <div style="font-size: 0.9rem; color: #4a5568;">Régimen: General de Ley</div>
                </div>
            </div>

            <div class="info-item">
                <div class="info-icon">
                    <i class="fa-regular fa-user"></i>
                </div>
                <div class="info-content">
                    <div class="info-label">CLIENTE</div>
                    <div class="info-value"><?php echo htmlspecialchars($razon_social); ?></div>
                    <div style="font-size: 0.9rem; color: #4a5568;">RFC: <?php echo htmlspecialchars($venta['rfc'] ?? 'XAXX010101000'); ?></div>
                    <div style="font-size: 0.9rem; color: #4a5568;"><?php echo htmlspecialchars($venta['regimen_sat'] ?? 'Público en General'); ?></div>
                </div>
            </div>

            <div class="info-item">
                <div class="info-icon">
                    <i class="fa-regular fa-calendar"></i>
                </div>
                <div class="info-content">
                    <div class="info-label">FECHA Y HORA</div>
                    <div class="info-value"><?php echo $fecha_factura; ?></div>
                    <div style="font-size: 0.9rem; color: #4a5568;"><?php echo $hora_factura; ?> hrs</div>
                </div>
            </div>

            <div class="info-item">
                <div class="info-icon">
                    <i class="fa-solid fa-tag"></i>
                </div>
                <div class="info-content">
                    <div class="info-label">ESTATUS</div>
                    <div class="estatus-badge estatus-<?php echo $venta['estatus']; ?>">
                        <i class="fa-regular <?php 
                            echo $venta['estatus'] == 'pagado' ? 'fa-circle-check' : 
                                ($venta['estatus'] == 'pendiente' ? 'fa-clock' : 
                                ($venta['estatus'] == 'enviado' ? 'fa-truck' : 
                                ($venta['estatus'] == 'entregado' ? 'fa-circle-check' : 'fa-ban'))); 
                        ?> me-2"></i>
                        <?php echo $estatus; ?>
                    </div>
                    <div style="font-size: 0.9rem; color: #4a5568; margin-top: 5px;">
                        Pedido #<?php echo str_pad($venta_id, 8, '0', STR_PAD_LEFT); ?>
                    </div>
                </div>
            </div>

            <div class="info-item" style="grid-column: span 2;">
                <div class="info-icon">
                    <i class="fa-solid fa-location-dot"></i>
                </div>
                <div class="info-content">
                    <div class="info-label">DIRECCIÓN DE ENVÍO</div>
                    <div class="info-value"><?php echo htmlspecialchars($direccion); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de productos -->
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Producto</th>
                    <th>Marca/Modelo</th>
                    <th class="text-center">Cantidad</th>
                    <th class="text-end">Precio Unitario</th>
                    <th class="text-end">Importe</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($detalles as $index => $item): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($item['nombre']); ?></strong>
                        <div style="font-size: 0.85rem; color: #6c757d;">
                            <?php echo htmlspecialchars($item['categoria']); ?>
                        </div>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($item['marca']); ?>
                        <div style="font-size: 0.85rem; color: #6c757d;">
                            <?php echo htmlspecialchars($item['modelo']); ?>
                        </div>
                    </td>
                    <td class="text-center"><?php echo $item['cantidad']; ?></td>
                    <td class="text-end">$<?php echo number_format($item['precio_unitario'], 2); ?></td>
                    <td class="text-end">$<?php echo number_format($item['cantidad'] * $item['precio_unitario'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Totales -->
    <div class="totales-section">
        <div class="totales-grid">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>$<?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="total-row">
                <span>IVA (16%):</span>
                <span>$<?php echo number_format($iva, 2); ?></span>
            </div>
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span>$<?php echo number_format($total, 2); ?></span>
            </div>
            <div style="text-align: right; margin-top: 10px; font-size: 0.85rem; color: #6c757d;">
                <i class="fa-regular fa-clock"></i> 
                Método de pago: Tarjeta (Stripe) - <?php echo ucfirst($venta['tipo_venta'] ?? 'Minorista'); ?>
            </div>
        </div>
    </div>

    <!-- Footer con acciones -->
    <div class="footer-section">
        <div>
            <p class="mb-1" style="color: #6c757d; font-size: 0.9rem;">
                <i class="fa-regular fa-note-sticky"></i> 
                Esta factura es un comprobante fiscal. Conservar para cualquier aclaración.
            </p>
            <p style="color: #6c757d; font-size: 0.8rem; margin: 0;">
                <i class="fa-regular fa-building"></i> 
                NGS Store - RFC: NGS880515XXX - Domicilio: Av. Principal #123, Pachuca, Hgo.
            </p>
        </div>
        
        <div class="no-print" style="display: flex; gap: 10px;">
            <button onclick="window.print()" class="btn-imprimir">
                <i class="fa-solid fa-print"></i> Imprimir
            </button>
            
            <a href="historial_compras.php" class="btn-volver">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <!-- Sello digital (simulado) -->
    <div style="padding: 20px 30px; background: #f8f9fa; border-top: 1px solid #dee2e6; font-size: 0.8rem; color: #6c757d; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <i class="fa-solid fa-certificate me-2"></i>
            Sello digital: 20260222|<?php echo $num_factura; ?>|<?php echo number_format($total, 2); ?>|A1B2C3D4E5F6
        </div>
        <div>
            <i class="fa-regular fa-qrcode me-2"></i>
            Serie: NGS-<?php echo date('Y'); ?> | Folio: <?php echo str_pad($venta_id, 6, '0', STR_PAD_LEFT); ?>
        </div>
    </div>
</div>

<script>
    // Auto-print si se pasa el parámetro print=1 en la URL
    if (window.location.search.includes('print=1')) {
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        }
    }

    // Prevenir que los botones de imprimir cierren la ventana
    document.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
        });
    });
</script>

</body>
</html>