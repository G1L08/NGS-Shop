<?php
session_start();
include __DIR__ . '/../config/db.php';

// 1. SEGURIDAD: Verificar sesión y rol (Admin y Dueño pueden entrar)
if (!isset($_SESSION['user_rol']) || ($_SESSION['user_rol'] !== 'admin' && $_SESSION['user_rol'] !== 'dueño' && $_SESSION['user_rol'] !== 'dueno')) {
    header('Location: ../index.php'); exit;
}

// 2. BUSCADOR Y CONSULTA
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

// Traemos todos los productos. Los activos aparecerán primero.
$base_query = "SELECT *, COALESCE(estado, 'activo') AS estado_real FROM productos";

if ($busqueda) {
    $stmt = $pdo->prepare("$base_query WHERE nombre LIKE ? ORDER BY estado_real ASC, id DESC");
    $stmt->execute(["%$busqueda%"]);
} else {
    $stmt = $pdo->query("$base_query ORDER BY estado_real ASC, id DESC");
}
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Función para obtener la URL correcta de la imagen
function getProductImageUrl($imagePath) {
    // Si está vacío, usar placeholder
    if (empty($imagePath)) {
        return 'https://via.placeholder.com/300x200/e5e7eb/6b7280?text=Sin+Imagen';
    }
    
    // Si ya es una URL completa
    if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
        return $imagePath;
    }
    
    // Si es una ruta relativa con uploads/
    if (strpos($imagePath, 'uploads/') === 0) {
        return '../' . $imagePath;
    }
    
    // Si solo tiene el nombre del archivo
    if (strpos($imagePath, '/') === false) {
        return '../uploads/productos/' . $imagePath;
    }
    
    // Por defecto, asumir que es ruta relativa
    return '../' . $imagePath;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Productos | NGS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary: #007bff; 
            --bg-body: #f3f4f6; 
            --text-dark: #1f2937; 
            --text-gray: #6b7280; 
            --white: #ffffff;
            --green-btn: #10b981;
        }
        
        body { 
            margin: 0; 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg-body); 
            display: flex; 
            height: 100vh; 
            overflow: hidden; 
        }

        /* SIDEBAR */
        .sidebar { 
            width: 260px; 
            background: var(--white); 
            border-right: 1px solid #e5e7eb; 
            display: flex; 
            flex-direction: column; 
            padding: 25px 20px; 
            flex-shrink: 0; 
        }
        
        .sidebar h2 { 
            font-size: 1.3rem; 
            font-weight: 700; 
            margin-bottom: 35px; 
            color: var(--text-dark); 
            display: flex;
            align-items: center;
            gap: 10px; 
        }
        
        .menu-item { 
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px; 
            margin-bottom: 8px; 
            color: var(--text-gray); 
            text-decoration: none; 
            border-radius: 10px; 
            font-weight: 500; 
            transition: 0.3s; 
        }
        
        .menu-item:hover { 
            background-color: #f9fafb; 
            color: var(--primary); 
        }
        
        .menu-item.active { 
            background-color: #eef6ff; 
            color: var(--primary); 
            font-weight: 700; 
        }
        
        .logout-btn { 
            margin-top: auto; 
            color: #ef4444; 
            font-weight: 600; 
        }
        
        .logout-btn:hover { 
            background-color: #fef2f2; 
        }

        /* CONTENIDO PRINCIPAL */
        .main-content { 
            flex: 1; 
            padding: 40px; 
            overflow-y: auto; 
        }

        .action-bar { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 25px; 
            gap: 20px; 
        }
        
        .search-form { 
            flex: 1; 
            max-width: 400px; 
            position: relative; 
        }
        
        .search-input { 
            width: 100%; 
            padding: 12px 20px 12px 45px; 
            border: 1px solid #e5e7eb; 
            border-radius: 12px; 
            outline: none; 
            font-size: 0.95rem; 
            box-sizing: border-box; 
            transition: 0.2s; 
        }
        
        .search-input:focus { 
            border-color: var(--primary); 
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1); 
        }
        
        .search-icon { 
            position: absolute; 
            left: 15px; 
            top: 50%; 
            transform: translateY(-50%); 
            color: var(--text-gray); 
            font-size: 1rem; 
        }
        
        .btn-add { 
            background: var(--primary); 
            color: white; 
            text-decoration: none; 
            padding: 12px 20px; 
            border-radius: 12px; 
            font-weight: 600; 
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-add:hover { 
            background: #0056b3; 
            transform: translateY(-2px);
        }

        /* GRID Y TARJETAS */
        .grid-container { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
            gap: 25px; 
        }
        
        .card { 
            background: var(--white); 
            border-radius: 16px; 
            border: 1px solid #e5e7eb; 
            padding: 20px; 
            transition: 0.3s; 
            position: relative; 
            display: flex; 
            flex-direction: column;
            overflow: hidden;
        }
        
        .card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-color: var(--primary);
        }
        
        .card-header {
            position: relative;
            margin-bottom: 15px;
            height: 200px;
            overflow: hidden;
            border-radius: 10px;
            background: #f8fafc;
        }
        
        .card-img-container {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }
        
        .card-img { 
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            transition: transform 0.3s ease;
        }
        
        .card:hover .card-img {
            transform: scale(1.05);
        }
        
        .card-quick-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 5px;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 2;
        }
        
        .card:hover .card-quick-actions {
            opacity: 1;
        }
        
        .quick-btn {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-dark);
            text-decoration: none;
            transition: all 0.2s;
            backdrop-filter: blur(4px);
        }
        
        .quick-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: scale(1.1);
        }
        
        .card-title { 
            margin: 0 0 10px 0; 
            font-size: 1.1rem; 
            font-weight: 700; 
            color: var(--text-dark); 
            line-height: 1.4;
            min-height: 2.8em;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Contenedor de precios duales */
        .price-container {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 15px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
        }
        
        .price-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .price-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-gray);
            min-width: 65px;
        }
        
        .price-value {
            font-size: 1.1rem;
            font-weight: 700;
        }
        
        .price-minorista .price-value {
            color: var(--primary);
        }
        
        .price-mayorista .price-value {
            color: #059669;
        }
        
        .badge-precio {
            font-size: 0.65rem;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 700;
            text-transform: uppercase;
            white-space: nowrap;
        }
        
        .badge-minorista {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #7dd3fc;
        }
        
        .badge-mayorista {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        
        /* Stock y cantidad mínima */
        .stock-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .stock-count {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .stock-count.low {
            color: #dc2626;
        }
        
        .stock-count.good {
            color: #059669;
        }
        
        .minimo-info {
            font-size: 0.8rem;
            color: #f59e0b;
            background: #fef3c7;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .stock-icon {
            font-size: 1rem;
        }

        /* BADGES DE ESTADO */
        .status-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .status-badge { 
            font-size: 0.75rem; 
            padding: 6px 12px; 
            border-radius: 6px; 
            font-weight: 700; 
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .badge-activo { 
            background: #dcfce7; 
            color: #15803d; 
            border: 1px solid #86efac; 
        }
        
        .badge-inactivo { 
            background: #fee2e2; 
            color: #b91c1c; 
            border: 1px solid #fca5a5; 
        }

        .btn-edit { 
            display: block; 
            text-align: center; 
            background: #f8fafc; 
            color: var(--text-dark); 
            text-decoration: none; 
            padding: 12px; 
            border-radius: 8px; 
            font-weight: 600; 
            border: 1px solid #e5e7eb; 
            transition: 0.2s; 
            margin-top: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-edit:hover { 
            background: var(--primary); 
            color: white; 
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        /* Estilo para productos INACTIVOS */
        .card-inactivo { 
            opacity: 0.7; 
            background-color: #f9fafb; 
            border-style: dashed; 
        }
        
        .card-inactivo .card-img {
            filter: grayscale(1);
        }
        
        .card-inactivo:hover { 
            transform: translateY(0); 
            box-shadow: none; 
            border-color: #e5e7eb; 
        }
        
        /* Contador de productos */
        .product-count {
            background: white;
            padding: 12px 20px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: var(--text-dark);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .product-count span {
            background: var(--primary);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        /* Placeholder cuando no hay productos */
        .empty-state {
            grid-column: 1/-1;
            text-align: center;
            padding: 60px 20px;
            color: var(--text-gray);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.2;
            color: var(--primary);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .grid-container {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 15px;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-form {
                max-width: 100%;
            }
            
            .btn-add {
                width: 100%;
                justify-content: center;
            }
            
            .card-header {
                height: 180px;
            }
        }
        
        @media (max-width: 480px) {
            .grid-container {
                grid-template-columns: 1fr;
            }
            
            .price-row {
                flex-wrap: wrap;
            }
            
            .price-label {
                min-width: 55px;
                font-size: 0.75rem;
            }
            
            .price-value {
                font-size: 1rem;
            }
            
            .card-header {
                height: 160px;
            }
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <h2> ADMIN PANEL</h2>
        <nav>
            <a href="index.php" class="menu-item"><i class="fa-solid fa-house"></i> Inicio</a>
            <a href="productos.php" class="menu-item active"><i class="fa-solid fa-box-open"></i> Productos</a>
            <a href="ventas.php" class="menu-item"><i class="fa-solid fa-chart-line"></i> Ventas</a>
            <a href="pedidos.php" class="menu-item">
                <i class="fa-solid fa-truck"></i> Pedidos
            </a>
            <?php if(isset($_SESSION['user_rol']) && ($_SESSION['user_rol'] === 'dueño' || $_SESSION['user_rol'] === 'dueno')): ?>
                <a href="usuarios.php" class="menu-item"><i class="fa-solid fa-users-gear"></i> Usuarios</a>
            <?php endif; ?>
            
            <a href="../index.php" class="menu-item" style="margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 20px;">
                Regresar a Tienda
            </a>
        </nav>
        <a href="../logout.php" class="menu-item logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión</a>
    </aside>

    <main class="main-content">
        <h2 style="margin-top:0; margin-bottom:20px;">Gestión de Inventario</h2>
        
        <!-- Contador de productos -->
        <div class="product-count">
            <i class="fa-solid fa-boxes-stacked"></i> 
            <span>Productos: <?php echo count($productos); ?></span>
        </div>

        <div class="action-bar">
            <form class="search-form" method="GET">
                <i class="fas fa-search search-icon"></i>
                <input type="text" name="q" class="search-input" placeholder="Buscar por nombre de producto..." value="<?php echo htmlspecialchars($busqueda); ?>">
            </form>
            <a href="agregar_producto.php" class="btn-add">
                <i class="fa-solid fa-plus"></i> Nuevo Producto
            </a>
        </div>

        <div class="grid-container">
            <?php foreach($productos as $p): 
                $estado = $p['estado_real'];
                $esInactivo = ($estado === 'inactivo');
                
                // Obtener URL de imagen corregida
                $imagen_url = getProductImageUrl($p['imagen']);
                
                // Calcular precios
                $precio_minorista = $p['precio'];
                $precio_mayorista = $p['precio_mayorista'] ?? $precio_minorista * 0.8;
                $minimo_mayorista = $p['minimo_mayorista'] ?? 5;
                
                // Determinar clase de stock
                $stock_clase = ($p['stock'] <= 5) ? 'low' : 'good';
                $stock_text = ($p['stock'] <= 5) ? 'Stock bajo' : 'Stock disponible';
            ?>
            <div class="card <?php echo $esInactivo ? 'card-inactivo' : ''; ?>">
                <div class="card-header">
                    <div class="card-img-container">
                        <img src="<?php echo htmlspecialchars($imagen_url); ?>" 
                             class="card-img" 
                             alt="<?php echo htmlspecialchars($p['nombre']); ?>"
                             onerror="this.src='https://via.placeholder.com/300x200/e5e7eb/6b7280?text=Error+Cargando+Imagen'">
                    </div>
                    
                    <div class="card-quick-actions">
                        <a href="editar_producto.php?id=<?php echo $p['id']; ?>" class="quick-btn" title="Editar">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        <a href="eliminar_producto.php?id=<?php echo $p['id']; ?>" class="quick-btn" title="Eliminar" onclick="return confirm('¿Eliminar este producto?')">
                            <i class="fa-solid fa-trash"></i>
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <h3 class="card-title"><?php echo htmlspecialchars($p['nombre']); ?></h3>
                    
                    <!-- Precios duales -->
                    <div class="price-container">
                        <!-- Precio Minorista -->
                        <div class="price-row price-minorista">
                            <span class="badge-precio badge-minorista">Minorista</span>
                            <span class="price-label">Precio:</span>
                            <span class="price-value">$<?php echo number_format($precio_minorista, 2); ?></span>
                        </div>
                        
                        <!-- Precio Mayorista -->
                        <div class="price-row price-mayorista">
                            <span class="badge-precio badge-mayorista">Mayorista</span>
                            <span class="price-label">Precio:</span>
                            <span class="price-value">$<?php echo number_format($precio_mayorista, 2); ?></span>
                        </div>
                    </div>
                    
                    <!-- Información de stock -->
                    <div class="stock-info">
                        <div class="stock-count <?php echo $stock_clase; ?>">
                            <i class="fa-solid fa-boxes-stacked stock-icon"></i>
                            <span><?php echo $p['stock']; ?> unidades</span>
                        </div>
                        <div class="minimo-info">
                            <i class="fa-solid fa-tag"></i> Mín. <?php echo $minimo_mayorista; ?>
                        </div>
                    </div>
                    
                    <!-- Estado y acciones -->
                    <div class="status-section">
                        <span class="status-badge <?php echo $esInactivo ? 'badge-inactivo' : 'badge-activo'; ?>">
                            <?php if($esInactivo): ?>
                                <i class="fa-solid fa-ban"></i> Inactivo
                            <?php else: ?>
                                <i class="fa-solid fa-check-circle"></i> Activo
                            <?php endif; ?>
                        </span>
                        
                        <small style="color:var(--text-gray); font-size:0.85rem;">
                            <?php echo $stock_text; ?>
                        </small>
                    </div>
                    
                    <a href="editar_producto.php?id=<?php echo $p['id']; ?>" class="btn-edit">
                        <i class="fa-solid fa-edit"></i> Editar Producto
                    </a>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if(empty($productos)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-box-open"></i>
                    <h3 style="color:var(--text-dark); margin-bottom:10px;">No se encontraron productos</h3>
                    <p><?php echo $busqueda ? 'No hay productos que coincidan con tu búsqueda.' : 'No hay productos registrados en el sistema.'; ?></p>
                    <?php if($busqueda): ?>
                        <a href="productos.php" style="color:var(--primary); text-decoration:none; font-weight:600; display:inline-block; margin-top:15px; padding: 10px 20px; background: #eff6ff; border-radius: 8px;">
                            <i class="fa-solid fa-arrow-left"></i> Ver todos los productos
                        </a>
                    <?php else: ?>
                        <a href="agregar_producto.php" style="color:var(--primary); text-decoration:none; font-weight:600; display:inline-block; margin-top:15px; padding: 10px 20px; background: #eff6ff; border-radius: 8px;">
                            <i class="fa-solid fa-plus"></i> Agregar primer producto
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Búsqueda automática al escribir
        let searchTimeout;
        const searchInput = document.querySelector('.search-input');
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (this.value.length >= 2 || this.value.length === 0) {
                        this.form.submit();
                    }
                }, 500);
            });
            
            // Permitir búsqueda con Enter
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.form.submit();
                }
            });
        }
        
        // Manejo de errores en imágenes
        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('.card-img');
            images.forEach(img => {
                // Si la imagen falla al cargar, mostrar placeholder
                img.addEventListener('error', function() {
                    this.src = 'https://via.placeholder.com/300x200/e5e7eb/6b7280?text=Error+Cargando+Imagen';
                    this.style.objectFit = 'cover';
                    this.style.padding = '20px';
                });
            });
            
            // Tooltip para stock bajo
            document.querySelectorAll('.stock-count.low').forEach(function(element) {
                element.title = '¡Stock bajo! Considera reponer este producto.';
            });
            
            // Tooltip para productos inactivos
            document.querySelectorAll('.card-inactivo').forEach(function(card) {
                card.title = 'Producto inactivo - No visible en la tienda';
            });
        });
        
        // Animación de carga suave
        window.addEventListener('load', function() {
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>

</body>
</html>