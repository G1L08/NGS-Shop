<?php
session_start();
include __DIR__ . '/../config/db.php';

$rol = $_SESSION['user_rol'] ?? '';

// Si NO es admin Y TAMPOCO es dueño -> FUERA
if ($rol !== 'admin' && $rol !== 'dueño' && $rol !== 'dueno') {
    header('Location: ../login.php'); 
    exit;
}

// 1. CONFIGURACIÓN DE ALERTAS
$umbral_stock = 5; // Definimos el límite para avisar

// Consultamos productos con stock bajo o agotado
$stmt_notif = $pdo->prepare("SELECT id, nombre, stock, imagen FROM productos WHERE stock <= ? ORDER BY stock ASC");
$stmt_notif->execute([$umbral_stock]);
$alertas = $stmt_notif->fetchAll(PDO::FETCH_ASSOC);
$total_alertas = count($alertas);


// 2. OBTENER ESTADÍSTICAS
$stmt_prod = $pdo->query("SELECT COUNT(*) FROM productos");
$total_productos = $stmt_prod->fetchColumn();

$total_usuarios = 0;
if ($rol === 'dueño' || $rol === 'dueno') {
    $stmt_user = $pdo->query("SELECT COUNT(*) FROM usuarios");
    $total_usuarios = $stmt_user->fetchColumn();
}

// Etiqueta para el saludo
$rol_label = ($rol === 'dueño' || $rol === 'dueno') ? 'DUEÑO' : 'ADMINISTRADOR';
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
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

        /* CONTENIDO PRINCIPAL */
        .main-content { 
            flex: 1; 
            padding: 40px; 
            overflow-y: auto; 
        }

        /* NOTIFICACIONES Y CAMPANA */
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

        /* TARJETA DE BIENVENIDA */
        .welcome-card { 
            background: linear-gradient(135deg, #007bff, #0062cc); 
            color: white; padding: 35px; border-radius: 18px; 
            margin-bottom: 40px; box-shadow: 0 10px 20px rgba(0,123,255,0.2);
        }
        
        .welcome-card h1 { margin: 0; font-size: 2rem; font-weight: 700; }
        .welcome-card p { margin: 8px 0 0; opacity: 0.9; font-size: 1.1rem; }
        .welcome-card b { background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 5px; text-transform: uppercase; font-size: 0.85rem; margin-left: 5px; }

        /* ESTADÍSTICAS */
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

    </style>
</head>
<body>

    <aside class="sidebar">
        <h2><i class="fa-solid fa-shield-halved"></i> ADMIN PANEL</h2>
        
        <nav>
            <a href="index.php" class="menu-item active">
                <i class="fa-solid fa-house"></i> Inicio
            </a>
            <a href="productos.php" class="menu-item">
                <i class="fa-solid fa-box-open"></i> Productos
            </a>
            
            <?php if($rol === 'dueño' || $rol === 'dueno'): ?>
                <a href="usuarios.php" class="menu-item">
                    <i class="fa-solid fa-users-gear"></i> Usuarios
                </a>
            <?php endif; ?>

            <a href="../index.php" class="menu-item" style="margin-top: 20px; border-top: 1px solid #f3f4f6; padding-top: 20px;">
                <i class="fa-solid fa-shop"></i> Ver Tienda
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
                        <span>Productos en Catálogo</span>
                    </div>
                </div>
            </a>

            <?php if($rol === 'dueño' || $rol === 'dueno'): ?>
            <a href="usuarios.php" class="stat-card-link">
                <div class="stat-card">
                    <div class="icon-box green">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_usuarios; ?></h3>
                        <span>Usuarios Registrados</span>
                    </div>
                </div>
            </a>
            <?php endif; ?>
        </div>

    </main>

</body>
</html>