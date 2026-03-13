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
$carrito_id = $input['carrito_id'] ?? null;
$accion = $input['accion'] ?? null;

if (!$carrito_id || !$accion) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

try {
    // Obtener información del item en carrito
    $stmt = $pdo->prepare("SELECT c.*, p.stock, p.precio, p.precio_mayorista, p.minimo_mayorista, p.id as producto_id 
                           FROM carrito c 
                           JOIN productos p ON c.producto_id = p.id 
                           WHERE c.id = ? AND c.usuario_id = ?");
    $stmt->execute([$carrito_id, $_SESSION['user_id']]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
        exit;
    }

    // Calcular nueva cantidad
    $nueva_cantidad = $item['cantidad'];
    if ($accion === 'sumar') {
        $nueva_cantidad++;
    } elseif ($accion === 'restar') {
        $nueva_cantidad--;
    }

    // Validaciones
    if ($nueva_cantidad < 1) {
        echo json_encode(['success' => false, 'error' => 'La cantidad mínima es 1']);
        exit;
    }

    if ($nueva_cantidad > $item['stock']) {
        echo json_encode(['success' => false, 'error' => 'Stock insuficiente. Solo hay ' . $item['stock'] . ' disponibles']);
        exit;
    }

    // Actualizar cantidad en la base de datos
    $stmt_update = $pdo->prepare("UPDATE carrito SET cantidad = ? WHERE id = ?");
    $stmt_update->execute([$nueva_cantidad, $carrito_id]);

    // Calcular precios
    $precio_minorista = floatval($item['precio']);
    $precio_mayorista = $item['precio_mayorista'] ? floatval($item['precio_mayorista']) : $precio_minorista * 0.8;
    $minimo_mayorista = intval($item['minimo_mayorista'] ?? 5);
    
    $es_mayorista = ($nueva_cantidad >= $minimo_mayorista);
    $precio_aplicado = $es_mayorista ? $precio_mayorista : $precio_minorista;
    
    $subtotal_item = $precio_aplicado * $nueva_cantidad;
    $subtotal_minorista_item = $precio_minorista * $nueva_cantidad;
    $ahorro_item = $es_mayorista ? $subtotal_minorista_item - $subtotal_item : 0;

    // Calcular totales generales del carrito
    $stmt_totales = $pdo->prepare("SELECT 
        SUM(CASE WHEN c.cantidad >= p.minimo_mayorista 
            THEN COALESCE(p.precio_mayorista, p.precio) * c.cantidad
            ELSE p.precio * c.cantidad END) as subtotal,
        SUM(CASE WHEN c.cantidad >= p.minimo_mayorista 
            THEN (p.precio * c.cantidad) - (COALESCE(p.precio_mayorista, p.precio) * c.cantidad)
            ELSE 0 END) as ahorro_total,
        COUNT(CASE WHEN c.cantidad >= p.minimo_mayorista THEN 1 END) as productos_mayorista
        FROM carrito c 
        JOIN productos p ON c.producto_id = p.id 
        WHERE c.usuario_id = ?");
    
    $stmt_totales->execute([$_SESSION['user_id']]);
    $totales = $stmt_totales->fetch(PDO::FETCH_ASSOC);
    
    $subtotal_total = floatval($totales['subtotal'] ?? 0);
    $iva_total = $subtotal_total * 0.16;
    $total_final = $subtotal_total + $iva_total;

    echo json_encode([
        'success' => true,
        'cantidad' => $nueva_cantidad,
        'es_mayorista' => $es_mayorista,
        'precio_aplicado' => $precio_aplicado,
        'subtotal_item' => $subtotal_item,
        'ahorro_item' => $ahorro_item,
        'minimo' => $minimo_mayorista,
        'faltan' => max(0, $minimo_mayorista - $nueva_cantidad),
        'alcanzo_limite' => ($nueva_cantidad >= $item['stock']),
        'stock_disponible' => $item['stock'],
        'ahorro_posible' => ($precio_minorista - $precio_mayorista) * ($nueva_cantidad + max(0, $minimo_mayorista - $nueva_cantidad)),
        'subtotal' => $subtotal_total,
        'iva' => $iva_total,
        'total' => $total_final,
        'ahorro_total' => floatval($totales['ahorro_total'] ?? 0),
        'productos_mayorista' => intval($totales['productos_mayorista'] ?? 0)
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>