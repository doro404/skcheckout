<?php
require_once 'config.php';
require_once 'config/database.php';

function validateMerchant($request) {
    global $pdo;
    
    // Se a verificação de API key não for obrigatória, retorna sucesso
    if (!REQUIRE_API_KEY) {
        // Busca o comerciante pelo ID (se fornecido)
        $merchant_id = $_GET['merchant_id'] ?? $_POST['merchant_id'] ?? null;
        
        if ($merchant_id) {
            $stmt = $pdo->prepare("SELECT id, name, email FROM merchants WHERE id = ?");
            $stmt->execute([$merchant_id]);
            $merchant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($merchant) {
                return [
                    'success' => true,
                    'merchant' => $merchant,
                    'status_code' => 200
                ];
            }
        }
        
        // Se não encontrou o comerciante mas API key não é obrigatória, 
        // retorna um comerciante padrão
        return [
            'success' => true,
            'merchant' => [
                'id' => 1,
                'name' => 'Loja Padrão',
                'email' => 'contato@exemplo.com'
            ],
            'status_code' => 200
        ];
    }
    
    // A partir daqui, a verificação de API key é obrigatória
    
    // Verifica se a API key foi enviada no header
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
    
    // Se não estiver no header, verifica na query string
    if (!$apiKey) {
        $apiKey = $_GET['api_key'] ?? null;
    }
    
    // Se não estiver na query string, verifica no POST
    if (!$apiKey) {
        $apiKey = $_POST['api_key'] ?? null;
    }
    
    // Se não encontrou a API key em nenhum lugar
    if (!$apiKey) {
        return [
            'success' => false,
            'message' => 'API key não fornecida',
            'status_code' => 401
        ];
    }
    
    // Busca o comerciante pela API key
    $stmt = $pdo->prepare("SELECT id, name, email FROM merchants WHERE api_key = ?");
    $stmt->execute([$apiKey]);
    $merchant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$merchant) {
        return [
            'success' => false,
            'message' => 'API key inválida',
            'status_code' => 401
        ];
    }
    
    return [
        'success' => true,
        'merchant' => $merchant,
        'status_code' => 200
    ];
}

// Log da configuração atual
if (DEBUG) {
    error_log("Validação de API Key: " . (REQUIRE_API_KEY ? "Ativada" : "Desativada"));
}

// Exemplo de uso em uma API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
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
    
    // Continua com o processamento da requisição...
} 