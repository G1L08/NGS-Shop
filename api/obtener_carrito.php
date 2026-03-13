<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'total_items' => 0]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT SUM(cantidad) as total FROM carrito WHERE usuario_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total = $stmt->fetchColumn() ?: 0;

    echo json_encode([
        'success' => true,
        'total_items' => intval($total)
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'total_items' => 0]);
}
?>