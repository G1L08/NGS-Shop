<?php
session_start();
include __DIR__ . '/../config/db.php';

// 1. SEGURIDAD: Verificar sesión y rol (Admin y Dueño pueden editar)
$es_dueno = isset($_SESSION['user_rol']) && ($_SESSION['user_rol'] === 'dueño' || $_SESSION['user_rol'] === 'dueno');
$es_admin = isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === 'admin';

if (!$es_dueno && !$es_admin) {
    header('Location: ../index.php'); exit;
}

// 2. OBTENER Y VALIDAR ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header('Location: productos.php'); exit; }

// --- LÓGICA DE CAMBIO DE ESTADO (Eliminar/Reactivar) ---
if (isset($_POST['cambiar_estado'])) {
    $nuevo_estado = $_POST['nuevo_estado'];
    if ($nuevo_estado === 'inactivo' || ($nuevo_estado === 'activo' && $es_dueno)) {
        $fecha = ($nuevo_estado === 'inactivo') ? date('Y-m-d H:i:s') : null;
        $stmt = $pdo->prepare("UPDATE productos SET estado = ?, fecha_inactivo = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $fecha, $id]);
        header("Location: editar_producto.php?id=$id");
        exit;
    }
}

// 3. CARGAR DATOS DEL PRODUCTO
$stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
$stmt->execute([$id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) { header('Location: productos.php'); exit; }

$mensaje = '';

// 4. PROCESAR FORMULARIO DE EDICIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['cambiar_estado'])) {
    $nombre = trim($_POST['nombre']);
    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo']);
    $precio = $_POST['precio'];
    $stock = $_POST['stock'];
    $categoria = $_POST['categoria'];
    $caract = trim($_POST['caracteristica_especial']);
    $desc = trim($_POST['descripcion']);

    $img = $producto['imagen'];
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        $nombre_img = uniqid() . "." . $ext;
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], "../uploads/productos/" . $nombre_img)) {
            $img = "uploads/productos/" . $nombre_img;
        }
    }

    $sql = "UPDATE productos SET nombre=?, marca=?, modelo=?, precio=?, stock=?, categoria=?, caracteristica_especial=?, descripcion=?, imagen=? WHERE id=?";
    $stmtUpd = $pdo->prepare($sql);
    if ($stmtUpd->execute([$nombre, $marca, $modelo, $precio, $stock, $categoria, $caract, $desc, $img, $id])) {
        header('Location: productos.php?msg=actualizado'); exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto | NGS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #007bff; --bg-body: #f3f4f6; --text-dark: #1f2937; --text-gray: #6b7280; --white: #ffffff; --danger: #ef4444; --success: #22c55e; }
        body { margin: 0; font-family: 'Inter', sans-serif; background-color: var(--bg-body); display: flex; height: 100vh; overflow: hidden; }
        
        /* SIDEBAR ORIGINAL */
        .sidebar { width: 260px; background: var(--white); border-right: 1px solid #e5e7eb; padding: 25px 20px; display: flex; flex-direction: column; flex-shrink: 0; }
        .sidebar h2 { font-size: 1.3rem; font-weight: 700; margin-bottom: 30px; color: var(--text-dark); }
        .menu-item { display: flex; align-items: center; gap: 12px; padding: 12px 15px; margin-bottom: 8px; color: var(--text-gray); text-decoration: none; border-radius: 10px; font-weight: 500; }
        .menu-item.active { background-color: #eef6ff; color: var(--primary); font-weight: 700; }

        /* CONTENIDO ORIGINAL */
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .form-card { background: var(--white); padding: 40px; border-radius: 16px; border: 1px solid #e5e7eb; max-width: 800px; margin: 0 auto; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        
        .form-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid #f1f5f9; padding-bottom: 20px; }

        /* BOTONES NUEVOS DE ESTADO */
        .btn-delete { background: #fef2f2; color: var(--danger); border: 1px solid #fee2e2; padding: 10px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-active { background: #f0fdf4; color: var(--success); border: 1px solid #dcfce7; padding: 10px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; }

        /* GRID ORIGINAL */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .full-width { grid-column: span 2; }
        label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; color: var(--text-dark); }
        input, select, textarea { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; box-sizing: border-box; outline: none; }
        .btn-submit { width: 100%; background: var(--primary); color: white; padding: 14px; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; margin-top: 20px; font-size: 1rem; }
        
        .current-img-container { margin-top: 15px; padding: 15px; background: #f8fafc; border-radius: 12px; display: flex; align-items: center; gap: 20px; }
        .current-img { height: 100px; width: 100px; object-fit: contain; border-radius: 8px; background: white; border: 1px solid #e2e8f0; }
        .info-fecha { background: #fff7ed; color: #9a3412; padding: 12px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #ffedd5; font-size: 0.9rem; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <h2>ADMIN PANEL</h2>
        <nav>
            <a href="productos.php" class="menu-item active"><i class="fa-solid fa-box"></i> Productos</a>
        </nav>
        <a href="../logout.php" class="menu-item" style="margin-top:auto; color:var(--danger);"><i class="fa-solid fa-power-off"></i> Salir</a>
    </aside>

    <main class="main-content">
        <div class="form-card">
            <div class="form-header">
                <div>
                    <h2 style="margin:0;">Editar Producto</h2>
                    <span style="font-weight:700; color: <?php echo ($producto['estado'] == 'inactivo') ? 'var(--danger)' : 'var(--success)'; ?>">
                        ● <?php echo strtoupper($producto['estado'] ?: 'ACTIVO'); ?>
                    </span>
                </div>
                
                <div style="display:flex; gap:10px;">
                    <?php if(($producto['estado'] ?: 'activo') === 'activo'): ?>
                        <form method="POST" onsubmit="return confirm('¿Seguro que deseas inactivar este producto?');">
                            <input type="hidden" name="cambiar_estado" value="1">
                            <input type="hidden" name="nuevo_estado" value="inactivo">
                            <button type="submit" class="btn-delete"><i class="fa-solid fa-trash-can"></i> Eliminar Producto</button>
                        </form>
                    <?php elseif($es_dueno): ?>
                        <form method="POST">
                            <input type="hidden" name="cambiar_estado" value="1">
                            <input type="hidden" name="nuevo_estado" value="activo">
                            <button type="submit" class="btn-active"><i class="fa-solid fa-rotate-left"></i> Reactivar Producto</button>
                        </form>
                    <?php endif; ?>
                    <a href="productos.php" style="text-decoration:none; color:var(--text-gray); padding:10px;">Volver</a>
                </div>
            </div>

            <?php if($es_dueno && $producto['estado'] === 'inactivo' && $producto['fecha_inactivo']): ?>
                <div class="info-fecha">
                    <i class="fa-solid fa-calendar-xmark"></i> Producto marcado como inactivo el: <b><?php echo date('d/m/Y H:i', strtotime($producto['fecha_inactivo'])); ?></b>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Nombre del Producto</label>
                        <input type="text" name="nombre" value="<?php echo htmlspecialchars($producto['nombre']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Marca</label>
                        <input type="text" name="marca" value="<?php echo htmlspecialchars($producto['marca']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Modelo</label>
                        <input type="text" name="modelo" value="<?php echo htmlspecialchars($producto['modelo']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Precio ($)</label>
                        <input type="number" step="0.01" name="precio" value="<?php echo $producto['precio']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Stock</label>
                        <input type="number" name="stock" value="<?php echo $producto['stock']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Categoría</label>
                        <select name="categoria">
                            <?php 
                            $cats = ["Computación", "Celulares", "Audio", "Accesorios", "Otros"];
                            foreach($cats as $c) {
                                $sel = ($producto['categoria'] == $c) ? 'selected' : '';
                                echo "<option value='$c' $sel>$c</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Característica Especial</label>
                        <input type="text" name="caracteristica_especial" value="<?php echo htmlspecialchars($producto['caracteristica_especial']); ?>">
                    </div>
                    <div class="form-group full-width">
                        <label>Descripción Detallada</label>
                        <textarea name="descripcion" rows="4"><?php echo htmlspecialchars($producto['descripcion']); ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>Imagen del Producto</label>
                        <?php if(!empty($producto['imagen'])): ?>
                            <div class="current-img-container">
                                <img src="../<?php echo $producto['imagen']; ?>" class="current-img">
                                <div>
                                    <span style="font-size:0.85rem; color:var(--text-gray);">Imagen actual cargada.</span><br>
                                    <span style="font-size:0.85rem; color:var(--text-dark);">Seleccione un archivo nuevo para reemplazarla:</span>
                                </div>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="imagen" accept=".jpg,.jpeg,.png,.webp" style="margin-top:15px;">
                    </div>
                </div>
                <button type="submit" class="btn-submit">Guardar Cambios</button>
            </form>
        </div>
    </main>

</body>
</html>