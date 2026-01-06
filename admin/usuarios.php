<?php
session_start();
include __DIR__ . '/../config/db.php';

// 1. SEGURIDAD: Solo el DUEÑO puede entrar aquí
if (!isset($_SESSION['user_rol']) || ($_SESSION['user_rol'] !== 'dueño' && $_SESSION['user_rol'] !== 'dueno')) {
    // Si es admin, lo mandamos al dashboard; si es cliente/anon, a la tienda
    if (isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === 'admin') {
        header('Location: index.php');
    } else {
        header('Location: ../index.php');
    }
    exit;
}

$mensaje = '';

// 2. ACTUALIZAR ROL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['rol'])) {
    $id_user = (int)$_POST['user_id'];
    $nuevo_rol = $_POST['rol'];
    
    // Evitar cambiarse el rol a uno mismo
    if ($id_user != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("UPDATE usuarios SET rol = ? WHERE id = ?");
        if ($stmt->execute([$nuevo_rol, $id_user])) {
            // Redirección para evitar reenvío de formulario (PRG)
            header('Location: usuarios.php?msg=ok');
            exit;
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] == 'ok') {
    $mensaje = "Rol de usuario actualizado correctamente.";
}

// 3. BUSCADOR DE USUARIOS
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($busqueda) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE nombre LIKE ? OR email LIKE ? ORDER BY id DESC");
    $stmt->execute(["%$busqueda%", "%$busqueda%"]);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios | NGS Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root { 
            --primary: #007bff; 
            --primary-hover: #0056b3;
            --bg-body: #f3f4f6; 
            --text-dark: #1f2937; 
            --text-gray: #6b7280; 
            --green-btn: #10b981; 
            --white: #ffffff;
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
            transition: all 0.3s ease; 
        }

        .menu-item i { width: 20px; font-size: 1.1rem; }

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

        /* CONTENIDO PRINCIPAL */
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }

        /* ALERTAS */
        .alert-success { 
            background: #dcfce7; color: #166534; 
            padding: 15px; border-radius: 12px; 
            margin-bottom: 25px; border: 1px solid #bbf7d0; 
            font-size: 0.95rem; font-weight: 500;
            display: flex; align-items: center; gap: 10px;
        }

        /* ACTION BAR */
        .action-bar { 
            background: var(--white); padding: 20px; 
            border-radius: 12px; border: 1px solid #e5e7eb; 
            margin-bottom: 30px; display: flex; 
            justify-content: space-between; align-items: center; 
            gap: 15px; 
        }
        
        .search-form { flex: 1; display: flex; max-width: 450px; position: relative; }
        .search-form i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-gray); }
        .search-input { 
            width: 100%; padding: 12px 15px 12px 40px; 
            border: 1px solid #e5e7eb; border-radius: 10px; 
            outline: none; font-family: inherit; transition: 0.2s;
        }
        .search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,123,255,0.1); }
        
        .info-text { color: var(--text-gray); font-size: 0.95rem; font-weight: 500; }
        .info-text b { color: var(--text-dark); font-weight: 700; }

        /* TABLA */
        .table-container { 
            background: var(--white); border-radius: 16px; 
            border: 1px solid #e5e7eb; overflow: hidden; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); 
        }
        
        table { width: 100%; border-collapse: collapse; }
        
        th { 
            background: #f8fafc; text-align: left; 
            padding: 18px 25px; font-size: 0.8rem; 
            color: var(--text-gray); text-transform: uppercase; 
            letter-spacing: 0.05em; border-bottom: 1px solid #e5e7eb; 
            font-weight: 700; 
        }
        
        td { padding: 20px 25px; border-bottom: 1px solid #f1f5f9; color: var(--text-dark); vertical-align: middle; font-size: 0.95rem; }
        
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #fcfdfe; }
        
        .user-cell { display: flex; align-items: center; gap: 15px; }
        
        .user-avatar { 
            width: 42px; height: 42px; 
            background: #eef6ff; color: var(--primary); 
            border-radius: 12px; display: flex; 
            align-items: center; justify-content: center; 
            font-weight: 700; font-size: 1.1rem;
            border: 1px solid #dbeafe;
        }
        
        /* Badges de Rol */
        .badge { 
            padding: 5px 12px; border-radius: 20px; 
            font-size: 0.75rem; font-weight: 800; 
            text-transform: uppercase; display: inline-flex;
            align-items: center; gap: 5px;
        }
        .badge-dueño { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
        .badge-admin { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .badge-cliente { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }

        /* Formulario cambio rol */
        .role-form { display: flex; align-items: center; gap: 10px; }
        
        .role-select { 
            padding: 8px 12px; border-radius: 8px; 
            border: 1px solid #d1d5db; background: #fff; 
            font-size: 0.9rem; font-weight: 500;
            outline: none; cursor: pointer; transition: 0.2s; 
        }
        .role-select:focus { border-color: var(--primary); }
        
        .btn-save { 
            background: var(--primary); color: white; 
            border: none; padding: 8px 15px; 
            border-radius: 8px; cursor: pointer; 
            font-size: 0.85rem; font-weight: 700; 
            transition: 0.3s;
            display: flex; align-items: center; gap: 5px;
        }
        .btn-save:hover { background: var(--primary-hover); transform: translateY(-1px); }
        
        .self-label { 
            font-size: 0.8rem; color: var(--text-gray); 
            font-weight: 600; background: #f1f5f9; 
            padding: 6px 12px; border-radius: 8px; 
            display: inline-flex; align-items: center; gap: 5px;
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <h2><i class="fa-solid fa-shield-halved"></i> ADMIN PANEL</h2>
        
        <nav>
            <a href="index.php" class="menu-item">
                <i class="fa-solid fa-house"></i> Inicio
            </a>
            <a href="productos.php" class="menu-item">
                <i class="fa-solid fa-box-open"></i> Productos
            </a>
            <a href="usuarios.php" class="menu-item active">
                <i class="fa-solid fa-users-gear"></i> Usuarios
            </a>

            <a href="../index.php" class="menu-item" style="margin-top: 20px; border-top: 1px solid #f3f4f6; padding-top: 20px;">
                <i class="fa-solid fa-shop"></i> Ver Tienda
            </a>
        </nav>

        <a href="../logout.php" class="menu-item logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
        </a>
    </aside>

    <main class="main-content">
        <h2 style="margin-top:0; margin-bottom:25px; font-weight:700; color:var(--text-dark);">Directorio de Usuarios</h2>
        
        <?php if($mensaje): ?>
            <div class="alert-success">
                <i class="fa-solid fa-circle-check"></i> <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="action-bar">
            <form class="search-form" method="GET">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="q" class="search-input" placeholder="Buscar por nombre o email..." value="<?php echo htmlspecialchars($busqueda); ?>">
            </form>
            <span class="info-text">Total registrados: <b><?php echo count($usuarios); ?></b></span>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Correo Electrónico</th>
                        <th>Rol Actual</th>
                        <th>Acción / Cambiar Rol</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($usuarios) > 0): ?>
                        <?php foreach($usuarios as $u): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($u['nombre'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:700; color:var(--text-dark);"><?php echo htmlspecialchars($u['nombre']); ?></div>
                                        <div style="font-size:0.75rem; color:var(--text-gray); font-weight:600;">ID: #<?php echo $u['id']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="color:var(--text-gray); font-weight:500;">
                                <i class="fa-regular fa-envelope"></i> <?php echo htmlspecialchars($u['email']); ?>
                            </td>
                            <td>
                                <?php 
                                    $bClass = 'badge-cliente';
                                    $icon = 'fa-user';
                                    if($u['rol']=='admin') { $bClass = 'badge-admin'; $icon = 'fa-user-tie'; }
                                    if($u['rol']=='dueño' || $u['rol']=='dueno') { $bClass = 'badge-dueño'; $icon = 'fa-crown'; }
                                ?>
                                <span class="badge <?php echo $bClass; ?>">
                                    <i class="fa-solid <?php echo $icon; ?>"></i> <?php echo ucfirst($u['rol']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" class="role-form">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <select name="rol" class="role-select">
                                            <option value="cliente" <?php if($u['rol']=='cliente') echo 'selected'; ?>>Cliente</option>
                                            <option value="admin" <?php if($u['rol']=='admin') echo 'selected'; ?>>Admin</option>
                                            <option value="dueño" <?php if($u['rol']=='dueño' || $u['rol']=='dueno') echo 'selected'; ?>>Dueño</option>
                                        </select>
                                        <button type="submit" class="btn-save">
                                            <i class="fa-solid fa-floppy-disk"></i> Actualizar
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="self-label">
                                        <i class="fa-solid fa-user-check"></i> Tu cuenta actual
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align:center; padding:60px; color:var(--text-gray);">
                                <i class="fa-solid fa-user-slash" style="font-size: 2.5rem; display: block; margin-bottom: 15px; opacity: 0.2;"></i>
                                No se encontraron usuarios registrados.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

</body>
</html>
