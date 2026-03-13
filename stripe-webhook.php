<?php
require_once 'vendor/autoload.php';
require_once 'config/db.php';
$stripeConfig = require_once 'config/stripe.php';

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpoint_secret = 'whsec_tu_webhook_secret'; // Lo obtienes del dashboard de Stripe

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $endpoint_secret
    );
} catch(\UnexpectedValueException $e) {
    http_response_code(400);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit();
}

// Manejar el evento
switch ($event->type) {
    case 'checkout.session.completed':
        $session = $event->data->object;
        
        // Actualizar la sesión en tu BD
        $stmt = $pdo->prepare("UPDATE checkout_sessions 
                               SET estado = 'completado', procesado_en = NOW() 
                               WHERE stripe_session_id = ?");
        $stmt->execute([$session->id]);
        
        // Aquí puedes vaciar el carrito del usuario
        $usuario_id = $session->metadata->usuario_id ?? null;
        if ($usuario_id) {
            $stmt_delete = $pdo->prepare("DELETE FROM carrito WHERE usuario_id = ?");
            $stmt_delete->execute([$usuario_id]);
        }
        break;
        
    case 'checkout.session.async_payment_failed':
        $session = $event->data->object;
        
        $stmt = $pdo->prepare("UPDATE checkout_sessions 
                               SET estado = 'fallido' 
                               WHERE stripe_session_id = ?");
        $stmt->execute([$session->id]);
        break;
}

http_response_code(200);
?>