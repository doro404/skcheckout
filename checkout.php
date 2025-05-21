<?php
session_start();
require_once 'config/database.php';

// Log para debug
error_log("[Checkout] Iniciando validação");
error_log("[Checkout] Dados recebidos: " . json_encode($_REQUEST));

// Validação dos parâmetros obrigatórios
$required_params = ['amount', 'product_id', 'merchant_id', 'callback_url', 'product_name', 'merchant_name'];
$params = [];

foreach ($required_params as $param) {
    if (!isset($_REQUEST[$param]) || empty($_REQUEST[$param])) {
        error_log("[Checkout] Erro na validação: Parâmetro {$param} é obrigatório");
        die(json_encode([
            'status' => 'error',
            'message' => "Parâmetro {$param} é obrigatório"
        ]));
    }
    $params[$param] = $_REQUEST[$param];
}

// Validação específica do product_id
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND merchant_id = ? AND status = 'active'");
$stmt->execute([$params['product_id'], $params['merchant_id']]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    error_log("[Checkout] Erro: Produto não encontrado ou inativo");
    die(json_encode([
        'status' => 'error',
        'message' => 'Produto não encontrado ou inativo'
    ]));
}

// Validação do valor
if (floatval($params['amount']) != floatval($product['price'])) {
    error_log("[Checkout] Erro: Valor do produto não corresponde");
    die(json_encode([
        'status' => 'error',
        'message' => 'Valor do produto não corresponde'
    ]));
}

// Se chegou aqui, está tudo válido
$_SESSION['payment_data'] = $params;

error_log("[Checkout] Dados validados com sucesso: " . json_encode($params));

// Retorna sucesso
echo json_encode([
    'status' => 'success',
    'message' => 'Dados validados com sucesso'
]); 