<?php
session_start();
require_once 'config/database.php';
require_once 'config.php';

// Log para debug
error_log("[Payment Redirect] Parâmetros recebidos: " . json_encode($_GET));

// Verifica se os parâmetros necessários foram fornecidos
if (!isset($_GET['product_id']) || !isset($_GET['merchant_id'])) {
    header("Location: payment_inactive.php");
    exit;
}

// Verifica se o produto está ativo e busca configurações de callback
try {
    $stmt = $pdo->prepare("
        SELECT p.*, m.name as merchant_name, m.default_callback_url, m.use_dynamic_callback,
               p.product_callback_url, p.use_custom_callback
        FROM products p 
        JOIN merchants m ON p.merchant_id = m.id 
        WHERE p.id = ? AND m.id = ?
    ");
    $stmt->execute([$_GET['product_id'], $_GET['merchant_id']]);
    $product = $stmt->fetch();

    // Se o produto não existe ou não está ativo, redireciona para a página de produto inativo
    if (!$product || $product['status'] !== 'active') {
        header("Location: payment_inactive.php?product_id=" . $_GET['product_id'] . "&merchant_id=" . $_GET['merchant_id']);
        exit;
    }

    // Define o callback_url baseado nas configurações
    $callback_url = $_GET['callback_url'] ?? null;

    if (empty($callback_url)) {
        // Se o produto tem callback próprio configurado
        if ($product['use_custom_callback'] && !empty($product['product_callback_url'])) {
            $callback_url = $product['product_callback_url'];
        }
        // Se não tem callback próprio e usa dinâmico
        elseif ($product['use_dynamic_callback']) {
            $callback_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
            
            // Se não tiver Referer válido, usa a URL padrão do comerciante
            if (!filter_var($callback_url, FILTER_VALIDATE_URL)) {
                $callback_url = $product['default_callback_url'];
            }
        }
        // Se não usa dinâmico, usa a URL padrão do comerciante
        else {
            $callback_url = $product['default_callback_url'];
        }
    }

    // Adiciona o callback_url aos parâmetros se for válido
    if (!empty($callback_url)) {
        $_GET['callback_url'] = $callback_url;
    }

    // Se chegou aqui, o produto está ativo
    // Redireciona para o index.php mantendo todos os parâmetros
    $params = $_GET;
    $redirect_url = 'index.php?' . http_build_query($params);

    // Log do redirecionamento
    error_log("[Payment Redirect] Redirecionando para: " . $redirect_url);

    // Redireciona
    header("Location: " . $redirect_url);
    exit;
    
} catch (Exception $e) {
    error_log("[Payment Error] " . $e->getMessage());
    header("Location: payment_inactive.php");
    exit;
} 