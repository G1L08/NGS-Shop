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

// Procesar el formulario de contacto
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asunto = trim($_POST['asunto'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');
    $tipo = $_POST['tipo'] ?? 'consulta';
    $pedido_id = !empty($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : null;
    
    if (empty($asunto) || empty($mensaje)) {
        $error_message = 'Por favor completa todos los campos obligatorios.';
    } else {
        // Dentro del bloque else, después de validar que no hay campos vacíos
$destinatario = 'soporte@ngs.com';
$remitente = $_SESSION['user_email'] ?? 'noreply@tuweb.com'; // Fallback si no hay sesión

$headers = "From: " . $remitente . "\r\n";
$headers .= "Reply-To: " . $remitente . "\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

// Cuerpo del correo más detallado
$cuerpo = "Has recibido un nuevo mensaje de contacto:\n\n";
$cuerpo .= "Tipo: " . ucfirst($tipo) . "\n";
$cuerpo .= "Pedido ID: " . ($pedido_id ?? 'N/A') . "\n";
$cuerpo .= "Asunto: $asunto\n";
$cuerpo .= "Mensaje:\n$mensaje\n";

if (mail($destinatario, "Contacto: $asunto", $cuerpo, $headers)) {
    $success_message = 'Tu mensaje ha sido enviado correctamente.';
} else {
    $error_message = 'Hubo un error al enviar el correo. Inténtalo más tarde.';
}
    }
}

// Obtener pedidos del usuario para el selector (opcional)
$stmt_pedidos = $pdo->prepare("SELECT id, total, fecha FROM ventas WHERE usuario_id = ? ORDER BY fecha DESC LIMIT 5");
$stmt_pedidos->execute([$usuario_id]);
$pedidos_recientes = $stmt_pedidos->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servicio al Cliente | NGS Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ngs-blue: rgb(6 19 37 / 95%);
            --ngs-accent: #0d6efd;
            --bg-light: #f8f9fa;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
        }

        body {
            background-color: var(--bg-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Banner de perfil */
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
            text-align: center;
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

        /* Contenedor principal */
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
            background-color: var(--success);
            color: white;
        }

        .btn-store:hover {
            background-color: #218838;
            color: white;
            transform: translateY(-2px);
        }

        /* Grid principal */
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        /* Tarjetas de información */
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #eaeaea;
            height: fit-content;
        }

        .info-card h2 {
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 0.8rem;
        }

        .info-card h2 i {
            color: var(--ngs-accent);
        }

        /* Opciones de contacto */
        .contact-options {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        .contact-option {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 8px;
            background: #f8f9fa;
            transition: transform 0.3s ease;
        }

        .contact-option:hover {
            transform: translateX(5px);
        }

        .option-icon {
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--ngs-accent);
            font-size: 1.5rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .option-content h3 {
            font-size: 1rem;
            margin: 0 0 0.3rem 0;
            color: #333;
        }

        .option-content p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }

        .option-content a {
            color: var(--ngs-accent);
            text-decoration: none;
            font-weight: 600;
        }

        .option-content a:hover {
            text-decoration: underline;
        }

        /* FAQ */
        .faq-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .faq-item {
            border: 1px solid #eaeaea;
            border-radius: 8px;
            overflow: hidden;
        }

        .faq-question {
            padding: 1rem;
            background: white;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: #333;
            transition: background 0.3s ease;
        }

        .faq-question:hover {
            background: #f8f9fa;
        }

        .faq-question i {
            transition: transform 0.3s ease;
        }

        .faq-question.active i {
            transform: rotate(180deg);
        }

        .faq-answer {
            padding: 0 1rem;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            background: #f8f9fa;
            color: #666;
            font-size: 0.95rem;
        }

        .faq-answer.show {
            padding: 1rem;
            max-height: 200px;
        }

        /* Formulario de contacto */
        .contact-form {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #eaeaea;
        }

        .contact-form h2 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: #333;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--ngs-accent);
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23666' viewBox='0 0 16 16'%3E%3Cpath d='M8 11.5l-5-5h10l-5 5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.5rem;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .btn-submit {
            background: var(--ngs-accent);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-submit:hover {
            background: #0b5ed7;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
        }

        .btn-submit i {
            margin-right: 8px;
        }

        /* Horario de atención */
        .schedule {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .schedule-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            color: #666;
        }

        .schedule-item .days {
            font-weight: 600;
            color: #333;
        }

        .schedule-item .hours {
            color: var(--success);
            font-weight: 600;
        }

        /* Alertas */
        .alert {
            margin-bottom: 1.5rem;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            border-left: 4px solid transparent;
        }

        .alert-success {
            background-color: #d4edda;
            border-left-color: var(--success);
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-left-color: var(--danger);
            color: #721c24;
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
            
            .contact-grid {
                grid-template-columns: 1fr;
            }
            
            .contact-form {
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .contact-option {
                flex-direction: column;
                text-align: center;
            }
            
            .option-content {
                text-align: center;
            }
            
            .schedule-item {
                flex-direction: column;
                text-align: center;
                gap: 0.3rem;
            }
        }
    </style>
</head>
<body>

<!-- Banner de perfil -->
<div class="profile-banner">
    <div class="container container-custom">
        <div class="profile-banner-content">
            <h1>Servicio al Cliente</h1>
            <p>Estamos aquí para ayudarte con cualquier duda o problema</p>
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
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check me-2"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Grid principal -->
    <div class="contact-grid">
        <!-- Columna izquierda: Información y FAQ -->
        <div>
            <!-- Opciones de contacto -->
            <div class="info-card">
                <h2>
                    <i class="fa-solid fa-headset"></i>
                    Canales de atención
                </h2>
                
               <!-- <div class="contact-options">
                    <div class="contact-option">
                        <div class="option-icon">
                            <i class="fa-solid fa-phone"></i>
                        </div>
                        <div class="option-content">
                            <h3>Teléfono</h3>
                            <p>Llamada gratuita</p>
                            <a href="tel:+527711234567">(771) 123-4567</a>
                        </div>
                    </div>
    -->
                    <div class="contact-option">
                        <div class="option-icon">
                            <i class="fa-brands fa-whatsapp"></i>
                        </div>
                        <div class="option-content">
                            <h3>WhatsApp</h3>
                            <p>Atención inmediata</p>
                            <a href="https://wa.me/5643603454" target="_blank">+56 4360 3454</a>
                        </div>
                    </div>
                    
                    <div class="contact-option">
                        <div class="option-icon">
                            <i class="fa-regular fa-envelope"></i>
                        </div>
                        <div class="option-content">
                            <h3>Correo electrónico</h3>
                            <p>Respuesta en 24h</p>
                            <a href="mailto:soporte@ngs.com">soporte@ngs.com</a>
                        </div>
                    </div>
                    
                    <div class="contact-option">
                        <div class="option-icon">
                            <i class="fa-regular fa-clock"></i>
                        </div>
                        <div class="option-content">
                            <h3>Horario de atención</h3>
                            <p>Lunes a viernes: 9:00 - 18:00</p>
                            <p>Sábados: 10:00 - 14:00</p>
                        </div>
                    </div>
                </div>
                
                <div class="schedule">
                    <div class="schedule-item">
                        <span class="days">Lunes a Viernes</span>
                        <span class="hours">9:00 AM - 6:00 PM</span>
                    </div>
                    <div class="schedule-item">
                        <span class="days">Sábados</span>
                        <span class="hours">10:00 AM - 2:00 PM</span>
                    </div>
                    <div class="schedule-item">
                        <span class="days">Domingos</span>
                        <span class="hours">Cerrado</span>
                    </div>
                </div>
            </div>

            <!-- Preguntas frecuentes -->
            <div class="info-card" style="margin-top: 1.5rem;">
                <h2>
                    <i class="fa-regular fa-circle-question"></i>
                    Preguntas frecuentes
                </h2>
                
                <div class="faq-list">
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            ¿Cómo puedo rastrear mi pedido?
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Puedes rastrear tu pedido desde tu perfil en la sección "Historial de Compras". Allí encontrarás el número de guía y el estado actualizado de tu envío.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            ¿Cuánto tarda la entrega?
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            El tiempo de entrega varía según tu ubicación. Generalmente es de 3 a 5 días hábiles en ciudades principales y de 5 a 7 días en el resto del país.
                        </div>
                    </div>
                    
                    <!-- <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            ¿Aceptan devoluciones?
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Sí, aceptamos devoluciones dentro de los primeros 30 días posteriores a la compra. El producto debe estar en su empaque original y en condiciones óptimas.
                        </div>
                    </div> -->
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            ¿Cómo obtengo mi factura?
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Puedes descargar tu factura desde la sección "Historial de Pagos" o "Historial de Compras". Busca el pedido y haz clic en el botón "Factura".
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            ¿Qué métodos de pago aceptan?
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            Aceptamos tarjetas de crédito/débito (Visa, Mastercard, American Express) y pagos a través de Stripe. Próximamente aceptaremos transferencias bancarias.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna derecha: Formulario de contacto -->
        <!-- <div>
            <div class="contact-form">
                <h2>
                    <i class="fa-regular fa-pen-to-square me-2"></i>
                    Envíanos un mensaje
                </h2>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label" for="tipo">Tipo de consulta</label>
                        <select class="form-control" id="tipo" name="tipo" required>
                            <option value="consulta">Consulta general</option>
                            <option value="pedido">Problema con mi pedido</option>
                            <option value="factura">Facturación</option>
                            <option value="devolucion">Devolución o garantía</option>
                            <option value="sugerencia">Sugerencia</option>
                            <option value="queja">Queja</option>
                        </select>
                    </div>
                    
                    <?php if (!empty($pedidos_recientes)): ?>
                    <div class="form-group">
                        <label class="form-label" for="pedido_id">Relacionado con pedido (opcional)</label>
                        <select class="form-control" id="pedido_id" name="pedido_id">
                            <option value="">-- Selecciona un pedido --</option>
                            <?php foreach($pedidos_recientes as $pedido): ?>
                                <option value="<?php echo $pedido['id']; ?>">
                                    #<?php echo str_pad($pedido['id'], 6, '0', STR_PAD_LEFT); ?> - 
                                    $<?php echo number_format($pedido['total'], 2); ?> - 
                                    <?php echo date('d/m/Y', strtotime($pedido['fecha'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label class="form-label" for="asunto">Asunto *</label>
                        <input type="text" class="form-control" id="asunto" name="asunto" 
                               placeholder="Ej: Problema con mi pedido" required maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="mensaje">Mensaje *</label>
                        <textarea class="form-control" id="mensaje" name="mensaje" 
                                  placeholder="Describe tu problema o consulta en detalle..." required></textarea>
                        <small class="text-muted">Máximo 1000 caracteres</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Adjuntar archivo (opcional)</label>
                        <input type="file" class="form-control" accept=".jpg,.jpeg,.png,.pdf" disabled>
                        <small class="text-muted">Máx. 5MB - Capturas de pantalla, facturas, etc.</small>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fa-regular fa-paper-plane"></i>
                        Enviar mensaje
                    </button>
                </form>
                
                <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #eee; text-align: center; color: #666; font-size: 0.9rem;">
                    <i class="fa-regular fa-clock me-1"></i>
                    Tiempo de respuesta promedio: <strong>2 horas</strong>
                </div>
            </div>
        </div>-->
    </div>

    <!-- Información adicional -->
    <div style="background: white; border-radius: 10px; padding: 2rem; margin-top: 2rem; border: 1px solid #eaeaea;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
            <div style="text-align: center;">
                <i class="fa-solid fa-truck-fast" style="font-size: 2rem; color: var(--ngs-accent); margin-bottom: 1rem;"></i>
                <h4 style="margin-bottom: 0.5rem;">Envíos a todo México</h4>
                <p style="color: #666; font-size: 0.9rem;">Entregas rápidas y seguras con seguimiento en línea</p>
            </div>
            
            <div style="text-align: center;">
                <i class="fa-solid fa-rotate-left" style="font-size: 2rem; color: var(--ngs-accent); margin-bottom: 1rem;"></i>
                <h4 style="margin-bottom: 0.5rem;">Devoluciones fáciles</h4>
                <p style="color: #666; font-size: 0.9rem;">30 días para devoluciones con reembolso garantizado</p>
            </div>
            
            <div style="text-align: center;">
                <i class="fa-solid fa-shield" style="font-size: 2rem; color: var(--ngs-accent); margin-bottom: 1rem;"></i>
                <h4 style="margin-bottom: 0.5rem;">Garantía extendida</h4>
                <p style="color: #666; font-size: 0.9rem;">1 año de garantía en todos nuestros productos</p>
            </div>
            
            <div style="text-align: center;">
                <i class="fa-solid fa-message" style="font-size: 2rem; color: var(--ngs-accent); margin-bottom: 1rem;"></i>
                <h4 style="margin-bottom: 0.5rem;">Soporte técnico</h4>
                <p style="color: #666; font-size: 0.9rem;">Asesoría especializada para instalación y configuración</p>
            </div>
        </div>
    </div>
</div>

<script>
    // Función para toggle de FAQ
    function toggleFAQ(element) {
        element.classList.toggle('active');
        const answer = element.nextElementSibling;
        answer.classList.toggle('show');
        
        // Cerrar otros FAQs
        const allQuestions = document.querySelectorAll('.faq-question');
        allQuestions.forEach(q => {
            if (q !== element && q.classList.contains('active')) {
                q.classList.remove('active');
                q.nextElementSibling.classList.remove('show');
            }
        });
    }

    // Auto-expandir primer FAQ
    document.addEventListener('DOMContentLoaded', function() {
        const firstQuestion = document.querySelector('.faq-question');
        if (firstQuestion) {
            firstQuestion.classList.add('active');
            firstQuestion.nextElementSibling.classList.add('show');
        }
    });
</script>

</body>
</html>