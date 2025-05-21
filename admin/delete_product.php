<?php
session_start();
require_once '../config/database.php';
require_once '../config.php';

header('Content-Type: application/json');

// Função para enviar resposta JSON
function sendJsonResponse($success, $message, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// Verificar se o usuário está logado como comerciante
if (!isset($_SESSION['merchant_id'])) {
    sendJsonResponse(false, "Você precisa estar logado para realizar esta ação.", 401);
}

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    sendJsonResponse(false, "ID do produto inválido.", 400);
}

$product_id = (int)$_GET['id'];

try {
    // Verificar se o produto pertence ao comerciante logado
    $stmt = $pdo->prepare("
        SELECT p.merchant_id, p.name, p.status
        FROM products p
        WHERE p.id = :id
    ");
    $stmt->execute(['id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        sendJsonResponse(false, "Produto não encontrado.", 404);
    }

    if ($product['merchant_id'] != $_SESSION['merchant_id']) {
        sendJsonResponse(false, "Você não tem permissão para modificar este produto.", 403);
    }

    // Mover o produto para o status cancelled
    $stmt = $pdo->prepare("
        UPDATE products 
        SET status = 'cancelled',
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id 
        AND merchant_id = :merchant_id
    ");
    
    if ($stmt->execute([
        'id' => $product_id,
        'merchant_id' => $_SESSION['merchant_id']
    ])) {
        sendJsonResponse(true, "Produto '" . htmlspecialchars($product['name']) . "' foi movido para a lista de produtos cancelados.");
    } else {
        sendJsonResponse(false, "Não foi possível cancelar o produto.", 500);
    }

} catch (Exception $e) {
    sendJsonResponse(false, "Erro ao processar o produto: " . $e->getMessage(), 500);
} 