<?php
require_once 'config/stripe.php';
require_once 'vendor/autoload.php';

$stripeConfig = require_once 'config/stripe.php';

echo "<h1>Diagnóstico de Stripe</h1>";

// Mostrar las claves (ocultando parte por seguridad)
echo "<h2>Claves configuradas:</h2>";
echo "Clave secreta: " . substr($stripeConfig['secret_key'], 0, 10) . "..." . substr($stripeConfig['secret_key'], -5) . "<br>";
echo "Clave pública: " . substr($stripeConfig['public_key'], 0, 10) . "..." . substr($stripeConfig['public_key'], -5) . "<br>";
echo "Modo prueba: " . ($stripeConfig['is_test'] ? 'Sí' : 'No') . "<br>";

// Probar conexión con Stripe
echo "<h2>Probando conexión con Stripe:</h2>";

try {
    // Intentar obtener la lista de productos (solo para probar la conexión)
    $products = \Stripe\Product::all(['limit' => 1]);
    echo "✅ Conexión exitosa con Stripe API<br>";
    echo "Puedes crear sesiones de pago correctamente<br>";
} catch (\Stripe\Exception\AuthenticationException $e) {
    echo "❌ Error de autenticación: " . $e->getMessage() . "<br>";
    echo "La clave API es incorrecta<br>";
} catch (Exception $e) {
    echo "❌ Error general: " . $e->getMessage() . "<br>";
}
?>