<?php
session_start();
require '../config/db.php'; // Ajusta la ruta según la estructura de carpetas

// Verificar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Obtener datos del usuario
$usuario_id = $_SESSION['user_id'];
$nombre_usuario = $_SESSION['nombre'] ?? '';

// Puedes obtener más datos del usuario si los necesitas
$stmt = $pdo->prepare("SELECT email, telefono_celular, razon_social, rfc FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Cuenta | NGS Store</title>
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
        }

        /* Banner de perfil */
        .profile-banner {
            background: linear-gradient(rgba(6, 19, 37, 0.9), rgba(6, 19, 37, 0.95)), 
                        url('../assets/img/banner-hero.webp');
            background-size: cover;
            background-position: center;
            padding: 4rem 0;
            margin-bottom: 2rem;
        }

        .profile-banner-content {
            color: white;
        }

        .profile-banner h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .profile-banner p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Información del usuario */
        .user-info-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            border: 1px solid #e0e0e0;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            background: var(--ngs-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin-right: 1.5rem;
        }

        .user-details h3 {
            margin-bottom: 0.5rem;
            color: var(--ngs-blue);
        }

        .user-details p {
            margin-bottom: 0.3rem;
            color: #666;
        }

        /* Estilo de Tarjetas tipo Amazon */
        .account-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1.5rem;
            height: 100%;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: flex-start;
        }

        .account-card:hover {
            background-color: #f3f3f3;
            color: inherit;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .icon-container {
            flex-shrink: 0;
            margin-right: 1rem;
        }

        .icon-container i {
            font-size: 2.5rem;
            color: var(--ngs-blue);
            opacity: 0.8;
        }

        .card-text-content h5 {
            margin-bottom: 0.2rem;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .card-text-content p {
            font-size: 0.9rem;
            color: #555;
            margin-bottom: 0;
        }

        .container-custom {
            max-width: 1000px;
        }

        /* Botones de acción */
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }

        .btn-store {
            background-color: var(--ngs-blue);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-store:hover {
            background-color: #051225;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn-logout {
            background-color: transparent;
            color: #dc3545;
            border: 1px solid #dc3545;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            background-color: #dc3545;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.15);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-banner {
                padding: 2.5rem 0;
            }
            
            .profile-banner h1 {
                font-size: 2rem;
            }
            
            .user-avatar {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
                margin-right: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-store, .btn-logout {
                width: 100%;
                max-width: 250px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<!-- Banner de perfil -->
<div class="profile-banner">
    <div class="container container-custom">
        <div class="profile-banner-content">
            <h1>Mi cuenta</h1>
            <p>Gestiona tu información, pedidos y preferencias</p>
        </div>
    </div>
</div>

<div class="container container-custom mt-4">
    <!-- Información del usuario -->
    <div class="user-info-card">
        <div class="d-flex align-items-center">
            <div class="user-avatar">
                <i class="fa-solid fa-user"></i>
            </div>
            <div class="user-details">
                <h3><?php echo htmlspecialchars($nombre_usuario); ?></h3>
                <p><i class="fa-solid fa-envelope me-2"></i> <?php echo htmlspecialchars($usuario['email'] ?? ''); ?></p>
                <p><i class="fa-solid fa-phone me-2"></i> <?php echo htmlspecialchars($usuario['telefono_celular'] ?? ''); ?></p>
                <?php if (!empty($usuario['razon_social'])): ?>
                    <p><i class="fa-solid fa-building me-2"></i> <?php echo htmlspecialchars($usuario['razon_social']); ?></p>
                <?php endif; ?>
                <?php if (!empty($usuario['rfc']) && $usuario['rfc'] != 'TEMP000000002'): ?>
                    <p><i class="fa-solid fa-id-card me-2"></i> RFC: <?php echo htmlspecialchars($usuario['rfc']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Opciones de cuenta -->
    <div class="row g-4">
        <div class="col-md-4">
            <a href="mis_pedidos.php" class="account-card">
                <div class="icon-container">
                    <i class="fa-solid fa-box-open"></i>
                </div>
                <div class="card-text-content">
                    <h5>Mis pedidos</h5>
                    <p>Rastrear, devolver o comprar productos de nuevo.</p>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="direcciones.php" class="account-card">
                <div class="icon-container">
                    <i class="fa-solid fa-location-dot"></i>
                </div>
                <div class="card-text-content">
                    <h5>Direcciones</h5>
                    <p>Editar direcciones para tus pedidos y entregas.</p>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="configuracion.php" class="account-card">
                <div class="icon-container">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <div class="card-text-content">
                    <h5>Configuración</h5>
                    <p>Cambiar contraseña, email y datos de perfil.</p>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="pagos.php" class="account-card">
                <div class="icon-container">
                    <i class="fa-solid fa-credit-card"></i>
                </div>
                <div class="card-text-content">
                    <h5>Mis pagos</h5>
                    <p>Administrar métodos de pago e historial de facturas.</p>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="documentos.php" class="account-card">
                <div class="icon-container">
                    <i class="fa-solid fa-file-invoice"></i>
                </div>
                <div class="card-text-content">
                    <h5>Documentos</h5>
                    <p>Subir o consultar tu constancia fiscal y RFC.</p>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="contacto.php" class="account-card">
                <div class="icon-container">
                    <i class="fa-solid fa-headset"></i>
                </div>
                <div class="card-text-content">
                    <h5>Servicio al cliente</h5>
                    <p>Ayuda con tus pedidos o dudas técnicas.</p>
                </div>
            </a>
        </div>
    </div>

    <!-- Botones de acción -->
    <div class="action-buttons">
        <a href="../index.php" class="btn-store">
            <span>Regresar a Tienda</span>
        </a>
        
        <a href="../logout.php" class="btn-logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Cerrar Sesión</span>
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>