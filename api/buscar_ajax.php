<?php
// api/buscar_ajax.php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$termino = $_GET['q'] ?? '';

if (strlen($termino) < 2) {
    echo json_encode(['success' => true, 'resultados' => []]);
    exit;
}

try {
    // Buscar productos que coincidan (solo activos y con stock)
    $stmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.nombre, 
            p.marca, 
            p.modelo, 
            p.precio_minorista as precio,
            p.precio_mayorista,
            p.categoria,
            (SELECT url_imagen 
             FROM producto_imagenes 
             WHERE producto_id = p.id 
               AND clasificacion = 'principal' 
             LIMIT 1) as imagen
        FROM productos p
        WHERE (p.nombre LIKE ? 
               OR p.descripcion LIKE ? 
               OR p.marca LIKE ? 
               OR p.modelo LIKE ? 
               OR p.categoria LIKE ?)
          AND p.estado = 'activo'
          AND p.stock > 0
        ORDER BY 
            CASE 
                WHEN p.nombre LIKE ? THEN 1
                WHEN p.marca LIKE ? THEN 2
                WHEN p.modelo LIKE ? THEN 3
                ELSE 4
            END,
            p.nombre ASC
        LIMIT 8
    ");

    $term = "%$termino%";
    $term_principio = "$termino%";
    
    $stmt->execute([
        $term, $term, $term, $term, $term,
        $term_principio, $term_principio, $term_principio
    ]);
    
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear resultados
    foreach ($resultados as &$item) {
        $item['imagen'] = $item['imagen'] ?? 'https://via.placeholder.com/50x50?text=NGS';
        $item['precio_formato'] = '$' . number_format($item['precio'], 2);
        
        // Resaltar el término de búsqueda
        $item['nombre_resaltado'] = preg_replace(
            '/(' . preg_quote($termino, '/') . ')/i',
            '<strong style="color:#2b6cb0;">$1</strong>',
            htmlspecialchars($item['nombre'])
        );
        
        $item['subtitulo'] = $item['marca'] . ' ' . $item['modelo'];
    }

    echo json_encode([
        'success' => true,
        'resultados' => $resultados,
        'total' => count($resultados)
    ]);

} catch (Exception $e) {
    error_log("Error en búsqueda: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al realizar la búsqueda'
    ]);
}
?>