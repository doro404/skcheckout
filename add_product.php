<?php
header('Content-Type: application/json');
require_once 'config/database.php';
require_once 'config.php';

// Verifica se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

try {
    // Verifica autenticação
    $headers = getallheaders();
    $api_key = $headers['X-Api-Key'] ?? null;

    if (!$api_key) {
        throw new Exception("API Key não fornecida", 401);
    }

    // Busca o comerciante pela API Key
    $stmt = $pdo->prepare("SELECT id FROM merchants WHERE api_key = ?");
    $stmt->execute([$api_key]);
    $merchant = $stmt->fetch();

    if (!$merchant) {
        throw new Exception("API Key inválida", 401);
    }

    // Pega os dados do produto
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception("Dados inválidos", 400);
    }

    // Validações básicas
    if (empty($data['name'])) {
        throw new Exception("Nome do produto é obrigatório", 400);
    }

    if (!isset($data['price']) || !is_numeric($data['price'])) {
        throw new Exception("Preço inválido", 400);
    }

    // Validação do tipo de entrega
    $delivery_type = $data['delivery_type'] ?? '';
    if (!in_array($delivery_type, ['download', 'physical', 'service'])) {
        throw new Exception("Tipo de entrega inválido. Use: download, physical ou service", 400);
    }

    // Processa upload do arquivo se fornecido via base64 e for tipo download
    $file_path = null;
    $file_name = null;

    if ($delivery_type === 'download') {
        if (empty($data['file'])) {
            throw new Exception("Arquivo é obrigatório para produtos do tipo download", 400);
        }

        if (empty($data['file']['content']) || empty($data['file']['name'])) {
            throw new Exception("Dados do arquivo incompletos", 400);
        }

        // Decodifica o arquivo base64
        $file_content = base64_decode($data['file']['content']);
        if ($file_content === false) {
            throw new Exception("Conteúdo do arquivo inválido", 400);
        }

        // Gera nome único para o arquivo
        $file_name = $data['file']['name'];
        $file_path = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $file_name);

        // Salva o arquivo
        if (!file_put_contents(UPLOAD_PATH . '/' . $file_path, $file_content)) {
            throw new Exception("Erro ao salvar arquivo", 500);
        }
    }

    // Insere o produto no banco
    $stmt = $pdo->prepare("
        INSERT INTO products (
            merchant_id, 
            name, 
            description, 
            price,
            delivery_type,
            file_path,
            file_name
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $merchant['id'],
        $data['name'],
        $data['description'] ?? null,
        $data['price'],
        $delivery_type,
        $file_path,
        $file_name
    ]);

    $product_id = $pdo->lastInsertId();

    // Retorna sucesso
    echo json_encode([
        'status' => 'success',
        'message' => 'Produto cadastrado com sucesso',
        'data' => [
            'product_id' => $product_id,
            'name' => $data['name'],
            'price' => $data['price'],
            'delivery_type' => $delivery_type,
            'file_name' => $file_name
        ]
    ]);

} catch (Exception $e) {
    $status_code = $e->getCode() ?: 500;
    http_response_code($status_code);
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 