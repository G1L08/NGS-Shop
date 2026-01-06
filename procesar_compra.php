<?php
session_start();
require 'config/db.php';

// Verificar login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['user_id'];

try {
    // 1. INICIAR TRANSACCIÓN (Para asegurar que todo se haga o nada se haga)
    $pdo->beginTransaction();

    // 2. OBTENER PRODUCTOS DEL CARRITO
    $stmt = $pdo->prepare("SELECT producto_id, cantidad FROM carrito WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($items) == 0) {
        throw new Exception("El carrito está vacío.");
    }

    // 3. RESTAR STOCK (Bucle por cada producto)
    $sql_update = "UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ?";
    $stmt_update = $pdo->prepare($sql_update);

    foreach ($items as $item) {
        // Restamos la cantidad comprada al stock actual
        // La condición "AND stock >= ?" evita que el stock quede negativo
        $stmt_update->execute([$item['cantidad'], $item['producto_id'], $item['cantidad']]);
        
        // Verificar si se actualizó (si no, significa que no había suficiente stock)
        if ($stmt_update->rowCount() == 0) {
            throw new Exception("No hay suficiente stock para el producto ID: " . $item['producto_id']);
        }
    }

    // 4. VACIAR EL CARRITO DEL USUARIO
    $stmt_delete = $pdo->prepare("DELETE FROM carrito WHERE usuario_id = ?");
    $stmt_delete->execute([$usuario_id]);

    // 5. CONFIRMAR CAMBIOS
    $pdo->commit();

    // Redirigir a la página de éxito
    header('Location: compra_exitosa.php');
    exit;

} catch (Exception $e) {
    // Si algo falla, deshacer todo
    $pdo->rollBack();
    die("Error en la compra: " . $e->getMessage() . " <a href='ver_carrito.php'>Volver</a>");
}
?>