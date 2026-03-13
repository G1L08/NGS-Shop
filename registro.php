<?php
session_start();
include 'config/db.php';

$mensaje = '';
$mostrar_modal_exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Validar si el correo o RFC ya existen ANTES de procesar
        $email = $_POST['email'];
        $rfc = strtoupper($_POST['rfc']);
        
        $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? OR rfc = ?");
        $stmt_check->execute([$email, $rfc]);
        
        if ($stmt_check->rowCount() > 0) {
            $mensaje = "Error: El correo o RFC ya están registrados.";
        } else {
            $pdo->beginTransaction();

            // 2. Procesar Archivo (Constancia Fiscal)
            $constancia_ruta = '';
            if (isset($_FILES['constancia_fiscal']) && $_FILES['constancia_fiscal']['error'] === 0) {
                $file_name = "constancia_" . time() . "_" . bin2hex(random_bytes(8)) . ".pdf";
                $file_tmp = $_FILES['constancia_fiscal']['tmp_name'];
                
                // Validar tamaño (máximo 10MB)
                if ($_FILES['constancia_fiscal']['size'] > 10 * 1024 * 1024) {
                    throw new Exception("El archivo es demasiado grande. Máximo 10MB.");
                }
                
                // Validar tipo
                $file_type = mime_content_type($file_tmp);
                if ($file_type !== 'application/pdf') {
                    throw new Exception("Solo se permiten archivos PDF.");
                }
                
                if (!is_dir('uploads')) mkdir('uploads', 0777, true);
                if (!is_dir('uploads/constancias')) mkdir('uploads/constancias', 0777, true);
                
                $constancia_ruta = 'uploads/constancias/' . $file_name;
                
                if (!move_uploaded_file($file_tmp, $constancia_ruta)) {
                    throw new Exception("Error al subir el archivo.");
                }
            } else {
                throw new Exception("Debe subir la Constancia de Situación Fiscal.");
            }

            // 3. Preparar datos para inserción
            $pass_hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $areas_interes = isset($_POST['areas']) ? implode(", ", $_POST['areas']) : '';
            
            // Convertir fecha al formato correcto
            $fecha_nacimiento = date('Y-m-d', strtotime($_POST['fecha_cumpleanos']));

            // 4. Insertar Datos con los nombres de campos CORRECTOS
            $sql = "INSERT INTO usuarios (
                nombre_prefijo, nombre, apellido_paterno, apellido_materno, 
                telefono_celular, telefono_oficina, email, fecha_nacimiento, 
                password, razon_social, nombre_comercial, pagina_web, rfc, 
                tipo_empresa, regimen_sat, pais, calle, num_exterior, num_interior, 
                cp, colonia, ciudad, estado, constancia_pdf, medio_contacto, 
                vende_internet, moneda, areas_interes, comentarios, rol, estatus
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'cliente', 'pendiente')";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['nombre_prefijo'] ?? null,
                $_POST['nombre'],
                $_POST['apellido_paterno'],
                $_POST['apellido_materno'],
                $_POST['telefono'],
                $_POST['tel_oficina'] ?? null,
                $email,
                $fecha_nacimiento,
                $pass_hash,
                $_POST['razon_social'],
                $_POST['nombre_comercial'],
                $_POST['web'] ?? null,
                $rfc,
                $_POST['tipo_empresa'],
                $_POST['regimen'],
                $_POST['pais'],
                $_POST['calle'],
                $_POST['n_ext'],
                $_POST['n_int'] ?? null,
                $_POST['cp'],
                $_POST['colonia'],
                $_POST['ciudad'],
                $_POST['estado'],
                $constancia_ruta,
                $_POST['medio'],
                isset($_POST['vende_internet']) ? 1 : 0,
                $_POST['moneda'],
                $areas_interes,
                $_POST['comentarios'] ?? null
            ]);

            $pdo->commit();
            
            // Mostrar modal de éxito
            $mostrar_modal_exito = true;
            
            // Guardar datos en sesión para limpiar formulario
            $_SESSION['registro_exitoso'] = true;
            
            // No redirigir, solo mostrar el modal
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Eliminar archivo si se subió pero hubo error en la BD
        if (!empty($constancia_ruta) && file_exists($constancia_ruta)) {
            @unlink($constancia_ruta);
        }
        
        $mensaje = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Distribuidor | NGS TECHNOLOGY</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #061325f2;
            --secondary: #2d3748;
            --accent: #2b6cb0;
            --light: #f7fafc;
            --border: #cbd5e0;
            --success: #38a169;
            --error: #e53e3e;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Open Sans', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
            color: var(--secondary);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Header de Navegación */
        .main-header {
            background: var(--primary);
            padding: 0 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            text-decoration: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 24px;
        }

        .logo i {
            color: var(--accent);
            font-size: 28px;
        }

        .nav-buttons {
            display: flex;
            gap: 15px;
        }

        .nav-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: 'Montserrat', sans-serif;
        }

        .nav-btn-primary {
            background: var(--accent);
            color: white;
        }

        .nav-btn-primary:hover {
            background: #4299e1;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(66, 153, 225, 0.3);
        }

        .nav-btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .nav-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .nav-btn i {
            font-size: 16px;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            background: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            padding: 30px 40px;
            color: white;
            border-bottom: 4px solid var(--accent);
        }

        .header h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .header-logo {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .header-logo i {
            font-size: 32px;
            color: #90cdf4;
        }

        .form-wrapper {
            padding: 40px;
        }

        .form-section {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid #e2e8f0;
        }

        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 18px;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 25px;
            padding-left: 15px;
            border-left: 4px solid var(--accent);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--accent);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--secondary);
            font-size: 14px;
        }

        .form-group label.required:after {
            content: " *";
            color: var(--error);
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="date"],
        input[type="password"],
        input[type="url"],
        select,
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-family: 'Open Sans', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
            background: var(--light);
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(43, 108, 176, 0.1);
            background: white;
        }

        input[type="file"] {
            padding: 10px;
            background: #f8fafc;
            border: 1px dashed var(--border);
            width: 100%;
            cursor: pointer;
        }

        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 10px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .checkbox-item:hover {
            background: #edf2f7;
            border-color: var(--accent);
        }

        .checkbox-item input[type="checkbox"] {
            width: auto;
            cursor: pointer;
        }

        .form-footer {
            background: #f8fafc;
            padding: 25px;
            border-top: 1px solid var(--border);
            margin-top: 40px;
            border-radius: 0 0 8px 8px;
        }

        .terms {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 25px;
        }

        .terms input[type="checkbox"] {
            margin-top: 3px;
        }

        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            padding: 16px 40px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 0 auto;
            font-family: 'Montserrat', sans-serif;
            letter-spacing: 0.5px;
            min-width: 300px;
        }

        .btn-submit:hover:not(:disabled) {
            background: var(--accent);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(43, 108, 176, 0.2);
        }

        .btn-submit:disabled {
            background: #a0aec0;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
            opacity: 0.7;
        }

        .alert {
            padding: 15px 20px;
            margin: 20px 40px;
            border-radius: 4px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #fed7d7;
            color: var(--error);
            border-left: 4px solid var(--error);
        }

        .alert-success {
            background: #c6f6d5;
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .helper-text {
            font-size: 12px;
            color: #718096;
            margin-top: 5px;
            font-style: italic;
        }

        .file-preview {
            margin-top: 10px;
            padding: 10px;
            background: #edf2f7;
            border-radius: 4px;
            font-size: 14px;
            display: none;
        }

        .file-preview.active {
            display: block;
        }

        /* Modal de éxito */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            overflow: hidden;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--success) 0%, #48bb78 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }

        .modal-header i {
            font-size: 64px;
            margin-bottom: 20px;
            display: block;
        }

        .modal-header h2 {
            font-family: 'Montserrat', sans-serif;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .modal-body {
            padding: 30px;
            text-align: center;
        }

        .modal-body p {
            margin-bottom: 20px;
            font-size: 16px;
            color: var(--secondary);
            line-height: 1.6;
        }

        .modal-footer {
            padding: 20px 30px;
            background: #f8fafc;
            border-top: 1px solid var(--border);
            text-align: center;
        }

        .btn-modal {
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px 40px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Montserrat', sans-serif;
            letter-spacing: 0.5px;
            min-width: 200px;
        }

        .btn-modal:hover {
            background: var(--accent);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(43, 108, 176, 0.2);
        }

        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }

            .nav-buttons {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }

            .form-wrapper {
                padding: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .header {
                padding: 20px;
            }
            
            .checkbox-grid {
                grid-template-columns: 1fr;
            }
            
            .btn-submit {
                min-width: auto;
                width: 100%;
            }
            
            .modal-content {
                width: 95%;
                margin: 20px;
            }
        }
    </style>
</head>
<body>

<!-- Header de Navegación -->
<header class="main-header">
    <div class="nav-container">
        <a href="index.php" class="logo">
            <i class="fas fa-shield-alt"></i>
            NGS TECHNOLOGY
        </a>
        
        <div class="nav-buttons">
            <a href="index.php" class="nav-btn nav-btn-secondary">
                <i class="fas fa-store"></i>
                Volver a Tienda
            </a>
            <a href="login.php" class="nav-btn nav-btn-primary">
                <i class="fas fa-sign-in-alt"></i>
                Iniciar Sesión
            </a>
        </div>
    </div>
</header>

<div class="container">
    <div class="header">
        <div class="header-logo">
            <i class="fas fa-user-plus"></i>
            <div>
                <h1>Registro de Nuevo Distribuidor</h1>
                <p>Complete el formulario para solicitar su cuenta de distribuidor</p>
            </div>
        </div>
    </div>

    <?php if($mensaje): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="regForm" class="form-wrapper">
        
        <!-- Sección 1: Datos Personales -->
        <div class="form-section">
            <h2 class="section-title">
                <i class="fas fa-user"></i>
                Datos Personales
            </h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="required">Tratamiento</label>
                    <select name="nombre_prefijo" required>
                        <option value="">Seleccionar...</option>
                        <option value="Sr.">Sr.</option>
                        <option value="Sra.">Sra.</option>
                        <option value="Ing.">Ing.</option>
                        <option value="Lic.">Lic.</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="required">Nombre(s)</label>
                    <input type="text" name="nombre" placeholder="Ej: Juan Carlos" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="required">Apellido Paterno</label>
                    <input type="text" name="apellido_paterno" required>
                </div>
                
                <div class="form-group">
                    <label class="required">Apellido Materno</label>
                    <input type="text" name="apellido_materno" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="required">Teléfono Celular</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="padding: 12px 15px; background: #edf2f7; border: 1px solid #cbd5e0; border-radius: 4px 0 0 4px; color: #4a5568; white-space: nowrap;">+52</span>
                        <input type="tel" name="telefono" maxlength="10" placeholder="8110000000" required 
                               pattern="[0-9]{10}" title="10 dígitos sin espacios" 
                               style="border-radius: 0 4px 4px 0; flex: 1;">
                    </div>
                    <div class="helper-text">10 dígitos sin espacios ni guiones</div>
                </div>
                
                <div class="form-group">
                    <label>Teléfono Oficina</label>
                    <input type="tel" name="tel_oficina" maxlength="10" pattern="[0-9]{10}">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="required">Correo Electrónico</label>
                    <input type="email" name="email" placeholder="correo@empresa.com" required>
                </div>
                
                <div class="form-group">
                    <label class="required">Fecha de Nacimiento</label>
                    <input type="date" name="fecha_cumpleanos" id="cumple" required 
                           max="<?php echo date('Y-m-d'); ?>" 
                           onchange="validarEdad()">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="required">Contraseña</label>
                    <input type="password" name="password" id="pass" required 
                           oninput="validarSeguridad()">
                    <div class="helper-text">Mínimo 6 caracteres, al menos 1 letra y 1 número</div>
                </div>
                
                <div class="form-group">
                    <label class="required">Confirmar Contraseña</label>
                    <input type="password" id="pass2" required oninput="validarSeguridad()">
                </div>
            </div>
        </div>

        <!-- Sección 2: Datos Fiscales -->
        <div class="form-section">
            <h2 class="section-title">
                <i class="fas fa-file-invoice-dollar"></i>
                Datos Fiscales
            </h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="required">Razón Social</label>
                    <input type="text" name="razon_social" required>
                </div>
                
                <div class="form-group">
                    <label class="required">Nombre Comercial</label>
                    <input type="text" name="nombre_comercial" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Página Web</label>
                    <input type="url" name="web" placeholder="https://www.tuempresa.com">
                </div>
                
                <div class="form-group">
                    <label class="required">RFC</label>
                    <input type="text" name="rfc" id="rfc" maxlength="13" required 
                           oninput="this.value = this.value.toUpperCase()"
                           placeholder="ABC123456XYZ">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="required">Tipo de Empresa</label>
                    <select name="tipo_empresa" required id="tipo_empresa">
                        <option value="">Seleccionar...</option>
                        <option value="Persona Moral">Persona Moral</option>
                        <option value="Persona Física">Persona Física</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="required">Régimen Fiscal (SAT)</label>
                    <select name="regimen" required id="regimen">
                        <option value="">Seleccionar...</option>
                        <optgroup label="Personas Morales">
                            <option value="Régimen General de Ley Personas Morales">Régimen General de Ley Personas Morales</option>
                            <option value="Régimen de Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras">Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras</option>
                        </optgroup>
                        <optgroup label="Personas Físicas">
                            <option value="Régimen Simplificado de Confianza">Régimen Simplificado de Confianza</option>
                            <option value="Régimen de Sueldos y Salarios e Ingresos Asimilados a Salarios">Sueldos y Salarios</option>
                            <option value="Régimen de Actividades Empresariales y Profesionales">Actividades Empresariales y Profesionales</option>
                        </optgroup>
                    </select>
                </div>
            </div>
        </div>

        <!-- Sección 3: Dirección Fiscal -->
        <div class="form-section">
            <h2 class="section-title">
                <i class="fas fa-map-marker-alt"></i>
                Dirección Fiscal
            </h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="required">País</label>
                    <input type="text" name="pais" value="México" readonly style="background: #e2e8f0; cursor: not-allowed;">
                </div>
                
                <div class="form-group">
                    <label class="required">Calle</label>
                    <input type="text" name="calle" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="required">Número Exterior</label>
                    <input type="text" name="n_ext" required>
                </div>
                
                <div class="form-group">
                    <label>Número Interior</label>
                    <input type="text" name="n_int">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="required">Código Postal</label>
                    <input type="text" name="cp" maxlength="5" required pattern="[0-9]{5}">
                </div>
                
                <div class="form-group">
                    <label class="required">Colonia</label>
                    <input type="text" name="colonia" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="required">Ciudad</label>
                    <input type="text" name="ciudad" required>
                </div>
                
                <div class="form-group">
                    <label class="required">Estado</label>
                    <select name="estado" required>
                        <option value="">Seleccionar...</option>
                        <option value="Aguascalientes">Aguascalientes</option>
                        <option value="Baja California">Baja California</option>
                        <option value="Baja California Sur">Baja California Sur</option>
                        <option value="Campeche">Campeche</option>
                        <option value="Chiapas">Chiapas</option>
                        <option value="Chihuahua">Chihuahua</option>
                        <option value="Coahuila">Coahuila</option>
                        <option value="Colima">Colima</option>
                        <option value="Durango">Durango</option>
                        <option value="Guanajuato">Guanajuato</option>
                        <option value="Guerrero">Gu Guerrero</option>
                        <option value="Hidalgo">Hidalgo</option>
                        <option value="Jalisco">Jalisco</option>
                        <option value="México">México</option>
                        <option value="Michoacán">Michoacán</option>
                        <option value="Morelos">Morelos</option>
                        <option value="Nayarit">Nayarit</option>
                        <option value="Nuevo León">Nuevo León</option>
                        <option value="Oaxaca">Oaxaca</option>
                        <option value="Puebla">Puebla</option>
                        <option value="Querétaro">Querétaro</option>
                        <option value="Quintana Roo">Quintana Roo</option>
                        <option value="San Luis Potosí">San Luis Potosí</option>
                        <option value="Sinaloa">Sinaloa</option>
                        <option value="Sonora">Sonora</option>
                        <option value="Tabasco">Tabasco</option>
                        <option value="Tamaulipas">Tamaulipas</option>
                        <option value="Tlaxcala">Tlaxcala</option>
                        <option value="Veracruz">Veracruz</option>
                        <option value="Yucatán">Yucatán</option>
                        <option value="Zacatecas">Zacatecas</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="required">Constancia de Situación Fiscal</label>
                    <input type="file" name="constancia_fiscal" id="constancia_fiscal" 
                           accept="application/pdf" required 
                           onchange="previewFile(this)">
                    <div class="file-preview" id="filePreview"></div>
                    <div class="helper-text">Solo PDF. Máximo 10MB. Se guardará en uploads/constancias/</div>
                </div>
            </div>
        </div>

        <!-- Sección 4: Información Adicional -->
        <div class="form-section">
            <h2 class="section-title">
                <i class="fas fa-info-circle"></i>
                Información Adicional
            </h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="required">¿Cómo nos encontró?</label>
                    <select name="medio" required>
                        <option value="">Seleccionar...</option>
                        <option value="Google">Google / Búsqueda Web</option>
                        <option value="Redes Sociales">Redes Sociales</option>
                        <option value="Recomendación">Recomendación</option>
                        <option value="Networking">Networking</option>
                        <option value="Publicidad">Publicidad</option>
                        <option value="Ferias/Eventos">Ferias/Eventos</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="required">Moneda Preferida</label>
                    <select name="moneda" required>
                        <option value="MXN">MXN - Pesos Mexicanos</option>
                        <option value="USD">USD - Dólares Americanos</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="required">Áreas de Interés</label>
                <div class="checkbox-grid">
                    <label class="checkbox-item">
                        <input type="checkbox" name="areas[]" value="CCTV IP / Análogo">
                        <span>CCTV IP / Análogo</span>
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="areas[]" value="Redes & WiFi">
                        <span>Redes & WiFi</span>
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="areas[]" value="Control de Acceso">
                        <span>Control de Acceso</span>
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="areas[]" value="Intrusión & Alarmas">
                        <span>Intrusión & Alarmas</span>
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="areas[]" value="Sistemas de Incendio">
                        <span>Sistemas de Incendio</span>
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="areas[]" value="Cómputo">
                        <span>Cómputo</span>
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="areas[]" value="Automatización">
                        <span>Automatización</span>
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="areas[]" value="Telecomunicaciones">
                        <span>Telecomunicaciones</span>
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="areas[]" value="Energía">
                        <span>Energía</span>
                    </label>
                </div>
                <div class="helper-text">Seleccione al menos un área de interés</div>
            </div>
            
            <div class="form-group">
                <label>Comentarios Adicionales</label>
                <textarea name="comentarios" rows="4" placeholder="Información adicional que considere relevante para su registro..."></textarea>
            </div>
            
            <div class="form-group">
                <label style="font-weight: normal;">
                    <input type="checkbox" name="vende_internet" value="1">
                    Vende productos/servicios por internet
                </label>
            </div>
        </div>

        <!-- Términos y Condiciones -->
        <div class="form-footer">
            <div class="terms">
                <input type="checkbox" id="terminos" required>
                <div>
                    <label for="terminos" style="font-weight: 600; margin-bottom: 5px; display: block;">
                        Aceptación de Términos y Condiciones
                    </label>
                    <p style="font-size: 14px; color: #718096;">
                        He leído y acepto el <a href="#" style="color: var(--accent); text-decoration: underline;">Aviso de Privacidad</a> 
                        y las <a href="#" style="color: var(--accent); text-decoration: underline;">Políticas de Distribuidor</a> de NGS TECHNOLOGY. 
                        Acepto que mis datos serán utilizados para fines comerciales y de contacto según lo establecido.
                    </p>
                </div>
            </div>
            
            <button type="submit" class="btn-submit" id="btnSubmit" disabled>
                <i class="fas fa-paper-plane"></i>
                ENVIAR SOLICITUD DE REGISTRO
            </button>
            
            <p style="text-align: center; margin-top: 20px; font-size: 13px; color: #718096;">
                <i class="fas fa-clock"></i> El proceso de aprobación puede tomar de 24 a 48 horas hábiles
            </p>
            
            <p style="text-align: center; margin-top: 15px; font-size: 14px;">
                ¿Ya tienes una cuenta? 
                <a href="login.php" style="color: var(--accent); font-weight: 600; text-decoration: none;">
                    <i class="fas fa-sign-in-alt"></i> Inicia sesión aquí
                </a>
            </p>
        </div>
    </form>
</div>

<!-- Modal de éxito -->
<div class="modal-overlay <?php echo $mostrar_modal_exito ? 'active' : ''; ?>" id="modalExito">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-check-circle"></i>
            <h2>¡Registro Exitoso!</h2>
        </div>
        <div class="modal-body">
            <p><strong>Su solicitud de registro como distribuidor ha sido enviada correctamente.</strong></p>
            <p>Su cuenta está <strong>pendiente de revisión</strong> por parte de nuestro equipo administrativo.</p>
            <p>El proceso de aprobación generalmente toma <strong>24 a 48 horas hábiles</strong>. Le notificaremos por correo electrónico una vez que su cuenta haya sido aprobada.</p>
            <p>Mientras tanto, puede guardar sus credenciales:</p>
            <div style="background: #f8fafc; padding: 15px; border-radius: 6px; margin: 15px 0; text-align: left;">
                <p><strong>Correo:</strong> <span id="modal-email"><?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?></span></p>
                <p><strong>Contraseña:</strong> <span id="modal-pass">[La que ingresó]</span></p>
            </div>
            <p><em>Archivo PDF guardado en: uploads/constancias/</em></p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal" onclick="cerrarModalYLimpiar()">
                <i class="fas fa-check"></i>
                Entendido
            </button>
        </div>
    </div>
</div>

<script>
function validarSeguridad() {
    const p1 = document.getElementById('pass').value;
    const p2 = document.getElementById('pass2').value;
    const regex = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$!%*?&]{6,}$/;
    const btn = document.getElementById('btnSubmit');
    const terminos = document.getElementById('terminos');

    // Validar campos requeridos
    const camposRequeridos = document.querySelectorAll('[required]');
    let todosValidos = true;
    
    camposRequeridos.forEach(campo => {
        if (!campo.value.trim() && campo.type !== 'file') {
            todosValidos = false;
        }
    });

    // Validar al menos un área de interés
    const areasCheckboxes = document.querySelectorAll('input[name="areas[]"]:checked');
    if (areasCheckboxes.length === 0) {
        todosValidos = false;
    }

    if (regex.test(p1) && p1 === p2 && terminos.checked && todosValidos) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> ENVIAR SOLICITUD DE REGISTRO';
    } else {
        btn.disabled = true;
        if (!terminos.checked) {
            btn.innerHTML = '<i class="fas fa-exclamation-circle"></i> ACEPTE LOS TÉRMINOS Y CONDICIONES';
        } else if (p1 !== p2) {
            btn.innerHTML = '<i class="fas fa-exclamation-circle"></i> LAS CONTRASEÑAS NO COINCIDEN';
        } else if (!regex.test(p1)) {
            btn.innerHTML = '<i class="fas fa-exclamation-circle"></i> CONTRASEÑA NO CUMPLE REQUISITOS';
        } else if (areasCheckboxes.length === 0) {
            btn.innerHTML = '<i class="fas fa-exclamation-circle"></i> SELECCIONE AL MENOS UN ÁREA DE INTERÉS';
        } else {
            btn.innerHTML = '<i class="fas fa-exclamation-circle"></i> COMPLETE TODOS LOS CAMPOS REQUERIDOS';
        }
    }
}

function validarEdad() {
    const fechaInput = document.getElementById('cumple');
    const fecha = new Date(fechaInput.value);
    const hoy = new Date();
    let edad = hoy.getFullYear() - fecha.getFullYear();
    const mes = hoy.getMonth() - fecha.getMonth();
    
    if (mes < 0 || (mes === 0 && hoy.getDate() < fecha.getDate())) {
        edad--;
    }
    
    if (edad < 18) {
        alert("Debe ser mayor de 18 años para registrarse como distribuidor.");
        fechaInput.value = "";
        fechaInput.focus();
    }
}

function previewFile(input) {
    const preview = document.getElementById('filePreview');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validar tamaño (10MB)
        if (file.size > 10 * 1024 * 1024) {
            alert("El archivo es demasiado grande. Máximo 10MB.");
            input.value = '';
            preview.innerHTML = '';
            preview.classList.remove('active');
            return;
        }
        
        // Validar tipo
        if (file.type !== 'application/pdf') {
            alert("Solo se permiten archivos PDF.");
            input.value = '';
            preview.innerHTML = '';
            preview.classList.remove('active');
            return;
        }
        
        const fileSize = (file.size / (1024*1024)).toFixed(2);
        preview.innerHTML = `
            <strong>Archivo seleccionado:</strong> ${file.name}<br>
            <strong>Tamaño:</strong> ${fileSize} MB<br>
            <strong>Tipo:</strong> PDF Document<br>
            <strong>Se guardará en:</strong> uploads/constancias/
        `;
        preview.classList.add('active');
    } else {
        preview.innerHTML = '';
        preview.classList.remove('active');
    }
    
    validarSeguridad();
}

function cerrarModalYLimpiar() {
    // Cerrar modal
    document.getElementById('modalExito').classList.remove('active');
    
    // Limpiar formulario solo si el registro fue exitoso
    <?php if ($mostrar_modal_exito): ?>
    document.getElementById('regForm').reset();
    document.querySelectorAll('.file-preview').forEach(el => {
        el.innerHTML = '';
        el.classList.remove('active');
    });
    
    // Resetear botón
    document.getElementById('btnSubmit').disabled = true;
    document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-exclamation-circle"></i> COMPLETE TODOS LOS CAMPOS REQUERIDOS';
    
    // Scroll al inicio
    window.scrollTo({ top: 0, behavior: 'smooth' });
    <?php endif; ?>
}

// Sincronizar régimen fiscal con tipo de empresa
document.getElementById('tipo_empresa').addEventListener('change', function() {
    const regimenSelect = document.getElementById('regimen');
    const tipo = this.value;
    
    // Mostrar solo las opciones relevantes
    Array.from(regimenSelect.options).forEach(option => {
        if (option.value === '') return;
        
        const isMoral = option.parentElement.label === 'Personas Morales';
        const isFisica = option.parentElement.label === 'Personas Físicas';
        
        if (tipo === 'Persona Moral') {
            option.style.display = isMoral ? '' : 'none';
        } else if (tipo === 'Persona Física') {
            option.style.display = isFisica ? '' : 'none';
        }
    });
    
    // Resetear selección si la opción actual no es válida
    if (regimenSelect.value !== '' && regimenSelect.options[regimenSelect.selectedIndex].style.display === 'none') {
        regimenSelect.value = '';
    }
});

// Validar RFC (formato mejorado)
document.getElementById('rfc').addEventListener('blur', function() {
    const rfc = this.value.trim().toUpperCase();
    
    if (rfc === '') return;
    
    // Expresión regular para RFC
    const regexMoral = /^[A-ZÑ&]{3}[0-9]{6}[A-Z0-9]{3}$/; // Persona Moral
    const regexFisica = /^[A-ZÑ&]{4}[0-9]{6}[A-Z0-9]{3}$/; // Persona Física
    
    const tipoEmpresa = document.getElementById('tipo_empresa').value;
    let esValido = false;
    
    if (tipoEmpresa === 'Persona Moral') {
        esValido = regexMoral.test(rfc);
    } else if (tipoEmpresa === 'Persona Física') {
        esValido = regexFisica.test(rfc);
    } else {
        // Si no se ha seleccionado tipo, probar ambos
        esValido = regexMoral.test(rfc) || regexFisica.test(rfc);
    }
    
    if (!esValido) {
        const mensaje = tipoEmpresa === 'Persona Moral' 
            ? 'RFC de Persona Moral inválido. Formato: AAA999999AAA' 
            : 'RFC de Persona Física inválido. Formato: AAAA999999AAA';
        
        alert(mensaje);
        this.focus();
        this.select();
    }
});

// Validar todos los campos requeridos
document.querySelectorAll('[required]').forEach(campo => {
    if (campo.type !== 'file') {
        campo.addEventListener('input', validarSeguridad);
        campo.addEventListener('change', validarSeguridad);
    }
});

// Validar áreas de interés
document.querySelectorAll('input[name="areas[]"]').forEach(checkbox => {
    checkbox.addEventListener('change', validarSeguridad);
});

// Validar términos cuando cambien
document.getElementById('terminos').addEventListener('change', validarSeguridad);

// Validar formulario antes de enviar
document.getElementById('regForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('btnSubmit');
    if (btn.disabled) {
        e.preventDefault();
        alert("Por favor complete todos los campos requeridos correctamente.");
        return false;
    }
    
    // Mostrar mensaje de carga
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> PROCESANDO REGISTRO...';
    btn.disabled = true;
    
    return true;
});

// Cerrar modal con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('modalExito').classList.contains('active')) {
        cerrarModalYLimpiar();
    }
});

// Cerrar modal al hacer clic fuera
document.getElementById('modalExito').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalYLimpiar();
    }
});

// Inicializar validación
validarSeguridad();
</script>

</body>
</html>