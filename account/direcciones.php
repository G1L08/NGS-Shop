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

// Inicializar mensajes
$success_message = '';
$error_message = '';
$warning_message = '';

// Procesar acciones (eliminar, establecer como predeterminada)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'eliminar' && isset($_POST['id'])) {
            $id = intval($_POST['id']);
            
            // Verificar que la dirección pertenezca al usuario
            $stmt = $pdo->prepare("SELECT * FROM sucursales WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$id, $usuario_id]);
            $direccion = $stmt->fetch();
            
            if ($direccion) {
                try {
                    // Eliminar la dirección
                    $stmt = $pdo->prepare("DELETE FROM sucursales WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $_SESSION['success'] = "Dirección eliminada correctamente";
                    header('Location: direcciones.php');
                    exit();
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Error al eliminar la dirección: " . $e->getMessage();
                }
            } else {
                $_SESSION['error'] = "Dirección no encontrada o no tienes permisos para eliminarla";
            }
        }
        
        if ($action === 'predeterminada' && isset($_POST['id'])) {
            $id = intval($_POST['id']);
            
            // Verificar que la dirección pertenezca al usuario
            $stmt = $pdo->prepare("SELECT * FROM sucursales WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$id, $usuario_id]);
            $direccion = $stmt->fetch();
            
            if ($direccion) {
                try {
                    // Primero quitar la predeterminada actual (si existe)
                    $stmt = $pdo->prepare("UPDATE sucursales SET activa = 0 WHERE usuario_id = ?");
                    $stmt->execute([$usuario_id]);
                    
                    // Establecer la nueva como predeterminada
                    $stmt = $pdo->prepare("UPDATE sucursales SET activa = 1 WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $_SESSION['success'] = "Dirección establecida como predeterminada";
                    header('Location: direcciones.php');
                    exit();
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Error al actualizar la dirección: " . $e->getMessage();
                }
            } else {
                $_SESSION['error'] = "Dirección no encontrada o no tienes permisos para modificarla";
            }
        }
    }
}

// Mostrar mensajes de sesión
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['warning'])) {
    $warning_message = $_SESSION['warning'];
    unset($_SESSION['warning']);
}

// Obtener las direcciones del usuario
$stmt = $pdo->prepare("
    SELECT * FROM sucursales 
    WHERE usuario_id = ? 
    ORDER BY activa DESC, fecha_registro DESC
");
$stmt->execute([$usuario_id]);
$direcciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener información del usuario para mostrar su dirección principal
$stmt_usuario = $pdo->prepare("
    SELECT calle, num_exterior, num_interior, cp, colonia, ciudad, estado 
    FROM usuarios 
    WHERE id = ?
");
$stmt_usuario->execute([$usuario_id]);
$usuario_info = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Direcciones | NGS Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --ngs-blue: rgb(6 19 37 / 95%);
            --ngs-accent: #0d6efd;
            --bg-light: #f8f9fa;
        }

        body {
            background-color: var(--bg-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Banner de perfil */
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

        /* Contenedor principal */
        .container-custom {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Navegación */
        .nav-buttons {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn-navigation {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-back {
            background-color: var(--ngs-blue);
            color: white;
        }

        .btn-back:hover {
            background-color: #051225;
            color: white;
            transform: translateY(-2px);
        }

        .btn-store {
            background-color: #28a745;
            color: white;
        }

        .btn-store:hover {
            background-color: #218838;
            color: white;
            transform: translateY(-2px);
        }

        .btn-add {
            background-color: var(--ngs-accent);
            color: white;
        }

        .btn-add:hover {
            background-color: #0b5ed7;
            color: white;
            transform: translateY(-2px);
        }

        /* Alertas */
        .alert {
            margin-bottom: 1.5rem;
        }

        /* Dirección principal del usuario */
        .address-main-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #eaeaea;
        }

        .address-main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .address-main-header h3 {
            font-size: 1.3rem;
            color: #333;
            margin: 0;
        }

        .address-main-badge {
            background-color: #28a745;
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .address-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .address-field {
            margin-bottom: 0.5rem;
        }

        .address-field strong {
            display: block;
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.2rem;
        }

        .address-value {
            font-size: 1rem;
            color: #333;
        }

        /* Listado de direcciones */
        .direcciones-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .direccion-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #eaeaea;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }

        .direccion-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .direccion-card.predeterminada {
            border: 2px solid #28a745;
        }

        .direccion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .direccion-header h4 {
            font-size: 1.1rem;
            color: #333;
            margin: 0;
            flex: 1;
        }

        .direccion-badge {
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-predeterminada {
            background-color: #28a745;
            color: white;
        }

        .badge-inactiva {
            background-color: #6c757d;
            color: white;
        }

        .direccion-body {
            margin-bottom: 1.5rem;
        }

        .contacto-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .contacto-field {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .contacto-field i {
            color: #666;
            width: 20px;
        }

        .direccion-actions {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            text-align: center;
        }

        .btn-editar {
            background-color: #ffc107;
            color: #212529;
            flex: 1;
        }

        .btn-editar:hover {
            background-color: #e0a800;
            color: #212529;
        }

        .btn-eliminar {
            background-color: #dc3545;
            color: white;
            flex: 1;
        }

        .btn-eliminar:hover {
            background-color: #c82333;
            color: white;
        }

        .btn-predeterminada {
            background-color: #28a745;
            color: white;
            width: 100%;
        }

        .btn-predeterminada:hover {
            background-color: #218838;
            color: white;
        }

        .btn-predeterminada.disabled {
            background-color: #6c757d;
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Sin direcciones */
        .sin-direcciones {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 10px;
            border: 2px dashed #ddd;
            margin-bottom: 2rem;
        }

        .sin-direcciones i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .sin-direcciones h3 {
            margin-bottom: 1rem;
            color: #333;
        }

        /* Modal de confirmación */
        .modal-confirm {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .modal-header {
            margin-bottom: 1.5rem;
        }

        .modal-header h3 {
            margin: 0;
            color: #333;
        }

        .modal-body {
            margin-bottom: 1.5rem;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .btn-modal {
            padding: 0.5rem 1.5rem;
            border-radius: 6px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-modal-cancel {
            background-color: #6c757d;
            color: white;
        }

        .btn-modal-cancel:hover {
            background-color: #5a6268;
        }

        .btn-modal-confirm {
            background-color: #dc3545;
            color: white;
        }

        .btn-modal-confirm:hover {
            background-color: #c82333;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-banner {
                padding: 2rem 0;
            }
            
            .profile-banner h1 {
                font-size: 1.8rem;
            }
            
            .nav-buttons {
                flex-direction: column;
            }
            
            .btn-navigation {
                width: 100%;
                justify-content: center;
            }
            
            .direcciones-grid {
                grid-template-columns: 1fr;
            }
            
            .address-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<!-- Banner de perfil -->
<div class="profile-banner">
    <div class="container container-custom">
        <div class="profile-banner-content">
            <h1>Mis Direcciones</h1>
            <p>Gestiona tus direcciones de envío y facturación</p>
        </div>
    </div>
</div>

<div class="container container-custom">
    <!-- Navegación -->
    <div class="nav-buttons">
        <a href="perfil.php" class="btn-navigation btn-back">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Volver al Perfil</span>
        </a>
        
        <a href="../index.php" class="btn-navigation btn-store">
            <span>Ir a la Tienda</span>
        </a>
        
        <a href="agregar_direccion.php" class="btn-navigation btn-add">
            <i class="fa-solid fa-plus"></i>
            <span>Agregar Dirección</span>
        </a>
    </div>

    <!-- Mensajes de notificación -->
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
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

    <!-- Dirección principal del usuario (de la tabla usuarios) -->
    <?php if ($usuario_info): ?>
        <div class="address-main-card">
            <div class="address-main-header">
                <h3>Dirección Principal de Facturación</h3>
                <span class="address-main-badge">Dirección de Registro</span>
            </div>
            
            <div class="address-details">
                <div class="address-field">
                    <strong>Calle</strong>
                    <div class="address-value"><?php echo htmlspecialchars($usuario_info['calle']); ?></div>
                </div>
                
                <div class="address-field">
                    <strong>Número Exterior</strong>
                    <div class="address-value"><?php echo htmlspecialchars($usuario_info['num_exterior']); ?></div>
                </div>
                
                <?php if (!empty($usuario_info['num_interior'])): ?>
                    <div class="address-field">
                        <strong>Número Interior</strong>
                        <div class="address-value"><?php echo htmlspecialchars($usuario_info['num_interior']); ?></div>
                    </div>
                <?php endif; ?>
                
                <div class="address-field">
                    <strong>Código Postal</strong>
                    <div class="address-value"><?php echo htmlspecialchars($usuario_info['cp']); ?></div>
                </div>
                
                <div class="address-field">
                    <strong>Colonia</strong>
                    <div class="address-value"><?php echo htmlspecialchars($usuario_info['colonia']); ?></div>
                </div>
                
                <div class="address-field">
                    <strong>Ciudad</strong>
                    <div class="address-value"><?php echo htmlspecialchars($usuario_info['ciudad']); ?></div>
                </div>
                
                <div class="address-field">
                    <strong>Estado</strong>
                    <div class="address-value"><?php echo htmlspecialchars($usuario_info['estado']); ?></div>
                </div>
            </div>
            
            <div class="text-muted small">
                <i class="fa-solid fa-info-circle me-1"></i>
                Esta dirección se utilizó en tu registro y para fines de facturación. Para modificar esta dirección, contacta al administrador.
            </div>
        </div>
    <?php endif; ?>

    <!-- Direcciones de envío (sucursales) -->
    <h2 class="mb-3">Direcciones de Envío</h2>
    
    <?php if (empty($direcciones)): ?>
        <div class="sin-direcciones">
            <i class="fa-solid fa-map-marker-alt"></i>
            <h3>No tienes direcciones de envío registradas</h3>
            <p class="text-muted">Agrega una dirección para poder recibir tus pedidos.</p>
            <a href="agregar_direccion.php" class="btn btn-primary mt-3">
                <i class="fa-solid fa-plus me-2"></i> Agregar primera dirección
            </a>
        </div>
    <?php else: ?>
        <div class="direcciones-grid">
            <?php foreach($direcciones as $direccion): ?>
                <div class="direccion-card <?php echo $direccion['activa'] ? 'predeterminada' : ''; ?>">
                    <div class="direccion-header">
                        <h4><?php echo htmlspecialchars($direccion['nombre']); ?></h4>
                        <span class="direccion-badge <?php echo $direccion['activa'] ? 'badge-predeterminada' : 'badge-inactiva'; ?>">
                            <?php echo $direccion['activa'] ? 'Predeterminada' : 'Inactiva'; ?>
                        </span>
                    </div>
                    
                    <div class="direccion-body">
                        <!-- Información de contacto -->
                        <?php if (!empty($direccion['contacto']) || !empty($direccion['telefono']) || !empty($direccion['email'])): ?>
                            <div class="contacto-info">
                                <?php if (!empty($direccion['contacto'])): ?>
                                    <div class="contacto-field">
                                        <i class="fa-solid fa-user"></i>
                                        <span><?php echo htmlspecialchars($direccion['contacto']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($direccion['telefono'])): ?>
                                    <div class="contacto-field">
                                        <i class="fa-solid fa-phone"></i>
                                        <span><?php echo htmlspecialchars($direccion['telefono']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($direccion['email'])): ?>
                                    <div class="contacto-field">
                                        <i class="fa-solid fa-envelope"></i>
                                        <span><?php echo htmlspecialchars($direccion['email']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Dirección -->
                        <div class="address-details">
                            <?php if (!empty($direccion['calle'])): ?>
                                <div class="address-field">
                                    <strong>Calle</strong>
                                    <div class="address-value"><?php echo htmlspecialchars($direccion['calle']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($direccion['num_exterior'])): ?>
                                <div class="address-field">
                                    <strong>Número Exterior</strong>
                                    <div class="address-value"><?php echo htmlspecialchars($direccion['num_exterior']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($direccion['num_interior'])): ?>
                                <div class="address-field">
                                    <strong>Número Interior</strong>
                                    <div class="address-value"><?php echo htmlspecialchars($direccion['num_interior']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($direccion['cp'])): ?>
                                <div class="address-field">
                                    <strong>Código Postal</strong>
                                    <div class="address-value"><?php echo htmlspecialchars($direccion['cp']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($direccion['colonia'])): ?>
                                <div class="address-field">
                                    <strong>Colonia</strong>
                                    <div class="address-value"><?php echo htmlspecialchars($direccion['colonia']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($direccion['ciudad'])): ?>
                                <div class="address-field">
                                    <strong>Ciudad</strong>
                                    <div class="address-value"><?php echo htmlspecialchars($direccion['ciudad']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($direccion['estado'])): ?>
                                <div class="address-field">
                                    <strong>Estado</strong>
                                    <div class="address-value"><?php echo htmlspecialchars($direccion['estado']); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="direccion-actions">
                        <!-- Editar dirección (enlace a formulario de edición) -->
                        <a href="editar_direccion.php?id=<?php echo $direccion['id']; ?>" class="btn-action btn-editar">
                            <i class="fa-solid fa-edit me-1"></i> Editar
                        </a>
                        
                        <!-- Eliminar dirección (formulario) -->
                        <form method="POST" style="flex: 1;" onsubmit="return confirmDelete('<?php echo htmlspecialchars(addslashes($direccion['nombre'])); ?>')">
                            <input type="hidden" name="action" value="eliminar">
                            <input type="hidden" name="id" value="<?php echo $direccion['id']; ?>">
                            <button type="submit" class="btn-action btn-eliminar w-100">
                                <i class="fa-solid fa-trash me-1"></i> Eliminar
                            </button>
                        </form>
                    </div>
                    
                    <!-- Establecer como predeterminada -->
                    <?php if (!$direccion['activa']): ?>
                        <div class="mt-2">
                            <form method="POST">
                                <input type="hidden" name="action" value="predeterminada">
                                <input type="hidden" name="id" value="<?php echo $direccion['id']; ?>">
                                <button type="submit" class="btn-action btn-predeterminada">
                                    <i class="fa-solid fa-star me-1"></i> Establecer como predeterminada
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="mt-2">
                            <button class="btn-action btn-predeterminada disabled">
                                <i class="fa-solid fa-check me-1"></i> Dirección predeterminada actual
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de confirmación de eliminación -->
<div id="confirmModal" class="modal-confirm" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Confirmar eliminación</h3>
        </div>
        <div class="modal-body">
            <p id="modalMessage">¿Estás seguro de que quieres eliminar esta dirección?</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal btn-modal-cancel" onclick="closeModal()">Cancelar</button>
            <button type="button" class="btn-modal btn-modal-confirm" onclick="confirmDeleteAction()">Eliminar</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Variables para el modal de confirmación
    let deleteForm = null;
    let deleteAddressName = '';
    
    // Función para confirmar eliminación
    function confirmDelete(addressName) {
        deleteAddressName = addressName;
        const modal = document.getElementById('confirmModal');
        const message = document.getElementById('modalMessage');
        message.textContent = `¿Estás seguro de que quieres eliminar la dirección "${addressName}"? Esta acción no se puede deshacer.`;
        modal.style.display = 'flex';
        return false; // Prevenir envío inmediato del formulario
    }
    
    // Función para cerrar el modal
    function closeModal() {
        const modal = document.getElementById('confirmModal');
        modal.style.display = 'none';
        deleteForm = null;
        deleteAddressName = '';
    }
    
    // Función para confirmar la eliminación
    function confirmDeleteAction() {
        if (deleteForm) {
            deleteForm.submit();
        }
        closeModal();
    }
    
    // Asignar el formulario al hacer clic en eliminar
    document.addEventListener('DOMContentLoaded', function() {
        const deleteButtons = document.querySelectorAll('form[onsubmit*="confirmDelete"]');
        deleteButtons.forEach(form => {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    deleteForm = form;
                    const addressName = form.getAttribute('onsubmit').match(/'([^']+)'/)[1];
                    confirmDelete(addressName);
                });
            }
        });
    });
</script>
</body>
</html>