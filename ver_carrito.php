<?php
session_start();
require 'config/db.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['user_id'];
$carrito_items = [];
$total_precio = 0;

// Consultar productos del carrito incluyendo el STOCK actual
$sql = "SELECT c.id AS carrito_id, c.cantidad, p.id AS producto_id, p.nombre, p.precio, p.imagen, p.stock 
        FROM carrito c 
        JOIN productos p ON c.producto_id = p.id 
        WHERE c.usuario_id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$usuario_id]);
$carrito_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular total
foreach ($carrito_items as $item) {
    $total_precio += ($item['precio'] * $item['cantidad']);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tu Carrito | NGS STORE</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #0f172a; color: white; font-family: 'Inter', sans-serif; margin: 0; }
        
        .navbar { padding: 20px 40px; background: #1e293b; display: flex; justify-content: space-between; align-items: center; }
        .brand { font-weight: bold; font-size: 1.5rem; }
        .btn-home { color: #94a3b8; text-decoration: none; }

        .container { max-width: 900px; margin: 40px auto; padding: 20px; }
        h1 { border-bottom: 1px solid #334155; padding-bottom: 20px; margin-bottom: 30px; }

        .cart-table { width: 100%; border-collapse: collapse; }
        .cart-table th { text-align: left; padding: 15px; color: #94a3b8; border-bottom: 1px solid #334155; }
        .cart-table td { padding: 15px; border-bottom: 1px solid #334155; vertical-align: middle; }
        
        .product-info { display: flex; align-items: center; gap: 15px; }
        
        /* Ajuste para que las imágenes se vean bien en el carrito */
        .product-img-container { 
            width: 60px; height: 60px; min-width: 60px; 
            background: white; border-radius: 8px; 
            display: flex; justify-content: center; align-items: center; 
            overflow: hidden; 
        }
        .product-img { width: 100%; height: 100%; object-fit: contain; padding: 2px; }
        
        .qty-control { display: flex; align-items: center; gap: 10px; background: #1e293b; width: fit-content; padding: 5px; border-radius: 8px; border: 1px solid #334155; }
        .btn-qty { 
            background: #334155; color: white; border: none; width: 30px; height: 30px; border-radius: 4px; 
            cursor: pointer; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: 0.2s;
        }
        .btn-qty:hover { background: #475569; }
        .btn-qty.disabled { opacity: 0.3; cursor: not-allowed; pointer-events: none; }
        
        .qty-number { font-weight: bold; width: 20px; text-align: center; }

        .btn-delete { color: #ef4444; cursor: pointer; text-decoration: none; }
        .total-section { text-align: right; margin-top: 30px; font-size: 1.5rem; font-weight: bold; }
        .btn-checkout { background: #3b82f6; color: white; padding: 15px 30px; border-radius: 8px; text-decoration: none; font-size: 1rem; display: inline-block; margin-top: 20px; }

        .stock-warning { font-size: 0.75rem; color: #f59e0b; display: block; margin-top: 5px; }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="brand">NGS STORE</div>
        <a href="index.php" class="btn-home">Seguir Comprando</a>
    </nav>

    <div class="container">
        <h1>Tu Carrito de Compras</h1>

        <?php if (count($carrito_items) > 0): ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Precio</th>
                        <th>Cant.</th>
                        <th>Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($carrito_items as $item): 
                        // --- LÓGICA DE IMAGEN ---
                        $img_db = $item['imagen'];
                        $img_final = 'https://via.placeholder.com/60?text=IMG';

                        if (!empty($img_db)) {
                            // Si ya tiene la ruta completa
                            if (strpos($img_db, 'uploads/') === 0) {
                                $img_final = $img_db;
                            } 
                            // Si solo tiene el nombre del archivo
                            else {
                                $img_final = 'uploads/productos/' . $img_db;
                            }
                        }

                        $subtotal = $item['precio'] * $item['cantidad'];
                        $alcanzo_limite = ($item['cantidad'] >= $item['stock']);
                    ?>
                        <tr>
                            <td>
                                <div class="product-info">
                                    <div class="product-img-container">
                                        <img src="<?php echo htmlspecialchars($img_final); ?>" class="product-img" alt="Foto">
                                    </div>
                                    <div>
                                        <span><?php echo htmlspecialchars($item['nombre']); ?></span>
                                        <?php if($alcanzo_limite): ?>
                                            <span class="stock-warning">Máx. alcanzado (<?php echo $item['stock']; ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>$<?php echo number_format($item['precio'], 2); ?></td>
                            
                            <td>
                                <div class="qty-control">
                                    <a href="actualizar_carrito.php?id=<?php echo $item['carrito_id']; ?>&accion=restar" class="btn-qty">
                                        <i class="fa-solid fa-minus" style="font-size: 0.8rem;"></i>
                                    </a>

                                    <span class="qty-number"><?php echo $item['cantidad']; ?></span>

                                    <a href="actualizar_carrito.php?id=<?php echo $item['carrito_id']; ?>&accion=sumar" 
                                       class="btn-qty <?php echo $alcanzo_limite ? 'disabled' : ''; ?>">
                                        <i class="fa-solid fa-plus" style="font-size: 0.8rem;"></i>
                                    </a>
                                </div>
                            </td>

                            <td style="color: #3b82f6; font-weight:bold;">$<?php echo number_format($subtotal, 2); ?></td>
                            <td>
                                <a href="borrar_carrito.php?id=<?php echo $item['carrito_id']; ?>" class="btn-delete">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="total-section">
                Total: <span style="color:#22c55e;">$<?php echo number_format($total_precio, 2); ?></span>
                <br>
                <a href="procesar_compra.php" class="btn-checkout">Proceder al Pago <i class="fa-solid fa-arrow-right"></i></a>
            </div>

        <?php else: ?>
            <div style="text-align:center; padding:50px; color:#94a3b8;">
                <h2>Tu carrito está vacío</h2>
                <a href="index.php" style="color:#3b82f6;">Ir a la tienda</a>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>