<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Modo de prueba (0 = test, 1 = producción)
$productionMode = 0;

if ($productionMode) {
    // Tus claves de producción
    $secretKey = "sk_live_TU_CLAVE_SECRETA_REAL";
    $publicKey = "pk_live_TU_CLAVE_PUBLICABLE_REAL";
} else {
    // 🔴 CORREGIDO: La primera debe ser SECRETA (sk_), la segunda PUBLICABLE (pk_)
    $secretKey = "sk_test_51T1pCZ4Xwrniv80rwsvCVhEyr1GKNrpNtjqmh5nXdSMuQrmzmQ9ATXxbrYVU4LgFTLFg2MdMcuN2Z8GDOJkLvgzs009CiweXrb";
    $publicKey = "pk_test_51T1pCZ4Xwrniv80rfJd42R4nsH883nx5Yq5hxbU6mXsEDOQEbavp96uapnygyUm7OyI5psV3GcR6REDmxSg2LlHw00vY9ST14b";
}

// Configurar Stripe con la clave SECRETA
\Stripe\Stripe::setApiKey($secretKey);

// Devolver las claves
return [
    'secret_key' => $secretKey,
    'public_key' => $publicKey,
    'is_test' => !$productionMode
];
?>