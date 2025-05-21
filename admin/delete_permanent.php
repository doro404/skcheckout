<?php
if (!defined('INCLUDED')) {
    session_start();
    require_once '../config/database.php';
    require_once '../config.php';
    header('Content-Type: application/json');
}

// Função para enviar resposta JSON
function sendJsonResponse($success, $message, $statusCode = 200) {
    if (!defined('INCLUDED')) {
        http_response_code($statusCode);
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    } else {
        if ($success) {
            $_SESSION['success'] = $message;
        } else {
            $_SESSION['error'] = $message;
        }
        return;
    }
}

// Verificar se o usuário está logado como comerciante
if (!isset($_SESSION['merchant_id'])) {
    sendJsonResponse(false, "Você precisa estar logado para realizar esta ação.", 401);
}

// Verificar se o ID foi fornecido (aceita tanto GET quanto POST)
$product_id = null;
if (isset($_POST['product_id'])) {
    $product_id = (int)$_POST['product_id'];
} elseif (isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
}

if (!$product_id) {
    sendJsonResponse(false, "ID do produto inválido.", 400);
}

try {
    // Verificar se o produto pertence ao comerciante logado e está cancelado ou inativo
    $stmt = $pdo->prepare("
        SELECT merchant_id, name, status, file_path
        FROM products 
        WHERE id = :id
    ");
    $stmt->execute(['id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        sendJsonResponse(false, "Produto não encontrado.", 404);
    }

    if ($product['merchant_id'] != $_SESSION['merchant_id']) {
        sendJsonResponse(false, "Você não tem permissão para excluir este produto.", 403);
    }

    if (!in_array($product['status'], ['cancelled', 'inactive'])) {
        sendJsonResponse(false, "Apenas produtos cancelados ou inativos podem ser excluídos permanentemente.", 400);
    }

    // Iniciar transação
    $pdo->beginTransaction();

    try {
        // Verificar se existem transações relacionadas
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM transactions 
            WHERE product_id = :product_id 
            AND merchant_id = :merchant_id
        ");
        $stmt->execute([
            'product_id' => $product_id,
            'merchant_id' => $_SESSION['merchant_id']
        ]);
        $transaction_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Se houver transações, excluí-las primeiro
        if ($transaction_count > 0) {
            $stmt = $pdo->prepare("
                DELETE FROM transactions 
                WHERE product_id = :product_id 
                AND merchant_id = :merchant_id
            ");
            $stmt->execute([
                'product_id' => $product_id,
                'merchant_id' => $_SESSION['merchant_id']
            ]);
        }

        // Excluir a imagem do produto se existir
        if (!empty($product['file_path'])) {
            $image_path = '../uploads/' . $product['file_path'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }

        // Excluir o produto
        $stmt = $pdo->prepare("
            DELETE FROM products 
            WHERE id = :id 
            AND merchant_id = :merchant_id
        ");
        $stmt->execute([
            'id' => $product_id,
            'merchant_id' => $_SESSION['merchant_id']
        ]);

        // Confirmar transação
        $pdo->commit();

        sendJsonResponse(true, "Produto '" . htmlspecialchars($product['name']) . "' e todas as suas transações foram excluídos permanentemente.");

    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    sendJsonResponse(false, "Erro ao excluir permanentemente o produto: " . $e->getMessage(), 500);
} 