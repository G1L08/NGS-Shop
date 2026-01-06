<?php
session_start();
include __DIR__ . '/../config/db.php';

// 1. SEGURIDAD: Solo admin y dueño pueden eliminar
if (!isset($_SESSION['user_rol']) || ($_SESSION['user_rol'] !== 'admin' && $_SESSION['user_rol'] !== 'dueño' && $_SESSION['user_rol'] !== 'dueno')) {
    header('Location: ../index.php');
    exit;
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // 2. OBTENER RUTA DE LA IMAGEN PARA BORRAR EL ARCHIVO
    $stmt = $pdo->prepare("SELECT imagen FROM productos WHERE id = ?");
    $stmt->execute([$id]);
    $producto = $stmt->fetch();

    if ($producto) {
        // Borrar el archivo físico de la imagen si existe
        $ruta_imagen = "../" . $producto['imagen'];
        if (!empty($producto['imagen']) && file_exists($ruta_imagen)) {
            unlink($ruta_imagen);
        }

        // 3. ELIMINAR DE LA BASE DE DATOS
        $stmt_del = $pdo->prepare("DELETE FROM productos WHERE id = ?");
        $stmt_del->execute([$id]);
    }
}

header('Location: productos.php?msg=eliminado');
exit;