<?php
session_start();
include __DIR__ . '/../config/db.php';

// 1. SEGURIDAD CORREGIDA: Permitir admin, dueño y dueno (para evitar bloqueos)
if (!isset($_SESSION['user_rol']) || ($_SESSION['user_rol'] !== 'admin' && $_SESSION['user_rol'] !== 'dueño' && $_SESSION['user_rol'] !== 'dueno')) {
    header('Location: ../index.php'); 
    exit;
}

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo']);
    $precio = $_POST['precio'];
    $stock = $_POST['stock'];
    $categoria = $_POST['categoria'];
    $caracteristica = trim($_POST['caracteristica_especial']);
    $descripcion = trim($_POST['descripcion']);
    
    // VALIDACIONES
    if (empty($nombre) || empty($marca) || empty($modelo) || empty($categoria)) {
        $mensaje = "Por favor, completa los campos obligatorios.";
    } 
    elseif (!is_numeric($precio) || $precio < 0) {
        $mensaje = "El precio debe ser un número válido.";
    } 
    elseif (!ctype_digit(strval($stock)) || $stock < 1) { 
        $mensaje = "El stock debe ser al menos 1 unidad.";
    } 
    else {
        $imagen_url = '';
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
            $archivo = $_FILES['imagen'];
            $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            $permitidos = ['jpg', 'jpeg', 'png', 'webp'];
            $max_size = 5 * 1024 * 1024; 

            if (!in_array($ext, $permitidos)) {
                $mensaje = "Formato no válido (Solo JPG, PNG, WEBP).";
            } elseif ($archivo['size'] > $max_size) {
                $mensaje = "La imagen es muy pesada (Máx 5MB).";
            } else {
                $nombre_nuevo = uniqid() . "." . $ext;
                $destino = "../uploads/productos/" . $nombre_nuevo;
                if (move_uploaded_file($archivo['tmp_name'], $destino)) {
                    $imagen_url = "uploads/productos/" . $nombre_nuevo;
                } else {
                    $mensaje = "Error al subir la imagen al servidor.";
                }
            }
        } else {
            $mensaje = "Es obligatorio subir una imagen del producto.";
        }

        if ($mensaje === '') {
            $sql = "INSERT INTO productos (nombre, marca, modelo, precio, stock, categoria, caracteristica_especial, descripcion, imagen, fecha_ingreso) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$nombre, $marca, $modelo, $precio, $stock, $categoria, $caracteristica, $descripcion, $imagen_url])) {
                header('Location: productos.php?msg=guardado');
                exit;
            } else {
                $mensaje = "Error al guardar en la base de datos.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Producto | NGS</title>
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
            border: 1px solid #e5e7eb; max-width: 800px; margin: 0 auto; 
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

        /* GRID */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .form-group { margin-bottom: 5px; }
        .form-group.full-width { grid-column: span 2; }

        label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark); font-size: 0.9rem; }
        
        input, select, textarea { 
            width: 100%; padding: 12px; border: 1px solid #e2e8f0; 
            border-radius: 10px; font-family: inherit; box-sizing: border-box; 
            outline: none; transition: 0.2s; font-size: 0.95rem;
        }
        
        input:focus, select:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,123,255,0.1); }
        textarea { resize: vertical; min-height: 100px; }

        .btn-submit { 
            width: 100%; background: var(--primary); color: white; 
            padding: 14px; border: none; border-radius: 10px; 
            font-size: 1rem; font-weight: 700; cursor: pointer; 
            transition: 0.3s; margin-top: 20px;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-1px); }
        
        .error-msg { 
            color: #b91c1c; background: #fef2f2; padding: 15px; 
            border-radius: 10px; margin-bottom: 25px; border: 1px solid #fee2e2;
            display: flex; align-items: center; gap: 10px; font-weight: 500;
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <h2><i class="fa-solid fa-shield-halved"></i> ADMIN PANEL</h2>
        <nav>
            <a href="index.php" class="menu-item"><i class="fa-solid fa-house"></i> Inicio</a>
            <a href="productos.php" class="menu-item active"><i class="fa-solid fa-box-open"></i> Productos</a>
            <?php if(isset($_SESSION['user_rol']) && ($_SESSION['user_rol'] === 'dueño' || $_SESSION['user_rol'] === 'dueno')): ?>
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
                <h2><i class="fa-solid fa-plus-circle"></i> Nuevo Producto</h2>
                <a href="productos.php" class="btn-cancel">Volver al Listado</a>
            </div>

            <?php if($mensaje): ?>
                <div class="error-msg">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Nombre del Producto</label>
                        <input type="text" name="nombre" required value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>" placeholder="Ej: Laptop Gamer ASUS">
                    </div>

                    <div class="form-group">
                        <label>Marca</label>
                        <input type="text" name="marca" required value="<?php echo isset($_POST['marca']) ? htmlspecialchars($_POST['marca']) : ''; ?>" placeholder="Ej: ASUS">
                    </div>
                    <div class="form-group">
                        <label>Modelo</label>
                        <input type="text" name="modelo" required value="<?php echo isset($_POST['modelo']) ? htmlspecialchars($_POST['modelo']) : ''; ?>" placeholder="Ej: G15 Strix">
                    </div>

                    <div class="form-group">
                        <label>Precio de Venta ($)</label>
                        <input type="number" step="0.01" min="0" name="precio" required value="<?php echo isset($_POST['precio']) ? htmlspecialchars($_POST['precio']) : ''; ?>" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label>Stock Inicial</label>
                        <input type="number" min="1" step="1" name="stock" required value="<?php echo isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : ''; ?>" placeholder="1">
                    </div>

                    <div class="form-group">
                        <label>Categoría</label>
                        <select name="categoria" required>
                            <option value="">Seleccionar...</option>
                            <?php 
                                $cats = ["Computación", "Celulares", "Audio", "Accesorios", "Otros"];
                                foreach($cats as $c) {
                                    $selected = (isset($_POST['categoria']) && $_POST['categoria'] === $c) ? 'selected' : '';
                                    echo "<option value='$c' $selected>$c</option>";
                                }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Característica Especial</label>
                        <input type="text" name="caracteristica_especial" value="<?php echo isset($_POST['caracteristica_especial']) ? htmlspecialchars($_POST['caracteristica_especial']) : ''; ?>" placeholder="Ej: 5G, Resistente al agua">
                    </div>

                    <div class="form-group full-width">
                        <label>Descripción Detallada</label>
                        <textarea name="descripcion" placeholder="Escribe detalles técnicos, garantía, etc..."><?php echo isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : ''; ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label>Imagen del Producto</label>
                        <input type="file" name="imagen" accept=".jpg,.jpeg,.png,.webp" required>
                        <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 8px;">
                            <i class="fa-solid fa-circle-info"></i> JPG, PNG o WEBP. Máximo 5MB.
                        </p>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Registrar Producto
                </button>
            </form>
        </div>
    </main>

</body>
</html>