<?php 
session_start();
require 'config/db.php';

$mensaje = '';
$mensaje_exito = '';
$token_prueba = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. PROCESO DE LOGIN NORMAL
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

            if ($usuario['rol'] === 'admin' || $usuario['rol'] === 'dueño' || $usuario['rol'] === 'dueno') {
                header('Location: admin/index.php');
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            $mensaje = "Correo o contraseña incorrectos.";
        }
    }
    
    // 2. PROCESO DE RECUPERACIÓN DE CONTRASEÑA
    if (isset($_POST['recovery_email'])) {
        $recovery_email = trim($_POST['recovery_email']);
        
        $stmt = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE email = ?");
        $stmt->execute([$recovery_email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario) {
            $token = bin2hex(random_bytes(32));
            $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $pdo->prepare("UPDATE usuarios SET reset_token = ?, reset_expiracion = ? WHERE id = ?");
            $stmt->execute([$token, $expiracion, $usuario['id']]);
            
            $reset_link = "http://localhost:3000/reset_password.php?token=$token";
            
            $mensaje_exito = "Se ha generado un enlace de recuperación.";
            $token_prueba = $reset_link; 
        } else {
            $mensaje_exito = "Si el email existe en nuestro sistema, recibirás un enlace.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | NGS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            margin: 0; 
            padding: 0; 
            font-family: 'Inter', sans-serif; 
            background-color: #0f172a; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            color: white; 
        }
        
        .login-card { 
            background-color: #1e293b; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); 
            width: 100%; 
            max-width: 400px; 
            text-align: center; 
            border: 1px solid #334155; 
        }
        
        h2 { 
            margin-top: 0; 
            margin-bottom: 30px; 
            font-size: 1.5rem; 
            color: #f8fafc; 
        }
        
        .error-msg { 
            background-color: #7f1d1d; 
            color: #fca5a5; 
            padding: 12px; 
            border-radius: 6px; 
            margin-bottom: 20px; 
            font-size: 0.9rem; 
            border: 1px solid #b91c1c; 
        }
        
        .success-msg { 
            background-color: #064e3b; 
            color: #a7f3d0; 
            padding: 12px; 
            border-radius: 6px; 
            margin-bottom: 20px; 
            font-size: 0.9rem; 
            border: 1px solid #047857; 
        }
        
        .form-group { 
            margin-bottom: 20px; 
            text-align: left; 
        }
        
        input { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #cbd5e1; 
            border-radius: 6px; 
            box-sizing: border-box; 
            background-color: #ffffff; 
            color: #0f172a; 
            font-size: 1rem; 
        }
        
        .password-wrapper { 
            position: relative; 
        }
        
        .toggle-password { 
            position: absolute; 
            right: 12px; 
            top: 50%; 
            transform: translateY(-50%); 
            cursor: pointer; 
            color: #64748b; 
        }
        
        .btn-login { 
            width: 100%; 
            background-color: #007bff; 
            color: white; 
            padding: 12px; 
            border: none; 
            border-radius: 6px; 
            font-weight: 700; 
            cursor: pointer; 
            font-size: 1rem; 
            transition: background-color 0.2s;
        }
        
        .btn-login:hover {
            background-color: #0056b3;
        }
        
        .btn-recovery { 
            width: 100%; 
            background-color: #f59e0b; 
            color: white; 
            padding: 12px; 
            border: none; 
            border-radius: 6px; 
            font-weight: 700; 
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-recovery:hover {
            background-color: #d97706;
        }
        
        .button-group { 
            display: flex; 
            gap: 10px; 
            margin-top: 25px; 
        }
        
        .btn-action { 
            flex: 1; 
            padding: 12px; 
            border-radius: 6px; 
            font-weight: 600; 
            text-decoration: none; 
            font-size: 0.9rem; 
            text-align: center; 
            transition: all 0.2s;
        }
        
        .btn-register { 
            background-color: #10b981; 
            color: white; 
        }
        
        .btn-register:hover {
            background-color: #0d9c6c;
        }
        
        .btn-store { 
            color: #3b82f6; 
            border: 1px solid #3b82f6; 
            background-color: transparent;
        }
        
        .btn-store:hover {
            background-color: rgba(59, 130, 246, 0.1);
        }
        
        .forgot-password { 
            margin: 15px 0; 
        }
        
        .forgot-password a { 
            color: #60a5fa; 
            text-decoration: none; 
            font-size: 0.9rem; 
            cursor: pointer; 
            transition: color 0.2s;
        }
        
        .forgot-password a:hover {
            color: #3b82f6;
            text-decoration: underline;
        }
        
        .recovery-form { 
            display: none; 
            margin-top: 20px; 
            padding-top: 20px; 
            border-top: 1px solid #334155; 
        }
        
        .test-link { 
            display: block; 
            margin-top: 10px; 
            padding: 10px; 
            background: #1e293b; 
            border: 1px dashed #3b82f6; 
            color: #60a5fa; 
            text-decoration: none; 
            font-size: 0.8rem; 
            word-break: break-all; 
            border-radius: 6px;
            transition: background 0.2s;
        }
        
        .test-link:hover {
            background: #334155;
        }
        
        .back-link {
            display: block;
            margin-top: 15px;
            color: #60a5fa;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .back-link:hover {
            color: #3b82f6;
            text-decoration: underline;
        }
        
        .back-link i {
            margin-right: 5px;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <h2>Bienvenido a NGS</h2>

        <?php if($mensaje): ?>
            <div class="error-msg"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <?php if($mensaje_exito): ?>
            <div class="success-msg">
                <?php echo $mensaje_exito; ?>
                <?php if($token_prueba): ?>
                    <a href="<?php echo $token_prueba; ?>" class="test-link">
                        <i class="fa-solid fa-link"></i> CLIC AQUÍ PARA SIMULAR EMAIL (PRUEBA)
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div id="login-form">
            <form method="POST">
                <div class="form-group">
                    <input type="email" name="email" required placeholder="Correo electrónico">
                </div>
                <div class="form-group">
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" required placeholder="Contraseña">
                        <i class="fa-solid fa-eye toggle-password" onclick="toggleVisibility()"></i>
                    </div>
                </div>
                <button type="submit" class="btn-login">ENTRAR</button>
            </form>

            <div class="forgot-password">
                <a onclick="showRecoveryForm()">¿Olvidaste tu contraseña?</a>
            </div>

            <div class="button-group">
                <a href="registro.php" class="btn-action btn-register">Registrarse</a>
                <a href="index.php" class="btn-action btn-store">Tienda</a>
            </div>
        </div>

        <div id="recovery-form" class="recovery-form">
            <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 20px;">
                Ingresa tu email para restablecer tu contraseña.
            </p>
            <form method="POST">
                <div class="form-group">
                    <input type="email" name="recovery_email" required placeholder="ejemplo@correo.com">
                </div>
                <button type="submit" class="btn-recovery">ENVIAR ENLACE</button>
            </form>
            <a class="back-link" onclick="showLoginForm()">
                <i class="fa-solid fa-arrow-left"></i> Volver al login
            </a>
        </div>
    </div>

    <script>
        function toggleVisibility() {
            const input = document.getElementById('password');
            const icon = document.querySelector('.toggle-password');
            input.type = input.type === "password" ? "text" : "password";
            icon.classList.toggle("fa-eye");
            icon.classList.toggle("fa-eye-slash");
        }
        
        function showRecoveryForm() {
            document.getElementById('login-form').style.display = 'none';
            document.getElementById('recovery-form').style.display = 'block';
        }
        
        function showLoginForm() {
            document.getElementById('recovery-form').style.display = 'none';
            document.getElementById('login-form').style.display = 'block';
        }

        <?php if($mensaje_exito || isset($_POST['recovery_email'])): ?>
            // Mostrar formulario de recuperación si hay mensaje de éxito
            showRecoveryForm();
        <?php endif; ?>
        
        // Añadir efecto de hover a los botones
        document.querySelectorAll('.btn-action').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.2)';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        });
        
        // Añadir efecto al botón de login
        document.querySelector('.btn-login').addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 12px rgba(0,123,255,0.3)';
        });
        
        document.querySelector('.btn-login').addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    </script>
</body>
</html>