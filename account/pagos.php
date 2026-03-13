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

// Inicializar mensajes
$success_message = '';
$error_message = '';
$warning_message = '';

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

// Filtrar por estado si se especifica
$estatus_filtro = isset($_GET['estatus']) ? $_GET['estatus'] : 'todos';

// Construir consulta base
$query = "
    SELECT 
        v.*,
        (SELECT COUNT(*) FROM detalle_ventas dv WHERE dv.venta_id = v.id) as total_productos,
        (SELECT SUM(dv.cantidad * dv.precio_unitario) FROM detalle_ventas dv WHERE dv.venta_id = v.id) as subtotal_calculado
    FROM ventas v
    WHERE v.usuario_id = ?
";

$params = [$usuario_id];

// Aplicar filtro de estatus
if ($estatus_filtro !== 'todos' && in_array($estatus_filtro, ['pendiente', 'pagado', 'enviado', 'entregado', 'cancelado'])) {
    $query .= " AND v.estatus = ?";
    $params[] = $estatus_filtro;
}

// Ordenar
$query .= " ORDER BY v.fecha DESC";

// Obtener los pedidos/pagos
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estadísticas
$total_pedidos = count($pagos);
$total_pagado = 0;
$total_pendiente = 0;

foreach ($pagos as $pago) {
    if ($pago['estatus'] === 'pagado' || $pago['estatus'] === 'enviado' || $pago['estatus'] === 'entregado') {
        $total_pagado += floatval($pago['total']);
    } else if ($pago['estatus'] === 'pendiente') {
        $total_pendiente += floatval($pago['total']);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Pagos | NGS Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        :root {
            --ngs-blue: rgb(6 19 37 / 95%);
            --ngs-accent: #0d6efd;
            --bg-light: #f8f9fa;
        }

        body {
            background-color: var(--bg-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .profile-banner {
            background: linear-gradient(rgba(6, 19, 37, 0.9), rgba(6, 19, 37, 0.95)), 
                        url('../assets/img/banner-hero.webp');
            background-size: cover;
            background-position: center;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }

        .profile-banner-content {
            color: white;
        }

        .profile-banner h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
        }

        .profile-banner p {
            font-size: 1rem;
            opacity: 0.9;
        }

        .container-custom {
            max-width: 1400px;
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
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-back {
            background-color: var(--ngs-blue);
            color: white;
        }

        .btn-back:hover {
            background-color: #051225;
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

        /* Tarjetas de resumen */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #eaeaea;
            transition: transform 0.3s ease;
        }

        .summary-card:hover {
            transform: translateY(-3px);
        }

        .summary-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }

        .summary-icon.total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .summary-icon.pagado {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .summary-icon.pendiente {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
        }

        .summary-icon.orders {
            background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
            color: white;
        }

        .summary-title {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .summary-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .summary-change {
            font-size: 0.85rem;
            color: #666;
        }

        /* Filtros */
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #eaeaea;
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .filter-title {
            font-size: 1.3rem;
            color: #333;
            font-weight: 600;
            margin: 0;
        }

        .filter-badges {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .filter-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            border: 1px solid #ddd;
            color: #666;
            background: white;
        }

        .filter-badge:hover {
            background: #f8f9fa;
            color: var(--ngs-blue);
            border-color: var(--ngs-blue);
        }

        .filter-badge.active {
            background: var(--ngs-blue);
            color: white;
            border-color: var(--ngs-blue);
        }

        /* Tabla de pagos */
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #eaeaea;
            margin-bottom: 3rem;
            overflow: hidden;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-header h2 {
            font-size: 1.4rem;
            color: #333;
            margin: 0;
        }

        .table-actions {
            display: flex;
            gap: 0.8rem;
            align-items: center;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            width: 250px;
            font-size: 0.9rem;
        }

        .search-box i {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .btn-export {
            background: white;
            border: 1px solid #ddd;
            color: #666;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-export:hover {
            background: #f8f9fa;
            color: var(--ngs-blue);
            border-color: var(--ngs-blue);
        }

        /* Estilos para DataTables */
        #pagosTable_wrapper {
            margin-top: 1rem;
        }

        #pagosTable {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            margin: 0;
        }

        #pagosTable thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            padding: 1rem;
            font-weight: 600;
            color: #333;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        #pagosTable tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #eee;
        }

        #pagosTable tbody tr:hover {
            background-color: #f8f9fa;
        }

        /* Badges de estatus */
        .estatus-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .estatus-pendiente {
            background-color: #fff3cd;
            color: #856404;
        }

        .estatus-pagado {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .estatus-enviado {
            background-color: #d4edda;
            color: #155724;
        }

        .estatus-entregado {
            background-color: #c3e6cb;
            color: #155724;
        }

        .estatus-cancelado {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Botones de acción */
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-view {
            background-color: var(--ngs-blue);
            color: white;
        }

        .btn-view:hover {
            background-color: #051225;
            color: white;
        }

        .btn-pay {
            background-color: #28a745;
            color: white;
        }

        .btn-pay:hover {
            background-color: #218838;
            color: white;
        }

        .btn-invoice {
            background-color: #6c757d;
            color: white;
        }

        .btn-invoice:hover {
            background-color: #5a6268;
            color: white;
        }

        /* Sin pagos */
        .sin-pagos {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 10px;
            border: 2px dashed #ddd;
        }

        .sin-pagos i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .sin-pagos h3 {
            margin-bottom: 1rem;
            color: #333;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-banner {
                padding: 2rem 0;
            }
            
            .profile-banner h1 {
                font-size: 1.8rem;
            }
            
            .nav-buttons {
                flex-direction: column;
            }
            
            .btn-navigation {
                width: 100%;
                justify-content: center;
            }
            
            .filter-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .table-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .search-box input {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .summary-cards {
                grid-template-columns: 1fr;
            }
            
            .filter-badges {
                justify-content: center;
            }
        }

        /* Animación para el botón de factura */
        .btn-invoice {
            position: relative;
            overflow: hidden;
        }

        .btn-invoice::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.3s, height 0.3s;
        }

        .btn-invoice:active::after {
            width: 100px;
            height: 100px;
        }
    </style>
</head>
<body>

<!-- Banner de perfil -->
<div class="profile-banner">
    <div class="container container-custom">
        <div class="profile-banner-content">
            <h1>Historial de Pagos</h1>
            <p>Consulta y gestiona tus transacciones y facturas</p>
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
            <span>Ir a la Tienda</span>
        </a>
    </div>

    <!-- Mensajes de notificación -->
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($warning_message)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            <?php echo htmlspecialchars($warning_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Resumen de pagos -->
    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-icon total">
                <i class="fa-solid fa-money-bill-wave"></i>
            </div>
            <div class="summary-title">Total Gastado</div>
            <div class="summary-value">$<?php echo number_format($total_pagado, 2); ?></div>
            <div class="summary-change">En todos los pedidos pagados</div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon pagado">
                <i class="fa-solid fa-credit-card"></i>
            </div>
            <div class="summary-title">Total Pagado</div>
            <div class="summary-value">$<?php echo number_format($total_pagado, 2); ?></div>
            <div class="summary-change">Transacciones completadas</div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon pendiente">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div class="summary-title">Por Pagar</div>
            <div class="summary-value">$<?php echo number_format($total_pendiente, 2); ?></div>
            <div class="summary-change">Pedidos pendientes de pago</div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon orders">
                <i class="fa-solid fa-receipt"></i>
            </div>
            <div class="summary-title">Total Pedidos</div>
            <div class="summary-value"><?php echo $total_pedidos; ?></div>
            <div class="summary-change">Desde tu registro</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filter-section">
        <div class="filter-header">
            <h2 class="filter-title">Filtrar por Estatus</h2>
            <div class="filter-badges">
                <a href="?estatus=todos" class="filter-badge <?php echo $estatus_filtro == 'todos' ? 'active' : ''; ?>">
                    Todos
                </a>
                <a href="?estatus=pendiente" class="filter-badge <?php echo $estatus_filtro == 'pendiente' ? 'active' : ''; ?>">
                    Pendientes
                </a>
                <a href="?estatus=pagado" class="filter-badge <?php echo $estatus_filtro == 'pagado' ? 'active' : ''; ?>">
                    Pagados
                </a>
                <a href="?estatus=enviado" class="filter-badge <?php echo $estatus_filtro == 'enviado' ? 'active' : ''; ?>">
                    Enviados
                </a>
                <a href="?estatus=entregado" class="filter-badge <?php echo $estatus_filtro == 'entregado' ? 'active' : ''; ?>">
                    Entregados
                </a>
                <a href="?estatus=cancelado" class="filter-badge <?php echo $estatus_filtro == 'cancelado' ? 'active' : ''; ?>">
                    Cancelados
                </a>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-calendar"></i></span>
                    <input type="date" class="form-control" id="fechaDesde" placeholder="Desde">
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-calendar"></i></span>
                    <input type="date" class="form-control" id="fechaHasta" placeholder="Hasta">
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de pagos -->
    <?php if (empty($pagos)): ?>
        <div class="sin-pagos">
            <i class="fa-solid fa-credit-card"></i>
            <h3>No tienes transacciones registradas</h3>
            <p class="text-muted">Cuando realices tu primer pedido, aparecerá aquí.</p>
            <a href="../index.php" class="btn btn-primary mt-3">
                <i class="fa-solid fa-shopping-cart me-2"></i> Ir a la tienda
            </a>
        </div>
    <?php else: ?>
        <div class="table-container">
            <div class="table-header">
                <h2>Historial de Transacciones</h2>
                <div class="table-actions">
                    <div class="search-box">
                        <i class="fa-solid fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Buscar por ID, monto...">
                    </div>
                    <button class="btn-export" onclick="exportToExcel()">
                        <i class="fa-solid fa-file-excel me-2"></i> Exportar
                    </button>
                </div>
            </div>

            <table id="pagosTable" class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Total</th>
                        <th>Estatus</th>
                        <th>Tipo</th>
                        <th>Productos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pagos as $pago): 
                        $fecha_formateada = date('d/m/Y H:i', strtotime($pago['fecha']));
                        $estatus_text = [
                            'pendiente' => 'Pendiente',
                            'pagado' => 'Pagado',
                            'enviado' => 'Enviado',
                            'entregado' => 'Entregado',
                            'cancelado' => 'Cancelado'
                        ];
                    ?>
                        <tr>
                            <td>
                                <strong>#<?php echo str_pad($pago['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                            </td>
                            <td>
                                <?php echo $fecha_formateada; ?>
                            </td>
                            <td>
                                <strong>$<?php echo number_format($pago['total'], 2); ?></strong>
                            </td>
                            <td>
                                <span class="estatus-badge estatus-<?php echo $pago['estatus']; ?>">
                                    <?php echo $estatus_text[$pago['estatus']] ?? $pago['estatus']; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $tipo_text = [
                                    'minorista' => 'Minorista',
                                    'mayorista' => 'Mayorista'
                                ];
                                echo $tipo_text[$pago['tipo_venta']] ?? $pago['tipo_venta'];
                                ?>
                            </td>
                            <td>
                                <?php echo $pago['total_productos']; ?> producto(s)
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="detalles_compra.php?id=<?php echo $pago['id']; ?>" 
                                       class="btn-action btn-view" 
                                       title="Ver detalles">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    
                                    <?php if ($pago['estatus'] == 'pendiente'): ?>
                                        <a href="../ver_carrito.php?pedido_id=<?php echo $pago['id']; ?>" 
                                           class="btn-action btn-pay" 
                                           title="Pagar pedido">
                                            <i class="fa-solid fa-credit-card"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($pago['estatus'], ['pagado', 'enviado', 'entregado'])): ?>
                                        <button class="btn-action btn-invoice" 
                                                onclick="generarFactura(<?php echo $pago['id']; ?>)" 
                                                title="Descargar factura">
                                            <i class="fa-solid fa-file-invoice"></i> Factura
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Inicializar DataTable
    $(document).ready(function() {
        const table = $('#pagosTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-MX.json'
            },
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Todos"]],
            order: [[1, 'desc']],
            responsive: true,
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            columnDefs: [
                { orderable: true, targets: [0, 1, 2, 3, 4, 5] },
                { orderable: false, targets: [6] }
            ]
        });

        // Buscar en la tabla
        $('#searchInput').on('keyup', function() {
            table.search(this.value).draw();
        });

        // Filtrar por fecha
        $('#fechaDesde, #fechaHasta').on('change', function() {
            const desde = $('#fechaDesde').val();
            const hasta = $('#fechaHasta').val();
            
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    const fecha = data[1]; // Columna de fecha
                    const fechaParts = fecha.split(' ')[0].split('/');
                    const fechaObj = new Date(fechaParts[2], fechaParts[1] - 1, fechaParts[0]);
                    
                    if (desde && hasta) {
                        const desdeObj = new Date(desde);
                        const hastaObj = new Date(hasta);
                        return fechaObj >= desdeObj && fechaObj <= hastaObj;
                    } else if (desde) {
                        const desdeObj = new Date(desde);
                        return fechaObj >= desdeObj;
                    } else if (hasta) {
                        const hastaObj = new Date(hasta);
                        return fechaObj <= hastaObj;
                    }
                    return true;
                }
            );
            table.draw();
            $.fn.dataTable.ext.search.pop();
        });
    });

    // Exportar a Excel
    function exportToExcel() {
        const table = document.getElementById('pagosTable');
        if (!table) return;
        
        Swal.fire({
            title: 'Exportando datos',
            text: 'Preparando archivo Excel...',
            icon: 'info',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        setTimeout(() => {
            const ws = XLSX.utils.table_to_sheet(table);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "HistorialPagos");
            
            const fileName = `historial_pagos_${new Date().toISOString().split('T')[0]}.xlsx`;
            XLSX.writeFile(wb, fileName);
            
            Swal.close();
            
            Swal.fire({
                title: 'Exportación completada',
                text: 'El archivo se ha descargado correctamente',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        }, 500);
    }

    // Función mejorada para generar factura
    function generarFactura(pedidoId) {
        // Verificar si el pedido tiene un estatus válido para factura
        const boton = event.currentTarget;
        boton.disabled = true;
        boton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generando...';
        
        // Mostrar loading
        Swal.fire({
            title: 'Generando Factura',
            text: 'Procesando solicitud, por favor espera...',
            icon: 'info',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            willOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Simular tiempo de procesamiento (en producción esto sería una llamada AJAX real)
        setTimeout(() => {
            // Aquí iría la lógica real para generar la factura
            // window.open(`factura.php?id=${pedidoId}&print=1`, '_blank');
            
            Swal.close();
            
            // Simular descarga exitosa
            Swal.fire({
                title: 'Factura generada',
                html: `
                    <div style="text-align: left; margin: 10px 0;">
                        <p><i class="fa-regular fa-circle-check text-success me-2"></i> Factura generada exitosamente</p>
                        <p><i class="fa-regular fa-file-pdf text-danger me-2"></i> Archivo: FAC-2026-${String(pedidoId).padStart(6, '0')}.pdf</p>
                        <p><i class="fa-regular fa-clock me-2"></i> Fecha: ${new Date().toLocaleDateString('es-MX')}</p>
                    </div>
                `,
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fa-solid fa-download me-2"></i>Descargar',
                cancelButtonText: '<i class="fa-solid fa-eye me-2"></i>Ver en línea',
                showDenyButton: true,
                denyButtonText: '<i class="fa-solid fa-print me-2"></i>Imprimir',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Simular descarga de PDF
                    const link = document.createElement('a');
                    link.href = `factura.php?id=${pedidoId}`;
                    link.target = '_blank';
                    link.download = `factura_${pedidoId}.pdf`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    Swal.fire({
                        title: '¡Descargado!',
                        text: 'La factura se ha descargado correctamente',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else if (result.isDenied) {
                    // Imprimir factura
                    window.open(`factura.php?id=${pedidoId}&print=1`, '_blank');
                    
                    Swal.fire({
                        title: 'Enviando a impresora',
                        text: 'La factura se abrirá en una nueva ventana',
                        icon: 'info',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    // Ver en línea
                    window.open(`factura.php?id=${pedidoId}`, '_blank');
                }
            });
            
            // Restaurar botón
            boton.disabled = false;
            boton.innerHTML = '<i class="fa-solid fa-file-invoice"></i> Factura';
        }, 1500);
    }
</script>

</body>
</html>