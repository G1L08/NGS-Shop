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

if (!$data) {
    echo json_encode(['error' => 'Datos inválidos']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT id FROM sucursales 
        WHERE usuario_id = ? 
        AND calle = ? 
        AND num_exterior = ? 
        AND (num_interior = ? OR (num_interior IS NULL AND ? = ''))
        AND cp = ? 
        AND colonia = ? 
        AND ciudad = ? 
        AND estado = ?
    ");
    
    $stmt->execute([
        $usuario_id,
        $data['calle'] ?? '',
        $data['num_exterior'] ?? '',
        $data['num_interior'] ?? '',
        $data['num_interior'] ?? '',
        $data['cp'] ?? '',
        $data['colonia'] ?? '',
        $data['ciudad'] ?? '',
        $data['estado'] ?? ''
    ]);
    
    echo json_encode([
        'duplicado' => $stmt->rowCount() > 0,
        'total' => $stmt->rowCount()
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}