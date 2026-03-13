<?php
session_start();
include __DIR__ . '/../config/db.php';

// 1. SEGURIDAD: Solo el DUEÑO puede entrar aquí
if (!isset($_SESSION['user_rol']) || ($_SESSION['user_rol'] !== 'dueño' && $_SESSION['user_rol'] !== 'dueno')) {
    // Si es admin, lo mandamos al dashboard; si es cliente/anon, a la tienda
    if (isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === 'admin') {
        header('Location: index.php');
    } else {
        header('Location: ../index.php');
    }
    exit;
}

$mensaje = '';

// 2. ACTUALIZAR ROL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['rol'])) {
    $id_user = (int)$_POST['user_id'];
    $nuevo_rol = $_POST['rol'];
    
    // Evitar cambiarse el rol a uno mismo
    if ($id_user != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("UPDATE usuarios SET rol = ? WHERE id = ?");
        if ($stmt->execute([$nuevo_rol, $id_user])) {
            // Redirección para evitar reenvío de formulario (PRG)
            header('Location: usuarios.php?msg=ok_rol');
            exit;
        }
    }
}

// 3. ACTUALIZAR ESTATUS DEL USUARIO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['estatus'])) {
    $id_user = (int)$_POST['user_id'];
    $nuevo_estatus = $_POST['estatus'];
    
    $stmt = $pdo->prepare("UPDATE usuarios SET estatus = ? WHERE id = ?");
    if ($stmt->execute([$nuevo_estatus, $id_user])) {
        header('Location: usuarios.php?msg=ok_estatus');
        exit;
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'ok_rol') {
        $mensaje = "Rol de usuario actualizado correctamente.";
    } elseif ($_GET['msg'] == 'ok_estatus') {
        $mensaje = "Estado de usuario actualizado correctamente.";
    }
}

// 4. PARÁMETROS DE FILTRADO
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtro_estatus = isset($_GET['estatus']) ? $_GET['estatus'] : 'todos';
$mes_actual = date('m');
$anio_actual = date('Y');

// 5. CONSULTA BASE
$query = "SELECT *, 
          constancia_pdf, 
          identificacion_pdf, 
          comprobante_domicilio 
          FROM usuarios WHERE 1=1";
$params = [];

// Aplicar búsqueda
if ($busqueda) {
    $query .= " AND (nombre LIKE ? OR email LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

// Aplicar filtro de estado
if ($filtro_estatus !== 'todos') {
    if ($filtro_estatus === 'pendiente') {
        $query .= " AND estatus = 'pendiente'";
    } elseif ($filtro_estatus === 'activo') {
        $query .= " AND estatus = 'activo'";
    } elseif ($filtro_estatus === 'inactivo') {
        $query .= " AND estatus = 'inactivo'";
    }
}

$query .= " ORDER BY id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. OBTENER DATOS ADICIONALES: COMPRAS MENSUALES Y DOCUMENTOS
foreach ($usuarios as &$usuario) {
    $user_id = $usuario['id'];
    
    // Obtener compras del mes actual (ventas del usuario como cliente)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_compras_mes, SUM(total) as monto_total_mes FROM ventas WHERE usuario_id = ? AND MONTH(fecha) = ? AND YEAR(fecha) = ?");
    $stmt->execute([$user_id, $mes_actual, $anio_actual]);
    $compras_mensuales = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $usuario['total_compras_mes'] = $compras_mensuales['total_compras_mes'] ?? 0;
    $usuario['monto_total_mes'] = $compras_mensuales['monto_total_mes'] ?? 0;
    
    // Obtener último pedido del mes
    $stmt = $pdo->prepare("SELECT fecha, estatus FROM ventas WHERE usuario_id = ? AND MONTH(fecha) = ? AND YEAR(fecha) = ? ORDER BY fecha DESC LIMIT 1");
    $stmt->execute([$user_id, $mes_actual, $anio_actual]);
    $ultimo_pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $usuario['ultimo_pedido'] = $ultimo_pedido['fecha'] ?? 'Sin pedidos este mes';
    $usuario['estatus_ultimo'] = $ultimo_pedido['estatus'] ?? 'N/A';
    
    // Determinar estado del usuario (basado en campo en BD)
    if (!isset($usuario['estatus']) || empty($usuario['estatus'])) {
        // Si no existe campo estatus, establecer por defecto
        $usuario['estatus'] = 'pendiente';
    }
    
    // ============ OBTENER PDFs DEL USUARIO - CORREGIDO ============
    $usuario['pdfs_subidos'] = [];
    
    // 1. Documentos de campos específicos en tabla usuarios
    $documentos_campos = [
        ['tipo' => 'constancia', 'nombre' => 'Constancia Fiscal', 'campo' => 'constancia_pdf'],
        ['tipo' => 'identificacion', 'nombre' => 'Identificación Oficial', 'campo' => 'identificacion_pdf'],
        ['tipo' => 'domicilio', 'nombre' => 'Comprobante de Domicilio', 'campo' => 'comprobante_domicilio']
    ];
    
    foreach ($documentos_campos as $doc_info) {
        $campo = $doc_info['campo'];
        if (!empty($usuario[$campo]) && file_exists(__DIR__ . '/../' . $usuario[$campo])) {
            $nombre_archivo = basename($usuario[$campo]);
            $usuario['pdfs_subidos'][] = [
                'nombre' => $doc_info['nombre'] . ' - ' . $nombre_archivo,
                'ruta' => $usuario[$campo],
                'tipo' => $doc_info['tipo'],
                'tamanio' => filesize(__DIR__ . '/../' . $usuario[$campo])
            ];
        }
    }
    
    // 2. Documentos de la tabla documentos_usuario (si existe)
    try {
        // Verificar si la tabla existe
        $stmt_check = $pdo->query("SHOW TABLES LIKE 'documentos_usuario'");
        if ($stmt_check->rowCount() > 0) {
            $stmt_docs = $pdo->prepare("
                SELECT nombre_archivo, ruta_archivo, tipo_documento, tamaño 
                FROM documentos_usuario 
                WHERE usuario_id = ? 
                ORDER BY fecha_subida DESC
            ");
            $stmt_docs->execute([$user_id]);
            $docs_tabla = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($docs_tabla as $doc) {
                if (file_exists(__DIR__ . '/../' . $doc['ruta_archivo'])) {
                    $usuario['pdfs_subidos'][] = [
                        'nombre' => $doc['nombre_archivo'],
                        'ruta' => $doc['ruta_archivo'],
                        'tipo' => $doc['tipo_documento'],
                        'tamanio' => $doc['tamaño']
                    ];
                }
            }
        }
    } catch (Exception $e) {
        // Si la tabla no existe, continuar sin errores
        error_log("Error al obtener documentos del usuario: " . $e->getMessage());
    }
    // ============ FIN CORRECCIÓN ============
    
    // Asignar clase CSS según estado
    if ($usuario['estatus'] === 'activo') {
        $usuario['estatus_color'] = 'badge-activo';
        $usuario['estatus_icon'] = 'fa-circle-check';
    } elseif ($usuario['estatus'] === 'pendiente') {
        $usuario['estatus_color'] = 'badge-pendiente';
        $usuario['estatus_icon'] = 'fa-clock';
    } elseif ($usuario['estatus'] === 'inactivo') {
        $usuario['estatus_color'] = 'badge-inactivo';
        $usuario['estatus_icon'] = 'fa-user-slash';
    }
    
    // Obtener fecha de último acceso
    if (isset($usuario['ultimo_login'])) {
        $usuario['ultimo_acceso_formatted'] = date('d/m/Y H:i', strtotime($usuario['ultimo_login']));
    } else {
        $usuario['ultimo_acceso_formatted'] = 'Nunca';
    }
}
unset($usuario);

// 7. CONTAR USUARIOS POR ESTADO
$usuarios_pendientes = array_filter($usuarios, function($u) { return $u['estatus'] === 'pendiente'; });
$usuarios_activos = array_filter($usuarios, function($u) { return $u['estatus'] === 'activo'; });
$usuarios_inactivos = array_filter($usuarios, function($u) { return $u['estatus'] === 'inactivo'; });

// 8. OBTENER ESTADÍSTICAS MENSUALES
$stmt = $pdo->prepare("SELECT COUNT(*) as total_compras_mes, SUM(total) as monto_total_mes FROM ventas WHERE MONTH(fecha) = ? AND YEAR(fecha) = ?");
$stmt->execute([$mes_actual, $anio_actual]);
$estadisticas_mensuales = $stmt->fetch(PDO::FETCH_ASSOC);

$total_compras_mes = $estadisticas_mensuales['total_compras_mes'] ?? 0;
$monto_total_mes = $estadisticas_mensuales['monto_total_mes'] ?? 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios | NGS Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root { 
            --primary: #007bff; 
            --primary-hover: #0056b3;
            --bg-body: #f3f4f6; 
            --text-dark: #1f2937; 
            --text-gray: #6b7280; 
            --green-btn: #10b981; 
            --white: #ffffff;
        }

        body { 
            margin: 0; 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg-body); 
            display: flex; 
            height: 100vh; 
            overflow: hidden; 
        }
        
        /* SIDEBAR */
        .sidebar { 
            width: 260px; 
            background: var(--white); 
            border-right: 1px solid #e5e7eb; 
            display: flex; 
            flex-direction: column; 
            padding: 25px 20px; 
            flex-shrink: 0; 
        }

        .sidebar h2 { 
            font-size: 1.3rem; 
            font-weight: 700;
            margin-bottom: 35px; 
            color: var(--text-dark); 
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .menu-item { 
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px; 
            margin-bottom: 8px; 
            color: var(--text-gray); 
            text-decoration: none; 
            border-radius: 10px; 
            font-weight: 500; 
            transition: all 0.3s ease; 
        }

        .menu-item i { width: 20px; font-size: 1.1rem; }

        .menu-item:hover { 
            background-color: #f9fafb; 
            color: var(--primary); 
        }

        .menu-item.active { 
            background-color: #eef6ff; 
            color: var(--primary); 
            font-weight: 700; 
        }

        .logout-btn { 
            margin-top: auto; 
            color: #ef4444; 
            font-weight: 600;
        }

        /* CONTENIDO PRINCIPAL */
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }

        /* ALERTAS */
        .alert-success { 
            background: #dcfce7; color: #166534; 
            padding: 15px; border-radius: 12px; 
            margin-bottom: 25px; border: 1px solid #bbf7d0; 
            font-size: 0.95rem; font-weight: 500;
            display: flex; align-items: center; gap: 10px;
        }

        /* ACTION BAR */
        .action-bar { 
            background: var(--white); padding: 20px; 
            border-radius: 12px; border: 1px solid #e5e7eb; 
            margin-bottom: 30px; display: flex; 
            justify-content: space-between; align-items: center; 
            gap: 15px; 
        }
        
        .search-form { flex: 1; display: flex; max-width: 450px; position: relative; }
        .search-form i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-gray); }
        .search-input { 
            width: 100%; padding: 12px 15px 12px 40px; 
            border: 1px solid #e5e7eb; border-radius: 10px; 
            outline: none; font-family: inherit; transition: 0.2s;
        }
        .search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,123,255,0.1); }
        
        .info-text { color: var(--text-gray); font-size: 0.95rem; font-weight: 500; }
        .info-text b { color: var(--text-dark); font-weight: 700; }

        /* Filtros de estatus */
        .filtro-estatus {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn-filtro {
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: white;
            color: var(--text-gray);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
        }
        
        .btn-filtro:hover {
            background: #f9fafb;
        }
        
        .btn-filtro.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .btn-filtro.active.pendiente { background: #f59e0b; border-color: #f59e0b; }
        .btn-filtro.active.activo { background: #10b981; border-color: #10b981; }
        .btn-filtro.active.inactivo { background: #6b7280; border-color: #6b7280; }

        /* TABLA */
        .table-container { 
            background: var(--white); border-radius: 16px; 
            border: 1px solid #e5e7eb; overflow: hidden; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); 
        }
        
        table { width: 100%; border-collapse: collapse; }
        
        th { 
            background: #f8fafc; text-align: left; 
            padding: 18px 25px; font-size: 0.8rem; 
            color: var(--text-gray); text-transform: uppercase; 
            letter-spacing: 0.05em; border-bottom: 1px solid #e5e7eb; 
            font-weight: 700; 
        }
        
        td { padding: 20px 25px; border-bottom: 1px solid #f1f5f9; color: var(--text-dark); vertical-align: middle; font-size: 0.95rem; }
        
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #fcfdfe; }
        
        .user-cell { 
            display: flex; 
            align-items: center; 
            gap: 15px; 
            cursor: pointer; 
            transition: all 0.2s;
        }
        
        .user-cell:hover { 
            color: var(--primary); 
        }
        
        .user-avatar { 
            width: 42px; height: 42px; 
            background: #eef6ff; color: var(--primary); 
            border-radius: 12px; display: flex; 
            align-items: center; justify-content: center; 
            font-weight: 700; font-size: 1.1rem;
            border: 1px solid #dbeafe;
            transition: all 0.2s;
        }
        
        .user-cell:hover .user-avatar {
            background: var(--primary);
            color: white;
            transform: scale(1.05);
        }
        
        /* Badges de Rol */
        .badge { 
            padding: 5px 12px; border-radius: 20px; 
            font-size: 0.75rem; font-weight: 800; 
            text-transform: uppercase; display: inline-flex;
            align-items: center; gap: 5px;
            white-space: nowrap;
        }
        .badge-dueño { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
        .badge-admin { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .badge-cliente { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }
        
        /* Badges de Estado */
        .badge-activo { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .badge-pendiente { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .badge-inactivo { background: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db; }
        
        /* Compras badge */
        .badge-compras {
            background: #f0f9ff;
            color: #0369a1;
            border: 1px solid #7dd3fc;
            font-weight: 600;
        }
        
        /* Formulario cambio rol */
        .role-form { display: flex; align-items: center; gap: 10px; }
        
        .role-select { 
            padding: 8px 12px; border-radius: 8px; 
            border: 1px solid #d1d5db; background: #fff; 
            font-size: 0.9rem; font-weight: 500;
            outline: none; cursor: pointer; transition: 0.2s; 
        }
        .role-select:focus { border-color: var(--primary); }
        
        .btn-save { 
            background: var(--primary); color: white; 
            border: none; padding: 8px 15px; 
            border-radius: 8px; cursor: pointer; 
            font-size: 0.85rem; font-weight: 700; 
            transition: 0.3s;
            display: flex; align-items: center; gap: 5px;
        }
        .btn-save:hover { background: var(--primary-hover); transform: translateY(-1px); }
        
        .self-label { 
            font-size: 0.8rem; color: var(--text-gray); 
            font-weight: 600; background: #f1f5f9; 
            padding: 6px 12px; border-radius: 8px; 
            display: inline-flex; align-items: center; gap: 5px;
        }
        
        /* Estadísticas rápidas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-content h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
        }
        
        .stat-content p {
            margin: 5px 0 0;
            font-size: 0.85rem;
            color: var(--text-gray);
            font-weight: 500;
        }
        
        /* Indicador del mes actual */
        .mes-indicator {
            background: var(--primary);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 15px;
        }
        
        /* MODAL ESTILOS */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 700px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.25rem;
            color: var(--text-dark);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-gray);
            cursor: pointer;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .close-modal:hover {
            background-color: #f3f4f6;
            color: var(--text-dark);
        }

        .modal-body {
            padding: 25px;
        }

        .user-info-grid {
            display: grid;
            gap: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-label {
            font-size: 0.85rem;
            color: var(--text-gray);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .info-value {
            font-size: 1rem;
            color: var(--text-dark);
            font-weight: 500;
            padding: 8px 12px;
            background-color: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .info-value.badge {
            display: inline-flex;
            width: fit-content;
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-secondary {
            background-color: #f3f4f6;
            color: var(--text-dark);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-secondary:hover {
            background-color: #e5e7eb;
        }
        
        /* Compras info en tabla */
        .compras-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .compras-count {
            font-weight: 700;
            color: var(--text-dark);
        }
        
        .compras-monto {
            font-size: 0.85rem;
            color: var(--text-gray);
        }
        
        /* Formulario cambio estado */
        .estatus-form {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
        }
        
        .estatus-select {
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            background: #fff;
            font-size: 0.8rem;
            font-weight: 500;
            outline: none;
            cursor: pointer;
            transition: 0.2s;
        }
        
        .estatus-select:focus {
            border-color: var(--primary);
        }
        
        .btn-estatus {
            background: #6b7280;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
            transition: 0.3s;
        }
        
        .btn-estatus:hover {
            background: #4b5563;
        }
        
        /* Sección de PDFs */
        .pdfs-section {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #e5e7eb;
        }
        
        .pdfs-section h4 {
            margin: 0 0 20px 0;
            font-size: 1.1rem;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .pdfs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
        }
        
        .pdf-card {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 15px;
            transition: all 0.2s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .pdf-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border-color: var(--primary);
        }
        
        .pdf-icon {
            width: 40px;
            height: 40px;
            background: #fee2e2;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            color: #dc2626;
            font-size: 1.3rem;
        }
        
        .pdf-name {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-dark);
            word-break: break-all;
            margin-bottom: 5px;
        }
        
        .pdf-size {
            font-size: 0.7rem;
            color: var(--text-gray);
        }
        
        .no-pdfs {
            text-align: center;
            padding: 30px;
            color: var(--text-gray);
            font-style: italic;
            background: #f9fafb;
            border-radius: 12px;
            grid-column: 1 / -1;
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <h2> ADMIN PANEL</h2>
        
        <nav>
            <a href="index.php" class="menu-item">
                <i class="fa-solid fa-house"></i> Inicio
            </a>
            <a href="productos.php" class="menu-item">
                <i class="fa-solid fa-box-open"></i> Productos
            </a>
            <a href="ventas.php" class="menu-item">
                <i class="fa-solid fa-chart-line"></i> Ventas
            </a>
            <a href="pedidos.php" class="menu-item">
                <i class="fa-solid fa-truck"></i> Pedidos
            </a>
            <a href="usuarios.php" class="menu-item active">
                <i class="fa-solid fa-users-gear"></i> Usuarios
            </a>

            <a href="../index.php" class="menu-item" style="margin-top: 20px; border-top: 1px solid #f3f4f6; padding-top: 20px;">
                 Regresar a Tienda
            </a>
        </nav>

        <a href="../logout.php" class="menu-item logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
        </a>
    </aside>

    <main class="main-content">
        <h2 style="margin-top:0; margin-bottom:25px; font-weight:700; color:var(--text-dark);">Directorio de Usuarios</h2>
        
        <!-- Indicador del mes -->
        <div class="mes-indicator">
            <i class="fa-solid fa-calendar"></i>
            <?php 
                $meses = [
                    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                ];
                echo "Estadísticas de " . $meses[(int)$mes_actual] . " " . $anio_actual;
            ?>
        </div>
        
        <?php if($mensaje): ?>
            <div class="alert-success">
                <i class="fa-solid fa-circle-check"></i> <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <!-- Estadísticas rápidas -->
        <div class="stats-grid">
            <div class="stat-card" onclick="filtrarEstatus('todos')">
                <div class="stat-icon" style="background: #eef6ff; color: var(--primary);">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo count($usuarios); ?></h3>
                    <p>Usuarios Totales</p>
                </div>
            </div>
            
            <div class="stat-card" onclick="filtrarEstatus('pendiente')">
                <div class="stat-icon" style="background: #fef3c7; color: #f59e0b;">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo count($usuarios_pendientes); ?></h3>
                    <p>Usuarios Pendientes</p>
                </div>
            </div>
            
            <div class="stat-card" onclick="filtrarEstatus('activo')">
                <div class="stat-icon" style="background: #dcfce7; color: #10b981;">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_compras_mes; ?></h3>
                    <p>Compras del Mes</p>
                </div>
            </div>
            
            <div class="stat-card" onclick="filtrarEstatus('inactivo')">
                <div class="stat-icon" style="background: #f3f4f6; color: #6b7280;">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>
                <div class="stat-content">
                    <h3>$<?php echo number_format($monto_total_mes, 2); ?></h3>
                    <p>Monto del Mes</p>
                </div>
            </div>
        </div>

        <div class="action-bar">
            <form class="search-form" method="GET" id="searchForm">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="q" class="search-input" placeholder="Buscar por nombre o email..." value="<?php echo htmlspecialchars($busqueda); ?>">
                <input type="hidden" name="estatus" id="estatusInput" value="<?php echo htmlspecialchars($filtro_estatus); ?>">
            </form>

            <div class="filtro-estatus">
                <button class="btn-filtro <?php echo $filtro_estatus === 'todos' ? 'active' : ''; ?>" onclick="filtrarEstatus('todos')">
                    <i class="fa-solid fa-users"></i> Todos
                </button>
                <button class="btn-filtro pendiente <?php echo $filtro_estatus === 'pendiente' ? 'active' : ''; ?>" onclick="filtrarEstatus('pendiente')">
                    <i class="fa-solid fa-clock"></i> Pendientes
                </button>
                <button class="btn-filtro activo <?php echo $filtro_estatus === 'activo' ? 'active' : ''; ?>" onclick="filtrarEstatus('activo')">
                    <i class="fa-solid fa-circle-check"></i> Activos
                </button>
                <button class="btn-filtro inactivo <?php echo $filtro_estatus === 'inactivo' ? 'active' : ''; ?>" onclick="filtrarEstatus('inactivo')">
                    <i class="fa-solid fa-user-slash"></i> Inactivos
                </button>
            </div>
            
            <span class="info-text">
                Mostrando: <b>
                <?php 
                    if ($filtro_estatus === 'todos') echo 'Todos';
                    elseif ($filtro_estatus === 'pendiente') echo 'Pendientes';
                    elseif ($filtro_estatus === 'activo') echo 'Activos';
                    elseif ($filtro_estatus === 'inactivo') echo 'Inactivos';
                ?>
                </b>
            </span>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Correo Electrónico</th>
                        <th>Compras del Mes</th>
                        <th>Estado</th>
                        <th>Rol Actual</th>
                        <th>Acción / Cambiar Rol</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($usuarios) > 0): ?>
                        <?php foreach($usuarios as $u): ?>
                        <tr>
                            <td>
                                <div class="user-cell" onclick="openUserModal(<?php echo htmlspecialchars(json_encode($u)); ?>)">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($u['nombre'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:700; color:var(--text-dark);"><?php echo htmlspecialchars($u['nombre']); ?></div>
                                        <div style="font-size:0.75rem; color:var(--text-gray); font-weight:600;">ID: #<?php echo $u['id']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="color:var(--text-gray); font-weight:500;">
                                <i class="fa-regular fa-envelope"></i> <?php echo htmlspecialchars($u['email']); ?>
                            </td>
                            <td>
                                <div class="compras-info">
                                    <?php if($u['total_compras_mes'] > 0): ?>
                                        <span class="compras-count">
                                            <i class="fa-solid fa-shopping-cart"></i> <?php echo $u['total_compras_mes']; ?> compras
                                        </span>
                                        <span class="compras-monto">
                                            Total: $<?php echo number_format($u['monto_total_mes'], 2); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-gray); font-style: italic;">
                                            Sin compras este mes
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php echo $u['estatus_color']; ?>">
                                    <i class="fa-solid <?php echo $u['estatus_icon']; ?>"></i> 
                                    <?php echo ucfirst($u['estatus']); ?>
                                </span>
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" class="estatus-form">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <select name="estatus" class="estatus-select" onchange="this.form.submit()">
                                        <option value="pendiente" <?php if($u['estatus']=='pendiente') echo 'selected'; ?>>Pendiente</option>
                                        <option value="activo" <?php if($u['estatus']=='activo') echo 'selected'; ?>>Activo</option>
                                        <option value="inactivo" <?php if($u['estatus']=='inactivo') echo 'selected'; ?>>Inactivo</option>
                                    </select>
                                </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                    $bClass = 'badge-cliente';
                                    $icon = 'fa-user';
                                    if($u['rol']=='admin') { $bClass = 'badge-admin'; $icon = 'fa-user-tie'; }
                                    if($u['rol']=='dueño' || $u['rol']=='dueno') { $bClass = 'badge-dueño'; $icon = 'fa-crown'; }
                                ?>
                                <span class="badge <?php echo $bClass; ?>">
                                    <i class="fa-solid <?php echo $icon; ?>"></i> <?php echo ucfirst($u['rol']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" class="role-form">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <select name="rol" class="role-select">
                                            <option value="cliente" <?php if($u['rol']=='cliente') echo 'selected'; ?>>Cliente</option>
                                            <option value="admin" <?php if($u['rol']=='admin') echo 'selected'; ?>>Admin</option>
                                            <option value="dueño" <?php if($u['rol']=='dueño' || $u['rol']=='dueno') echo 'selected'; ?>>Dueño</option>
                                        </select>
                                        <button type="submit" class="btn-save">
                                            <i class="fa-solid fa-floppy-disk"></i> Actualizar
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="self-label">
                                        <i class="fa-solid fa-user-check"></i> Tu cuenta actual
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:60px; color:var(--text-gray);">
                                <i class="fa-solid fa-user-slash" style="font-size: 2.5rem; display: block; margin-bottom: 15px; opacity: 0.2;"></i>
                                No se encontraron usuarios registrados.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- MODAL DE DETALLES DE USUARIO -->
    <div id="userModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Detalles del Usuario</h3>
                <button class="close-modal" onclick="closeUserModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="user-info-grid">
                    <div class="info-item">
                        <span class="info-label">ID de Usuario</span>
                        <div class="info-value" id="modal-id"></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Nombre Completo</span>
                        <div class="info-value" id="modal-nombre"></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Correo Electrónico</span>
                        <div class="info-value" id="modal-email"></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Rol Actual</span>
                        <div class="info-value badge" id="modal-rol"></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Estado del Usuario</span>
                        <div class="info-value badge" id="modal-estatus"></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fecha de Registro</span>
                        <div class="info-value" id="modal-fecha"></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Compras del Mes</span>
                        <div class="info-value" id="modal-compras-mes"></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Monto del Mes</span>
                        <div class="info-value" id="modal-monto-mes"></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Último Pedido</span>
                        <div class="info-value" id="modal-ultimo-pedido"></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Estado Último Pedido</span>
                        <div class="info-value" id="modal-estatus-pedido"></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Último Acceso</span>
                        <div class="info-value" id="modal-ultimo-acceso"></div>
                    </div>
                </div>
                
                <!-- Sección de PDFs -->
                <div class="pdfs-section">
                    <h4>
                        <i class="fa-solid fa-file-pdf"></i>
                        Documentos PDF Subidos
                    </h4>
                    <div id="modal-pdfs" class="pdfs-grid">
                        <!-- Los PDFs se cargarán aquí dinámicamente -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeUserModal()">Cerrar</button>
            </div>
        </div>
    </div>

    <script>
        // Función para formatear bytes para mostrar tamaño
        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }
        
        // Función para filtrar por estado
        function filtrarEstatus(estatus) {
            document.getElementById('estatusInput').value = estatus;
            document.getElementById('searchForm').submit();
        }

        // Función para abrir modal con detalles del usuario
        function openUserModal(userData) {
            // Llenar los campos del modal con los datos del usuario
            document.getElementById('modal-id').textContent = '#' + userData.id;
            document.getElementById('modal-nombre').textContent = userData.nombre || 'No disponible';
            document.getElementById('modal-email').textContent = userData.email || 'No disponible';
            document.getElementById('modal-fecha').textContent = userData.fecha_registro || 'No disponible';
            
            // Rol con badge
            const rolBadge = document.getElementById('modal-rol');
            rolBadge.textContent = userData.rol ? userData.rol.charAt(0).toUpperCase() + userData.rol.slice(1) : 'No disponible';
            rolBadge.className = 'info-value badge';
            if (userData.rol === 'admin') rolBadge.classList.add('badge-admin');
            else if (userData.rol === 'dueño' || userData.rol === 'dueno') rolBadge.classList.add('badge-dueño');
            else rolBadge.classList.add('badge-cliente');
            
            // Estatus con badge
            const estatusBadge = document.getElementById('modal-estatus');
            estatusBadge.textContent = userData.estatus ? userData.estatus.charAt(0).toUpperCase() + userData.estatus.slice(1) : 'No disponible';
            estatusBadge.className = 'info-value badge';
            if (userData.estatus_color) estatusBadge.classList.add(userData.estatus_color);
            
            // Compras del mes
            document.getElementById('modal-compras-mes').textContent = userData.total_compras_mes + ' compras este mes';
            document.getElementById('modal-monto-mes').textContent = '$' + (userData.monto_total_mes ? parseFloat(userData.monto_total_mes).toFixed(2) : '0.00');
            document.getElementById('modal-ultimo-pedido').textContent = userData.ultimo_pedido || 'Sin pedidos este mes';
            document.getElementById('modal-estatus-pedido').textContent = userData.estatus_ultimo || 'N/A';
            document.getElementById('modal-ultimo-acceso').textContent = userData.ultimo_acceso_formatted || 'Nunca';
            
            // Cargar PDFs del usuario
            const pdfsContainer = document.getElementById('modal-pdfs');
            pdfsContainer.innerHTML = '';
            
            if (userData.pdfs_subidos && userData.pdfs_subidos.length > 0) {
                userData.pdfs_subidos.forEach(pdf => {
                    const pdfNombre = pdf.nombre || pdf;
                    const pdfRuta = pdf.ruta || pdf;
                    const pdfTipo = pdf.tipo || 'documento';
                    const pdfTamanio = pdf.tamanio ? formatBytes(pdf.tamanio) : 'Tamaño no disponible';
                    
                    const pdfCard = document.createElement('a');
                    pdfCard.href = pdfRuta;
                    pdfCard.target = '_blank';
                    pdfCard.className = 'pdf-card';
                    pdfCard.title = pdfNombre;
                    
                    const pdfIcon = document.createElement('div');
                    pdfIcon.className = 'pdf-icon';
                    pdfIcon.innerHTML = '<i class="fa-solid fa-file-pdf"></i>';
                    
                    const pdfName = document.createElement('div');
                    pdfName.className = 'pdf-name';
                    pdfName.textContent = pdfNombre.length > 30 ? pdfNombre.substring(0, 27) + '...' : pdfNombre;
                    
                    const pdfSize = document.createElement('div');
                    pdfSize.className = 'pdf-size';
                    pdfSize.textContent = pdfTipo.charAt(0).toUpperCase() + pdfTipo.slice(1) + ' • ' + pdfTamanio;
                    
                    pdfCard.appendChild(pdfIcon);
                    pdfCard.appendChild(pdfName);
                    pdfCard.appendChild(pdfSize);
                    pdfsContainer.appendChild(pdfCard);
                });
            } else {
                const noPdfs = document.createElement('div');
                noPdfs.className = 'no-pdfs';
                noPdfs.innerHTML = '<i class="fa-solid fa-file-circle-exclamation" style="font-size: 1.5rem; margin-bottom: 10px; opacity: 0.3;"></i><br>No hay documentos PDF subidos por este usuario.';
                pdfsContainer.appendChild(noPdfs);
            }
            
            // Mostrar el modal
            document.getElementById('userModal').style.display = 'flex';
            
            // Prevenir scroll en el body
            document.body.style.overflow = 'hidden';
        }

        function closeUserModal() {
            document.getElementById('userModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Cerrar modal al hacer clic fuera del contenido
        document.getElementById('userModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUserModal();
            }
        });

        // Cerrar modal con la tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeUserModal();
            }
        });

        // Búsqueda automática al escribir
        let searchTimeout;
        document.querySelector('.search-input').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                document.getElementById('searchForm').submit();
            }, 500);
        });
    </script>
</body>
</html>