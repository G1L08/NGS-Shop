<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sesión no iniciada']);
    exit;
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);
$producto_id = $input['producto_id'] ?? null;
$cantidad = $input['cantidad'] ?? 1;
$precio = $input['precio'] ?? null;

if (!$producto_id) {
    echo json_encode(['success' => false, 'error' => 'ID de producto no válido']);
    exit;
}

try {
    // Verificar si el producto ya está en el carrito
    $stmt_check = $pdo->prepare("SELECT id, cantidad FROM carrito WHERE usuario_id = ? AND producto_id = ?");
    $stmt_check->execute([$_SESSION['user_id'], $producto_id]);
    $existe = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($existe) {
        // Actualizar cantidad
        $nueva_cantidad = $existe['cantidad'] + $cantidad;
        $stmt_update = $pdo->prepare("UPDATE carrito SET cantidad = ? WHERE id = ?");
        $stmt_update->execute([$nueva_cantidad, $existe['id']]);
    } else {
        // Insertar nuevo
        $stmt_insert = $pdo->prepare("INSERT INTO carrito (usuario_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
        $stmt_insert->execute([$_SESSION['user_id'], $producto_id, $cantidad, $precio]);
    }

    // Obtener nuevo total del carrito
    $stmt_total = $pdo->prepare("SELECT SUM(cantidad) as total FROM carrito WHERE usuario_id = ?");
    $stmt_total->execute([$_SESSION['user_id']]);
    $total_items = $stmt_total->fetchColumn() ?: 0;

    echo json_encode([
        'success' => true,
        'total_items' => $total_items,
        'message' => 'Producto agregado correctamente'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error en la base de datos']);
}
?>