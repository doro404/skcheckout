<?php
session_start();
require_once '../config/database.php';
require_once '../config.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['merchant_id'])) {
    header('Location: login.php');
    exit;
}

// Verifica se o método é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Método inválido para reativação de produto.';
    header('Location: products.php');
    exit;
}

// Verifica se o ID do produto foi fornecido
if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
    $_SESSION['error_message'] = 'ID do produto não fornecido.';
    header('Location: products.php');
    exit;
}

$product_id = (int)$_POST['product_id'];

try {
    // Primeiro, verifica se o produto existe e pertence ao merchant
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id AND merchant_id = :merchant_id");
    $stmt->execute([
        'id' => $product_id,
        'merchant_id' => $_SESSION['merchant_id']
    ]);
    
    $product = $stmt->fetch();
    
    if (!$product) {
        throw new Exception('Produto não encontrado ou você não tem permissão para reativá-lo.');
    }
    
    if ($product['status'] === 'active') {
        throw new Exception('Este produto já está ativo.');
    }
    
    // Atualiza o status do produto para ativo
    $stmt = $pdo->prepare("UPDATE products SET status = 'active', updated_at = NOW() WHERE id = :id AND merchant_id = :merchant_id");
    $stmt->execute([
        'id' => $product_id,
        'merchant_id' => $_SESSION['merchant_id']
    ]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['success_message'] = 'Produto reativado com sucesso!';
    } else {
        throw new Exception('Não foi possível reativar o produto.');
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Erro ao reativar produto: ' . $e->getMessage();
}

header('Location: products.php');
exit; 