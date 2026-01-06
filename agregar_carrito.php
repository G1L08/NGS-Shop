<?php
session_start();
require 'config/db.php';

// 1. Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['user_id'])) {
    // Si no está logueado, lo mandamos al login
    echo "<script>
        alert('Debes iniciar sesión para agregar productos al carrito.');
        window.location.href = 'login.php';
    </script>";
    exit;
}

// 2. Verificar que lleguen los datos del producto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    
    $usuario_id = $_SESSION['user_id']; // ID del usuario logueado
    $producto_id = (int)$_POST['id'];
    $cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 1;

    try {
        // 3. Revisar si el producto YA existe en el carrito de este usuario
        $stmt = $pdo->prepare("SELECT id, cantidad FROM carrito WHERE usuario_id = ? AND producto_id = ?");
        $stmt->execute([$usuario_id, $producto_id]);
        $item_existente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($item_existente) {
            // SI EXISTE: Actualizamos la cantidad (sumamos la nueva a la que ya tenía)
            $nueva_cantidad = $item_existente['cantidad'] + $cantidad;
            $update = $pdo->prepare("UPDATE carrito SET cantidad = ? WHERE id = ?");
            $update->execute([$nueva_cantidad, $item_existente['id']]);
        } else {
            // NO EXISTE: Lo insertamos como nuevo registro
            $insert = $pdo->prepare("INSERT INTO carrito (usuario_id, producto_id, cantidad) VALUES (?, ?, ?)");
            $insert->execute([$usuario_id, $producto_id, $cantidad]);
        }

        // 4. Redirigir atrás (al producto o al index)
        $pagina_anterior = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        header("Location: $pagina_anterior");
        exit;

    } catch (PDOException $e) {
        die("Error al agregar al carrito: " . $e->getMessage());
    }
} else {
    // Si intentan entrar directo al archivo sin enviar datos
    header('Location: index.php');
}
?>