<?php
session_start();
require 'config/db.php';

$session_id = $_GET['session_id'] ?? '';

if ($session_id) {
    // Opcional: marcar la sesión como cancelada
    $stmt = $pdo->prepare("UPDATE checkout_sessions SET estado = 'fallido' WHERE stripe_session_id = ?");
    $stmt->execute([$session_id]);
}

$_SESSION['warning'] = "El pago fue cancelado. Tu carrito sigue guardado para cuando quieras completar la compra.";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Cancelado | NGS Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
        .cancel-container { max-width: 500px; margin: 100px auto; text-align: center; padding: 40px; background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .cancel-icon { font-size: 4rem; color: #dc3545; margin-bottom: 20px; }
        h1 { margin-bottom: 20px; color: #343a40; }
        p { color: #6c757d; margin-bottom: 30px; }
        .btn { padding: 12px 30px; margin: 5px; }
    </style>
</head>
<body>
    <div class="cancel-container">
        <div class="cancel-icon">
            <i class="fa-regular fa-circle-xmark"></i>
        </div>
        <h1>Pago Cancelado</h1>
        <p>No te preocupes, tu carrito sigue guardado. Puedes completar tu compra cuando quieras.</p>
        <a href="ver_carrito.php" class="btn btn-primary">
            <i class="fa-solid fa-cart-shopping"></i> Volver al Carrito
        </a>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-store"></i> Seguir Comprando
        </a>
    </div>
</body>
</html>