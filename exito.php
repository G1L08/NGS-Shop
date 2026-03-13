<?php
session_start();
require 'config/db.php';

$session_id = $_GET['session_id'] ?? '';

if (!$session_id) {
    header('Location: index.php');
    exit;
}

$usuario_id = $_SESSION['user_id'] ?? null;

if (!$usuario_id) {
    header('Location: login.php');
    exit;
}

try {
    // Buscar la sesión de checkout en tu base de datos
    $stmt = $pdo->prepare("SELECT * FROM checkout_sessions WHERE stripe_session_id = ? AND usuario_id = ?");
    $stmt->execute([$session_id, $usuario_id]);
    $checkout_session = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($checkout_session) {
        // Actualizar el estado de la sesión a completado
        $stmt_update = $pdo->prepare("UPDATE checkout_sessions SET estado = 'completado', procesado_en = NOW() WHERE id = ?");
        $stmt_update->execute([$checkout_session['id']]);

        // Crear la venta en la tabla ventas
        $stmt_venta = $pdo->prepare("
            INSERT INTO ventas (usuario_id, total, estatus, fecha, tipo_venta, subtotal, iva) 
            VALUES (?, ?, 'pagado', NOW(), 'minorista', ?, ?)
        ");
        
        // Calcular subtotal e IVA (asumiendo IVA 16%)
        $subtotal = $checkout_session['total'] / 1.16;
        $iva = $checkout_session['total'] - $subtotal;
        
        $stmt_venta->execute([$usuario_id, $checkout_session['total'], $subtotal, $iva]);
        $venta_id = $pdo->lastInsertId();

        // Decodificar los items del carrito
        $items = json_decode($checkout_session['items_json'], true);

        // Insertar cada producto en detalle_ventas
        foreach ($items as $item) {
            $stmt_detalle = $pdo->prepare("
                INSERT INTO detalle_ventas (venta_id, producto_id, cantidad, precio_unitario, precio_aplicado) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            // Determinar qué precio se aplicó (puedes calcularlo según cantidad)
            $precio_aplicado = $item['precio_aplicado'] ?? $item['precio'];
            
            $stmt_detalle->execute([
                $venta_id,
                $item['producto_id'],
                $item['cantidad'],
                $item['precio'], // precio unitario original
                $precio_aplicado // precio que realmente pagó
            ]);

            // Actualizar stock (restar la cantidad comprada)
            $stmt_stock = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
            $stmt_stock->execute([$item['cantidad'], $item['producto_id']]);
        }

        // Vaciar el carrito del usuario
        $stmt_vaciar = $pdo->prepare("DELETE FROM carrito WHERE usuario_id = ?");
        $stmt_vaciar->execute([$usuario_id]);

        // Registrar en el historial de pedidos
        $stmt_historial = $pdo->prepare("
            INSERT INTO historial_pedidos (venta_id, estatus_nuevo, fecha_movimiento, comentario) 
            VALUES (?, 'pagado', NOW(), 'Pago completado con Stripe')
        ");
        $stmt_historial->execute([$venta_id]);

        $_SESSION['success'] = "¡Compra realizada con éxito! Tu pedido #" . str_pad($venta_id, 6, '0', STR_PAD_LEFT) . " ha sido registrado.";
    }

} catch (PDOException $e) {
    error_log("Error en exito.php: " . $e->getMessage());
    $_SESSION['error'] = "Hubo un problema al procesar tu compra. Por favor contacta a soporte.";
} catch (Exception $e) {
    error_log("Error general en exito.php: " . $e->getMessage());
    $_SESSION['error'] = "Error inesperado. Contacta a soporte.";
}

// Redirigir al historial de compras
header('Location: account/historial_compras.php');
exit;
?>