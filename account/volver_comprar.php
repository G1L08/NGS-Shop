<?php
session_start();
require '../config/db.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$usuario_id = $_SESSION['user_id'];

// Verificar si se recibió el ID del pedido
if (!isset($_GET['pedido_id'])) {
    $_SESSION['error'] = "No se especificó el pedido a re-comprar";
    header('Location: mis_pedidos.php');
    exit();
}

$pedido_id = intval($_GET['pedido_id']);

try {
    // Verificar que el pedido pertenece al usuario
    $stmt = $pdo->prepare("SELECT id FROM ventas WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$pedido_id, $usuario_id]);
    
    if ($stmt->rowCount() == 0) {
        $_SESSION['error'] = "El pedido no existe o no pertenece a tu cuenta";
        header('Location: mis_pedidos.php');
        exit();
    }
    
    // Obtener los productos del pedido
    $stmt_detalle = $pdo->prepare("
        SELECT producto_id, cantidad 
        FROM detalle_ventas 
        WHERE venta_id = ?
    ");
    $stmt_detalle->execute([$pedido_id]);
    $productos_pedido = $stmt_detalle->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar stock y agregar al carrito
    $productos_agregados = 0;
    $productos_sin_stock = [];
    
    foreach ($productos_pedido as $producto) {
        // Verificar stock actual
        $stmt_stock = $pdo->prepare("SELECT stock, nombre FROM productos WHERE id = ? AND estado = 'activo'");
        $stmt_stock->execute([$producto['producto_id']]);
        $info_producto = $stmt_stock->fetch(PDO::FETCH_ASSOC);
        
        if ($info_producto && $info_producto['stock'] >= $producto['cantidad']) {
            // Verificar si ya existe en el carrito
            $stmt_existe = $pdo->prepare("SELECT id, cantidad FROM carrito WHERE usuario_id = ? AND producto_id = ?");
            $stmt_existe->execute([$usuario_id, $producto['producto_id']]);
            $existe = $stmt_existe->fetch(PDO::FETCH_ASSOC);
            
            if ($existe) {
                // Actualizar cantidad
                $nueva_cantidad = $existe['cantidad'] + $producto['cantidad'];
                $stmt_update = $pdo->prepare("UPDATE carrito SET cantidad = ? WHERE id = ?");
                $stmt_update->execute([$nueva_cantidad, $existe['id']]);
            } else {
                // Insertar nuevo item
                $stmt_insert = $pdo->prepare("INSERT INTO carrito (usuario_id, producto_id, cantidad) VALUES (?, ?, ?)");
                $stmt_insert->execute([$usuario_id, $producto['producto_id'], $producto['cantidad']]);
            }
            $productos_agregados++;
        } else {
            // Producto sin stock suficiente o inactivo
            $productos_sin_stock[] = [
                'nombre' => $info_producto['nombre'] ?? 'Producto ID ' . $producto['producto_id'],
                'cantidad_solicitada' => $producto['cantidad'],
                'stock_disponible' => $info_producto['stock'] ?? 0
            ];
        }
    }
    
    // Preparar mensaje de éxito/error
    if ($productos_agregados > 0) {
        $_SESSION['success'] = "Se agregaron $productos_agregados productos al carrito";
        
        if (!empty($productos_sin_stock)) {
            $mensaje_stock = " Algunos productos no están disponibles:";
            foreach ($productos_sin_stock as $item) {
                $mensaje_stock .= " {$item['nombre']} (solicitados: {$item['cantidad_solicitada']}, disponibles: {$item['stock_disponible']});";
            }
            $_SESSION['warning'] = trim($mensaje_stock);
        }
        
        // Redirigir al carrito
        header('Location: ../ver_carrito.php');
        exit();
    } else {
        $_SESSION['error'] = "No se pudieron agregar los productos al carrito (sin stock suficiente)";
        header('Location: mis_pedidos.php');
        exit();
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error al procesar la solicitud: " . $e->getMessage();
    header('Location: mis_pedidos.php');
    exit();
}
?>