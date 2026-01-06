<?php
session_start();
require 'config/db.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['user_id'];

// Solo procesar si viene el parámetro 'id' por GET
if (isset($_GET['id'])) {
    $carrito_id = (int)$_GET['id']; // Convertir a entero para seguridad

    try {
        // Preparar la consulta para evitar inyección SQL
        $stmt = $pdo->prepare("DELETE FROM carrito 
                               WHERE id = ? AND usuario_id = ?");
        
        $stmt->execute([$carrito_id, $usuario_id]);

        // Mensaje de éxito opcional
        $_SESSION['mensaje_exito'] = "Producto eliminado del carrito.";

    } catch (Exception $e) {
        // En caso de error (poco probable, pero por seguridad)
        $_SESSION['mensaje_error'] = "Error al eliminar el producto del carrito.";
    }
} else {
    $_SESSION['mensaje_error'] = "No se especificó el producto a eliminar.";
}

// Redirigir siempre de vuelta al carrito
header('Location: ver_carrito.php');
exit;
?>