<?php
session_start();
require_once 'config/database.php';
require_once 'config.php';

// Log para debug
error_log("[Callback] Iniciando callback");
error_log("[Callback] Parâmetros recebidos: " . json_encode($_GET));

try {
    // Verifica se temos os parâmetros necessários
    $payment_id = $_GET['payment_id'] ?? null;
    $status = $_GET['status'] ?? 'pending';
    $callback_url = $_GET['callback_url'] ?? null;
    
    if (!$payment_id) {
        throw new Exception("Payment ID não fornecido");
    }

    // Busca a transação no banco de dados
    $stmt = $pdo->prepare("
        SELECT t.*, p.price, m.webhook_url
        FROM transactions t
        JOIN products p ON t.product_id = p.id
        JOIN merchants m ON t.merchant_id = m.id
        WHERE t.payment_id = ?
    ");
    
    $stmt->execute([$payment_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new Exception("Transação não encontrada para o payment_id: " . $payment_id);
    }

    // Atualiza o status da transação
    $stmt = $pdo->prepare("
        UPDATE transactions 
        SET status = ?, 
            updated_at = CURRENT_TIMESTAMP 
        WHERE payment_id = ?
    ");
    $stmt->execute([$status, $payment_id]);

    // Se não temos callback_url nos parâmetros GET, tentamos usar da sessão
    if (!$callback_url && isset($_SESSION['payment_data']['callback_url'])) {
        $callback_url = $_SESSION['payment_data']['callback_url'];
    }

    // Se ainda não temos callback_url, lança exceção
    if (!$callback_url) {
        throw new Exception("URL de callback não encontrada");
    }

    // Monta os parâmetros para a URL de callback
    $params = [
        'status' => $status,
        'amount' => $transaction['amount'],
        'product_id' => $transaction['product_id'],
        'merchant_id' => $transaction['merchant_id'],
        'payment_id' => $payment_id,
        'external_reference' => $_GET['external_reference'] ?? ''
    ];

    // Adiciona os parâmetros à URL
    $separator = (parse_url($callback_url, PHP_URL_QUERY) ? '&' : '?');
    $callback_url .= $separator . http_build_query($params);

    // Log da URL de redirecionamento
    error_log("[Callback] Redirecionando para: " . $callback_url);

    // Redireciona para a URL de callback
    header("Location: " . $callback_url);
    exit;

} catch (Exception $e) {
    error_log("[Callback] Erro: " . $e->getMessage());
    
    // Se temos uma URL de callback nos parâmetros GET, redirecionamos com erro
    if (isset($_GET['callback_url'])) {
        $error_url = $_GET['callback_url'];
        $separator = (parse_url($error_url, PHP_URL_QUERY) ? '&' : '?');
        $error_url .= $separator . http_build_query([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
        header("Location: " . $error_url);
        exit;
    }
    
    // Caso contrário, mostra o erro
    die("Erro: " . $e->getMessage());
} 