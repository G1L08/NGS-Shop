<?php
session_start();
// Cambiado a include y verificado que la ruta sea correcta según tus otros archivos
include __DIR__ . '/config/db.php'; 

// 1. VALIDACIÓN DE ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id = (int)$_GET['id']; // Forzamos a entero por seguridad

// 2. OBTENER DATOS DEL PRODUCTO
$stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
$stmt->execute([$id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    die("<div style='background:#0f172a; color:white; text-align:center; padding:50px; height:100vh; font-family:sans-serif;'>
            <h2>Producto no encontrado</h2>
            <p>El producto que buscas no existe o ha sido eliminado.</p>
            <a href='index.php' style='color:#3b82f6; text-decoration:none;'>Volver a la tienda</a>
         </div>");
}

// 3. LÓGICA DEL CONTADOR DEL CARRITO
$total_items = 0;
// Primero verificamos si hay una sesión de usuario (Base de datos)
if (isset($_SESSION['user_id'])) {
    $stmt_cart = $pdo->prepare("SELECT SUM(cantidad) as total FROM carrito WHERE usuario_id = ?");
    $stmt_cart->execute([$_SESSION['user_id']]);
    $row_cart = $stmt_cart->fetch(PDO::FETCH_ASSOC);
    $total_items = $row_cart['total'] ?: 0;
} 
// Si no hay sesión, verificamos si existe un carrito temporal en la variable $_SESSION
elseif (isset($_SESSION['carrito'])) {
    foreach ($_SESSION['carrito'] as $item) {
        $total_items += $item['cantidad'];
    }
}

// 4. RUTA DE LA IMAGEN (Ajustado a tu carpeta uploads)
$img = !empty($producto['imagen']) ? $producto['imagen'] : "https://via.placeholder.com/600x600?text=Sin+Imagen";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($producto['nombre']); ?> | NGS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #3b82f6; --bg-dark: #0f172a; --card-bg: #1e293b; --text-main: #f8fafc; --text-dim: #94a3b8; }
        
        body { margin: 0; padding: 0; font-family: 'Inter', sans-serif; background-color: var(--bg-dark); color: var(--text-main); min-height: 100vh; }

        /* Navbar */
        .navbar {
            padding: 15px 40px;
            background: #1e293b;
            border-bottom: 1px solid #334155;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky; top: 0; z-index: 100;
        }
        .brand { font-size: 1.5rem; font-weight: 700; color: white; letter-spacing: 1px; }
        
        .nav-right { display: flex; align-items: center; gap: 25px; }
        .btn-home { color: var(--text-dim); text-decoration: none; font-weight: 500; transition: 0.3s; }
        .btn-home:hover { color: var(--primary); }

        .cart-btn { position: relative; color: white; text-decoration: none; font-size: 1.3rem; transition: 0.2s; }
        .cart-badge { 
            position: absolute; top: -8px; right: -8px; 
            background: #ef4444; color: white; font-size: 0.7rem; font-weight: bold; 
            padding: 2px 6px; border-radius: 10px; border: 2px solid #1e293b;
        }

        /* Contenedor Principal */
        .main-container {
            max-width: 1100px; margin: 40px auto; padding: 20px;
            display: grid; grid-template-columns: 1fr 1fr; gap: 50px; align-items: start;
        }

        .img-card {
            background-color: var(--card-bg); padding: 20px; border-radius: 16px; border: 1px solid #334155;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3); display: flex; justify-content: center; align-items: center;
        }
        .img-card img { max-width: 100%; height: auto; border-radius: 8px; max-height: 500px; object-fit: contain; }

        .info-section h1 { font-size: 2.5rem; margin: 0 0 10px 0; line-height: 1.2; }
        .category-tag { display: inline-block; background: #334155; color: var(--text-dim); padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; margin-bottom: 20px; text-transform: uppercase; }
        .price { font-size: 2.2rem; font-weight: 700; color: var(--primary); margin-bottom: 20px; }
        .description { color: #cbd5e1; line-height: 1.7; font-size: 1.05rem; margin-bottom: 30px; padding-bottom: 30px; border-bottom: 1px solid #334155; }

        .purchase-controls { display: flex; gap: 15px; align-items: center; }
        .qty-selector { background: var(--bg-dark); border: 1px solid #475569; color: white; padding: 12px; border-radius: 8px; cursor: pointer; }
        
        .btn-add { 
            flex: 1; background-color: var(--primary); color: white; border: none; 
            padding: 14px 25px; border-radius: 8px; font-size: 1rem; font-weight: 600; 
            cursor: pointer; transition: 0.2s; display: flex; justify-content: center; align-items: center; gap: 10px; 
        }
        .btn-add:hover { background-color: #2563eb; transform: translateY(-2px); }
        .btn-add:disabled { background-color: #475569; cursor: not-allowed; transform: none; }

        .stock-badge { display: block; margin-top: 15px; font-size: 0.9rem; color: #22c55e; }
        .stock-badge.out { color: #ef4444; }

        @media (max-width: 768px) { .main-container { grid-template-columns: 1fr; gap: 30px; } }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="brand">NGS STORE</div>
        <div class="nav-right">
            <a href="ver_carrito.php" class="cart-btn">
                <i class="fa-solid fa-cart-shopping"></i>
                <?php if($total_items > 0): ?>
                    <span class="cart-badge"><?php echo $total_items; ?></span>
                <?php endif; ?>
            </a>
            <a href="index.php" class="btn-home">← Volver a la Tienda</a>
        </div>
    </nav>

    <div class="main-container">
        <div class="img-card">
            <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($producto['nombre']); ?>">
        </div>

        <div class="info-section">
            <span class="category-tag"><?php echo htmlspecialchars($producto['categoria']); ?></span>
            <h1><?php echo htmlspecialchars($producto['nombre']); ?></h1>
            <div class="price">$<?php echo number_format($producto['precio'], 2); ?></div>
            
            <div class="description">
                <strong>Marca:</strong> <?php echo htmlspecialchars($producto['marca']); ?><br>
                <strong>Modelo:</strong> <?php echo htmlspecialchars($producto['modelo']); ?><br><br>
                <?php echo nl2br(htmlspecialchars($producto['descripcion'])); ?>
            </div>

            <form action="agregar_carrito.php" method="POST">
                <input type="hidden" name="id" value="<?php echo $producto['id']; ?>">
                
                <?php if ($producto['stock'] > 0): ?>
                    <div class="purchase-controls">
                        <select name="cantidad" class="qty-selector">
                            <?php 
                            // Solo permitir seleccionar hasta el stock disponible o máximo 10
                            $max_val = min($producto['stock'], 10);
                            for($i=1; $i<=$max_val; $i++): 
                            ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="btn-add">
                            <i class="fa-solid fa-cart-plus"></i> Añadir al Carrito
                        </button>
                    </div>
                    <span class="stock-badge">
                        <i class="fa-solid fa-check-circle"></i> Disponible (Stock: <?php echo $producto['stock']; ?>)
                    </span>
                <?php else: ?>
                    <button type="button" disabled class="btn-add">Agotado</button>
                    <span class="stock-badge out"><i class="fa-solid fa-times-circle"></i> Sin stock disponible</span>
                <?php endif; ?>
            </form>
        </div>
    </div>

</body>
</html>