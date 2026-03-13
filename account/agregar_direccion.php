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
$form_data = [
    'nombre' => '',
    'contacto' => '',
    'telefono' => '',
    'email' => '',
    'calle' => '',
    'num_exterior' => '',
    'num_interior' => '',
    'cp' => '',
    'colonia' => '',
    'ciudad' => '',
    'estado' => ''
];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitizar y validar datos
    $form_data['nombre'] = trim($_POST['nombre'] ?? '');
    $form_data['contacto'] = trim($_POST['contacto'] ?? '');
    $form_data['telefono'] = trim($_POST['telefono'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['calle'] = trim($_POST['calle'] ?? '');
    $form_data['num_exterior'] = trim($_POST['num_exterior'] ?? '');
    $form_data['num_interior'] = trim($_POST['num_interior'] ?? '');
    $form_data['cp'] = trim($_POST['cp'] ?? '');
    $form_data['colonia'] = trim($_POST['colonia'] ?? '');
    $form_data['ciudad'] = trim($_POST['ciudad'] ?? '');
    $form_data['estado'] = trim($_POST['estado'] ?? '');
    $activa = isset($_POST['activa']) ? 1 : 0;

    // Validaciones
    if (empty($form_data['nombre'])) {
        $errors[] = "El nombre de la dirección es obligatorio";
    } elseif (strlen($form_data['nombre']) > 200) {
        $errors[] = "El nombre no puede exceder 200 caracteres";
    }

    if (!empty($form_data['telefono']) && !preg_match('/^[0-9\-\+\s\(\)]{10,20}$/', $form_data['telefono'])) {
        $errors[] = "El teléfono no es válido";
    }

    if (!empty($form_data['email']) && !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "El email no es válido";
    }

    if (empty($form_data['calle'])) {
        $errors[] = "La calle es obligatoria";
    }

    if (empty($form_data['num_exterior'])) {
        $errors[] = "El número exterior es obligatorio";
    }

    if (empty($form_data['cp'])) {
        $errors[] = "El código postal es obligatorio";
    } elseif (!preg_match('/^[0-9]{5}$/', $form_data['cp'])) {
        $errors[] = "El código postal debe tener 5 dígitos";
    }

    if (empty($form_data['colonia'])) {
        $errors[] = "La colonia es obligatoria";
    }

    if (empty($form_data['ciudad'])) {
        $errors[] = "La ciudad es obligatoria";
    }

    if (empty($form_data['estado'])) {
        $errors[] = "El estado es obligatorio";
    }

    // Si no hay errores, guardar en la base de datos
    if (empty($errors)) {
        try {
            // Si se marca como activa, primero desactivar todas las demás
            if ($activa) {
                $stmt = $pdo->prepare("UPDATE sucursales SET activa = 0 WHERE usuario_id = ?");
                $stmt->execute([$usuario_id]);
            }

            // Insertar nueva dirección
            $stmt = $pdo->prepare("
                INSERT INTO sucursales (
                    usuario_id, nombre, contacto, telefono, email,
                    calle, num_exterior, num_interior, cp, colonia,
                    ciudad, estado, activa, fecha_registro
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $usuario_id,
                $form_data['nombre'],
                $form_data['contacto'],
                $form_data['telefono'],
                $form_data['email'],
                $form_data['calle'],
                $form_data['num_exterior'],
                $form_data['num_interior'],
                $form_data['cp'],
                $form_data['colonia'],
                $form_data['ciudad'],
                $form_data['estado'],
                $activa
            ]);

            $_SESSION['success'] = "Dirección agregada correctamente";
            header('Location: direcciones.php');
            exit();

        } catch (PDOException $e) {
            $errors[] = "Error al guardar la dirección: " . $e->getMessage();
        }
    }
}

// Estados de México (puedes ampliar esta lista)
$estados_mexico = [
    'Aguascalientes',
    'Baja California',
    'Baja California Sur',
    'Campeche',
    'Chiapas',
    'Chihuahua',
    'Ciudad de México',
    'Coahuila',
    'Colima',
    'Durango',
    'Estado de México',
    'Guanajuato',
    'Guerrero',
    'Hidalgo',
    'Jalisco',
    'Michoacán',
    'Morelos',
    'Nayarit',
    'Nuevo León',
    'Oaxaca',
    'Puebla',
    'Querétaro',
    'Quintana Roo',
    'San Luis Potosí',
    'Sinaloa',
    'Sonora',
    'Tabasco',
    'Tamaulipas',
    'Tlaxcala',
    'Veracruz',
    'Yucatán',
    'Zacatecas'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Dirección | NGS Store</title>
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

        .container-custom {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .form-container {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 3rem;
        }

        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
        }

        .form-section h3 {
            color: var(--ngs-blue);
            font-size: 1.3rem;
            margin-bottom: 1.2rem;
            padding-bottom: 0.5rem;
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

        .form-text {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .btn-navigation {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-back {
            background-color: #6c757d;
            color: white;
        }

        .btn-back:hover {
            background-color: #5a6268;
            color: white;
            transform: translateY(-2px);
        }

        .btn-submit {
            background-color: var(--ngs-blue);
            color: white;
            padding: 10px 30px;
            font-weight: 500;
        }

        .btn-submit:hover {
            background-color: #051225;
            color: white;
            transform: translateY(-2px);
        }

        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }

        .form-check-input:checked {
            background-color: var(--ngs-blue);
            border-color: var(--ngs-blue);
        }

        .alert {
            margin-bottom: 1.5rem;
        }

        .address-preview {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            border: 1px dashed #ddd;
        }

        .address-preview h5 {
            font-size: 1rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .profile-banner {
                padding: 2rem 0;
            }
            
            .profile-banner h1 {
                font-size: 1.8rem;
            }
            
            .form-container {
                padding: 1.5rem;
            }
            
            .form-footer {
                flex-direction: column;
                gap: 1rem;
            }
            
            .form-footer .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<!-- Banner de perfil -->
<div class="profile-banner">
    <div class="container container-custom">
        <div class="profile-banner-content">
            <h1>Agregar Nueva Dirección</h1>
            <p>Completa el formulario para agregar una nueva dirección de envío</p>
        </div>
    </div>
</div>

<div class="container container-custom">
    <!-- Mensajes de error -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <h5 class="alert-heading"><i class="fa-solid fa-exclamation-triangle me-2"></i>Error en el formulario</h5>
            <ul class="mb-0">
                <?php foreach($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Formulario -->
    <div class="form-container">
        <form method="POST" action="" id="direccionForm">
            <!-- Sección 1: Información de la dirección -->
            <div class="form-section">
                <h3><i class="fa-solid fa-map-marker-alt me-2"></i>Información de la Dirección</h3>
                
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="nombre" class="form-label required">Nombre de la dirección</label>
                        <input type="text" 
                               class="form-control" 
                               id="nombre" 
                               name="nombre" 
                               value="<?php echo htmlspecialchars($form_data['nombre']); ?>"
                               placeholder="Ej: Casa, Oficina, Sucursal Principal"
                               required
                               maxlength="200">
                        <div class="form-text">Un nombre que te ayude a identificar esta dirección fácilmente.</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="contacto" class="form-label">Persona de contacto</label>
                        <input type="text" 
                               class="form-control" 
                               id="contacto" 
                               name="contacto" 
                               value="<?php echo htmlspecialchars($form_data['contacto']); ?>"
                               placeholder="Nombre del receptor"
                               maxlength="100">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="telefono" class="form-label">Teléfono de contacto</label>
                        <input type="tel" 
                               class="form-control" 
                               id="telefono" 
                               name="telefono" 
                               value="<?php echo htmlspecialchars($form_data['telefono']); ?>"
                               placeholder="Ej: 555-123-4567"
                               maxlength="20">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="email" class="form-label">Email de contacto</label>
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($form_data['email']); ?>"
                               placeholder="correo@ejemplo.com"
                               maxlength="150">
                    </div>
                </div>
            </div>

            <!-- Sección 2: Ubicación -->
            <div class="form-section">
                <h3><i class="fa-solid fa-location-dot me-2"></i>Ubicación</h3>
                
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="calle" class="form-label required">Calle</label>
                        <input type="text" 
                               class="form-control" 
                               id="calle" 
                               name="calle" 
                               value="<?php echo htmlspecialchars($form_data['calle']); ?>"
                               placeholder="Nombre de la calle, avenida, etc."
                               required
                               maxlength="200">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="num_exterior" class="form-label required">Número Exterior</label>
                        <input type="text" 
                               class="form-control" 
                               id="num_exterior" 
                               name="num_exterior" 
                               value="<?php echo htmlspecialchars($form_data['num_exterior']); ?>"
                               placeholder="Ej: 123"
                               required
                               maxlength="20">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="num_interior" class="form-label">Número Interior</label>
                        <input type="text" 
                               class="form-control" 
                               id="num_interior" 
                               name="num_interior" 
                               value="<?php echo htmlspecialchars($form_data['num_interior']); ?>"
                               placeholder="Ej: 401, A"
                               maxlength="20">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="cp" class="form-label required">Código Postal</label>
                        <input type="text" 
                               class="form-control" 
                               id="cp" 
                               name="cp" 
                               value="<?php echo htmlspecialchars($form_data['cp']); ?>"
                               placeholder="5 dígitos"
                               required
                               maxlength="5"
                               pattern="[0-9]{5}">
                        <div class="form-text">Ingresa solo números</div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="colonia" class="form-label required">Colonia</label>
                        <input type="text" 
                               class="form-control" 
                               id="colonia" 
                               name="colonia" 
                               value="<?php echo htmlspecialchars($form_data['colonia']); ?>"
                               placeholder="Nombre de la colonia"
                               required
                               maxlength="100">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="ciudad" class="form-label required">Ciudad / Municipio</label>
                        <input type="text" 
                               class="form-control" 
                               id="ciudad" 
                               name="ciudad" 
                               value="<?php echo htmlspecialchars($form_data['ciudad']); ?>"
                               placeholder="Nombre de la ciudad o municipio"
                               required
                               maxlength="100">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="estado" class="form-label required">Estado</label>
                        <select class="form-select" id="estado" name="estado" required>
                            <option value="">Seleccionar estado</option>
                            <?php foreach($estados_mexico as $estado): ?>
                                <option value="<?php echo htmlspecialchars($estado); ?>"
                                    <?php echo ($form_data['estado'] === $estado) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($estado); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Vista previa de la dirección -->
                <div class="address-preview" id="addressPreview">
                    <h5>Vista previa de la dirección:</h5>
                    <div id="previewContent">
                        <em class="text-muted">Complete los campos para ver la vista previa...</em>
                    </div>
                </div>
            </div>

            <!-- Sección 3: Opciones -->
            <div class="form-section">
                <h3><i class="fa-solid fa-gear me-2"></i>Opciones</h3>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" 
                               type="checkbox" 
                               id="activa" 
                               name="activa" 
                               value="1"
                               <?php echo isset($_POST['activa']) ? 'checked' : 'checked'; ?>>
                        <label class="form-check-label" for="activa">
                            <strong>Establecer como dirección predeterminada</strong>
                        </label>
                        <div class="form-text">
                            Esta será la dirección utilizada por defecto para nuevos pedidos.
                            Solo puede haber una dirección predeterminada.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones del formulario -->
            <div class="form-footer">
                <a href="direcciones.php" class="btn-navigation btn-back">
                    <i class="fa-solid fa-arrow-left me-2"></i>Cancelar
                </a>
                
                <button type="submit" class="btn btn-submit">
                    <i class="fa-solid fa-save me-2"></i>Guardar Dirección
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Función para generar vista previa de la dirección
    function updateAddressPreview() {
        const calle = document.getElementById('calle').value;
        const numExterior = document.getElementById('num_exterior').value;
        const numInterior = document.getElementById('num_interior').value;
        const colonia = document.getElementById('colonia').value;
        const cp = document.getElementById('cp').value;
        const ciudad = document.getElementById('ciudad').value;
        const estado = document.getElementById('estado').value;
        const contacto = document.getElementById('contacto').value;
        
        let previewHTML = '';
        
        if (calle || numExterior || colonia) {
            // Dirección completa
            let direccion = '';
            if (calle) direccion += calle;
            if (numExterior) direccion += ' #' + numExterior;
            if (numInterior) direccion += ' Int. ' + numInterior;
            if (colonia) direccion += ', ' + colonia;
            
            previewHTML += `<p><strong>Dirección:</strong> ${direccion}</p>`;
            
            // Ciudad, estado y CP
            let ubicacion = '';
            if (cp) ubicacion += 'CP ' + cp;
            if (ciudad) ubicacion += (ubicacion ? ', ' : '') + ciudad;
            if (estado) ubicacion += (ubicacion ? ', ' : '') + estado;
            
            if (ubicacion) {
                previewHTML += `<p><strong>Ubicación:</strong> ${ubicacion}</p>`;
            }
            
            // Contacto
            if (contacto) {
                previewHTML += `<p><strong>Contacto:</strong> ${contacto}</p>`;
            }
        } else {
            previewHTML = '<em class="text-muted">Complete los campos para ver la vista previa...</em>';
        }
        
        document.getElementById('previewContent').innerHTML = previewHTML;
    }
    
    // Actualizar vista previa al cambiar campos
    const camposDireccion = ['calle', 'num_exterior', 'num_interior', 'colonia', 'cp', 'ciudad', 'estado', 'contacto'];
    camposDireccion.forEach(campo => {
        document.getElementById(campo).addEventListener('input', updateAddressPreview);
        document.getElementById(campo).addEventListener('change', updateAddressPreview);
    });
    
    // Validar formulario antes de enviar
    document.getElementById('direccionForm').addEventListener('submit', function(e) {
        const cp = document.getElementById('cp').value;
        const cpRegex = /^[0-9]{5}$/;
        
        if (cp && !cpRegex.test(cp)) {
            e.preventDefault();
            alert('El código postal debe tener exactamente 5 dígitos numéricos.');
            document.getElementById('cp').focus();
            return false;
        }
        
        // Validar teléfono si está presente
        const telefono = document.getElementById('telefono').value;
        if (telefono && !/^[0-9\-\+\s\(\)]{10,20}$/.test(telefono)) {
            e.preventDefault();
            alert('Por favor ingrese un número de teléfono válido (10-20 dígitos).');
            document.getElementById('telefono').focus();
            return false;
        }
        
        return true;
    });
    
    // Inicializar vista previa
    document.addEventListener('DOMContentLoaded', updateAddressPreview);
</script>
</body>
</html>