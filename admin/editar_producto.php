<?php
session_start();
include __DIR__ . '/../config/db.php';

// 1. SEGURIDAD: Verificar sesión y rol (Admin y Dueño pueden editar)
$es_dueno = isset($_SESSION['user_rol']) && ($_SESSION['user_rol'] === 'dueño' || $_SESSION['user_rol'] === 'dueno');
$es_admin = isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === 'admin';

if (!$es_dueno && !$es_admin) {
    header('Location: ../index.php'); 
    exit;
}

// 2. OBTENER Y VALIDAR ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { 
    header('Location: productos.php'); 
    exit; 
}

// 3. CARGAR DATOS DEL PRODUCTO Y SUS IMÁGENES
$stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
$stmt->execute([$id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) { 
    header('Location: productos.php'); 
    exit; 
}

// Cargar imágenes del producto desde la nueva tabla
$stmt_imagenes = $pdo->prepare("SELECT * FROM producto_imagenes WHERE producto_id = ? ORDER BY clasificacion, orden");
$stmt_imagenes->execute([$id]);
$imagenes = $stmt_imagenes->fetchAll(PDO::FETCH_ASSOC);

// Organizar imágenes por clasificación
$imagenes_por_clasificacion = [
    'principal' => [],
    'tecnica' => [],
    'contenido' => [],
    'adicional' => []
];

foreach ($imagenes as $imagen) {
    $clasificacion = $imagen['clasificacion'];
    $imagenes_por_clasificacion[$clasificacion][] = $imagen;
}

$mensaje = '';

// 4. LÓGICA DE CAMBIO DE ESTADO (Inactivar/Reactivar)
if (isset($_POST['cambiar_estado'])) {
    $nuevo_estado = $_POST['nuevo_estado'];
    
    // Cualquier admin/dueño puede inactivar
    if ($nuevo_estado === 'inactivo' && ($es_admin || $es_dueno)) {
        $fecha = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("UPDATE productos SET estado = 'inactivo', fecha_inactivo = ? WHERE id = ?");
        $stmt->execute([$fecha, $id]);
        header("Location: editar_producto.php?id=$id");
        exit;
    }
    // SOLO dueños pueden reactivar (puedes cambiar a $es_admin || $es_dueno si quieres que ambos puedan)
    elseif ($nuevo_estado === 'activo' && ($es_admin || $es_dueno)) {
        $stmt = $pdo->prepare("UPDATE productos SET estado = 'activo', fecha_inactivo = NULL WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: editar_producto.php?id=$id");
        exit;
    }
}

// 5. PROCESAR FORMULARIO DE EDICIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['cambiar_estado'])) {
    // Campos básicos
    $nombre = trim($_POST['nombre']);
    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo']);
    $precio_minorista = $_POST['precio_minorista'];
    $precio_mayorista = $_POST['precio_mayorista'];
    $minimo_mayorista = $_POST['minimo_mayorista'];
    $stock = $_POST['stock'];
    $categoria = $_POST['categoria'];
    $caracteristica = trim($_POST['caracteristica_especial']);
    $descripcion = trim($_POST['descripcion']);
    
    // Nuevos campos del PDF
    $tipo_producto = trim($_POST['tipo_producto']);
    $etapa = trim($_POST['etapa']);
    $meses_garantia = $_POST['meses_garantia'];
    
    // Especificaciones técnicas
    $especificaciones = trim($_POST['especificaciones']);
    $resolucion = trim($_POST['resolucion']);
    $proteccion = trim($_POST['proteccion']);
    $conexion = trim($_POST['conexion']);
    
    // Contenido incluido
    $contenido_incluido = trim($_POST['contenido_incluido']);
    
    // VALIDACIONES básicas
    if (empty($nombre) || empty($marca) || empty($modelo) || empty($categoria)) {
        $mensaje = "Por favor, completa los campos obligatorios.";
    } 
    elseif (!is_numeric($precio_minorista) || $precio_minorista < 0) {
        $mensaje = "El precio minorista debe ser un número válido.";
    } 
    elseif (!is_numeric($precio_mayorista) || $precio_mayorista < 0) {
        $mensaje = "El precio mayorista debe ser un número válido.";
    }
    elseif (!empty($minimo_mayorista) && (!is_numeric($minimo_mayorista) || $minimo_mayorista < 2)) {
        $mensaje = "El mínimo para mayorista debe ser al menos 2 unidades.";
    }
    elseif (!ctype_digit(strval($stock)) || $stock < 1) { 
        $mensaje = "El stock debe ser al menos 1 unidad.";
    } 
    else {
        // Iniciar transacción para asegurar que todo se actualice correctamente
        $pdo->beginTransaction();
        
        try {
            // 1. Actualizar el producto en la tabla productos
            $sql_producto = "UPDATE productos SET 
                nombre = ?, marca = ?, modelo = ?, precio = ?, precio_mayorista = ?, minimo_mayorista = ?, 
                stock = ?, categoria = ?, caracteristica_especial = ?, descripcion = ?, tipo_producto = ?, etapa = ?, 
                meses_garantia = ?, especificaciones = ?, resolucion = ?, proteccion = ?, 
                conexion = ?, contenido_incluido = ?
                WHERE id = ?";
            
            $stmt_producto = $pdo->prepare($sql_producto);
            $stmt_producto->execute([
                $nombre, $marca, $modelo, $precio_minorista, $precio_mayorista, $minimo_mayorista,
                $stock, $categoria, $caracteristica, $descripcion, $tipo_producto, $etapa, 
                $meses_garantia, $especificaciones, $resolucion, $proteccion, 
                $conexion, $contenido_incluido, $id
            ]);
            
            // Función para procesar y guardar imágenes nuevas
            function guardarNuevaImagen($archivo, $producto_id, $clasificacion, $orden = 0) {
                global $pdo;
                
                $permitidos = ['jpg', 'jpeg', 'png', 'webp'];
                $max_size = 5 * 1024 * 1024;
                
                // Validar archivo
                if ($archivo['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Error en la subida de imagen");
                }
                
                $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
                
                if (!in_array($ext, $permitidos)) {
                    throw new Exception("Formato no válido (Solo JPG, PNG, WEBP)");
                }
                
                if ($archivo['size'] > $max_size) {
                    throw new Exception("La imagen es muy pesada (Máx 5MB)");
                }
                
                // Generar nombre único para la imagen
                $nombre_nuevo = uniqid() . "_" . $clasificacion . "." . $ext;
                $destino = "../uploads/productos/" . $nombre_nuevo;
                $url_imagen = "uploads/productos/" . $nombre_nuevo;
                
                // Crear directorio si no existe
                if (!is_dir("../uploads/productos")) {
                    mkdir("../uploads/productos", 0755, true);
                }
                
                // Mover archivo al servidor
                if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
                    throw new Exception("Error al subir la imagen al servidor");
                }
                
                // Insertar en la tabla producto_imagenes
                $sql_imagen = "INSERT INTO producto_imagenes (producto_id, clasificacion, url_imagen, orden) 
                               VALUES (?, ?, ?, ?)";
                $stmt_imagen = $pdo->prepare($sql_imagen);
                $stmt_imagen->execute([$producto_id, $clasificacion, $url_imagen, $orden]);
                
                return true;
            }
            
            // 2. Procesar nueva imagen principal (si se subió)
            if (isset($_FILES['imagen_principal']) && $_FILES['imagen_principal']['error'] === UPLOAD_ERR_OK) {
                // Eliminar imagen principal anterior
                $pdo->prepare("DELETE FROM producto_imagenes WHERE producto_id = ? AND clasificacion = 'principal'")
                    ->execute([$id]);
                
                guardarNuevaImagen($_FILES['imagen_principal'], $id, 'principal', 1);
            }
            
            // 3. Procesar nuevas imágenes técnicas (si se subieron)
            if (isset($_FILES['imagenes_tecnicas']) && $_FILES['imagenes_tecnicas']['name'][0]) {
                // Obtener el máximo orden actual para imágenes técnicas
                $stmt_max_orden = $pdo->prepare("SELECT MAX(orden) as max_orden FROM producto_imagenes WHERE producto_id = ? AND clasificacion = 'tecnica'");
                $stmt_max_orden->execute([$id]);
                $max_orden = $stmt_max_orden->fetch(PDO::FETCH_ASSOC)['max_orden'] ?: 0;
                
                $orden = $max_orden + 1;
                foreach ($_FILES['imagenes_tecnicas']['name'] as $key => $name) {
                    if ($_FILES['imagenes_tecnicas']['error'][$key] === UPLOAD_ERR_OK) {
                        $archivo = [
                            'name' => $name,
                            'type' => $_FILES['imagenes_tecnicas']['type'][$key],
                            'tmp_name' => $_FILES['imagenes_tecnicas']['tmp_name'][$key],
                            'error' => $_FILES['imagenes_tecnicas']['error'][$key],
                            'size' => $_FILES['imagenes_tecnicas']['size'][$key]
                        ];
                        
                        guardarNuevaImagen($archivo, $id, 'tecnica', $orden);
                        $orden++;
                    }
                }
            }
            
            // 4. Procesar nuevas imágenes de contenido (si se subieron)
            if (isset($_FILES['imagenes_contenido']) && $_FILES['imagenes_contenido']['name'][0]) {
                // Obtener el máximo orden actual para imágenes de contenido
                $stmt_max_orden = $pdo->prepare("SELECT MAX(orden) as max_orden FROM producto_imagenes WHERE producto_id = ? AND clasificacion = 'contenido'");
                $stmt_max_orden->execute([$id]);
                $max_orden = $stmt_max_orden->fetch(PDO::FETCH_ASSOC)['max_orden'] ?: 0;
                
                $orden = $max_orden + 1;
                foreach ($_FILES['imagenes_contenido']['name'] as $key => $name) {
                    if ($_FILES['imagenes_contenido']['error'][$key] === UPLOAD_ERR_OK) {
                        $archivo = [
                            'name' => $name,
                            'type' => $_FILES['imagenes_contenido']['type'][$key],
                            'tmp_name' => $_FILES['imagenes_contenido']['tmp_name'][$key],
                            'error' => $_FILES['imagenes_contenido']['error'][$key],
                            'size' => $_FILES['imagenes_contenido']['size'][$key]
                        ];
                        
                        guardarNuevaImagen($archivo, $id, 'contenido', $orden);
                        $orden++;
                    }
                }
            }
            
            // 5. Procesar nuevas imágenes adicionales (si se subieron)
            if (isset($_FILES['imagenes_adicionales']) && $_FILES['imagenes_adicionales']['name'][0]) {
                // Obtener el máximo orden actual para imágenes adicionales
                $stmt_max_orden = $pdo->prepare("SELECT MAX(orden) as max_orden FROM producto_imagenes WHERE producto_id = ? AND clasificacion = 'adicional'");
                $stmt_max_orden->execute([$id]);
                $max_orden = $stmt_max_orden->fetch(PDO::FETCH_ASSOC)['max_orden'] ?: 0;
                
                $orden = $max_orden + 1;
                foreach ($_FILES['imagenes_adicionales']['name'] as $key => $name) {
                    if ($_FILES['imagenes_adicionales']['error'][$key] === UPLOAD_ERR_OK) {
                        $archivo = [
                            'name' => $name,
                            'type' => $_FILES['imagenes_adicionales']['type'][$key],
                            'tmp_name' => $_FILES['imagenes_adicionales']['tmp_name'][$key],
                            'error' => $_FILES['imagenes_adicionales']['error'][$key],
                            'size' => $_FILES['imagenes_adicionales']['size'][$key]
                        ];
                        
                        guardarNuevaImagen($archivo, $id, 'adicional', $orden);
                        $orden++;
                    }
                }
            }
            
            // 6. Eliminar imágenes específicas si se solicita
            if (isset($_POST['eliminar_imagenes'])) {
                $imagenes_a_eliminar = $_POST['eliminar_imagenes'];
                foreach ($imagenes_a_eliminar as $imagen_id) {
                    // Primero obtener la URL de la imagen para eliminar el archivo físico
                    $stmt_get_img = $pdo->prepare("SELECT url_imagen FROM producto_imagenes WHERE id = ?");
                    $stmt_get_img->execute([$imagen_id]);
                    $imagen = $stmt_get_img->fetch(PDO::FETCH_ASSOC);
                    
                    if ($imagen && file_exists("../" . $imagen['url_imagen'])) {
                        unlink("../" . $imagen['url_imagen']);
                    }
                    
                    // Eliminar de la base de datos
                    $pdo->prepare("DELETE FROM producto_imagenes WHERE id = ?")->execute([$imagen_id]);
                }
            }
            
            // Confirmar transacción
            $pdo->commit();
            
            header('Location: editar_producto.php?id=' . $id . '&msg=actualizado');
            exit;
            
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $pdo->rollBack();
            $mensaje = "Error: " . $e->getMessage();
        }
    }
}

// Si hay mensaje de actualización exitosa en la URL
if (isset($_GET['msg']) && $_GET['msg'] === 'actualizado') {
    $mensaje_success = "Producto actualizado correctamente.";
}

// Cargar categorías para el select
$categorias = [
    "CCTV IP / Análogo",
    "Redes & WiFi",
    "Control de Acceso",
    "Intrusión & Alarmas",
    "Sistemas de Incendio",
    "Cómputo",
    "Automatización",
    "Telecomunicaciones",
    "Energía",
    "Accesorios",
    "Otros"
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto | NGS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary: #007bff; 
            --primary-hover: #0056b3;
            --bg-body: #f3f4f6; 
            --text-dark: #1f2937; 
            --text-gray: #6b7280; 
            --white: #ffffff;
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
            --info: #3b82f6;
            --secondary: #8b5cf6;
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
            width: 260px; background: var(--white); border-right: 1px solid #e5e7eb; 
            display: flex; flex-direction: column; padding: 25px 20px; flex-shrink: 0; 
        }

        .sidebar h2 { 
            font-size: 1.3rem; font-weight: 700; margin-bottom: 35px; color: var(--text-dark); 
            display: flex; align-items: center; gap: 10px;
        }

        .menu-item { 
            display: flex; align-items: center; gap: 12px; padding: 12px 15px; 
            margin-bottom: 8px; color: var(--text-gray); text-decoration: none; 
            border-radius: 10px; font-weight: 500; transition: 0.3s; 
        }

        .menu-item:hover { background-color: #f9fafb; color: var(--primary); }
        .menu-item.active { background-color: #eef6ff; color: var(--primary); font-weight: 700; }
        .logout-btn { margin-top: auto; color: var(--danger); font-weight: 600; }

        /* CONTENIDO */
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }

        /* FORM CARD */
        .form-card { 
            background: var(--white); padding: 40px; border-radius: 16px; 
            border: 1px solid #e5e7eb; max-width: 1000px; margin: 0 auto; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); 
        }

        .form-header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 30px; border-bottom: 1px solid #f1f5f9; padding-bottom: 20px;
        }
        
        .form-header h2 { margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-dark); }

        .btn-cancel { 
            background-color: #f1f5f9; color: var(--text-gray); 
            text-decoration: none; font-size: 0.85rem; padding: 10px 18px; 
            border-radius: 8px; font-weight: 600; transition: 0.2s;
        }
        .btn-cancel:hover { background-color: #e2e8f0; color: var(--text-dark); }

        /* BOTONES DE ESTADO */
        .btn-delete { 
            background: #fef2f2; color: var(--danger); border: 1px solid #fee2e2; 
            padding: 10px 18px; border-radius: 8px; cursor: pointer; font-weight: 600;
            text-decoration: none; display: inline-block;
        }
        
        .btn-active { 
            background: #f0fdf4; color: var(--success); border: 1px solid #dcfce7; 
            padding: 10px 18px; border-radius: 8px; cursor: pointer; font-weight: 600;
            text-decoration: none; display: inline-block;
        }

        /* SECCIONES ACORDEÓN */
        .section-toggle {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px 20px;
            margin: 25px 0;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .section-toggle i {
            transition: transform 0.3s;
        }
        
        .section-content {
            display: none;
            padding: 20px;
            background-color: #fafafa;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px dashed #d1d5db;
        }
        
        .section-content.active {
            display: block;
        }

        /* GRID */
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group.full-width { grid-column: span 2; }

        label { 
            display: block; margin-bottom: 8px; font-weight: 600; 
            color: var(--text-dark); font-size: 0.9rem; 
        }
        
        label.required::after { content: " *"; color: var(--danger); }
        
        input, select, textarea { 
            width: 100%; padding: 12px; border: 1px solid #e2e8f0; 
            border-radius: 10px; font-family: inherit; box-sizing: border-box; 
            outline: none; transition: 0.2s; font-size: 0.95rem;
        }
        
        input:focus, select:focus, textarea:focus { 
            border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,123,255,0.1); 
        }
        
        textarea { resize: vertical; min-height: 80px; }
        
        .textarea-small { min-height: 60px; }
        .textarea-large { min-height: 120px; }

        /* ESTILOS PARA PRECIOS */
        .price-container {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .price-item {
            flex: 1;
            background: #f8fafc;
            border-radius: 10px;
            padding: 15px;
            border: 1px solid #e2e8f0;
        }
        
        .price-item.minorista {
            border-left: 4px solid var(--primary);
        }
        
        .price-item.mayorista {
            border-left: 4px solid var(--secondary);
        }
        
        .price-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: var(--text-dark);
        }
        
        .price-label.minorista i {
            color: var(--primary);
        }
        
        .price-label.mayorista i {
            color: var(--secondary);
        }
        
        .price-input {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .price-savings {
            background: #dcfce7;
            color: #166534;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-top: 10px;
            text-align: center;
            font-weight: 600;
        }
        
        .minimo-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .minimo-label {
            font-size: 0.9rem;
            color: var(--text-gray);
            flex-shrink: 0;
        }
        
        .minimo-input {
            flex: 1;
            max-width: 150px;
        }

        /* ESTILOS PARA SUBIDA DE IMÁGENES */
        .image-upload-container {
            border: 2px dashed #d1d5db;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            background-color: #f9fafb;
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        
        .image-upload-container.principal {
            border-color: var(--primary);
            background-color: #f0f9ff;
        }
        
        .image-upload-container.tecnicas {
            border-color: var(--warning);
            background-color: #fffbeb;
        }
        
        .image-upload-container.contenido {
            border-color: var(--success);
            background-color: #f0fdf4;
        }
        
        .image-upload-container.adicionales {
            border-color: var(--info);
            background-color: #eff6ff;
        }
        
        .clasificacion-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .badge-principal {
            background-color: var(--primary);
            color: white;
        }
        
        .badge-tecnica {
            background-color: var(--warning);
            color: #1f2937;
        }
        
        .badge-contenido {
            background-color: var(--success);
            color: white;
        }
        
        .badge-adicional {
            background-color: var(--info);
            color: white;
        }
        
        .image-upload-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .icon-principal { color: var(--primary); }
        .icon-tecnica { color: var(--warning); }
        .icon-contenido { color: var(--success); }
        .icon-adicional { color: var(--info); }
        
        .image-upload-text {
            color: #6b7280;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }
        
        .image-upload-btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s;
            margin: 5px;
        }
        
        .btn-principal { background-color: var(--primary); }
        .btn-tecnica { background-color: var(--warning); }
        .btn-contenido { background-color: var(--success); }
        .btn-adicional { background-color: var(--info); }
        
        .image-upload-btn:hover {
            opacity: 0.9;
        }
        
        /* IMÁGENES EXISTENTES */
        .imagenes-existente-container {
            margin: 20px 0;
        }
        
        .imagenes-existente-title {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 15px;
            font-size: 1rem;
        }
        
        .imagenes-existente-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .imagen-existente-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #e5e7eb;
            height: 150px;
            background-color: #f9fafb;
        }
        
        .imagen-existente-item.principal { border-color: var(--primary); }
        .imagen-existente-item.tecnica { border-color: var(--warning); }
        .imagen-existente-item.contenido { border-color: var(--success); }
        .imagen-existente-item.adicional { border-color: var(--info); }
        
        .imagen-existente-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .imagen-existente-item:hover img {
            transform: scale(1.05);
        }
        
        .eliminar-imagen-checkbox {
            position: absolute;
            top: 10px;
            left: 10px;
            background: white;
            padding: 5px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .imagen-tipo-badge {
            position: absolute;
            bottom: 10px;
            left: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
        }
        
        /* VISTA PREVIA DE NUEVAS IMÁGENES */
        .image-preview-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .image-preview {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #e5e7eb;
            height: 120px;
            background-color: #f9fafb;
        }
        
        .image-preview.principal { border-color: var(--primary); }
        .image-preview.tecnica { border-color: var(--warning); }
        .image-preview.contenido { border-color: var(--success); }
        .image-preview.adicional { border-color: var(--info); }
        
        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background-color: rgba(239, 68, 68, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.8rem;
        }
        
        .image-count {
            font-size: 0.85rem;
            color: #6b7280;
            margin-top: 10px;
            text-align: center;
        }

        .btn-submit { 
            width: 100%; background: var(--primary); color: white; 
            padding: 14px; border: none; border-radius: 10px; 
            font-size: 1rem; font-weight: 700; cursor: pointer; 
            transition: 0.3s; margin-top: 30px;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-1px); }
        
        .error-msg { 
            color: #b91c1c; background: #fef2f2; padding: 15px; 
            border-radius: 10px; margin-bottom: 25px; border: 1px solid #fee2e2;
            display: flex; align-items: center; gap: 10px; font-weight: 500;
        }
        
        .success-msg { 
            color: #166534; background: #f0fdf4; padding: 15px; 
            border-radius: 10px; margin-bottom: 25px; border: 1px solid #bbf7d0;
            display: flex; align-items: center; gap: 10px; font-weight: 500;
        }
        
        .note { 
            font-size: 0.8rem; color: var(--text-gray); font-style: italic; 
            margin-top: 5px;
        }
        
        .clasificacion-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
            padding: 10px;
            background-color: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .info-icon {
            font-size: 1.2rem;
        }
        
        .info-principal .info-icon { color: var(--primary); }
        .info-tecnica .info-icon { color: var(--warning); }
        .info-contenido .info-icon { color: var(--success); }
        .info-adicional .info-icon { color: var(--info); }
        
        .info-fecha { 
            background: #fff7ed; color: #9a3412; padding: 12px; 
            border-radius: 10px; margin-bottom: 20px; border: 1px solid #ffedd5; 
            font-size: 0.9rem; display: flex; align-items: center; gap: 10px;
        }
        
        .estado-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .estado-activo {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .estado-inactivo {
            background-color: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <h2>ADMIN PANEL</h2>
        <nav>
            <a href="index.php" class="menu-item"><i class="fa-solid fa-house"></i> Inicio</a>
            <a href="productos.php" class="menu-item active"><i class="fa-solid fa-box-open"></i> Productos</a>
            <?php if($es_dueno): ?>
                <a href="ventas.php" class="menu-item"><i class="fa-solid fa-chart-line"></i> Ventas</a>
                <a href="usuarios.php" class="menu-item"><i class="fa-solid fa-users-gear"></i> Usuarios</a>
            <?php endif; ?>
            <a href="../index.php" class="menu-item" style="margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 20px;">
                <i class="fa-solid fa-shop"></i> Ver Tienda
            </a>
        </nav>
        <a href="../logout.php" class="menu-item logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión</a>
    </aside>

    <main class="main-content">
        <div class="form-card">
            <div class="form-header">
                <div>
                    <h2 style="margin:0;">Editar Producto</h2>
                    <div style="display: flex; align-items: center; margin-top: 5px;">
                        <span>ID: #<?php echo $producto['id']; ?></span>
                        <span class="estado-badge <?php echo ($producto['estado'] == 'inactivo') ? 'estado-inactivo' : 'estado-activo'; ?>">
                            <?php echo strtoupper($producto['estado'] ?: 'ACTIVO'); ?>
                        </span>
                    </div>
                </div>
                
                <div style="display:flex; gap:10px;">
                    <?php 
                    $estado_actual = $producto['estado'] ?: 'activo';
                    
                    // Si el producto está activo, mostrar botón para inactivar
                    if($estado_actual === 'activo'): ?>
                        <form method="POST" onsubmit="return confirm('¿Seguro que deseas inactivar este producto? Los usuarios no podrán verlo ni comprarlo.');" style="margin:0;">
                            <input type="hidden" name="cambiar_estado" value="1">
                            <input type="hidden" name="nuevo_estado" value="inactivo">
                            <button type="submit" class="btn-delete">
                                <i class="fa-solid fa-trash-can"></i> Inactivar Producto
                            </button>
                        </form>
                    <?php 
                    // Si el producto está inactivo Y el usuario es dueño/admin, mostrar botón para reactivar
                    elseif($estado_actual === 'inactivo' && ($es_dueno || $es_admin)): ?>
                        <form method="POST" onsubmit="return confirm('¿Seguro que deseas reactivar este producto? Volverá a estar visible en la tienda.');" style="margin:0;">
                            <input type="hidden" name="cambiar_estado" value="1">
                            <input type="hidden" name="nuevo_estado" value="activo">
                            <button type="submit" class="btn-active">
                                <i class="fa-solid fa-rotate-left"></i> Reactivar Producto
                            </button>
                        </form>
                    <?php endif; ?>
                    <a href="productos.php" class="btn-cancel">Volver al Listado</a>
                </div>
            </div>

            <?php if($es_dueno && $producto['estado'] === 'inactivo' && $producto['fecha_inactivo']): ?>
                <div class="info-fecha">
                    <i class="fa-solid fa-calendar-xmark"></i> 
                    Producto inactivado el: <b><?php echo date('d/m/Y H:i', strtotime($producto['fecha_inactivo'])); ?></b>
                </div>
            <?php endif; ?>

            <?php if($mensaje): ?>
                <div class="error-msg">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>

            <?php if(isset($mensaje_success)): ?>
                <div class="success-msg">
                    <i class="fa-solid fa-circle-check"></i> <?php echo $mensaje_success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="productoForm">
                
                <!-- SECCIÓN 1: INFORMACIÓN BÁSICA -->
                <div class="section-toggle" onclick="toggleSection('basica')">
                    <span><i class="fa-solid fa-circle-info"></i> Información Básica</span>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
                
                <div id="basica" class="section-content active">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="required">Nombre del Producto</label>
                            <input type="text" name="nombre" required value="<?php echo htmlspecialchars($producto['nombre']); ?>" placeholder="Ej: Cámara IP Bullet de 4 Megapíxeles">
                        </div>

                        <div class="form-group">
                            <label class="required">Marca</label>
                            <input type="text" name="marca" required value="<?php echo htmlspecialchars($producto['marca']); ?>" placeholder="Ej: DAHUA, HIKVISION, TP-Link">
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Modelo</label>
                            <input type="text" name="modelo" required value="<?php echo htmlspecialchars($producto['modelo']); ?>" placeholder="Ej: DH-IPC-HFW1431S1-A-S6">
                        </div>

                        <div class="form-group full-width">
                            <label class="required">Precios de Venta</label>
                            <div class="price-container">
                                <div class="price-item minorista">
                                    <div class="price-label minorista">
                                        <i class="fa-solid fa-user"></i>
                                        <span>Precio Minorista ($)</span>
                                    </div>
                                    <input type="number" step="0.01" min="0" name="precio_minorista" required 
                                           value="<?php echo isset($producto['precio']) ? $producto['precio'] : ''; ?>" 
                                           placeholder="0.00" class="price-input" id="precioMinorista">
                                </div>
                                
                                <div class="price-item mayorista">
                                    <div class="price-label mayorista">
                                        <i class="fa-solid fa-users"></i>
                                        <span>Precio Mayorista ($)</span>
                                    </div>
                                    <input type="number" step="0.01" min="0" name="precio_mayorista" required 
                                           value="<?php echo isset($producto['precio_mayorista']) ? $producto['precio_mayorista'] : ''; ?>" 
                                           placeholder="0.00" class="price-input" id="precioMayorista">
                                </div>
                            </div>
                            
                            <div class="minimo-container">
                                <div class="minimo-label">
                                    Aplicar precio mayorista a partir de:
                                </div>
                                <input type="number" min="2" step="1" name="minimo_mayorista" 
                                       value="<?php echo isset($producto['minimo_mayorista']) ? $producto['minimo_mayorista'] : '2'; ?>" 
                                       placeholder="2" class="minimo-input" id="minimoMayorista">
                                <span>unidades</span>
                            </div>
                            
                            <div id="ahorroContainer" class="price-savings" style="display: none;">
                                <i class="fa-solid fa-tag"></i>
                                <span id="ahorroText"></span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Stock Disponible</label>
                            <input type="number" min="0" step="1" name="stock" required value="<?php echo $producto['stock']; ?>" placeholder="0">
                        </div>

                        <div class="form-group">
                            <label class="required">Categoría</label>
                            <select name="categoria" required>
                                <option value="">Seleccionar...</option>
                                <?php 
                                    foreach($categorias as $c) {
                                        $selected = ($producto['categoria'] == $c) ? 'selected' : '';
                                        echo "<option value='$c' $selected>$c</option>";
                                    }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Tipo de Producto</label>
                            <select name="tipo_producto">
                                <option value="">Seleccionar...</option>
                                <option value="Más Vendidos" <?php echo ($producto['tipo_producto'] === 'Más Vendidos') ? 'selected' : ''; ?>>Más Vendidos</option>
                                <option value="Nuevo" <?php echo ($producto['tipo_producto'] === 'Nuevo') ? 'selected' : ''; ?>>Nuevo</option>
                                <option value="Oferta" <?php echo ($producto['tipo_producto'] === 'Oferta') ? 'selected' : ''; ?>>Oferta</option>
                                <option value="Recomendado" <?php echo ($producto['tipo_producto'] === 'Recomendado') ? 'selected' : ''; ?>>Recomendado</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Etapa</label>
                            <select name="etapa">
                                <option value="">Seleccionar...</option>
                                <option value="De Línea" <?php echo ($producto['etapa'] === 'De Línea') ? 'selected' : ''; ?>>De Línea</option>
                                <option value="Nuevo Ingreso" <?php echo ($producto['etapa'] === 'Nuevo Ingreso') ? 'selected' : ''; ?>>Nuevo Ingreso</option>
                                <option value="Discontinuado" <?php echo ($producto['etapa'] === 'Discontinuado') ? 'selected' : ''; ?>>Discontinuado</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Meses de Garantía</label>
                            <input type="number" min="0" max="60" name="meses_garantia" value="<?php echo isset($producto['meses_garantia']) ? $producto['meses_garantia'] : '12'; ?>" placeholder="12">
                        </div>
                        
                        <div class="form-group">
                            <label>Característica Especial</label>
                            <input type="text" name="caracteristica_especial" value="<?php echo htmlspecialchars($producto['caracteristica_especial'] ?? ''); ?>" placeholder="Ej: IP67, PoE, WDR, 5G">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>Descripción Detallada</label>
                            <textarea name="descripcion" class="textarea-large" placeholder="Describe el producto, sus usos, aplicaciones, etc..."><?php echo htmlspecialchars($producto['descripcion'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- SECCIÓN 2: IMAGEN PRINCIPAL -->
                <div class="section-toggle" onclick="toggleSection('imagenPrincipal')">
                    <span><i class="fa-solid fa-image"></i> Imagen Principal</span>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
                
                <div id="imagenPrincipal" class="section-content">
                    <div class="clasificacion-info info-principal">
                        <i class="fa-solid fa-star info-icon"></i>
                        <div>
                            <strong>Imagen Principal:</strong> Esta será la imagen principal que aparecerá en el catálogo y como miniatura del producto.
                        </div>
                    </div>
                    
                    <!-- Mostrar imagen principal existente -->
                    <?php if(!empty($imagenes_por_clasificacion['principal'])): ?>
                        <div class="imagenes-existente-container">
                            <div class="imagenes-existente-title">Imagen Principal Actual:</div>
                            <div class="imagenes-existente-grid">
                                <?php foreach($imagenes_por_clasificacion['principal'] as $imagen): ?>
                                    <div class="imagen-existente-item principal">
                                        <img src="../<?php echo htmlspecialchars($imagen['url_imagen']); ?>" alt="Imagen principal">
                                        <div class="eliminar-imagen-checkbox">
                                            <input type="checkbox" name="eliminar_imagenes[]" value="<?php echo $imagen['id']; ?>" id="eliminar_principal_<?php echo $imagen['id']; ?>">
                                            <label for="eliminar_principal_<?php echo $imagen['id']; ?>" style="cursor: pointer;">Eliminar</label>
                                        </div>
                                        <div class="imagen-tipo-badge">Principal</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="note">Marca la casilla "Eliminar" para quitar la imagen actual y luego selecciona una nueva.</p>
                        </div>
                    <?php else: ?>
                        <div class="info-fecha" style="margin-bottom: 20px;">
                            <i class="fa-solid fa-circle-info"></i> Este producto no tiene imagen principal asignada.
                        </div>
                    <?php endif; ?>
                    
                    <!-- Subir nueva imagen principal -->
                    <div class="form-group full-width">
                        <label><?php echo empty($imagenes_por_clasificacion['principal']) ? 'Imagen Principal (Requerida)' : 'Nueva Imagen Principal (Opcional)'; ?></label>
                        <div class="image-upload-container principal" id="principalUploadContainer">
                            <div class="clasificacion-badge badge-principal">Principal</div>
                            <div class="image-upload-icon icon-principal">
                                <i class="fa-solid fa-camera"></i>
                            </div>
                            <div class="image-upload-text">
                                Haz clic para seleccionar una nueva imagen principal
                            </div>
                            <button type="button" class="image-upload-btn btn-principal" onclick="document.getElementById('imagen_principal').click()">
                                <i class="fa-solid fa-upload"></i> Seleccionar Nueva Imagen
                            </button>
                            <input type="file" id="imagen_principal" name="imagen_principal" accept=".jpg,.jpeg,.png,.webp" style="display: none;" <?php echo empty($imagenes_por_clasificacion['principal']) ? 'required' : ''; ?> onchange="previewImage(this, 'principal')">
                            <p class="note"><i class="fa-solid fa-circle-info"></i> JPG, PNG o WEBP. Máximo 5MB. Esta imagen aparecerá como principal en el catálogo.</p>
                            
                            <div id="principalPreviewContainer" class="image-preview-container">
                                <!-- Vista previa de la nueva imagen principal -->
                            </div>
                            <div id="principalCount" class="image-count"></div>
                        </div>
                    </div>
                </div>
                
                <!-- SECCIÓN 3: ESPECIFICACIONES TÉCNICAS CON IMÁGENES -->
                <div class="section-toggle" onclick="toggleSection('especificaciones')">
                    <span><i class="fa-solid fa-microchip"></i> Especificaciones Técnicas</span>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
                
                <div id="especificaciones" class="section-content">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Resolución</label>
                            <input type="text" name="resolucion" value="<?php echo htmlspecialchars($producto['resolucion'] ?? ''); ?>" placeholder="Ej: 4 MP (2560x1440), 1080p">
                        </div>
                        
                        <div class="form-group">
                            <label>Protección</label>
                            <input type="text" name="proteccion" value="<?php echo htmlspecialchars($producto['proteccion'] ?? ''); ?>" placeholder="Ej: IP67, IK10">
                        </div>
                        
                        <div class="form-group">
                            <label>Tipo de Conexión</label>
                            <select name="conexion">
                                <option value="">Seleccionar...</option>
                                <option value="PoE" <?php echo (isset($producto['conexion']) && $producto['conexion'] === 'PoE') ? 'selected' : ''; ?>>PoE (Power over Ethernet)</option>
                                <option value="WiFi" <?php echo (isset($producto['conexion']) && $producto['conexion'] === 'WiFi') ? 'selected' : ''; ?>>WiFi</option>
                                <option value="Cable" <?php echo (isset($producto['conexion']) && $producto['conexion'] === 'Cable') ? 'selected' : ''; ?>>Cable</option>
                                <option value="Híbrido" <?php echo (isset($producto['conexion']) && $producto['conexion'] === 'Híbrido') ? 'selected' : ''; ?>>Híbrido</option>
                                <option value="Inalámbrico" <?php echo (isset($producto['conexion']) && $producto['conexion'] === 'Inalámbrico') ? 'selected' : ''; ?>>Inalámbrico</option>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>Especificaciones Detalladas</label>
                            <textarea name="especificaciones" class="textarea-large" placeholder="Detalles técnicos, compatibilidad, protocolos, etc..."><?php echo htmlspecialchars($producto['especificaciones'] ?? ''); ?></textarea>
                            <p class="note">Ej: Ángulo de visión 104°, IR de 30m, compresión H.265+, micrófono integrado, WDR real</p>
                        </div>
                        
                        <!-- Mostrar imágenes técnicas existentes -->
                        <?php if(!empty($imagenes_por_clasificacion['tecnica'])): ?>
                            <div class="form-group full-width">
                                <div class="imagenes-existente-container">
                                    <div class="imagenes-existente-title">Imágenes Técnicas Actuales (<?php echo count($imagenes_por_clasificacion['tecnica']); ?>):</div>
                                    <div class="imagenes-existente-grid">
                                        <?php foreach($imagenes_por_clasificacion['tecnica'] as $imagen): ?>
                                            <div class="imagen-existente-item tecnica">
                                                <img src="../<?php echo htmlspecialchars($imagen['url_imagen']); ?>" alt="Imagen técnica">
                                                <div class="eliminar-imagen-checkbox">
                                                    <input type="checkbox" name="eliminar_imagenes[]" value="<?php echo $imagen['id']; ?>" id="eliminar_tecnica_<?php echo $imagen['id']; ?>">
                                                    <label for="eliminar_tecnica_<?php echo $imagen['id']; ?>" style="cursor: pointer;">Eliminar</label>
                                                </div>
                                                <div class="imagen-tipo-badge">Técnica</div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="note">Marca las casillas para eliminar imágenes específicas.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Subir nuevas imágenes técnicas -->
                        <div class="form-group full-width">
                            <label>Agregar Nuevas Imágenes Técnicas (Opcional)</label>
                            <div class="clasificacion-info info-tecnica">
                                <i class="fa-solid fa-diagram-project info-icon"></i>
                                <div>
                                    <strong>Imágenes Técnicas:</strong> Sube diagramas, especificaciones visuales, gráficos técnicos, etc.
                                </div>
                            </div>
                            
                            <div class="image-upload-container tecnicas" id="tecnicasUploadContainer">
                                <div class="clasificacion-badge badge-tecnica">Técnica</div>
                                <div class="image-upload-icon icon-tecnica">
                                    <i class="fa-solid fa-microchip"></i>
                                </div>
                                <div class="image-upload-text">
                                    Sube nuevas imágenes técnicas, diagramas, especificaciones visuales
                                </div>
                                <button type="button" class="image-upload-btn btn-tecnica" onclick="document.getElementById('imagenes_tecnicas').click()">
                                    <i class="fa-solid fa-upload"></i> Agregar Imágenes Técnicas
                                </button>
                                <input type="file" id="imagenes_tecnicas" name="imagenes_tecnicas[]" accept=".jpg,.jpeg,.png,.webp" multiple style="display: none;" onchange="previewMultipleImages(this, 'tecnica')">
                                <p class="note"><i class="fa-solid fa-circle-info"></i> Puedes seleccionar múltiples imágenes. Máximo 5MB por imagen.</p>
                                
                                <div id="tecnicaPreviewContainer" class="image-preview-container">
                                    <!-- Vistas previas de nuevas imágenes técnicas -->
                                </div>
                                <div id="tecnicaCount" class="image-count"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- SECCIÓN 4: CONTENIDO INCLUIDO CON IMÁGENES -->
                <div class="section-toggle" onclick="toggleSection('incluido')">
                    <span><i class="fa-solid fa-box-open"></i> Contenido Incluido</span>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
                
                <div id="incluido" class="section-content">
                    <div class="form-group full-width">
                        <label>¿Qué incluye el producto?</label>
                        <textarea name="contenido_incluido" class="textarea-large" placeholder="Ej: 
• 1x Cámara Bullet
• 1x Manual de usuario
• 1x Póliza de garantía
• 1x Adaptador de corriente
• 1x Cable de red"><?php echo htmlspecialchars($producto['contenido_incluido'] ?? ''); ?></textarea>
                        <p class="note">Usa viñetas (•) o números para listar los elementos incluidos.</p>
                    </div>
                    
                    <!-- Mostrar imágenes de contenido existentes -->
                    <?php if(!empty($imagenes_por_clasificacion['contenido'])): ?>
                        <div class="form-group full-width">
                            <div class="imagenes-existente-container">
                                <div class="imagenes-existente-title">Imágenes de Contenido Actuales (<?php echo count($imagenes_por_clasificacion['contenido']); ?>):</div>
                                <div class="imagenes-existente-grid">
                                    <?php foreach($imagenes_por_clasificacion['contenido'] as $imagen): ?>
                                        <div class="imagen-existente-item contenido">
                                            <img src="../<?php echo htmlspecialchars($imagen['url_imagen']); ?>" alt="Imagen de contenido">
                                            <div class="eliminar-imagen-checkbox">
                                                <input type="checkbox" name="eliminar_imagenes[]" value="<?php echo $imagen['id']; ?>" id="eliminar_contenido_<?php echo $imagen['id']; ?>">
                                                <label for="eliminar_contenido_<?php echo $imagen['id']; ?>" style="cursor: pointer;">Eliminar</label>
                                            </div>
                                            <div class="imagen-tipo-badge">Contenido</div>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                <p class="note">Marca las casillas para eliminar imágenes específicas.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Subir nuevas imágenes de contenido -->
                    <div class="form-group full-width">
                        <label>Agregar Nuevas Fotos del Contenido Incluido (Opcional)</label>
                        <div class="clasificacion-info info-contenido">
                            <i class="fa-solid fa-box-open info-icon"></i>
                            <div>
                                <strong>Imágenes de Contenido:</strong> Sube fotos de lo que viene en la caja, manuales, accesorios incluidos.
                            </div>
                        </div>
                        
                        <div class="image-upload-container contenido" id="contenidoUploadContainer">
                            <div class="clasificacion-badge badge-contenido">Contenido</div>
                            <div class="image-upload-icon icon-contenido">
                                <i class="fa-solid fa-box"></i>
                            </div>
                            <div class="image-upload-text">
                                Sube nuevas fotos de lo que viene en la caja, manuales, accesorios
                            </div>
                            <button type="button" class="image-upload-btn btn-contenido" onclick="document.getElementById('imagenes_contenido').click()">
                                <i class="fa-solid fa-upload"></i> Agregar Imágenes de Contenido
                            </button>
                            <input type="file" id="imagenes_contenido" name="imagenes_contenido[]" accept=".jpg,.jpeg,.png,.webp" multiple style="display: none;" onchange="previewMultipleImages(this, 'contenido')">
                            <p class="note"><i class="fa-solid fa-circle-info"></i> Puedes seleccionar múltiples imágenes. Máximo 5MB por imagen.</p>
                            
                            <div id="contenidoPreviewContainer" class="image-preview-container">
                                <!-- Vistas previas de nuevas imágenes de contenido -->
                            </div>
                            <div id="contenidoCount" class="image-count"></div>
                        </div>
                    </div>
                </div>
                
                <!-- SECCIÓN 5: IMÁGENES ADICIONALES -->
                <div class="section-toggle" onclick="toggleSection('adicionales')">
                    <span><i class="fa-solid fa-images"></i> Imágenes Adicionales</span>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
                
                <div id="adicionales" class="section-content">
                    <!-- Mostrar imágenes adicionales existentes -->
                    <?php if(!empty($imagenes_por_clasificacion['adicional'])): ?>
                        <div class="form-group full-width">
                            <div class="imagenes-existente-container">
                                <div class="imagenes-existente-title">Imágenes Adicionales Actuales (<?php echo count($imagenes_por_clasificacion['adicional']); ?>):</div>
                                <div class="imagenes-existente-grid">
                                    <?php foreach($imagenes_por_clasificacion['adicional'] as $imagen): ?>
                                        <div class="imagen-existente-item adicional">
                                            <img src="../<?php echo htmlspecialchars($imagen['url_imagen']); ?>" alt="Imagen adicional">
                                            <div class="eliminar-imagen-checkbox">
                                                <input type="checkbox" name="eliminar_imagenes[]" value="<?php echo $imagen['id']; ?>" id="eliminar_adicional_<?php echo $imagen['id']; ?>">
                                                <label for="eliminar_adicional_<?php echo $imagen['id']; ?>" style="cursor: pointer;">Eliminar</label>
                                            </div>
                                            <div class="imagen-tipo-badge">Adicional</div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <p class="note">Marca las casillas para eliminar imágenes específicas.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Subir nuevas imágenes adicionales -->
                    <div class="clasificacion-info info-adicional">
                        <i class="fa-solid fa-camera-retro info-icon"></i>
                        <div>
                            <strong>Imágenes Adicionales:</strong> Sube fotos del producto desde diferentes ángulos, en uso, instalación, comparativas, etc.
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Agregar Nuevas Imágenes Adicionales (Opcional)</label>
                        <div class="image-upload-container adicionales" id="adicionalesUploadContainer">
                            <div class="clasificacion-badge badge-adicional">Adicional</div>
                            <div class="image-upload-icon icon-adicional">
                                <i class="fa-solid fa-images"></i>
                            </div>
                            <div class="image-upload-text">
                                Sube nuevas fotos adicionales del producto, diferentes ángulos, en uso
                            </div>
                            <button type="button" class="image-upload-btn btn-adicional" onclick="document.getElementById('imagenes_adicionales').click()">
                                <i class="fa-solid fa-upload"></i> Agregar Imágenes Adicionales
                            </button>
                            <input type="file" id="imagenes_adicionales" name="imagenes_adicionales[]" accept=".jpg,.jpeg,.png,.webp" multiple style="display: none;" onchange="previewMultipleImages(this, 'adicional')">
                            <p class="note"><i class="fa-solid fa-circle-info"></i> Puedes seleccionar múltiples imágenes. Máximo 5MB por imagen.</p>
                            
                            <div id="adicionalPreviewContainer" class="image-preview-container">
                                <!-- Vistas previas de nuevas imágenes adicionales -->
                            </div>
                            <div id="adicionalCount" class="image-count"></div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-save"></i> Guardar Cambios
                </button>
            </form>
        </div>
    </main>

    <script>
        // Funciones para las secciones acordeón
        function toggleSection(sectionId) {
            const section = document.getElementById(sectionId);
            const icon = section.previousElementSibling.querySelector('.fa-chevron-down');
            
            section.classList.toggle('active');
            
            if (section.classList.contains('active')) {
                icon.style.transform = 'rotate(180deg)';
            } else {
                icon.style.transform = 'rotate(0deg)';
            }
        }
        
        // Mantener abierta la primera sección por defecto
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('basica').classList.add('active');
            const firstIcon = document.querySelector('#basica').previousElementSibling.querySelector('.fa-chevron-down');
            if (firstIcon) {
                firstIcon.style.transform = 'rotate(180deg)';
            }
            
            // Calcular ahorro inicial
            calcularAhorro();
        });
        
        // Funciones para manejar imágenes
        const imagePreviews = {
            principal: [],
            tecnica: [],
            contenido: [],
            adicional: []
        };
        
        // Función para vista previa de imagen única (principal)
        function previewImage(input, type) {
            const container = document.getElementById(`${type}PreviewContainer`);
            const countElement = document.getElementById(`${type}Count`);
            container.innerHTML = '';
            imagePreviews[type] = [];
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const preview = {
                        src: e.target.result,
                        name: input.files[0].name
                    };
                    
                    imagePreviews[type].push(preview);
                    
                    const previewElement = document.createElement('div');
                    previewElement.className = `image-preview ${type}`;
                    previewElement.innerHTML = `
                        <img src="${e.target.result}" alt="${input.files[0].name}">
                        <button type="button" class="remove-image" onclick="removeImage('${type}', 0)">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    `;
                    
                    container.appendChild(previewElement);
                    container.style.display = 'grid';
                    updateImageCount(type);
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Función para vista previa de múltiples imágenes
        function previewMultipleImages(input, type) {
            const previewContainer = document.getElementById(`${type}PreviewContainer`);
            
            // Limpiar vistas previas de nuevas imágenes
            imagePreviews[type] = [];
            previewContainer.innerHTML = '';
            
            // Procesar cada archivo seleccionado
            for (let i = 0; i < input.files.length; i++) {
                const file = input.files[i];
                
                if (file.type.match('image.*')) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const preview = {
                            index: imagePreviews[type].length,
                            src: e.target.result,
                            name: file.name
                        };
                        
                        imagePreviews[type].push(preview);
                        
                        // Crear elemento de vista previa
                        const previewElement = document.createElement('div');
                        previewElement.className = `image-preview ${type}`;
                        previewElement.innerHTML = `
                            <img src="${e.target.result}" alt="${file.name}">
                            <button type="button" class="remove-image" onclick="removeImage('${type}', ${imagePreviews[type].length - 1})">
                                <i class="fa-solid fa-times"></i>
                            </button>
                        `;
                        
                        previewContainer.appendChild(previewElement);
                        previewContainer.style.display = 'grid';
                        updateImageCount(type);
                    }
                    
                    reader.readAsDataURL(file);
                }
            }
        }
        
        // Función para eliminar imagen
        function removeImage(type, index) {
            const input = document.getElementById(type === 'principal' ? 'imagen_principal' : `imagenes_${type}s`);
            
            if (type === 'principal') {
                // Para imagen principal
                input.value = '';
                imagePreviews[type] = [];
                document.getElementById(`${type}PreviewContainer`).innerHTML = '';
                document.getElementById(`${type}PreviewContainer`).style.display = 'none';
                updateImageCount(type);
            } else {
                // Para imágenes múltiples
                const dataTransfer = new DataTransfer();
                
                // Agregar todos los archivos excepto el eliminado
                for (let i = 0; i < input.files.length; i++) {
                    if (i !== index) {
                        dataTransfer.items.add(input.files[i]);
                    }
                }
                
                // Actualizar el input file
                input.files = dataTransfer.files;
                
                // Actualizar las vistas previas
                previewMultipleImages(input, type);
            }
        }
        
        // Función para actualizar contador de imágenes
        function updateImageCount(type) {
            const countElement = document.getElementById(`${type}Count`);
            const count = imagePreviews[type].length;
            
            if (count > 0) {
                countElement.textContent = `${count} nueva(s) imagen(es) ${getClasificacionName(type)} seleccionada(s)`;
                countElement.style.display = 'block';
            } else {
                countElement.style.display = 'none';
            }
        }
        
        // Función para obtener nombre de clasificación
        function getClasificacionName(type) {
            const names = {
                'principal': 'principal',
                'tecnica': 'técnica',
                'contenido': 'de contenido',
                'adicional': 'adicional'
            };
            return names[type] || '';
        }
        
        // Función para calcular ahorro entre precios
        function calcularAhorro() {
            const precioMinorista = parseFloat(document.getElementById('precioMinorista').value) || 0;
            const precioMayorista = parseFloat(document.getElementById('precioMayorista').value) || 0;
            const ahorroContainer = document.getElementById('ahorroContainer');
            const ahorroText = document.getElementById('ahorroText');
            
            if (precioMinorista > 0 && precioMayorista > 0 && precioMayorista < precioMinorista) {
                const ahorro = precioMinorista - precioMayorista;
                const porcentajeAhorro = ((ahorro / precioMinorista) * 100).toFixed(1);
                
                ahorroText.textContent = `Ahorro por unidad: $${ahorro.toFixed(2)} (${porcentajeAhorro}%)`;
                ahorroContainer.style.backgroundColor = '#dcfce7';
                ahorroContainer.style.color = '#166534';
                ahorroContainer.style.display = 'block';
            } else if (precioMinorista > 0 && precioMayorista > 0 && precioMayorista > precioMinorista) {
                ahorroText.textContent = 'El precio mayorista debe ser menor o igual al precio minorista';
                ahorroContainer.style.backgroundColor = '#fee2e2';
                ahorroContainer.style.color = '#991b1b';
                ahorroContainer.style.display = 'block';
            } else {
                ahorroContainer.style.display = 'none';
            }
        }
        
        // Event listeners para calcular ahorro
        const precioMinoristaInput = document.getElementById('precioMinorista');
        const precioMayoristaInput = document.getElementById('precioMayorista');
        
        if (precioMinoristaInput) {
            precioMinoristaInput.addEventListener('input', calcularAhorro);
        }
        if (precioMayoristaInput) {
            precioMayoristaInput.addEventListener('input', calcularAhorro);
        }
        
        // Validación del formulario
        document.getElementById('productoForm').addEventListener('submit', function(e) {
            // Validar precios
            const precioMinorista = parseFloat(document.getElementById('precioMinorista').value);
            const precioMayorista = parseFloat(document.getElementById('precioMayorista').value);
            
            if (precioMayorista > precioMinorista) {
                e.preventDefault();
                alert('El precio mayorista no puede ser mayor que el precio minorista.');
                return false;
            }
            
            // Validar que haya imagen principal si no hay una existente
            const imagenPrincipalInput = document.getElementById('imagen_principal');
            const hasExistingPrincipal = <?php echo !empty($imagenes_por_clasificacion['principal']) ? 'true' : 'false'; ?>;
            const isPrincipalMarkedForDeletion = document.querySelector('input[name="eliminar_imagenes[]"]:checked') !== null;
            
            if (!hasExistingPrincipal && !imagenPrincipalInput.files.length) {
                e.preventDefault();
                alert('Por favor, selecciona una imagen principal para el producto.');
                return false;
            }
            
            if (hasExistingPrincipal && isPrincipalMarkedForDeletion && !imagenPrincipalInput.files.length) {
                e.preventDefault();
                alert('Si eliminas la imagen principal actual, debes seleccionar una nueva imagen principal.');
                return false;
            }
            
            // Validar tamaño máximo de archivos (5MB)
            const maxSize = 5 * 1024 * 1024;
            const allInputs = [
                imagenPrincipalInput,
                document.getElementById('imagenes_tecnicas'),
                document.getElementById('imagenes_contenido'),
                document.getElementById('imagenes_adicionales')
            ];
            
            for (const input of allInputs) {
                if (input && input.files) {
                    for (let i = 0; i < input.files.length; i++) {
                        if (input.files[i].size > maxSize) {
                            e.preventDefault();
                            alert(`El archivo "${input.files[i].name}" excede el tamaño máximo de 5MB.`);
                            return false;
                        }
                    }
                }
            }
            
            return true;
        });
        
        // Confirmar eliminación de imágenes
        document.querySelectorAll('input[name="eliminar_imagenes[]"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    const confirmDelete = confirm('¿Estás seguro de que quieres eliminar esta imagen?');
                    if (!confirmDelete) {
                        this.checked = false;
                    }
                }
            });
        });
    </script>

</body>
</html>