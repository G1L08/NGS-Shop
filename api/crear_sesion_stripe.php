<?php
session_start();
require_once __DIR__ . '/../config/db.php';
$stripeConfig = require_once __DIR__ . '/../config/stripe.php';

header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$usuario_id = $_SESSION['user_id'];
$tasa_iva = 0.16; // 16% de IVA

try {
    // Obtener items del carrito
    $sql = "SELECT c.*, p.nombre, p.precio, p.precio_mayorista, p.minimo_mayorista 
            FROM carrito c 
            JOIN productos p ON c.producto_id = p.id 
            WHERE c.usuario_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        echo json_encode(['error' => 'Carrito vacío']);
        exit;
    }

    // Obtener información del usuario
    $stmt_user = $pdo->prepare("SELECT email FROM usuarios WHERE id = ?");
    $stmt_user->execute([$usuario_id]);
    $usuario = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // Preparar los items para Stripe (CON IVA INCLUIDO)
    $line_items = [];
    $total_con_iva = 0;

    foreach ($items as $item) {
        $minimo_mayorista = $item['minimo_mayorista'] ?? 5;
        
        // Determinar precio base (sin IVA)
        $precio_base = ($item['cantidad'] >= $minimo_mayorista && $item['precio_mayorista']) 
            ? floatval($item['precio_mayorista']) 
            : floatval($item['precio']);
        
        // 🔴 IMPORTANTE: Calcular precio CON IVA incluido
        $precio_con_iva = $precio_base * (1 + $tasa_iva);
        
        // Calcular subtotal para este item (con IVA)
        $subtotal_item = $precio_con_iva * intval($item['cantidad']);
        $total_con_iva += $subtotal_item;
        
        $line_items[] = [
            'price_data' => [
                'currency' => 'mxn',
                'product_data' => [
                    'name' => $item['nombre'],
                    'description' => "Cantidad: {$item['cantidad']} - " . 
                                   ($item['cantidad'] >= $minimo_mayorista ? 'Mayorista' : 'Minorista') . 
                                   " (IVA incluido)",
                ],
                // Stripe trabaja en centavos, por eso multiplicamos por 100
                'unit_amount' => intval(round($precio_con_iva * 100)),
            ],
            'quantity' => intval($item['cantidad']),
        ];
    }

    // Crear la sesión de Checkout
    $checkout_session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => $line_items,
        'mode' => 'payment',
        'success_url' => 'http://localhost/NGS-Shop-main/exito.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => 'http://localhost/NGS-Shop-main/cancelado.php',
        'customer_email' => $usuario['email'] ?? '',
        'metadata' => [
            'usuario_id' => $usuario_id,
            'total_con_iva' => $total_con_iva
        ]
    ]);
    
    // Guardar en base de datos (opcional)
    $items_json = json_encode($items);
    $stmt_save = $pdo->prepare("INSERT INTO checkout_sessions 
        (usuario_id, items_json, total, estado, creado_en, stripe_session_id) 
        VALUES (?, ?, ?, 'pendiente', NOW(), ?)");
    $stmt_save->execute([$usuario_id, $items_json, $total_con_iva, $checkout_session->id]);
    
    echo json_encode(['id' => $checkout_session->id]);

} catch (\Stripe\Exception\ApiErrorException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe Error: ' . $e->getMessage()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB Error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
?>