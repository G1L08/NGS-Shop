<?php
session_start();
require 'config/db.php';

$mensaje = '';
$mensaje_exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- PROCESO DE LOGIN ---
    if (isset($_POST['email']) && isset($_POST['password'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($password, $usuario['password'])) {
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['user_rol'] = $usuario['rol'];

            // Redirección según el rol definido en tu BD
            if (in_array($usuario['rol'], ['admin', 'dueño', 'dueno'])) {
                header('Location: admin/index.php');
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            $mensaje = "Correo o contraseña incorrectos.";
        }
    }
    
    // --- PROCESO DE RECUPERACIÓN DE CONTRASEÑA ---
    if (isset($_POST['recovery_email'])) {
        $recovery_email = trim($_POST['recovery_email']);
        
        $stmt = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE email = ?");
        $stmt->execute([$recovery_email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario) {
            $token = bin2hex(random_bytes(32));
            $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Actualiza el token en la tabla usuarios
            $stmt = $pdo->prepare("UPDATE usuarios SET reset_token = ?, reset_expiracion = ? WHERE id = ?");
            $stmt->execute([$token, $expiracion, $usuario['id']]);
            
            $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $dominio = $_SERVER['HTTP_HOST'];
            $enlace_recuperacion = "$protocolo://$dominio/reset_password.php?token=$token";
            
            // Mensaje de éxito con el enlace de prueba visible
            $mensaje_exito = "<strong>Enlace de recuperación generado:</strong><br>";
            $mensaje_exito .= "<a href='$enlace_recuperacion' style='color: #2563eb; word-break: break-all;'>$enlace_recuperacion</a><br><br>";
            $mensaje_exito .= "Este enlace expirará en 1 hora.";
        } else {
            $mensaje_exito = "Si el email existe en nuestro sistema, recibirás un enlace para restablecer tu contraseña.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | NGS Store</title>
    <style>
        body { font-family: sans-serif; background-color: #f3f4f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .login-container { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h2, h3 { color: #1f2937; margin-top: 0; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #d1d5db; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background-color: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background-color: #1d4ed8; }
        .alerta { padding: 12px; margin-bottom: 1rem; border-radius: 4px; font-size: 0.9rem; }
        .error { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .exito { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        hr { border: 0; border-top: 1px solid #e5e7eb; margin: 2rem 0; }
        .toggle-link { color: #2563eb; text-decoration: none; font-size: 0.85rem; cursor: pointer; }
    </style>
</head>
<body>

<div class="login-container">
    <?php if ($mensaje): ?>
        <div class="alerta error"><?php echo $mensaje; ?></div>
    <?php endif; ?>

    <?php if ($mensaje_exito): ?>
        <div class="alerta exito"><?php echo $mensaje_exito; ?></div>
    <?php endif; ?>

    <div id="login-form">
        <h2>Iniciar Sesión</h2>
        <form method="POST">
            <label>Correo Electrónico</label>
            <input type="email" name="email" required>
            
            <label>Contraseña</label>
            <input type="password" name="password" required>
            
            <button type="submit">Ingresar</button>
        </form>
        <p style="text-align: center;">
            <a class="toggle-link" onclick="toggleForms()">¿Olvidaste tu contraseña?</a>
        </p>
    </div>

    <div id="recovery-form" style="display: none;">
        <h3>Recuperar Contraseña</h3>
        <form method="POST">
            <p style="font-size: 0.9rem; color: #6b7280;">Introduce tu email y te mostraremos el enlace de recuperación.</p>
            <input type="email" name="recovery_email" placeholder="ejemplo@correo.com" required>
            <button type="submit">Generar Enlace</button>
        </form>
        <p style="text-align: center;">
            <a class="toggle-link" onclick="toggleForms()">Volver al Login</a>
        </p>
    </div>
</div>

<script>
    function toggleForms() {
        const login = document.getElementById('login-form');
        const recovery = document.getElementById('recovery-form');
        if (login.style.display === 'none') {
            login.style.display = 'block';
            recovery.style.display = 'none';
        } else {
            login.style.display = 'none';
            recovery.style.display = 'block';
        }
    }
</script>

</body>
</html>