<?php
session_start();
require 'config/db.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // 1. Buscar usuario
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Verificar contraseña
    if ($usuario && password_verify($password, $usuario['password'])) {
        
        // 3. Guardar datos en sesión
        $_SESSION['user_id'] = $usuario['id'];
        $_SESSION['nombre'] = $usuario['nombre'];
        
        // IMPORTANTE: La base de datos tiene columna 'rol', pero tu admin busca 'user_rol'.
        // Aquí hacemos la conversión para que todo funcione:
        $_SESSION['user_rol'] = $usuario['rol']; 

        // 4. Redirección CORRECTA (Admins al panel, Clientes a la tienda)
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
        body { margin: 0; padding: 0; font-family: 'Inter', sans-serif; background-color: #0f172a; display: flex; justify-content: center; align-items: center; height: 100vh; color: white; }
        
        .login-card { background-color: #1e293b; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); width: 100%; max-width: 400px; text-align: center; border: 1px solid #334155; }
        
        .login-card h2 { margin-top: 0; margin-bottom: 30px; font-size: 1.5rem; color: #f8fafc; }

        .error-msg { background-color: #7f1d1d; color: #fca5a5; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9rem; border: 1px solid #b91c1c; }

        .form-group { margin-bottom: 20px; text-align: left; }
        
        input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-family: inherit; background-color: #ffffff; color: #0f172a; font-size: 1rem; }
        input:focus { outline: none; border-color: #3b82f6; ring: 2px solid #3b82f6; }

        .password-wrapper { position: relative; }
        .password-wrapper input { padding-right: 40px; }
        .toggle-password { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #64748b; font-size: 1rem; }
        .toggle-password:hover { color: #334155; }

        .btn-login { width: 100%; background-color: #007bff; color: white; padding: 12px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; transition: 0.2s; font-size: 1rem; margin-top: 10px; }
        .btn-login:hover { background-color: #0056b3; }

        .links { margin-top: 25px; font-size: 0.9rem; color: #94a3b8; }
        .links a { color: #3b82f6; text-decoration: none; display: block; margin-top: 5px; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <div class="login-card">
        <h2>Bienvenido a NGS</h2>

        <?php if($mensaje): ?>
            <div class="error-msg"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <input type="email" name="email" required placeholder="ejemplo@ejemplo.com">
            </div>

            <div class="form-group">
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" required placeholder="••••••••">
                    <i class="fa-solid fa-eye toggle-password" onclick="toggleVisibility()"></i>
                </div>
            </div>

            <button type="submit" class="btn-login">ENTRAR</button>
        </form>

        <div class="links">
            ¿No tienes cuenta? <a href="registro.php">Regístrate aquí</a>
            <a href="index.php">← Volver a la tienda</a>
        </div>
    </div>

    <script>
        function toggleVisibility() {
            const input = document.getElementById('password');
            const icon = document.querySelector('.toggle-password');
            
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
    </script>

</body>
</html>