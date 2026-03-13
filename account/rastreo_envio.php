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

// Verificar si se recibió el ID del pedido
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No se especificó el pedido";
    header('Location: mis_pedidos.php');
    exit();
}

$pedido_id = intval($_GET['id']);

try {
    // Obtener información del pedido
    $stmt = $pdo->prepare("
        SELECT v.*, 
               CONCAT(u.nombre, ' ', u.apellido_paterno) as nombre_cliente,
               u.calle,
               u.num_exterior,
               u.num_interior,
               u.colonia,
               u.ciudad,
               u.estado,
               u.cp
        FROM ventas v
        JOIN usuarios u ON v.usuario_id = u.id
        WHERE v.id = ? AND v.usuario_id = ? AND v.estatus IN ('enviado', 'entregado')
    ");
    $stmt->execute([$pedido_id, $usuario_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
        $_SESSION['error'] = "El pedido no está en proceso de envío o no tienes permisos para verlo";
        header('Location: mis_pedidos.php');
        exit();
    }
    
    // Obtener información del envío (simulada por ahora)
    $envio_info = generarInfoEnvioSimulada($pedido_id, $pedido);
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error al cargar la información: " . $e->getMessage();
    header('Location: mis_pedidos.php');
    exit();
}

// Función para generar información de envío simulada
function generarInfoEnvioSimulada($pedido_id, $pedido) {
    $info_base = [
        'numero_guia' => 'NG' . str_pad($pedido_id, 8, '0', STR_PAD_LEFT) . 'MX',
        'empresa_mensajeria' => 'Estafeta',
        'fecha_envio' => date('Y-m-d H:i:s', strtotime('-2 days')),
        'fecha_estimada_entrega' => date('Y-m-d H:i:s', strtotime('+3 days')),
        'peso' => rand(1, 10) . ' kg',
        'paquetes' => rand(1, 3),
        'contacto_mensajeria' => '800-123-4567',
        'url_seguimiento' => 'https://rastreo.estafeta.com/?guia=NG' . str_pad($pedido_id, 8, '0', STR_PAD_LEFT) . 'MX'
    ];
    
    // Verificar si $pedido está definido
    if (!$pedido) {
        // Si no hay datos de pedido, usar valores por defecto
        $ciudad = 'CDMX';
        $estado_actual = 'enviado';
    } else {
        $ciudad = $pedido['ciudad'] ?? 'CDMX';
        $estado_actual = $pedido['estatus'] ?? 'enviado';
    }
    
    // Generar historial de seguimiento
    $historial = [
        [
            'fecha' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'estado' => 'Enviado',
            'ubicacion' => 'Centro de distribución NGS',
            'descripcion' => 'Paquete recibido por la mensajería'
        ],
        [
            'fecha' => date('Y-m-d H:i:s', strtotime('-1 days')),
            'estado' => 'En tránsito',
            'ubicacion' => 'Planta de clasificación CDMX',
            'descripcion' => 'Paquete en proceso de clasificación'
        ],
        [
            'fecha' => date('Y-m-d H:i:s', strtotime('-12 hours')),
            'estado' => 'En tránsito',
            'ubicacion' => 'Centro de distribución ' . $ciudad,
            'descripcion' => 'Paquete en camino a la ciudad destino'
        ],
        [
            'fecha' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'estado' => 'En reparto',
            'ubicacion' => 'Oficina local ' . $ciudad,
            'descripcion' => 'Paquete asignado a repartidor'
        ]
    ];
    
    // Si ya fue entregado, agregar evento de entrega
    if ($estado_actual == 'entregado') {
        $historial[] = [
            'fecha' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'estado' => 'Entregado',
            'ubicacion' => $ciudad,
            'descripcion' => 'Paquete entregado al destinatario'
        ];
    }
    
    $info_base['historial'] = $historial;
    return $info_base;
}

// Formatear fechas
$fecha_envio = date('d/m/Y H:i', strtotime($envio_info['fecha_envio']));
$fecha_estimada = date('d/m/Y H:i', strtotime($envio_info['fecha_estimada_entrega']));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rastreo Envío #<?php echo str_pad($pedido_id, 6, '0', STR_PAD_LEFT); ?> | NGS Store</title>
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
        }

        /* Banner de perfil */
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

        /* Contenedor principal */
        .container-custom {
            max-width: 1200px;
        }

        /* Tarjetas */
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #eaeaea;
        }

        .card-title {
            color: var(--ngs-blue);
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f0f0f0;
        }

        /* Info de envío */
        .envio-header {
            background: linear-gradient(135deg, var(--ngs-blue), #0d6efd);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .numero-guia {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .estado-envio {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            font-weight: 500;
        }

        /* Timeline de seguimiento */
        .tracking-timeline {
            position: relative;
            padding: 1rem 0;
        }

        .tracking-timeline::before {
            content: '';
            position: absolute;
            left: 1.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }

        .tracking-step {
            position: relative;
            padding-left: 3rem;
            margin-bottom: 1.5rem;
        }

        .tracking-step:last-child {
            margin-bottom: 0;
        }

        .tracking-step.active .step-icon {
            background: var(--ngs-blue);
            color: white;
            border-color: var(--ngs-blue);
        }

        .tracking-step.completed .step-icon {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }

        .step-icon {
            position: absolute;
            left: 1rem;
            top: 0;
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            background: white;
            border: 2px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            z-index: 2;
        }

        .step-content {
            padding: 0.5rem 0;
        }

        .step-title {
            font-weight: 600;
            margin-bottom: 0.2rem;
        }

        .step-location {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
        }

        .step-date {
            color: #6c757d;
            font-size: 0.85rem;
        }

        .step-desc {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.3rem;
        }

        /* Información detallada */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-item {
            padding: 0.8rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .info-label {
            font-weight: 600;
            color: #555;
            font-size: 0.85rem;
            text-transform: uppercase;
            margin-bottom: 0.3rem;
        }

        .info-value {
            color: #333;
            font-size: 1.1rem;
        }

        /* Mapa (simulado) */
        .map-container {
            height: 200px;
            background: #e9ecef;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .map-point {
            position: absolute;
            width: 12px;
            height: 12px;
            background: #dc3545;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .map-origin {
            top: 30%;
            left: 20%;
        }

        .map-destination {
            top: 70%;
            right: 20%;
        }

        .map-route {
            position: absolute;
            top: 40%;
            left: 25%;
            right: 25%;
            height: 3px;
            background: var(--ngs-blue);
            border-radius: 3px;
        }

        .map-route::after {
            content: '';
            position: absolute;
            right: -5px;
            top: -4px;
            width: 10px;
            height: 10px;
            border-right: 3px solid var(--ngs-blue);
            border-top: 3px solid var(--ngs-blue);
            transform: rotate(45deg);
        }

        /* Botones */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .btn-action {
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
            background-color: #6c757d;
            color: white;
        }

        .btn-back:hover {
            background-color: #5a6268;
            color: white;
            transform: translateY(-2px);
        }

        .btn-external {
            background-color: #17a2b8;
            color: white;
        }

        .btn-external:hover {
            background-color: #138496;
            color: white;
            transform: translateY(-2px);
        }

        .btn-detail {
            background-color: var(--ngs-blue);
            color: white;
        }

        .btn-detail:hover {
            background-color: #051225;
            color: white;
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-banner {
                padding: 2rem 0;
            }
            
            .profile-banner h1 {
                font-size: 1.8rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
            }
            
            .tracking-step {
                padding-left: 2.5rem;
            }
            
            .step-icon {
                width: 2.5rem;
                height: 2.5rem;
                left: 0.75rem;
            }
        }
    </style>
</head>
<body>

<!-- Banner de perfil -->
<div class="profile-banner">
    <div class="container container-custom">
        <div class="profile-banner-content">
            <h1>Rastreo de Envío</h1>
            <p>Pedido #<?php echo str_pad($pedido_id, 6, '0', STR_PAD_LEFT); ?></p>
        </div>
    </div>
</div>

<div class="container container-custom">
    <!-- Encabezado del envío -->
    <div class="envio-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="mb-2">
                    <small class="opacity-75">Número de guía</small>
                    <div class="numero-guia"><?php echo $envio_info['numero_guia']; ?></div>
                </div>
                <div class="mb-2">
                    <small class="opacity-75">Mensajería</small>
                    <div class="h5"><?php echo $envio_info['empresa_mensajeria']; ?></div>
                </div>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="estado-envio">
                    <i class="fa-solid fa-truck"></i>
                    <span><?php echo $pedido['estatus'] == 'entregado' ? 'Entregado' : 'En camino'; ?></span>
                </div>
                <div class="mt-3">
                    <div class="opacity-75">Fecha estimada de entrega</div>
                    <div class="h5"><?php echo $fecha_estimada; ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Timeline de seguimiento -->
        <div class="col-lg-8">
            <div class="info-card">
                <h3 class="card-title">
                    <i class="fa-solid fa-map-location-dot me-2"></i>Seguimiento del envío
                </h3>
                
                <div class="tracking-timeline">
                    <?php 
                    $total_pasos = count($envio_info['historial']);
                    $paso_actual = 0;
                    foreach($envio_info['historial'] as $index => $evento): 
                        $fecha_evento = date('d/m/Y H:i', strtotime($evento['fecha']));
                        $clase_paso = '';
                        
                        // Determinar si el paso está activo o completado
                        if ($index == $total_pasos - 1) {
                            $clase_paso = $pedido['estatus'] == 'entregado' ? 'completed' : 'active';
                            $paso_actual = $index;
                        } elseif ($index < $total_pasos - 1) {
                            $clase_paso = 'completed';
                        }
                    ?>
                        <div class="tracking-step <?php echo $clase_paso; ?>">
                            <div class="step-icon">
                                <?php if ($evento['estado'] == 'Enviado'): ?>
                                    <i class="fa-solid fa-box"></i>
                                <?php elseif ($evento['estado'] == 'En tránsito'): ?>
                                    <i class="fa-solid fa-truck-moving"></i>
                                <?php elseif ($evento['estado'] == 'En reparto'): ?>
                                    <i class="fa-solid fa-truck-fast"></i>
                                <?php elseif ($evento['estado'] == 'Entregado'): ?>
                                    <i class="fa-solid fa-check"></i>
                                <?php endif; ?>
                            </div>
                            <div class="step-content">
                                <div class="step-title"><?php echo htmlspecialchars($evento['estado']); ?></div>
                                <div class="step-location">
                                    <i class="fa-solid fa-location-dot me-1"></i>
                                    <?php echo htmlspecialchars($evento['ubicacion']); ?>
                                </div>
                                <div class="step-date">
                                    <i class="fa-solid fa-clock me-1"></i>
                                    <?php echo $fecha_evento; ?>
                                </div>
                                <div class="step-desc"><?php echo htmlspecialchars($evento['descripcion']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Progreso -->
                <div class="mt-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Progreso del envío</span>
                        <span><?php echo $paso_actual + 1; ?> de <?php echo $total_pasos; ?> pasos</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar" 
                             role="progressbar" 
                             style="width: <?php echo (($paso_actual + 1) / $total_pasos) * 100; ?>%"
                             aria-valuenow="<?php echo (($paso_actual + 1) / $total_pasos) * 100; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100"></div>
                    </div>
                </div>
            </div>

            <!-- Mapa simulado -->
            <div class="info-card mt-3">
                <h3 class="card-title">
                    <i class="fa-solid fa-map me-2"></i>Ruta del envío
                </h3>
                <div class="map-container">
                    <div class="map-point map-origin"></div>
                    <div class="map-point map-destination"></div>
                    <div class="map-route"></div>
                    <div class="text-center">
                        <i class="fa-solid fa-truck fa-3x text-muted"></i>
                        <div class="mt-2 text-muted">Ruta de envío</div>
                        <small class="text-muted">Centro de distribución → <?php echo htmlspecialchars($pedido['ciudad']); ?></small>
                    </div>
                </div>
                <div class="row mt-3 text-center">
                    <div class="col">
                        <div class="text-muted">Origen</div>
                        <div class="fw-bold">Centro de distribución NGS</div>
                    </div>
                    <div class="col">
                        <div class="text-muted">Destino</div>
                        <div class="fw-bold"><?php echo htmlspecialchars($pedido['ciudad']); ?>, <?php echo htmlspecialchars($pedido['estado']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información detallada -->
        <div class="col-lg-4">
            <div class="info-card">
                <h3 class="card-title">
                    <i class="fa-solid fa-info-circle me-2"></i>Información del envío
                </h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Número de guía</div>
                        <div class="info-value"><?php echo $envio_info['numero_guia']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Mensajería</div>
                        <div class="info-value"><?php echo $envio_info['empresa_mensajeria']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Fecha de envío</div>
                        <div class="info-value"><?php echo $fecha_envio; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Entrega estimada</div>
                        <div class="info-value"><?php echo $fecha_estimada; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Peso</div>
                        <div class="info-value"><?php echo $envio_info['peso']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Paquetes</div>
                        <div class="info-value"><?php echo $envio_info['paquetes']; ?></div>
                    </div>
                </div>
            </div>

            <div class="info-card mt-3">
                <h3 class="card-title">
                    <i class="fa-solid fa-user me-2"></i>Destinatario
                </h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Nombre</div>
                        <div class="info-value"><?php echo htmlspecialchars($pedido['nombre_cliente']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Dirección</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($pedido['calle']); ?> 
                            <?php echo htmlspecialchars($pedido['num_exterior']); ?>
                            <?php if (!empty($pedido['num_interior'])): ?>
                                Int. <?php echo htmlspecialchars($pedido['num_interior']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Colonia</div>
                        <div class="info-value"><?php echo htmlspecialchars($pedido['colonia']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Ciudad</div>
                        <div class="info-value"><?php echo htmlspecialchars($pedido['ciudad']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Estado</div>
                        <div class="info-value"><?php echo htmlspecialchars($pedido['estado']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Código Postal</div>
                        <div class="info-value"><?php echo htmlspecialchars($pedido['cp']); ?></div>
                    </div>
                </div>
            </div>

            <div class="info-card mt-3">
                <h3 class="card-title">
                    <i class="fa-solid fa-phone me-2"></i>Soporte
                </h3>
                <div class="text-center">
                    <div class="h4 mb-3"><?php echo $envio_info['empresa_mensajeria']; ?></div>
                    <div class="mb-3">
                        <i class="fa-solid fa-phone fa-2x text-primary"></i>
                        <div class="mt-2"><?php echo $envio_info['contacto_mensajeria']; ?></div>
                    </div>
                    <p class="text-muted small">Para consultas sobre el seguimiento, contacta directamente a la mensajería.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Botones de acción -->
    <div class="action-buttons">
        <a href="mis_pedidos.php" class="btn-action btn-back">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Volver a Mis Pedidos</span>
        </a>
        
        <a href="<?php echo $envio_info['url_seguimiento']; ?>" 
           target="_blank" 
           class="btn-action btn-external">
            <i class="fa-solid fa-external-link-alt"></i>
            <span>Seguimiento en sitio oficial</span>
        </a>
        
        <a href="detalles_compra.php?id=<?php echo $pedido_id; ?>" class="btn-action btn-detail">
            <i class="fa-solid fa-file-invoice"></i>
            <span>Ver detalle del pedido</span>
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Actualizar automáticamente cada 30 segundos
    setTimeout(function() {
        location.reload();
    }, 30000);
    
    // Mostrar notificación si el paquete fue entregado
    <?php if ($pedido['estatus'] == 'entregado'): ?>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            showNotification('¡Pedido entregado!', 'Tu pedido ha sido entregado exitosamente.', 'success');
        }, 1000);
    });
    <?php endif; ?>
    
    function showNotification(title, message, type) {
        // Crear notificación
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
        notification.innerHTML = `
            <strong>${title}</strong><br>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(notification);
        
        // Auto-eliminar después de 5 segundos
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
</script>
</body>
</html>