<?php
session_start();
require '../config/db.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$usuario_id = $_SESSION['user_id'];
$nombre_usuario = $_SESSION['nombre'] ?? '';

// Inicializar variables
$errors = [];
$success_message = '';
$warning_message = '';
$tab_activa = 'perfil'; // Tab por defecto

// Obtener información del usuario
$stmt = $pdo->prepare("
    SELECT u.*, 
           GROUP_CONCAT(DISTINCT a.nombre ORDER BY a.nombre SEPARATOR ', ') as areas_nombres
    FROM usuarios u
    LEFT JOIN usuario_areas ua ON u.id = ua.usuario_id
    LEFT JOIN areas_interes a ON ua.area_id = a.id
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header('Location: ../login.php');
    exit();
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password)) {
        $errors[] = "Debes ingresar tu contraseña actual";
    } elseif (!password_verify($current_password, $usuario['password'])) {
        $errors[] = "La contraseña actual es incorrecta";
    } elseif (empty($new_password)) {
        $errors[] = "Debes ingresar una nueva contraseña";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "La nueva contraseña debe tener al menos 8 caracteres";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "Las contraseñas nuevas no coinciden";
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $usuario_id]);
            
            $success_message = "Contraseña actualizada correctamente";
            $tab_activa = 'seguridad';
        } catch (PDOException $e) {
            $errors[] = "Error al actualizar la contraseña: " . $e->getMessage();
        }
    }
}

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_perfil'])) {
    $telefono_celular = trim($_POST['telefono_celular'] ?? '');
    $telefono_oficina = trim($_POST['telefono_oficina'] ?? '');
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
    $pagina_web = trim($_POST['pagina_web'] ?? '');
    $vende_internet = isset($_POST['vende_internet']) ? 1 : 0;
    $moneda = $_POST['moneda'] ?? 'MXN';
    $areas_interes = $_POST['areas_interes'] ?? [];
    $comentarios = trim($_POST['comentarios'] ?? '');
    
    // Validaciones
    if (empty($telefono_celular)) {
        $errors[] = "El teléfono celular es obligatorio";
    } elseif (!preg_match('/^[0-9\-\+\s\(\)]{10,20}$/', $telefono_celular)) {
        $errors[] = "El teléfono celular no es válido";
    }
    
    if (!empty($telefono_oficina) && !preg_match('/^[0-9\-\+\s\(\)]{10,20}$/', $telefono_oficina)) {
        $errors[] = "El teléfono de oficina no es válido";
    }
    
    if (!empty($fecha_nacimiento)) {
        $fecha_valida = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento);
        if (!$fecha_valida || $fecha_valida->format('Y-m-d') !== $fecha_nacimiento) {
            $errors[] = "La fecha de nacimiento no es válida";
        }
    }
    
    if (!empty($pagina_web) && !filter_var($pagina_web, FILTER_VALIDATE_URL)) {
        $errors[] = "La página web no tiene un formato válido";
    }
    
    // Si no hay errores, actualizar
    if (empty($errors)) {
        try {
            // Actualizar datos básicos
            $stmt = $pdo->prepare("
                UPDATE usuarios SET 
                    telefono_celular = ?,
                    telefono_oficina = ?,
                    fecha_nacimiento = ?,
                    pagina_web = ?,
                    vende_internet = ?,
                    moneda = ?,
                    comentarios = ?
                WHERE id = ?
            ");
            
            $fecha_nacimiento = empty($fecha_nacimiento) ? null : $fecha_nacimiento;
            
            $stmt->execute([
                $telefono_celular,
                $telefono_oficina,
                $fecha_nacimiento,
                $pagina_web,
                $vende_internet,
                $moneda,
                $comentarios,
                $usuario_id
            ]);
            
            // Actualizar áreas de interés
            // Primero eliminar las existentes
            $stmt_delete = $pdo->prepare("DELETE FROM usuario_areas WHERE usuario_id = ?");
            $stmt_delete->execute([$usuario_id]);
            
            // Insertar las nuevas áreas
            if (!empty($areas_interes)) {
                $stmt_insert = $pdo->prepare("INSERT INTO usuario_areas (usuario_id, area_id) VALUES (?, ?)");
                foreach ($areas_interes as $area_id) {
                    if (is_numeric($area_id)) {
                        $stmt_insert->execute([$usuario_id, intval($area_id)]);
                    }
                }
            }
            
            $success_message = "Perfil actualizado correctamente";
            $tab_activa = 'perfil';
            
            // Refrescar datos del usuario
            $stmt->execute([$usuario_id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $errors[] = "Error al actualizar el perfil: " . $e->getMessage();
        }
    }
}

// Procesar preferencias de notificaciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_notificaciones'])) {
    $notificaciones_email = isset($_POST['notificaciones_email']) ? 1 : 0;
    $notificaciones_pedidos = isset($_POST['notificaciones_pedidos']) ? 1 : 0;
    $notificaciones_promociones = isset($_POST['notificaciones_promociones']) ? 1 : 0;
    
    // Aquí normalmente actualizarías en una tabla de preferencias
    // Por ahora solo mostramos mensaje
    $success_message = "Preferencias de notificaciones actualizadas";
    $tab_activa = 'notificaciones';
}

// Obtener todas las áreas de interés para el select
$stmt_areas = $pdo->query("SELECT * FROM areas_interes WHERE activo = 1 ORDER BY nombre");
$areas = $stmt_areas->fetchAll(PDO::FETCH_ASSOC);

// Obtener áreas seleccionadas por el usuario
$stmt_user_areas = $pdo->prepare("SELECT area_id FROM usuario_areas WHERE usuario_id = ?");
$stmt_user_areas->execute([$usuario_id]);
$user_areas_selected = $stmt_user_areas->fetchAll(PDO::FETCH_COLUMN, 0);

// Determinar tab activa desde GET
if (isset($_GET['tab'])) {
    $tab_activa = $_GET['tab'];
}

// Formatear fecha de nacimiento para input
$fecha_nacimiento_formatted = '';
if (!empty($usuario['fecha_nacimiento']) && $usuario['fecha_nacimiento'] != '0000-00-00') {
    $fecha_nacimiento_formatted = date('Y-m-d', strtotime($usuario['fecha_nacimiento']));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Cuenta | NGS Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --ngs-blue: rgb(6 19 37 / 95%);
            --ngs-accent: #0d6efd;
            --bg-light: #f8f9fa;
            --sidebar-width: 280px;
        }

        body {
            background-color: var(--bg-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .profile-banner {
            background: linear-gradient(rgba(6, 19, 37, 0.9), rgba(6, 19, 37, 0.95)), 
                        url('../assets/img/banner-hero.webp');
            background-size: cover;
            background-position: center;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }

        .profile-banner-content {
            color: white;
        }

        .profile-banner h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
        }

        .profile-banner p {
            font-size: 1rem;
            opacity: 0.9;
        }

        /* Layout principal */
        .config-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            gap: 2rem;
            padding: 0 15px;
        }

        /* Sidebar */
        .config-sidebar {
            width: var(--sidebar-width);
            flex-shrink: 0;
        }

        .sidebar-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #eaeaea;
            overflow: hidden;
        }

        .user-info {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid #eee;
            background: linear-gradient(135deg, var(--ngs-blue) 0%, #1a365d 100%);
            color: white;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            color: var(--ngs-blue);
        }

        .user-name {
            font-weight: 600;
            margin-bottom: 0.3rem;
            font-size: 1.1rem;
        }

        .user-role {
            font-size: 0.9rem;
            opacity: 0.9;
            background: rgba(255,255,255,0.2);
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            display: inline-block;
        }

        .user-details {
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            font-size: 0.9rem;
        }

        .user-detail-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }

        .user-detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #666;
        }

        .detail-value {
            font-weight: 500;
        }

        /* Navegación */
        .nav-vertical {
            display: flex;
            flex-direction: column;
            padding: 1rem 0;
        }

        .nav-link-sidebar {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 1.5rem;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .nav-link-sidebar:hover {
            background-color: #f8f9fa;
            color: var(--ngs-blue);
        }

        .nav-link-sidebar.active {
            background-color: rgba(var(--ngs-blue), 0.05);
            color: var(--ngs-blue);
            border-left-color: var(--ngs-blue);
            font-weight: 500;
        }

        .nav-link-sidebar i {
            width: 20px;
            text-align: center;
        }

        /* Contenido principal */
        .config-content {
            flex: 1;
        }

        .config-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #eaeaea;
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .config-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            background: #f8f9fa;
        }

        .config-header h2 {
            margin: 0;
            color: var(--ngs-blue);
            font-size: 1.5rem;
            font-weight: 600;
        }

        .config-body {
            padding: 1.5rem;
        }

        /* Formularios */
        .form-section {
            margin-bottom: 2rem;
        }

        .form-section h4 {
            color: #333;
            font-size: 1.2rem;
            margin-bottom: 1.2rem;
            padding-bottom: 0.7rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 0.3rem;
        }

        .required::after {
            content: " *";
            color: #dc3545;
        }

        .form-text-help {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.3rem;
        }

        .form-check-input:checked {
            background-color: var(--ngs-blue);
            border-color: var(--ngs-blue);
        }

        .btn-primary-custom {
            background-color: var(--ngs-blue);
            border-color: var(--ngs-blue);
            color: white;
            padding: 0.5rem 2rem;
            font-weight: 500;
        }

        .btn-primary-custom:hover {
            background-color: #051225;
            border-color: #051225;
            color: white;
        }

        .btn-outline-custom {
            border-color: #ddd;
            color: #495057;
            padding: 0.5rem 1.5rem;
        }

        .btn-outline-custom:hover {
            background-color: #f8f9fa;
            border-color: #ccc;
        }

        /* Alertas */
        .alert {
            margin-bottom: 1.5rem;
        }

        /* Tarjeta de información */
        .info-card {
            background: #e7f3ff;
            border: 1px solid #b6d4fe;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .info-card h5 {
            color: #084298;
            margin-bottom: 0.8rem;
            font-size: 1.1rem;
        }

        .info-card p {
            color: #055160;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        /* Select de áreas de interés */
        .areas-select-container {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            padding: 0.5rem;
        }

        .area-checkbox-item {
            padding: 0.5rem;
            border-bottom: 1px solid #eee;
        }

        .area-checkbox-item:last-child {
            border-bottom: none;
        }

        .area-checkbox-item:hover {
            background-color: #f8f9fa;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .config-container {
                flex-direction: column;
            }
            
            .config-sidebar {
                width: 100%;
            }
            
            .nav-vertical {
                flex-direction: row;
                overflow-x: auto;
                padding: 0.5rem;
            }
            
            .nav-link-sidebar {
                white-space: nowrap;
                border-left: none;
                border-bottom: 3px solid transparent;
            }
            
            .nav-link-sidebar.active {
                border-left: none;
                border-bottom-color: var(--ngs-blue);
            }
        }

        @media (max-width: 768px) {
            .profile-banner {
                padding: 2rem 0;
            }
            
            .profile-banner h1 {
                font-size: 1.8rem;
            }
            
            .config-body {
                padding: 1rem;
            }
        }

        /* Indicador de fuerza de contraseña */
        .password-strength {
            height: 5px;
            background: #e9ecef;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0;
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .strength-weak {
            background-color: #dc3545;
        }

        .strength-medium {
            background-color: #ffc107;
        }

        .strength-strong {
            background-color: #28a745;
        }
    </style>
</head>
<body>

<!-- Banner de perfil -->
<div class="profile-banner">
    <div class="container">
        <div class="profile-banner-content">
            <h1>Configuración de Cuenta</h1>
            <p>Administra tu perfil, seguridad y preferencias</p>
        </div>
    </div>
</div>

<div class="container config-container">
    <!-- Sidebar de navegación -->
    <div class="config-sidebar">
        <div class="sidebar-card">
            <!-- Información del usuario -->
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div class="user-name"><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido_paterno']); ?></div>
                <div class="user-role">
                    <?php 
                    $roles = [
                        'cliente' => 'Cliente',
                        'distribuidor' => 'Distribuidor',
                        'admin' => 'Administrador',
                        'dueño' => 'Dueño'
                    ];
                    echo htmlspecialchars($roles[$usuario['rol']] ?? $usuario['rol']);
                    ?>
                </div>
            </div>
            
            <div class="user-details">
                <div class="user-detail-item">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($usuario['email']); ?></span>
                </div>
                <div class="user-detail-item">
                    <span class="detail-label">Estado:</span>
                    <span class="detail-value">
                        <?php 
                        $estatus_colors = [
                            'activo' => 'success',
                            'pendiente' => 'warning',
                            'inactivo' => 'danger',
                            'rechazado' => 'danger'
                        ];
                        $estatus_text = [
                            'activo' => 'Activo',
                            'pendiente' => 'Pendiente',
                            'inactivo' => 'Inactivo',
                            'rechazado' => 'Rechazado'
                        ];
                        $color = $estatus_colors[$usuario['estatus']] ?? 'secondary';
                        $text = $estatus_text[$usuario['estatus']] ?? $usuario['estatus'];
                        ?>
                        <span class="badge bg-<?php echo $color; ?>"><?php echo htmlspecialchars($text); ?></span>
                    </span>
                </div>
                <div class="user-detail-item">
                    <span class="detail-label">Registro:</span>
                    <span class="detail-value"><?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?></span>
                </div>
                <?php if ($usuario['ultimo_login']): ?>
                <div class="user-detail-item">
                    <span class="detail-label">Último acceso:</span>
                    <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($usuario['ultimo_login'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Navegación vertical -->
            <nav class="nav-vertical">
                <a href="?tab=perfil" class="nav-link-sidebar <?php echo $tab_activa == 'perfil' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-edit"></i>
                    <span>Perfil</span>
                </a>
                <a href="?tab=seguridad" class="nav-link-sidebar <?php echo $tab_activa == 'seguridad' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>Seguridad</span>
                </a>
                <a href="?tab=notificaciones" class="nav-link-sidebar <?php echo $tab_activa == 'notificaciones' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-bell"></i>
                    <span>Notificaciones</span>
                </a>
                <a href="?tab=preferencias" class="nav-link-sidebar <?php echo $tab_activa == 'preferencias' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-sliders"></i>
                    <span>Preferencias</span>
                </a>
                <div class="mt-3 px-3">
                    <a href="perfil.php" class="btn btn-outline-custom w-100">
                        <i class="fa-solid fa-arrow-left me-2"></i>Volver al Perfil
                    </a>
                </div>
            </nav>
        </div>
    </div>

    <!-- Contenido principal -->
    <div class="config-content">
        <!-- Mensajes de notificación -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <h5 class="alert-heading"><i class="fa-solid fa-exclamation-triangle me-2"></i>Errores encontrados</h5>
                <ul class="mb-0">
                    <?php foreach($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($warning_message)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                <?php echo htmlspecialchars($warning_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Pestaña: Perfil -->
        <?php if ($tab_activa == 'perfil'): ?>
            <div class="config-card">
                <div class="config-header">
                    <h2><i class="fa-solid fa-user-edit me-2"></i>Información del Perfil</h2>
                </div>
                <div class="config-body">
                    <form method="POST" action="">
                        <!-- Información de contacto -->
                        <div class="form-section">
                            <h4>Información de Contacto</h4>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="telefono_celular" class="form-label required">Teléfono Celular</label>
                                    <input type="tel" 
                                           class="form-control" 
                                           id="telefono_celular" 
                                           name="telefono_celular" 
                                           value="<?php echo htmlspecialchars($usuario['telefono_celular']); ?>"
                                           placeholder="Ej: 7711510756"
                                           required>
                                    <div class="form-text-help">Tu número de contacto principal</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="telefono_oficina" class="form-label">Teléfono de Oficina</label>
                                    <input type="tel" 
                                           class="form-control" 
                                           id="telefono_oficina" 
                                           name="telefono_oficina" 
                                           value="<?php echo htmlspecialchars($usuario['telefono_oficina']); ?>"
                                           placeholder="Ej: 7711234567">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="fecha_nacimiento" 
                                           name="fecha_nacimiento" 
                                           value="<?php echo htmlspecialchars($fecha_nacimiento_formatted); ?>"
                                           max="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="pagina_web" class="form-label">Página Web</label>
                                    <input type="url" 
                                           class="form-control" 
                                           id="pagina_web" 
                                           name="pagina_web" 
                                           value="<?php echo htmlspecialchars($usuario['pagina_web']); ?>"
                                           placeholder="https://www.ejemplo.com">
                                </div>
                            </div>
                        </div>

                        <!-- Preferencias de negocio -->
                        <div class="form-section">
                            <h4>Preferencias de Negocio</h4>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Moneda Preferida</label>
                                    <select class="form-select" name="moneda">
                                        <option value="MXN" <?php echo $usuario['moneda'] == 'MXN' ? 'selected' : ''; ?>>Pesos Mexicanos (MXN)</option>
                                        <option value="USD" <?php echo $usuario['moneda'] == 'USD' ? 'selected' : ''; ?>>Dólares Americanos (USD)</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-check mt-4 pt-2">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="vende_internet" 
                                               name="vende_internet" 
                                               value="1"
                                               <?php echo $usuario['vende_internet'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="vende_internet">
                                            Vende productos por internet
                                        </label>
                                        <div class="form-text-help">¿Vendes productos en línea o tienes tienda virtual?</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Áreas de Interés</label>
                                <div class="areas-select-container">
                                    <?php foreach($areas as $area): ?>
                                        <div class="area-checkbox-item">
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       id="area_<?php echo $area['id']; ?>" 
                                                       name="areas_interes[]" 
                                                       value="<?php echo $area['id']; ?>"
                                                       <?php echo in_array($area['id'], $user_areas_selected) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="area_<?php echo $area['id']; ?>">
                                                    <?php echo htmlspecialchars($area['nombre']); ?>
                                                    <?php if (!empty($area['descripcion'])): ?>
                                                        <small class="text-muted d-block"><?php echo htmlspecialchars($area['descripcion']); ?></small>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text-help">Selecciona las áreas en las que estás interesado para recibir información relevante</div>
                            </div>
                        </div>

                        <!-- Comentarios adicionales -->
                        <div class="form-section">
                            <h4>Información Adicional</h4>
                            <div class="mb-3">
                                <label for="comentarios" class="form-label">Comentarios o Información Adicional</label>
                                <textarea class="form-control" 
                                          id="comentarios" 
                                          name="comentarios" 
                                          rows="3"
                                          placeholder="Cualquier información adicional que quieras compartir con nosotros..."><?php echo htmlspecialchars($usuario['comentarios']); ?></textarea>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="d-flex justify-content-between">
                            <button type="submit" name="actualizar_perfil" class="btn btn-primary-custom">
                                <i class="fa-solid fa-save me-2"></i>Guardar Cambios
                            </button>
                            <a href="perfil.php" class="btn btn-outline-custom">
                                <i class="fa-solid fa-times me-2"></i>Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Pestaña: Seguridad -->
        <?php if ($tab_activa == 'seguridad'): ?>
            <div class="config-card">
                <div class="config-header">
                    <h2><i class="fa-solid fa-shield-halved me-2"></i>Seguridad y Contraseña</h2>
                </div>
                <div class="config-body">
                    <!-- Tarjeta informativa -->
                    <div class="info-card">
                        <h5><i class="fa-solid fa-circle-info me-2"></i>Consejos de seguridad</h5>
                        <p>• Usa una contraseña única que no hayas usado en otros sitios</p>
                        <p>• Incluye mayúsculas, minúsculas, números y símbolos</p>
                        <p>• Evita información personal como tu nombre o fecha de nacimiento</p>
                        <p>• Considera usar un gestor de contraseñas</p>
                    </div>

                    <form method="POST" action="" id="passwordForm">
                        <div class="form-section">
                            <h4>Cambiar Contraseña</h4>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="current_password" class="form-label required">Contraseña Actual</label>
                                    <input type="password" 
                                           class="form-control" 
                                           id="current_password" 
                                           name="current_password" 
                                           required>
                                    <div class="form-text-help">Debes ingresar tu contraseña actual para realizar cambios</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="new_password" class="form-label required">Nueva Contraseña</label>
                                    <input type="password" 
                                           class="form-control" 
                                           id="new_password" 
                                           name="new_password" 
                                           required
                                           minlength="8">
                                    <div class="form-text-help">Mínimo 8 caracteres</div>
                                    <div class="password-strength">
                                        <div class="password-strength-bar" id="passwordStrengthBar"></div>
                                    </div>
                                    <small class="text-muted" id="passwordStrengthText">Fuerza: --</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label required">Confirmar Nueva Contraseña</label>
                                    <input type="password" 
                                           class="form-control" 
                                           id="confirm_password" 
                                           name="confirm_password" 
                                           required
                                           minlength="8">
                                    <div class="form-text-help">Vuelve a escribir la nueva contraseña</div>
                                    <div class="invalid-feedback" id="passwordMatchError">Las contraseñas no coinciden</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" name="cambiar_password" class="btn btn-primary-custom">
                                <i class="fa-solid fa-key me-2"></i>Cambiar Contraseña
                            </button>
                            <a href="?tab=perfil" class="btn btn-outline-custom">
                                <i class="fa-solid fa-arrow-left me-2"></i>Volver a Perfil
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Pestaña: Notificaciones -->
        <?php if ($tab_activa == 'notificaciones'): ?>
            <div class="config-card">
                <div class="config-header">
                    <h2><i class="fa-solid fa-bell me-2"></i>Preferencias de Notificaciones</h2>
                </div>
                <div class="config-body">
                    <form method="POST" action="">
                        <div class="form-section">
                            <h4>Configurar Notificaciones</h4>
                            <p class="text-muted mb-4">Elige qué tipo de notificaciones quieres recibir en tu correo electrónico.</p>
                            
                            <div class="mb-4">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="notificaciones_email" 
                                           name="notificaciones_email" 
                                           value="1"
                                           checked>
                                    <label class="form-check-label" for="notificaciones_email">
                                        <strong>Notificaciones por Email</strong>
                                    </label>
                                    <div class="form-text-help">Recibir notificaciones importantes en tu correo electrónico</div>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="notificaciones_pedidos" 
                                           name="notificaciones_pedidos" 
                                           value="1"
                                           checked>
                                    <label class="form-check-label" for="notificaciones_pedidos">
                                        <strong>Actualizaciones de Pedidos</strong>
                                    </label>
                                    <div class="form-text-help">Recibir notificaciones cuando tus pedidos cambien de estado</div>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="notificaciones_promociones" 
                                           name="notificaciones_promociones" 
                                           value="1"
                                           checked>
                                    <label class="form-check-label" for="notificaciones_promociones">
                                        <strong>Promociones y Ofertas</strong>
                                    </label>
                                    <div class="form-text-help">Recibir información sobre promociones especiales y descuentos</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" name="actualizar_notificaciones" class="btn btn-primary-custom">
                                <i class="fa-solid fa-save me-2"></i>Guardar Preferencias
                            </button>
                            <a href="?tab=perfil" class="btn btn-outline-custom">
                                <i class="fa-solid fa-times me-2"></i>Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Pestaña: Preferencias -->
        <?php if ($tab_activa == 'preferencias'): ?>
            <div class="config-card">
                <div class="config-header">
                    <h2><i class="fa-solid fa-sliders me-2"></i>Preferencias de la Cuenta</h2>
                </div>
                <div class="config-body">
                    <div class="form-section">
                        <h4>Configuración General</h4>
                        
                        <div class="info-card">
                            <h5><i class="fa-solid fa-gear me-2"></i>Configuración del Sistema</h5>
                            <p>La configuración avanzada de tu cuenta está vinculada a tu perfil de cliente.</p>
                            <p>Para realizar cambios en tu información fiscal o empresarial, contacta al administrador.</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Idioma Preferido</label>
                                <select class="form-select" disabled>
                                    <option selected>Español (México)</option>
                                    <option>English (US)</option>
                                </select>
                                <div class="form-text-help">Próximamente disponible</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Zona Horaria</label>
                                <select class="form-select" disabled>
                                    <option selected>America/Mexico_City (GMT-6)</option>
                                    <option>America/New_York (GMT-5)</option>
                                    <option>America/Los_Angeles (GMT-8)</option>
                                </select>
                                <div class="form-text-help">Próximamente disponible</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h4>Exportar Datos</h4>
                        <p class="text-muted mb-3">Puedes solicitar una copia de tus datos personales en formato PDF.</p>
                        
                        <div class="d-grid gap-2 d-md-block">
                            <button class="btn btn-outline-custom" type="button" disabled>
                                <i class="fa-solid fa-file-pdf me-2"></i>Exportar Datos Personales
                            </button>
                            <button class="btn btn-outline-custom" type="button" disabled>
                                <i class="fa-solid fa-file-excel me-2"></i>Exportar Historial de Pedidos
                            </button>
                        </div>
                        <div class="form-text-help mt-2">Próximamente disponible</div>
                    </div>

                    <div class="form-section">
                        <h4>Cuenta</h4>
                        
                        <div class="d-grid gap-2">
                            <a href="#" class="btn btn-outline-danger" onclick="return confirm('¿Estás seguro de que quieres desactivar tu cuenta?')">
                                <i class="fa-solid fa-user-slash me-2"></i>Solicitar Desactivación de Cuenta
                            </a>
                            <small class="text-muted">Esta acción debe ser aprobada por el administrador</small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Validación de fuerza de contraseña
    document.addEventListener('DOMContentLoaded', function() {
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const strengthBar = document.getElementById('passwordStrengthBar');
        const strengthText = document.getElementById('passwordStrengthText');
        
        if (newPassword) {
            newPassword.addEventListener('input', function() {
                const password = this.value;
                const strength = calculatePasswordStrength(password);
                
                // Actualizar barra de fuerza
                strengthBar.style.width = strength.percentage + '%';
                strengthBar.className = 'password-strength-bar ' + strength.class;
                
                // Actualizar texto
                strengthText.textContent = 'Fuerza: ' + strength.text;
                strengthText.className = strength.textColor;
            });
        }
        
        if (confirmPassword && newPassword) {
            confirmPassword.addEventListener('input', function() {
                const isMatch = this.value === newPassword.value;
                
                if (this.value && !isMatch) {
                    this.classList.add('is-invalid');
                    document.getElementById('passwordMatchError').style.display = 'block';
                } else {
                    this.classList.remove('is-invalid');
                    document.getElementById('passwordMatchError').style.display = 'none';
                }
            });
            
            newPassword.addEventListener('input', function() {
                if (confirmPassword.value) {
                    const isMatch = confirmPassword.value === this.value;
                    
                    if (!isMatch) {
                        confirmPassword.classList.add('is-invalid');
                        document.getElementById('passwordMatchError').style.display = 'block';
                    } else {
                        confirmPassword.classList.remove('is-invalid');
                        document.getElementById('passwordMatchError').style.display = 'none';
                    }
                }
            });
        }
        
        // Función para calcular fuerza de contraseña
        function calculatePasswordStrength(password) {
            let score = 0;
            let strength = {
                percentage: 0,
                class: '',
                text: 'Débil',
                textColor: 'text-danger'
            };
            
            if (!password) {
                return strength;
            }
            
            // Longitud
            if (password.length >= 8) score += 20;
            if (password.length >= 12) score += 10;
            
            // Caracteres diversos
            if (/[a-z]/.test(password)) score += 10;
            if (/[A-Z]/.test(password)) score += 15;
            if (/[0-9]/.test(password)) score += 15;
            if (/[^a-zA-Z0-9]/.test(password)) score += 20;
            
            // Evitar secuencias simples
            if (!/(.)\1{2,}/.test(password)) score += 10;
            if (!/\d{4,}/.test(password)) score += 10;
            
            // Limitar score a 100
            score = Math.min(score, 100);
            strength.percentage = score;
            
            // Determinar categoría
            if (score >= 80) {
                strength.class = 'strength-strong';
                strength.text = 'Fuerte';
                strength.textColor = 'text-success';
            } else if (score >= 50) {
                strength.class = 'strength-medium';
                strength.text = 'Media';
                strength.textColor = 'text-warning';
            } else {
                strength.class = 'strength-weak';
                strength.text = 'Débil';
                strength.textColor = 'text-danger';
            }
            
            return strength;
        }
        
        // Validación de formulario de contraseña
        const passwordForm = document.getElementById('passwordForm');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                const currentPass = document.getElementById('current_password');
                const newPass = document.getElementById('new_password');
                const confirmPass = document.getElementById('confirm_password');
                
                let isValid = true;
                
                // Validar que las nuevas contraseñas coincidan
                if (newPass.value !== confirmPass.value) {
                    confirmPass.classList.add('is-invalid');
                    document.getElementById('passwordMatchError').style.display = 'block';
                    isValid = false;
                }
                
                // Validar longitud mínima
                if (newPass.value.length < 8) {
                    newPass.classList.add('is-invalid');
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Por favor, corrige los errores en el formulario.');
                }
            });
        }
        
        // Validación de teléfono
        const telefonoInputs = document.querySelectorAll('input[type="tel"]');
        telefonoInputs.forEach(input => {
            input.addEventListener('blur', function() {
                const value = this.value.trim();
                if (value && !/^[0-9\-\+\s\(\)]{10,20}$/.test(value)) {
                    this.classList.add('is-invalid');
                    this.nextElementSibling.textContent = 'Formato de teléfono inválido (10-20 dígitos)';
                    this.nextElementSibling.style.color = '#dc3545';
                } else {
                    this.classList.remove('is-invalid');
                    const originalText = this.id === 'telefono_celular' 
                        ? 'Tu número de contacto principal' 
                        : '';
                    this.nextElementSibling.textContent = originalText;
                    this.nextElementSibling.style.color = '';
                }
            });
        });
    });
</script>
</body>
</html>