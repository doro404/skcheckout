<?php
require_once 'config.php';
require_once 'config/database.php';
require_once 'validate_merchant.php';

// Validação do comerciante
$validation = validateMerchant($_REQUEST);

if (!$validation['success']) {
    http_response_code($validation['status_code']);
    echo json_encode([
        'status' => 'error',
        'message' => $validation['message']
    ]);
    exit;
}

$merchant = $validation['merchant'];

// Exemplo de como o comerciante pode gerar um link de pagamento
try {
    // Validação dos dados recebidos
    $required = ['amount', 'product_id', 'callback_url'];
    $errors = [];
    
    foreach ($required as $field) {
        if (!isset($_POST[$field])) {
            $errors[] = "Campo {$field} é obrigatório";
        }
    }
    
    if (!empty($errors)) {
        throw new Exception(implode(", ", $errors));
    }
    
    // Gera o link de pagamento
    $params = http_build_query([
        'amount' => $_POST['amount'],
        'product_id' => $_POST['product_id'],
        'callback_url' => $_POST['callback_url'],
        'merchant_id' => $merchant['id'],
        'merchant_name' => $merchant['name'],
        'product_name' => $_POST['product_name'] ?? 'Produto'
    ]);
    
    $payment_url = BASE_URL . "/index.php?" . $params;
    
    echo json_encode([
        'status' => 'success',
        'payment_url' => $payment_url
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 