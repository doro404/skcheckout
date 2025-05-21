<?php
require_once 'config.php';
require_once 'config/database.php';

try {
    // Primeiro, vamos verificar se o banco de dados existe
    $pdo->query("CREATE DATABASE IF NOT EXISTS payment_system");
    $pdo->query("USE payment_system");
    
    echo "=== Verificação do Banco de Dados ===\n\n";
    
    // Verificar merchant_id = 3
    $stmt = $pdo->prepare("SELECT * FROM merchants WHERE id = ?");
    $stmt->execute([3]);
    $merchant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Comerciante (ID: 3):\n";
    if ($merchant) {
        echo "- Encontrado\n";
        echo "- Nome: " . $merchant['name'] . "\n";
        echo "- Email: " . $merchant['email'] . "\n";
    } else {
        echo "- NÃO ENCONTRADO!\n";
    }
    
    echo "\n";
    
    // Verificar product_id = 12
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([12]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Produto (ID: 12):\n";
    if ($product) {
        echo "- Encontrado\n";
        echo "- Nome: " . $product['name'] . "\n";
        echo "- Preço: R$ " . number_format($product['price'], 2, ',', '.') . "\n";
        echo "- Merchant ID: " . $product['merchant_id'] . "\n";
    } else {
        echo "- NÃO ENCONTRADO!\n";
    }
    
    echo "\n=== Recriando dados se necessário ===\n\n";
    
    // Se o comerciante não existe, vamos criar
    if (!$merchant) {
        $pdo->exec("
            INSERT INTO merchants (id, name, email, webhook_url) VALUES 
            (3, 'DevLoja', 'contato@devloja.com.br', 'https://sualoja.com.br/webhook')
        ");
        echo "Comerciante (ID: 3) criado!\n";
    }
    
    // Se o produto não existe, vamos criar
    if (!$product) {
        $pdo->exec("
            INSERT INTO products (id, merchant_id, name, description, price) VALUES 
            (12, 3, 'Curso PHP', 'Curso completo de PHP', 1.00)
        ");
        echo "Produto (ID: 12) criado!\n";
    }
    
    echo "\nVerificação concluída!\n";
    
} catch (PDOException $e) {
    die("Erro: " . $e->getMessage() . "\n");
} 