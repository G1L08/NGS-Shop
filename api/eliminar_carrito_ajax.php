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
$tasa_iva = 0.16;

try {
    // Obtener items del carrito con toda la información
    $sql = "SELECT c.*, p.nombre, p.precio, p.precio_mayorista, p.minimo_mayorista, p.stock, p.id as producto_id
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

    // Preparar los items para Stripe y guardar información completa
    $line_items = [];
    $total_con_iva = 0;
    $items_para_guardar = [];

    foreach ($items as $item) {
        $minimo_mayorista = $item['minimo_mayorista'] ?? 5;
        
        // Determinar qué precio aplica (mayorista o minorista)
        $es_mayorista = ($item['cantidad'] >= $minimo_mayorista && $item['precio_mayorista']);
        $precio_base = $es_mayorista ? floatval($item['precio_mayorista']) : floatval($item['precio']);
        
        // Calcular precio con IVA
        $precio_con_iva = $precio_base * (1 + $tasa_iva);
        $subtotal_item = $precio_con_iva * intval($item['cantidad']);
        $total_con_iva += $subtotal_item;
        
        $line_items[] = [
            'price_data' => [
                'currency' => 'mxn',
                'product_data' => [
                    'name' => $item['nombre'],
                    'description' => "Cantidad: {$item['cantidad']} - " . ($es_mayorista ? 'Mayorista' : 'Minorista'),
                ],
                'unit_amount' => intval(round($precio_con_iva * 100)),
            ],
            'quantity' => intval($item['cantidad']),
        ];

        // Guardar información detallada para después
        $items_para_guardar[] = [
            'producto_id' => $item['producto_id'],
            'nombre' => $item['nombre'],
            'cantidad' => $item['cantidad'],
            'precio' => $item['precio'], // precio original minorista
            'precio_mayorista' => $item['precio_mayorista'],
            'precio_aplicado' => $precio_base, // precio que realmente pagó (sin IVA)
            'es_mayorista' => $es_mayorista,
            'minimo_mayorista' => $minimo_mayorista
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
    
    // Guardar en base de datos con toda la información
    $items_json = json_encode($items_para_guardar);
    $stmt_save = $pdo->prepare("
        INSERT INTO checkout_sessions 
        (usuario_id, items_json, total, estado, creado_en, stripe_session_id) 
        VALUES (?, ?, ?, 'pendiente', NOW(), ?)
    ");
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