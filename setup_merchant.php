<?php
require_once 'config/database.php';

try {
    // Dados do comerciante de teste
    $merchant = [
        'name' => 'Loja Teste',
        'email' => 'teste@exemplo.com',
        'password' => password_hash('123456', PASSWORD_DEFAULT),
        'webhook_url' => 'http://localhost/callback.php'
    ];

    // Verificar se o comerciante já existe
    $stmt = $pdo->prepare("SELECT id FROM merchants WHERE email = ?");
    $stmt->execute([$merchant['email']]);
    
    if (!$stmt->fetch()) {
        // Inserir o comerciante
        $stmt = $pdo->prepare("
            INSERT INTO merchants (name, email, password, webhook_url) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $merchant['name'],
            $merchant['email'],
            $merchant['password'],
            $merchant['webhook_url']
        ]);
        
        echo "Comerciante de teste criado com sucesso!\n";
        echo "Email: " . $merchant['email'] . "\n";
        echo "Senha: 123456\n";
    } else {
        echo "Comerciante de teste já existe!\n";
    }

} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
} 