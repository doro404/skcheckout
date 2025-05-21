<?php
require_once 'config.php';
require_once 'config/database.php';

try {
    // Criar tabela merchants se não existir
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS merchants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            webhook_url VARCHAR(255),
            api_key VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");

    // Criar tabela products se não existir
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            merchant_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (merchant_id) REFERENCES merchants(id)
        ) ENGINE=InnoDB;
    ");

    // Criar tabela transactions se não existir
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            payment_id VARCHAR(255) NOT NULL,
            merchant_id INT NOT NULL,
            product_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            status VARCHAR(50) NOT NULL,
            payer_email VARCHAR(255) NOT NULL,
            payer_document VARCHAR(20) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (merchant_id) REFERENCES merchants(id),
            FOREIGN KEY (product_id) REFERENCES products(id)
        ) ENGINE=InnoDB;
    ");

    // Inserir merchant de teste (ID = 3) se não existir
    $stmt = $pdo->prepare("SELECT id FROM merchants WHERE id = 3");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec("
            INSERT INTO merchants (id, name, email, webhook_url) VALUES 
            (3, 'DevLoja', 'contato@devloja.com.br', 'https://sualoja.com.br/webhook')
        ");
        echo "Merchant DevLoja (ID: 3) criado com sucesso!\n";
    }

    // Inserir produto de teste (ID = 12) se não existir
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = 12");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec("
            INSERT INTO products (id, merchant_id, name, description, price) VALUES 
            (12, 3, 'Curso PHP', 'Curso completo de PHP', 1.00)
        ");
        echo "Produto Curso PHP (ID: 12) criado com sucesso!\n";
    }

    echo "Configuração concluída com sucesso!\n";

} catch (PDOException $e) {
    die("Erro na configuração: " . $e->getMessage() . "\n");
} 