<?php
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['id']) && isset($_GET['accion'])) {
    $carrito_id = $_GET['id'];
    $accion = $_GET['accion'];

    // 1. OBTENER INFORMACIÓN ACTUAL (Cantidad en carrito y Stock real del producto)
    // Hacemos un JOIN para saber cuánto stock tiene el producto ligado a este ítem del carrito
    $stmt = $pdo->prepare("SELECT c.cantidad, p.stock 
                           FROM carrito c 
                           JOIN productos p ON c.producto_id = p.id 
                           WHERE c.id = ?");
    $stmt->execute([$carrito_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item) {
        $cantidad_actual = $item['cantidad'];
        $stock_maximo = $item['stock'];

        // 2. LÓGICA DE SUMAR / RESTAR
        if ($accion == 'sumar') {
            if ($cantidad_actual < $stock_maximo) {
                $cantidad_actual++;
            } else {
                // Opcional: Podrías mandar un mensaje de error si quieres
            }
        } elseif ($accion == 'restar') {
            if ($cantidad_actual > 1) {
                $cantidad_actual--;
            }
        }

        // 3. ACTUALIZAR BASE DE DATOS
        $update = $pdo->prepare("UPDATE carrito SET cantidad = ? WHERE id = ?");
        $update->execute([$cantidad_actual, $carrito_id]);
    }
}

// Volver al carrito
header('Location: ver_carrito.php');
exit;
?>