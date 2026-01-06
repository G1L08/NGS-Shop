<?php
session_start();
include 'config/db.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $paterno = trim($_POST['apellido_paterno']);
    $materno = trim($_POST['apellido_materno']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $fecha_nacimiento = $_POST['fecha_nacimiento']; // Nuevo campo
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_rol = 'cliente';

    // 1. Validar Edad (PHP)
    $fecha_nac = new DateTime($fecha_nacimiento);
    $hoy = new DateTime();
    $edad = $hoy->diff($fecha_nac)->y;

    if ($edad < 18) {
        $mensaje = "Lo sentimos, debes ser mayor de 18 años para registrarte.";
    }
    elseif ($password !== $confirm_password) {
        $mensaje = "Las contraseñas no coinciden.";
    }
    elseif (preg_match('/[0-9]/', $nombre) || preg_match('/[0-9]/', $paterno) || preg_match('/[0-9]/', $materno)) {
        $mensaje = "El nombre y los apellidos no pueden contener números.";
    }
    elseif (!ctype_digit($telefono) || strlen($telefono) > 10) {
        $mensaje = "El teléfono debe contener solo números (máximo 10).";
    }
    else {
        // Verificar si el correo existe
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $mensaje = "Este correo ya está registrado.";
        } else {
            // Encriptar password
            $pass_hash = password_hash($password, PASSWORD_BCRYPT);
            
            // Insertar en BD (Incluyendo fecha_nacimiento)
            $sql = "INSERT INTO usuarios (nombre, apellido_paterno, apellido_materno, email, telefono, fecha_nacimiento, password, user_rol) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$nombre, $paterno, $materno, $email, $telefono, $fecha_nacimiento, $pass_hash, $user_rol])) {
                header('Location: login.php?registrado=1');
                exit;
            } else {
                $mensaje = "Error al registrarse en la base de datos.";
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
    <title>Crear Cuenta | NGS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; padding: 0; font-family: 'Inter', sans-serif; background-color: #f3f4f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .register-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); width: 100%; max-width: 400px; }
        .register-card h2 { margin-top: 0; text-align: center; color: #1f2937; margin-bottom: 20px; }
        
        .form-group { margin-bottom: 15px; }
        .form-row { display: flex; gap: 10px; } 
        label { display: block; margin-bottom: 5px; color: #374151; font-weight: 500; font-size: 0.9rem; }
        input { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; font-family: inherit; transition: 0.2s; }
        input:focus { border-color: #8b5cf6; outline: none; ring: 2px solid #ddd6fe; }
        
        /* Estilos específicos para el input de fecha */
        input[type="date"] { color: #374151; }

        .password-wrapper { position: relative; }
        .password-wrapper input { padding-right: 40px; }
        .toggle-password { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #6b7280; }
        
        .btn-register { width: 100%; background-color: #8b5cf6; color: white; padding: 12px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.2s; margin-top: 10px; }
        .btn-register:hover { background-color: #7c3aed; }
        .btn-register:disabled { background-color: #c4b5fd; cursor: not-allowed; }

        .links { text-align: center; margin-top: 20px; font-size: 0.9rem; }
        .links a { color: #8b5cf6; text-decoration: none; font-weight: 500; }
        .links a:hover { text-decoration: underline; }

        .error-msg { background-color: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.9rem; text-align: center; }
        .input-error { font-size: 0.75rem; color: #dc2626; margin-top: 4px; display: none; }
    </style>
</head>
<body>

    <div class="register-card">
        <h2>Crear Cuenta</h2>

        <?php if($mensaje): ?>
            <div class="error-msg"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <form method="POST" id="registroForm">
            <div class="form-group">
                <label>Nombre</label>
                <input type="text" name="nombre" required placeholder="Tu nombre" oninput="validarTexto(this)">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Apellido Paterno</label>
                    <input type="text" name="apellido_paterno" required placeholder="Paterno" oninput="validarTexto(this)">
                </div>
                <div class="form-group">
                    <label>Apellido Materno</label>
                    <input type="text" name="apellido_materno" required placeholder="Materno" oninput="validarTexto(this)">
                </div>
            </div>

            <div class="form-group">
                <label>Fecha de Nacimiento</label>
                <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" required onchange="validarEdad JS()">
                <span id="edadError" class="input-error">Debes ser mayor de 18 años.</span>
            </div>

            <div class="form-group">
                <label>Teléfono</label>
                <input type="tel" name="telefono" id="telefono" required maxlength="10" placeholder="Ej: 5512345678" oninput="validarTelefono(this)">
            </div>

            <div class="form-group">
                <label>Correo Electrónico</label>
                <input type="email" name="email" required placeholder="ejemplo@ejemplo.com">
            </div>

            <div class="form-group">
                <label>Contraseña</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" required placeholder="******" oninput="validarPasswords()">
                    <i class="fa-solid fa-eye toggle-password" onclick="toggleVisibility('password', this)"></i>
                </div>
            </div>

            <div class="form-group">
                <label>Repetir Contraseña</label>
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" required placeholder="******" oninput="validarPasswords()">
                    <i class="fa-solid fa-eye toggle-password" onclick="toggleVisibility('confirm_password', this)"></i>
                </div>
                <span id="passError" class="input-error">Las contraseñas no coinciden.</span>
            </div>

            <button type="submit" class="btn-register" id="btnSubmit">Registrarse</button>
        </form>

        <div class="links">
            ¿Ya tienes cuenta? <a href="login.php">Inicia Sesión aquí</a>
        </div>
    </div>

    <script>
        function validarTexto(input) {
            input.value = input.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');
        }

        function validarTelefono(input) {
            input.value = input.value.replace(/[^0-9]/g, '');
        }

        function validarEdadJS() {
            const inputFecha = document.getElementById('fecha_nacimiento').value;
            const errorSpan = document.getElementById('edadError');
            const btn = document.getElementById('btnSubmit');

            if(inputFecha) {
                const fechaNac = new Date(inputFecha);
                const hoy = new Date();
                let edad = hoy.getFullYear() - fechaNac.getFullYear();
                const m = hoy.getMonth() - fechaNac.getMonth();
           
                if (m < 0 || (m === 0 && hoy.getDate() < fechaNac.getDate())) {
                    edad--;
                }

                if (edad < 18) {
                    errorSpan.style.display = 'block';
                    btn.disabled = true;
                    btn.style.opacity = "0.7";
                } else {
                    errorSpan.style.display = 'none';
                    validarPasswords(); 
                }
            }
        }

        function validarPasswords() {
            const pass1 = document.getElementById('password').value;
            const pass2 = document.getElementById('confirm_password').value;
            const errorSpan = document.getElementById('passError');
            const btn = document.getElementById('btnSubmit');
            const errorEdad = document.getElementById('edadError').style.display === 'block';

            let passOk = true;
            if (pass2.length > 0 && pass1 !== pass2) {
                errorSpan.style.display = 'block';
                passOk = false;
            } else {
                errorSpan.style.display = 'none';
            }

            if (passOk && !errorEdad) {
                btn.disabled = false;
                btn.style.opacity = "1";
            } else {
                btn.disabled = true;
                btn.style.opacity = "0.7";
            }
        }

        function toggleVisibility(inputId, icon) {
            const input = document.getElementById(inputId);
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
