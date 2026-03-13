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

// Configuración de archivos - RUTA CORREGIDA
$upload_dir = '../uploads/documentos/'; // Carpeta documentos dentro de uploads
$max_file_size = 10 * 1024 * 1024; // 10MB
$allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];

// Crear directorio si no existe
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Procesar eliminación de documento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_documento'])) {
    $documento_id = intval($_POST['documento_id']);
    
    // Verificar que el documento pertenece al usuario
    $stmt = $pdo->prepare("SELECT * FROM documentos_usuario WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$documento_id, $usuario_id]);
    $documento = $stmt->fetch();
    
    if ($documento) {
        try {
            // Eliminar archivo físico
            $file_path = $documento['ruta_archivo'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Eliminar registro de la base de datos
            $stmt = $pdo->prepare("DELETE FROM documentos_usuario WHERE id = ?");
            $stmt->execute([$documento_id]);
            
            $_SESSION['success'] = "Documento eliminado correctamente";
            header('Location: documentos.php');
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error al eliminar el documento: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Documento no encontrado o no tienes permisos";
    }
}

// Procesar subida de documento - CÓDIGO MEJORADO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_documento'])) {
    $tipo_documento = $_POST['tipo_documento'] ?? '';
    $descripcion = trim($_POST['descripcion'] ?? '');
    
    $errors = [];
    
    // Validaciones
    if (empty($tipo_documento) || !in_array($tipo_documento, ['constancia', 'identificacion', 'domicilio', 'contrato', 'otro'])) {
        $errors[] = "Tipo de documento inválido";
    }
    
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "Debes seleccionar un archivo";
    } elseif ($_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Error al subir el archivo: " . $_FILES['archivo']['error'];
    } else {
        $file = $_FILES['archivo'];
        
        // Validar tamaño
        if ($file['size'] > $max_file_size) {
            $errors[] = "El archivo es demasiado grande (máximo 10MB)";
        }
        
        // Validar tipo usando finfo para mayor seguridad
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $file_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Tipo de archivo no permitido. Solo PDF, JPG, PNG";
        }
        
        // Validar extensión
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($file_ext, $allowed_ext)) {
            $errors[] = "Extensión no permitida. Solo PDF, JPG, PNG";
        }
    }
    
    if (empty($errors)) {
        try {
            // Generar nombre único para el archivo - FORMATO MEJORADO
            $timestamp = time();
            $random_str = bin2hex(random_bytes(8));
            $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
            $file_name = "doc_{$usuario_id}_{$timestamp}_{$random_str}_{$safe_filename}.{$file_ext}";
            $file_path = $upload_dir . $file_name;
            
            // Mover archivo al directorio
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Insertar en base de datos - ACTUALIZAR TABLA USUARIOS SI ES NECESARIO
                $stmt = $pdo->prepare("
                    INSERT INTO documentos_usuario 
                    (usuario_id, tipo_documento, nombre_archivo, ruta_archivo, tamaño, fecha_subida) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $usuario_id,
                    $tipo_documento,
                    $file['name'], // Nombre original
                    $file_path,    // Ruta completa
                    $file['size']
                ]);
                
                // ACTUALIZAR TABLA USUARIOS SEGÚN EL TIPO DE DOCUMENTO
                if (in_array($tipo_documento, ['constancia', 'identificacion', 'domicilio'])) {
                    $campo_usuario = '';
                    switch($tipo_documento) {
                        case 'constancia':
                            $campo_usuario = 'constancia_pdf';
                            break;
                        case 'identificacion':
                            $campo_usuario = 'identificacion_pdf';
                            break;
                        case 'domicilio':
                            $campo_usuario = 'comprobante_domicilio';
                            break;
                    }
                    
                    if ($campo_usuario) {
                        $stmt_update = $pdo->prepare("
                            UPDATE usuarios 
                            SET {$campo_usuario} = ? 
                            WHERE id = ?
                        ");
                        $stmt_update->execute([$file_path, $usuario_id]);
                    }
                }
                
                $_SESSION['success'] = "Documento subido correctamente y vinculado a tu perfil";
                header('Location: documentos.php');
                exit();
            } else {
                $_SESSION['error'] = "Error al mover el archivo al servidor. Verifica permisos de la carpeta.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error al guardar el documento: " . $e->getMessage();
        } catch (Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
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

// Obtener documentos del usuario
$stmt = $pdo->prepare("
    SELECT * FROM documentos_usuario 
    WHERE usuario_id = ? 
    ORDER BY fecha_subida DESC
");

$stmt->execute([$usuario_id]);
$documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar documentos por tipo
$documentos_por_tipo = [
    'constancia' => 0,
    'identificacion' => 0,
    'domicilio' => 0,
    'contrato' => 0,
    'otro' => 0
];

foreach ($documentos as $doc) {
    $documentos_por_tipo[$doc['tipo_documento']]++;
}

// Obtener información del usuario para verificar documentos requeridos
$stmt_usuario = $pdo->prepare("
    SELECT constancia_pdf, identificacion_pdf, comprobante_domicilio, estatus 
    FROM usuarios WHERE id = ?
");
$stmt_usuario->execute([$usuario_id]);
$usuario_info = $stmt_usuario->fetch();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Documentos | NGS Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
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

        /* Resumen de documentos */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #eaeaea;
            transition: transform 0.3s ease;
            text-align: center;
        }

        .summary-card:hover {
            transform: translateY(-3px);
        }

        .summary-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 1rem;
            color: white;
        }

        .icon-constancia {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .icon-identificacion {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }

        .icon-domicilio {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }

        .icon-contrato {
            background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
        }

        .icon-otro {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }

        .summary-title {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .summary-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }

        /* Formulario de subida */
        .upload-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #eaeaea;
        }

        .upload-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .upload-header h3 {
            font-size: 1.3rem;
            color: #333;
            margin: 0;
        }

        .upload-icon {
            width: 50px;
            height: 50px;
            background: var(--ngs-blue);
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 0.5rem;
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

        .file-upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .file-upload-area:hover {
            border-color: var(--ngs-blue);
            background: #e9f7fe;
        }

        .file-upload-area.dragover {
            border-color: var(--ngs-blue);
            background: #d1ecf1;
        }

        .file-upload-icon {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .file-upload-text h4 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .file-upload-text p {
            color: #666;
            margin-bottom: 1rem;
        }

        .file-upload-btn {
            background: white;
            border: 1px solid var(--ngs-blue);
            color: var(--ngs-blue);
            padding: 0.5rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-btn:hover {
            background: var(--ngs-blue);
            color: white;
        }

        .file-preview {
            margin-top: 1rem;
            display: none;
        }

        .file-preview.active {
            display: block;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #eee;
        }

        .file-icon {
            font-size: 2rem;
            color: #dc3545;
        }

        .file-icon.pdf {
            color: #dc3545;
        }

        .file-icon.image {
            color: #28a745;
        }

        .file-details {
            flex: 1;
        }

        .file-name {
            font-weight: 500;
            margin-bottom: 0.3rem;
        }

        .file-size {
            font-size: 0.85rem;
            color: #666;
        }

        .btn-remove-file {
            background: none;
            border: none;
            color: #dc3545;
            font-size: 1.2rem;
            cursor: pointer;
        }

        .btn-submit-upload {
            background-color: var(--ngs-blue);
            color: white;
            padding: 0.75rem 2rem;
            font-weight: 500;
            border: none;
            border-radius: 6px;
            width: 100%;
            transition: all 0.3s ease;
        }

        .btn-submit-upload:hover {
            background-color: #051225;
        }

        .btn-submit-upload:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }

        /* Tabla de documentos */
        .documents-table-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #eaeaea;
            margin-bottom: 3rem;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-header h2 {
            font-size: 1.4rem;
            color: #333;
            margin: 0;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            width: 250px;
            font-size: 0.9rem;
        }

        .search-box i {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        /* Estilos para DataTables */
        #documentosTable_wrapper {
            margin-top: 1rem;
        }

        #documentosTable {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            margin: 0;
        }

        #documentosTable thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            padding: 1rem;
            font-weight: 600;
            color: #333;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        #documentosTable tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #eee;
        }

        #documentosTable tbody tr:hover {
            background-color: #f8f9fa;
        }

        /* Badges de tipo */
        .tipo-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .tipo-constancia {
            background-color: #e0d4f9;
            color: #5a3d8a;
        }

        .tipo-identificacion {
            background-color: #d4edda;
            color: #155724;
        }

        .tipo-domicilio {
            background-color: #fff3cd;
            color: #856404;
        }

        .tipo-contrato {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .tipo-otro {
            background-color: #e2e3e5;
            color: #383d41;
        }

        /* Botones de acción */
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-view {
            background-color: var(--ngs-blue);
            color: white;
        }

        .btn-view:hover {
            background-color: #051225;
            color: white;
        }

        .btn-download {
            background-color: #28a745;
            color: white;
        }

        .btn-download:hover {
            background-color: #218838;
            color: white;
        }

        .btn-delete {
            background-color: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background-color: #c82333;
            color: white;
        }

        /* Sin documentos */
        .sin-documentos {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 10px;
            border: 2px dashed #ddd;
        }

        .sin-documentos i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .sin-documentos h3 {
            margin-bottom: 1rem;
            color: #333;
        }

        /* Modal de confirmación */
        .modal-confirm .modal-content {
            border-radius: 10px;
            border: none;
        }

        .modal-confirm .modal-header {
            background: var(--ngs-blue);
            color: white;
            border-radius: 10px 10px 0 0;
        }

        .modal-confirm .modal-body {
            padding: 2rem;
            text-align: center;
        }

        .modal-confirm .modal-footer {
            border-top: none;
            padding: 1rem 2rem 2rem;
            justify-content: center;
            gap: 1rem;
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
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-box input {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .summary-cards {
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
            <h1>Mis Documentos</h1>
            <p>Gestiona y sube tus documentos personales y empresariales</p>
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

    <!-- Estado de documentos requeridos -->
    <?php if ($usuario_info && $usuario_info['estatus'] == 'pendiente'): ?>
        <div class="alert alert-warning">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-exclamation-triangle fa-2x me-3"></i>
                <div>
                    <h5 class="alert-heading mb-1">Documentos Pendientes de Aprobación</h5>
                    <p class="mb-0">Tu cuenta está pendiente de aprobación. Asegúrate de subir todos los documentos requeridos:</p>
                    <ul class="mb-0 mt-2">
                        <li><?php echo !empty($usuario_info['constancia_pdf']) ? '✓' : '✗'; ?> Constancia Fiscal</li>
                        <li><?php echo !empty($usuario_info['identificacion_pdf']) ? '✓' : '✗'; ?> Identificación Oficial</li>
                        <li><?php echo !empty($usuario_info['comprobante_domicilio']) ? '✓' : '✗'; ?> Comprobante de Domicilio</li>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Resumen de documentos -->
    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-icon icon-constancia">
                <i class="fa-solid fa-file-contract"></i>
            </div>
            <div class="summary-title">Constancias Fiscales</div>
            <div class="summary-value"><?php echo $documentos_por_tipo['constancia']; ?></div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon icon-identificacion">
                <i class="fa-solid fa-id-card"></i>
            </div>
            <div class="summary-title">Identificaciones</div>
            <div class="summary-value"><?php echo $documentos_por_tipo['identificacion']; ?></div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon icon-domicilio">
                <i class="fa-solid fa-house"></i>
            </div>
            <div class="summary-title">Comprobantes Domicilio</div>
            <div class="summary-value"><?php echo $documentos_por_tipo['domicilio']; ?></div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon icon-contrato">
                <i class="fa-solid fa-handshake"></i>
            </div>
            <div class="summary-title">Contratos</div>
            <div class="summary-value"><?php echo $documentos_por_tipo['contrato']; ?></div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon icon-otro">
                <i class="fa-solid fa-folder"></i>
            </div>
            <div class="summary-title">Otros Documentos</div>
            <div class="summary-value"><?php echo $documentos_por_tipo['otro']; ?></div>
        </div>
    </div>

    <!-- Formulario de subida -->
    <div class="upload-card">
        <div class="upload-header">
            <div class="upload-icon">
                <i class="fa-solid fa-cloud-upload-alt"></i>
            </div>
            <h3>Subir Nuevo Documento</h3>
        </div>
        
        <form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
            <!-- Campo oculto para identificar la acción -->
            <input type="hidden" name="subir_documento" value="1">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="tipo_documento" class="form-label required">Tipo de Documento</label>
                    <select class="form-select" id="tipo_documento" name="tipo_documento" required>
                        <option value="">Seleccionar tipo</option>
                        <option value="constancia">Constancia Fiscal</option>
                        <option value="identificacion">Identificación Oficial</option>
                        <option value="domicilio">Comprobante de Domicilio</option>
                        <option value="contrato">Contrato</option>
                        <option value="otro">Otro Documento</option>
                    </select>
                    <div class="form-text-help">Selecciona el tipo de documento que vas a subir</div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="descripcion" class="form-label">Descripción (Opcional)</label>
                    <input type="text" 
                           class="form-control" 
                           id="descripcion" 
                           name="descripcion" 
                           placeholder="Ej: Constancia situación fiscal 2024">
                    <div class="form-text-help">Breve descripción para identificar el documento</div>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label required">Archivo</label>
                <div class="file-upload-area" id="fileUploadArea">
                    <input type="file" 
                           id="archivo" 
                           name="archivo" 
                           accept=".pdf,.jpg,.jpeg,.png"
                           style="display: none;">
                    
                    <div class="file-upload-icon">
                        <i class="fa-solid fa-cloud-upload-alt"></i>
                    </div>
                    
                    <div class="file-upload-text">
                        <h4>Arrastra y suelta tu archivo aquí</h4>
                        <p>o haz clic para seleccionar</p>
                        <p class="small text-muted">Formatos permitidos: PDF, JPG, PNG (Máx. 10MB)</p>
                        <div class="file-upload-btn" id="selectFileBtn">
                            <i class="fa-solid fa-folder-open me-2"></i>Seleccionar Archivo
                        </div>
                    </div>
                </div>
                
                <div class="file-preview" id="filePreview">
                    <div class="file-info">
                        <div class="file-icon pdf">
                            <i class="fa-solid fa-file-pdf"></i>
                        </div>
                        <div class="file-details">
                            <div class="file-name" id="fileName">Nombre del archivo</div>
                            <div class="file-size" id="fileSize">Tamaño</div>
                        </div>
                        <button type="button" class="btn-remove-file" id="removeFileBtn">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn-submit-upload" id="submitBtn" disabled>
                <i class="fa-solid fa-upload me-2"></i>Subir Documento
            </button>
        </form>
    </div>

    <!-- Tabla de documentos -->
    <div class="documents-table-container">
        <div class="table-header">
            <h2>Documentos Subidos</h2>
            <div class="search-box">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="searchInput" placeholder="Buscar por nombre, tipo...">
            </div>
        </div>

        <?php if (empty($documentos)): ?>
            <div class="sin-documentos">
                <i class="fa-solid fa-folder-open"></i>
                <h3>No tienes documentos subidos</h3>
                <p class="text-muted">Comienza subiendo tu primer documento usando el formulario arriba.</p>
            </div>
        <?php else: ?>
            <table id="documentosTable" class="table table-hover">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Tamaño</th>
                        <th>Fecha de Subida</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($documentos as $doc): 
                        $file_ext = strtolower(pathinfo($doc['nombre_archivo'], PATHINFO_EXTENSION));
                        $tipo_text = [
                            'constancia' => 'Constancia Fiscal',
                            'identificacion' => 'Identificación',
                            'domicilio' => 'Comprobante Domicilio',
                            'contrato' => 'Contrato',
                            'otro' => 'Otro'
                        ];
                        $tamanio_formateado = formatBytes($doc['tamaño']);
                        $fecha_formateada = date('d/m/Y H:i', strtotime($doc['fecha_subida']));
                    ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($file_ext == 'pdf'): ?>
                                        <i class="fa-solid fa-file-pdf text-danger"></i>
                                    <?php elseif (in_array($file_ext, ['jpg', 'jpeg', 'png'])): ?>
                                        <i class="fa-solid fa-file-image text-success"></i>
                                    <?php else: ?>
                                        <i class="fa-solid fa-file text-secondary"></i>
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars($doc['nombre_archivo']); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="tipo-badge tipo-<?php echo $doc['tipo_documento']; ?>">
                                    <?php echo $tipo_text[$doc['tipo_documento']]; ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $tamanio_formateado; ?>
                            </td>
                            <td>
                                <?php echo $fecha_formateada; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="<?php echo htmlspecialchars($doc['ruta_archivo']); ?>" 
                                       target="_blank" 
                                       class="btn-action btn-view" 
                                       title="Ver documento">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    
                                    <a href="<?php echo htmlspecialchars($doc['ruta_archivo']); ?>" 
                                       download="<?php echo htmlspecialchars($doc['nombre_archivo']); ?>" 
                                       class="btn-action btn-download" 
                                       title="Descargar">
                                        <i class="fa-solid fa-download"></i>
                                    </a>
                                    
                                    <button type="button" 
                                            class="btn-action btn-delete" 
                                            title="Eliminar"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#confirmDeleteModal"
                                            data-doc-id="<?php echo $doc['id']; ?>"
                                            data-doc-name="<?php echo htmlspecialchars($doc['nombre_archivo']); ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de confirmación de eliminación -->
<div class="modal fade modal-confirm" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-trash-can me-2"></i>Confirmar Eliminación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <i class="fa-solid fa-triangle-exclamation fa-3x text-warning mb-3"></i>
                <h5 id="modalDocName">¿Estás seguro de que quieres eliminar este documento?</h5>
                <p class="text-muted">Esta acción no se puede deshacer. El documento será eliminado permanentemente.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="" id="deleteForm">
                    <input type="hidden" name="eliminar_documento" value="1">
                    <input type="hidden" name="documento_id" id="modalDocId" value="">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar Documento</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    // Función para formatear bytes
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    // Variables globales
    let selectedFile = null;

    // Inicializar DataTable
    $(document).ready(function() {
        const table = $('#documentosTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-MX.json'
            },
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Todos"]],
            order: [[3, 'desc']], // Ordenar por fecha descendente
            responsive: true,
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>'
        });

        // Buscar en la tabla
        $('#searchInput').on('keyup', function() {
            table.search(this.value).draw();
        });

        // Configurar modal de eliminación
        $('#confirmDeleteModal').on('show.bs.modal', function(event) {
            const button = $(event.relatedTarget);
            const docId = button.data('doc-id');
            const docName = button.data('doc-name');
            
            const modal = $(this);
            modal.find('#modalDocName').text(`¿Estás seguro de que quieres eliminar "${docName}"?`);
            modal.find('#modalDocId').val(docId);
        });
    });

    // Gestión de subida de archivos
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('archivo');
        const fileUploadArea = document.getElementById('fileUploadArea');
        const selectFileBtn = document.getElementById('selectFileBtn');
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const removeFileBtn = document.getElementById('removeFileBtn');
        const submitBtn = document.getElementById('submitBtn');
        const tipoDocumento = document.getElementById('tipo_documento');
        const uploadForm = document.getElementById('uploadForm');

        // Abrir selector de archivo al hacer clic en el área
        selectFileBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fileInput.click();
        });

        fileUploadArea.addEventListener('click', function(e) {
            if (e.target !== selectFileBtn && !selectFileBtn.contains(e.target)) {
                fileInput.click();
            }
        });

        // Drag & Drop
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.add('dragover');
        });

        fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('dragover');
        });

        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('dragover');
            
            if (e.dataTransfer.files.length) {
                handleFileSelect(e.dataTransfer.files[0]);
            }
        });

        // Cambio en el input de archivo
        fileInput.addEventListener('change', function(e) {
            if (this.files.length) {
                handleFileSelect(this.files[0]);
            }
        });

        // Remover archivo seleccionado
        removeFileBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            resetFileInput();
        });

        // Validar formulario antes de enviar
        uploadForm.addEventListener('submit', function(e) {
            if (!selectedFile || !tipoDocumento.value) {
                e.preventDefault();
                alert('Por favor, selecciona un archivo y el tipo de documento.');
                return false;
            }

            // Validar tamaño máximo (10MB)
            if (selectedFile.size > 10 * 1024 * 1024) {
                e.preventDefault();
                alert('El archivo es demasiado grande. El tamaño máximo es 10MB.');
                return false;
            }

            // Validar tipo de archivo
            const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
            if (!allowedTypes.includes(selectedFile.type)) {
                e.preventDefault();
                alert('Tipo de archivo no permitido. Solo se aceptan PDF, JPG y PNG.');
                return false;
            }

            // Mostrar mensaje de carga
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Subiendo...';
            submitBtn.disabled = true;
            
            return true;
        });

        // Habilitar/deshabilitar botón de enviar
        function updateSubmitButton() {
            submitBtn.disabled = !(selectedFile && tipoDocumento.value);
            if (!submitBtn.disabled) {
                submitBtn.innerHTML = '<i class="fa-solid fa-upload me-2"></i>Subir Documento';
            }
        }

        tipoDocumento.addEventListener('change', updateSubmitButton);

        // Manejar selección de archivo
        function handleFileSelect(file) {
            selectedFile = file;
            
            // Mostrar información del archivo
            fileName.textContent = file.name;
            fileSize.textContent = formatBytes(file.size);
            
            // Cambiar ícono según tipo de archivo
            const fileIcon = filePreview.querySelector('.file-icon');
            fileIcon.className = 'file-icon';
            
            if (file.type === 'application/pdf') {
                fileIcon.classList.add('pdf');
                fileIcon.innerHTML = '<i class="fa-solid fa-file-pdf"></i>';
            } else if (file.type.includes('image')) {
                fileIcon.classList.add('image');
                fileIcon.innerHTML = '<i class="fa-solid fa-file-image"></i>';
            } else {
                fileIcon.innerHTML = '<i class="fa-solid fa-file"></i>';
            }
            
            // Mostrar preview
            filePreview.classList.add('active');
            
            // Actualizar botón de enviar
            updateSubmitButton();
        }

        // Resetear input de archivo
        function resetFileInput() {
            selectedFile = null;
            fileInput.value = '';
            filePreview.classList.remove('active');
            updateSubmitButton();
        }

        // Formatear tamaño de archivo
        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }
    });
</script>

</body>
</html>

<?php
// Función para formatear bytes (versión PHP)
function formatBytes($bytes, $decimals = 2) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $dm = $decimals < 0 ? 0 : $decimals;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return number_format($bytes / pow($k, $i), $dm) . ' ' . $sizes[$i];
}
?>