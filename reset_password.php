<?php
session_start();
require 'config/db.php';

$error = '';
$success = '';
$mostrar_formulario = false;

// 1. VERIFICACIÓN INICIAL DEL TOKEN (GET)
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    
    // Buscar el usuario y validar que el token exista y no haya expirado
    $stmt = $pdo->prepare("SELECT id, reset_expiracion FROM usuarios WHERE reset_token = ?");
    $stmt->execute([$token]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario) {
        $expiracion = new DateTime($usuario['reset_expiracion']);
        $ahora = new DateTime();
        
        if ($expiracion > $ahora) {
            $mostrar_formulario = true;
            // Guardamos datos en sesión para el procesamiento posterior del POST
            $_SESSION['reset_user_id'] = $usuario['id'];
            $_SESSION['reset_token_valido'] = $token;
        } else {
            $error = "El enlace de recuperación ha expirado. Por favor, solicita uno nuevo.";
        }
    } else {
        $error = "El enlace de recuperación no es válido o ya fue utilizado.";
    }
}

// 2. PROCESO DE ACTUALIZACIÓN DE CONTRASEÑA (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    // Validar que la sesión de recuperación esté activa
    if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_token_valido'])) {
        $error = "Solicitud no válida o sesión expirada.";
    } else {
        $nueva_password = $_POST['new_password'];
        $confirmar_password = $_POST['confirm_password'];
        
        // Validaciones de servidor
        if (strlen($nueva_password) < 8) {
            $error = "La contraseña debe tener al menos 8 caracteres.";
        } elseif ($nueva_password !== $confirmar_password) {
            $error = "Las contraseñas no coinciden.";
        } else {
            $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
            
            try {
                $pdo->beginTransaction();
                
                // Actualizar contraseña y LIMPIAR el token para invalidarlo
                $stmt = $pdo->prepare("UPDATE usuarios SET password = ?, reset_token = NULL, reset_expiracion = NULL WHERE id = ?");
                $stmt->execute([$password_hash, $_SESSION['reset_user_id']]);
                
                if ($stmt->rowCount() > 0) {
                    $success = "¡Contraseña actualizada correctamente! Redirigiendo al login...";
                    
                    // Limpiar datos de sesión de recuperación
                    unset($_SESSION['reset_user_id']);
                    unset($_SESSION['reset_token_valido']);
                    $mostrar_formulario = false;
                    
                    // Redirección automática tras 3 segundos
                    header("refresh:3;url=login.php");
                } else {
                    $error = "No se pudo realizar el cambio. El enlace podría haber sido invalidado.";
                }
                
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error al procesar la solicitud: " . $e->getMessage();
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
    <title>Restablecer Contraseña | NGS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; padding: 20px; font-family: 'Inter', sans-serif; background-color: #0f172a; display: flex; justify-content: center; align-items: center; min-height: 100vh; color: white; }
        .reset-card { background-color: #1e293b; padding: 40px; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5); width: 100%; max-width: 400px; text-align: center; border: 1px solid #334155; }
        h2 { margin-top: 0; margin-bottom: 20px; font-size: 1.5rem; color: #f8fafc; }
        .error-msg { background-color: #7f1d1d; color: #fca5a5; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9rem; border: 1px solid #b91c1c; }
        .success-msg { background-color: #064e3b; color: #a7f3d0; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9rem; border: 1px solid #047857; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 8px; color: #cbd5e1; font-weight: 500; font-size: 0.9rem; }
        input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; background-color: #ffffff; color: #0f172a; font-size: 1rem; }
        .password-wrapper { position: relative; }
        .toggle-password { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #64748b; }
        .btn-reset { width: 100%; background-color: #10b981; color: white; padding: 12px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; transition: 0.3s; font-size: 1rem; }
        .btn-reset:hover { background-color: #059669; }
        .btn-reset:disabled { background-color: #334155; cursor: not-allowed; }
        .password-strength { margin-top: 8px; height: 4px; border-radius: 2px; background-color: #334155; }
        .strength-bar { height: 100%; width: 0%; transition: 0.3s; }
        .strength-weak { background-color: #ef4444; }
        .strength-strong { background-color: #10b981; }
        .links { margin-top: 25px; }
        .links a { color: #60a5fa; text-decoration: none; font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="reset-card">
    <?php if($mostrar_formulario): ?>
        <h2>Nueva Contraseña</h2>
        
        <?php if($error): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" id="resetForm">
            <div class="form-group">
                <label>Nueva Contraseña</label>
                <div class="password-wrapper">
                    <input type="password" name="new_password" id="new_password" required minlength="8" onkeyup="checkStrength(this.value)">
                    <i class="fa-solid fa-eye toggle-password" onclick="toggleVis('new_password', this)"></i>
                </div>
                <div class="password-strength"><div class="strength-bar" id="strengthBar"></div></div>
            </div>
            
            <div class="form-group">
                <label>Confirmar Contraseña</label>
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" required onkeyup="checkMatch()">
                    <i class="fa-solid fa-eye toggle-password" onclick="toggleVis('confirm_password', this)"></i>
                </div>
                <div id="matchText" style="font-size: 0.8rem; margin-top: 5px;"></div>
            </div>
            
            <button type="submit" class="btn-reset" id="submitBtn">ACTUALIZAR</button>
        </form>

    <?php else: ?>
        <h2>Estado de Solicitud</h2>
        <?php if($success): ?>
            <div class="success-msg"><?php echo $success; ?></div>
            <p>Serás redirigido en unos segundos...</p>
        <?php else: ?>
            <div class="error-msg"><?php echo $error ?: "Token no válido o expirado."; ?></div>
            <p>Por favor, solicita un nuevo enlace desde el Login.</p>
        <?php endif; ?>
        <div class="links"><a href="login.php">Volver al Inicio de Sesión</a></div>
    <?php endif; ?>
</div>

<script>
    function toggleVis(id, icon) {
        const input = document.getElementById(id);
        if (input.type === "password") {
            input.type = "text";
            icon.classList.replace("fa-eye", "fa-eye-slash");
        } else {
            input.type = "password";
            icon.classList.replace("fa-eye-slash", "fa-eye");
        }
    }

    function checkStrength(p) {
        const bar = document.getElementById('strengthBar');
        let s = 0;
        if(p.length >= 8) s += 40;
        if(/[A-Z]/.test(p)) s += 30;
        if(/[0-9]/.test(p)) s += 30;
        bar.style.width = s + '%';
        bar.className = 'strength-bar ' + (s < 70 ? 'strength-weak' : 'strength-strong');
    }

    function checkMatch() {
        const p1 = document.getElementById('new_password').value;
        const p2 = document.getElementById('confirm_password').value;
        const txt = document.getElementById('matchText');
        const btn = document.getElementById('submitBtn');
        
        if (p2 === "") { txt.textContent = ""; return; }
        
        if (p1 === p2) {
            txt.textContent = "✓ Coinciden"; txt.style.color = "#10b981"; btn.disabled = false;
        } else {
            txt.textContent = "✗ No coinciden"; txt.style.color = "#ef4444"; btn.disabled = true;
        }
    }
</script>

</body>
</html>