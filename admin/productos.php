<?php
session_start();
include __DIR__ . '/../config/db.php';

// 1. SEGURIDAD: Verificar sesión y rol (Admin y Dueño pueden entrar)
if (!isset($_SESSION['user_rol']) || ($_SESSION['user_rol'] !== 'admin' && $_SESSION['user_rol'] !== 'dueño' && $_SESSION['user_rol'] !== 'dueno')) {
    header('Location: ../index.php'); exit;
}

// 2. BUSCADOR Y CONSULTA
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

// Traemos todos los productos. Los activos aparecerán primero.
$base_query = "SELECT *, COALESCE(estado, 'activo') AS estado_real FROM productos";

if ($busqueda) {
    $stmt = $pdo->prepare("$base_query WHERE nombre LIKE ? ORDER BY estado_real ASC, id DESC");
    $stmt->execute(["%$busqueda%"]);
} else {
    $stmt = $pdo->query("$base_query ORDER BY estado_real ASC, id DESC");
}
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Productos | NGS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary: #007bff; 
            --bg-body: #f3f4f6; 
            --text-dark: #1f2937; 
            --text-gray: #6b7280; 
            --white: #ffffff;
            --green-btn: #10b981;
        }
        
        body { margin: 0; font-family: 'Inter', sans-serif; background-color: var(--bg-body); display: flex; height: 100vh; overflow: hidden; }

        /* SIDEBAR (Tu formato antiguo) */
        .sidebar { width: 260px; background: var(--white); border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; padding: 25px 20px; flex-shrink: 0; }
        .sidebar h2 { font-size: 1.3rem; font-weight: 700; margin-bottom: 35px; color: var(--text-dark); display: flex; align-items: center; gap: 10px; }
        .menu-item { display: flex; align-items: center; gap: 12px; padding: 12px 15px; margin-bottom: 8px; color: var(--text-gray); text-decoration: none; border-radius: 10px; font-weight: 500; transition: 0.3s; }
        .menu-item.active { background-color: #eef6ff; color: var(--primary); font-weight: 700; }
        .logout-btn { margin-top: auto; color: #ef4444; font-weight: 600; }

        /* CONTENIDO PRINCIPAL */
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }

        .action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 20px; }
        .search-form { flex: 1; max-width: 400px; }
        .search-input { width: 100%; padding: 12px 20px; border: 1px solid #e5e7eb; border-radius: 12px; outline: none; font-size: 0.95rem; box-sizing: border-box; }
        
        .btn-add { background: var(--primary); color: white; text-decoration: none; padding: 12px 20px; border-radius: 12px; font-weight: 600; transition: 0.3s; }
        .btn-add:hover { background: #0056b3; }

        /* GRID Y TARJETAS (Tu formato antiguo) */
        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 25px; }
        .card { background: var(--white); border-radius: 16px; border: 1px solid #e5e7eb; padding: 20px; transition: 0.3s; position: relative; display: flex; flex-direction: column; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .card-img { width: 100%; height: 180px; object-fit: contain; margin-bottom: 15px; border-radius: 10px; }
        .card-title { margin: 0 0 10px 0; font-size: 1.1rem; font-weight: 700; color: var(--text-dark); }
        .card-price { display: block; font-size: 1.25rem; font-weight: 700; color: var(--primary); margin-bottom: 15px; }

        /* BADGES DE ESTADO */
        .status-badge { font-size: 0.7rem; padding: 4px 10px; border-radius: 6px; font-weight: 700; text-transform: uppercase; }
        .badge-activo { background: #dcfce7; color: #15803d; }
        .badge-inactivo { background: #fee2e2; color: #b91c1c; }

        .btn-edit { display: block; text-align: center; background: #f8fafc; color: var(--text-dark); text-decoration: none; padding: 10px; border-radius: 8px; font-weight: 600; border: 1px solid #e5e7eb; transition: 0.2s; margin-top: 15px; }
        .btn-edit:hover { background: var(--primary); color: white; }

        /* Estilo para productos INACTIVOS */
        .card-inactivo { opacity: 0.75; background-color: #f9fafb; border-style: dashed; }
        .card-inactivo .card-img { filter: grayscale(0.8); }
    </style>
</head>
<body>

    <aside class="sidebar">
        <h2><i class="fa-solid fa-shield-halved"></i> ADMIN PANEL</h2>
        <nav>
            <a href="index.php" class="menu-item"><i class="fa-solid fa-house"></i> Inicio</a>
            <a href="productos.php" class="menu-item active"><i class="fa-solid fa-box-open"></i> Productos</a>
            <?php if(isset($_SESSION['user_rol']) && ($_SESSION['user_rol'] === 'dueño' || $_SESSION['user_rol'] === 'dueno')): ?>
                <a href="usuarios.php" class="menu-item"><i class="fa-solid fa-users-gear"></i> Usuarios</a>
            <?php endif; ?>
            <a href="../index.php" class="menu-item" style="margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 20px;">
                <i class="fa-solid fa-shop"></i> Ver Tienda
            </a>
        </nav>
        <a href="../logout.php" class="menu-item logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión</a>
    </aside>

    <main class="main-content">
        <h2 style="margin-top:0; margin-bottom:20px;">Gestión de Inventario</h2>

        <div class="action-bar">
            <form class="search-form" method="GET">
                <input type="text" name="q" class="search-input" placeholder="Buscar producto..." value="<?php echo htmlspecialchars($busqueda); ?>">
            </form>
            <a href="agregar_producto.php" class="btn-add">+ Nuevo Producto</a>
        </div>

        <div class="grid-container">
            <?php foreach($productos as $p): 
                $estado = $p['estado_real'];
                $esInactivo = ($estado === 'inactivo');
            ?>
            <div class="card <?php echo $esInactivo ? 'card-inactivo' : ''; ?>">
                <img src="../<?php echo !empty($p['imagen']) ? $p['imagen'] : 'https://via.placeholder.com/300?text=Sin+Imagen'; ?>" class="card-img">
                <div class="card-body">
                    <h3 class="card-title"><?php echo htmlspecialchars($p['nombre']); ?></h3>
                    <span class="card-price">$<?php echo number_format($p['precio'], 2); ?></span>
                    
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span class="status-badge <?php echo $esInactivo ? 'badge-inactivo' : 'badge-activo'; ?>">
                            <?php echo $estado; ?>
                        </span>
                        <small style="color:var(--text-gray)"><?php echo $p['stock']; ?> Stock</small>
                    </div>
                    
                    <a href="editar_producto.php?id=<?php echo $p['id']; ?>" class="btn-edit">Editar</a>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if(empty($productos)): ?>
                <div style="grid-column: 1/-1; text-align:center; padding:50px; color:var(--text-gray);">
                    <i class="fa-solid fa-box-open fa-3x" style="margin-bottom:15px; opacity:0.2;"></i>
                    <p>No hay productos registrados con ese criterio.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>