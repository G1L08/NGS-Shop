<?php
session_start();
require 'config/db.php';

// 1. Obtener contador del carrito
$total_items = 0;
if (isset($_SESSION['user_id'])) {
    $stmt_cart = $pdo->prepare("SELECT SUM(cantidad) as total FROM carrito WHERE usuario_id = ?");
    $stmt_cart->execute([$_SESSION['user_id']]);
    $row_cart = $stmt_cart->fetch(PDO::FETCH_ASSOC);
    $total_items = $row_cart['total'] ?? 0;
}

// 2. Obtener productos
try {
    $sql = "SELECT p.*, COALESCE(c.cantidad, 0) AS en_carrito
            FROM productos p
            LEFT JOIN carrito c ON p.id = c.producto_id AND c.usuario_id = ?
            WHERE p.estado = 'activo'
            ORDER BY p.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id'] ?? 0]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $productos = [];
}

// Datos de sesión
$usuario_id = $_SESSION['user_id'] ?? null;
$nombre_usuario = $_SESSION['nombre'] ?? '';
$rol_actual = $_SESSION['user_rol'] ?? $_SESSION['rol'] ?? '';
$es_staff = in_array($rol_actual, ['admin', 'dueño', 'dueno']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGS | NetGuard Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-body: #061325; --bg-card: #0b1c2c; --bg-nav: rgba(6, 19, 37, 0.95);
            --accent: #3b82f6; --text-main: #ffffff; --text-sec: #94a3b8;
            --border-color: #1e293b; --error: #ef4444; --success: #22c55e;
        }
        body { background-color: var(--bg-body); color: var(--text-main); font-family: 'Inter', sans-serif; margin: 0; }
        
        header { background: var(--bg-nav); padding: 15px 0; border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 100; backdrop-filter: blur(10px); }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .navbar { display: flex; justify-content: space-between; align-items: center; }
        .logo-text { font-weight: 800; font-size: 1.5rem; color: white; text-decoration: none; letter-spacing: -1px; }
        .nav-links { display: flex; align-items: center; gap: 20px; }
        
        .btn { padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: 0.2s; }
        .btn-login { border: 1px solid var(--accent); color: var(--accent); }
        .btn-login:hover { background: var(--accent); color: white; }
        .btn-admin { background: #3b82f6; color: white; border: none; }
        .btn-admin:hover { background: #669cf2ff; }

        .cart-btn { position: relative; color: white; text-decoration: none; font-size: 1.2rem; margin-right: 10px; }
        .cart-badge { 
            position: absolute; top: -8px; right: -8px; 
            background: #ef4444; color: white; 
            font-size: 0.7rem; font-weight: bold; 
            padding: 2px 6px; border-radius: 10px; 
            border: 2px solid var(--bg-nav);
        }

        /* --- SECCIÓN HERO CORREGIDA --- */
        .hero { 
            text-align: center; 
            padding: 140px 20px; 
            /* Capa oscura + Imagen de fondo */
            background: linear-gradient(rgba(6, 19, 37, 0.7), rgba(6, 19, 37, 0.9)), 
                        url('assets/img/banner-hero.webp'); 
            background-size: cover; 
            background-position: center; 
            background-attachment: fixed;
            border-bottom: 1px solid var(--border-color);
        }

        .hero h1 { 
            font-size: 3.5rem; 
            margin: 0; 
            font-weight: 800;
            background: linear-gradient(to right, #ffffff, #3b82f6); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
            text-shadow: 0 10px 20px rgba(0,0,0,0.4);
        }
        
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 30px; padding: 40px 0; }
        
        .card { 
            background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; 
            transition: 0.3s; display: flex; flex-direction: column;
        }
        .card:hover { transform: translateY(-5px); border-color: var(--accent); box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        
        .card-img-container {
            width: 100%; height: 220px; background: white; 
            display: flex; justify-content: center; align-items: center; overflow: hidden;
        }
        .card-img { width: 100%; height: 100%; object-fit: contain; padding: 10px; box-sizing: border-box; }
        
        .card-body { padding: 20px; flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
        .card-title { margin: 0 0 10px 0; font-size: 1.1rem; font-weight: 600; }
        .price { color: var(--accent); font-size: 1.4rem; font-weight: 700; margin-bottom: 10px; }

        .stock-info { font-size: 0.9rem; margin-bottom: 12px; min-height: 24px; }
        .stock-available { color: var(--success); }
        .stock-low { color: #f59e0b; }
        .stock-out { color: var(--error); font-weight: bold; }

        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .btn-detail { 
            background: transparent; border: 1px solid var(--border-color); color: var(--text-sec); 
            text-align: center; padding: 10px; border-radius: 6px; text-decoration: none; font-size: 0.9rem;
        }
        .btn-detail:hover { background: var(--border-color); color: white; }

        .btn-add-cart { 
            background: var(--accent); border: none; color: white; 
            padding: 10px; border-radius: 6px; cursor: pointer; font-weight: 600;
            transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .btn-add-cart:hover { background: #2563eb; }
        .btn-add-cart:disabled { background: #4b5563; cursor: not-allowed; opacity: 0.7; }

        footer { text-align: center; padding: 40px; color: var(--text-sec); border-top: 1px solid var(--border-color); margin-top: 50px; }
    </style>
</head>
<body>

    <header>
        <div class="container navbar">
            <a href="index.php" class="logo-text">NeguardSistems</a>
            
            <nav class="nav-links">
                <a href="ver_carrito.php" class="cart-btn">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <?php if($total_items > 0): ?>
                        <span class="cart-badge"><?php echo $total_items; ?></span>
                    <?php endif; ?>
                </a>

                <?php if ($usuario_id): ?>
                    <span style="font-size:0.9rem; color:#94a3b8;">Hola, <b style="color:white;"><?php echo htmlspecialchars($nombre_usuario); ?></b></span>
                    <?php if ($es_staff): ?>
                        <a href="admin/index.php" class="btn btn-admin">PANEL</a>
                    <?php endif; ?>
                    <a href="logout.php" style="color:#ef4444; text-decoration:none; font-size:0.9rem;">Salir</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-login">ENTRAR</a>
                    <a href="registro.php" class="btn" style="background:white; color:#061325;">REGISTRO</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <div class="hero">
        <div class="container">
            <h1>HARDWARE DE ALTO NIVEL</h1>
            <p style="color:#fff; font-size: 1.2rem; margin-top:15px; font-weight: 500;">Potencia tu equipo con los mejores componentes.</p>
        </div>
    </div>

    <section class="container">
        <div class="grid">
            <?php foreach($productos as $p): 
                $disponible = $p['stock'] - $p['en_carrito'];
                $sin_stock = $disponible <= 0;

                $img_db = $p['imagen'];
                $img_final = 'https://via.placeholder.com/300?text=Sin+Imagen'; 

                if (!empty($img_db)) {
                    if (strpos($img_db, 'uploads/') === 0) {
                        $img_final = $img_db;
                    } 
                    else {
                        $img_final = 'uploads/productos/' . $img_db;
                    }
                }
            ?>
                <div class="card">
                    <div class="card-img-container">
                        <img src="<?php echo htmlspecialchars($img_final); ?>" class="card-img" alt="<?= htmlspecialchars($p['nombre']) ?>">
                    </div>

                    <div class="card-body">
                        <div>
                            <h3 class="card-title"><?= htmlspecialchars($p['nombre']) ?></h3>
                            <span class="price">$<?= number_format($p['precio'], 2) ?></span>
                        </div>

                        <div class="stock-info">
                            <?php if ($sin_stock): ?>
                                <span class="stock-out">Sin stock</span>
                            <?php elseif ($disponible <= 3): ?>
                                <span class="stock-low">¡Últimas <?= $disponible ?> unidades!</span>
                            <?php else: ?>
                                <span class="stock-available">Disponibles: <?= $disponible ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="actions">
                            <a href="producto.php?id=<?= $p['id'] ?>" class="btn-detail">Ver Detalles</a>
                            
                            <form action="agregar_carrito.php" method="POST">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn-add-cart" <?= $sin_stock ? 'disabled' : '' ?>>
                                    <i class="fa-solid fa-plus"></i> Añadir
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <footer>
        <p>&copy; <?= date('Y') ?> NetGuard Systems. Todos los derechos reservados.</p>
    </footer>
</body>
</html>