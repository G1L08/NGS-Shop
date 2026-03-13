<?php
session_start();
require '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$usuario_id = $_SESSION['user_id'];

// Leer datos JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['nombre'])) {
    echo json_encode(['error' => 'Nombre requerido']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT id FROM sucursales 
        WHERE usuario_id = ? 
        AND nombre = ?
    ");
    
    $stmt->execute([
        $usuario_id,
        trim($data['nombre'])
    ]);
    
    echo json_encode([
        'duplicado' => $stmt->rowCount() > 0,
        'total' => $stmt->rowCount()
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}