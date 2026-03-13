<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>¡Gracias por tu compra! | NGS STORE</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #0f172a;
            color: #f8fafc;
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            text-align: center;
        }
        .card {
            background: #1e293b;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            max-width: 500px;
            width: 90%;
            border: 1px solid #334155;
        }
        .icon-box {
            font-size: 5rem;
            color: #22c55e; /* Verde éxito */
            margin-bottom: 20px;
        }
        h1 { margin: 0 0 15px 0; font-size: 2rem; }
        p { color: #94a3b8; font-size: 1.1rem; line-height: 1.6; margin-bottom: 30px; }
        
        .btn-home {
            display: inline-block;
            background-color: #3b82f6;
            color: white;
            padding: 15px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }
        .btn-home:hover { background-color: #2563eb; transform: translateY(-3px); }
    </style>
</head>
<body>

    <div class="card">
        <div class="icon-box">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <h1>¡Gracias por tu compra!</h1>
        <p>Tu pedido ha sido procesado exitosamente. Hemos actualizado nuestro inventario y pronto prepararemos tu envío.</p>
        
        <a href="index.php" class="btn-home">
            <i class="fa-solid fa-store"></i> Volver a la Tienda
        </a>
    </div>

</body>
</html>