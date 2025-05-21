<?php
require_once 'config/database.php'; // ajuste o caminho conforme seu projeto

// Dados do merchant para cadastrar
$name = "Nome do Merchant";
$email = "email@exemplo.com";
$password_plain = "admin99";

// Gerar hash da senha
$password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

// Gerar uma API key aleatória (64 caracteres hexadecimais)
$api_key = bin2hex(random_bytes(32));

try {
    $stmt = $pdo->prepare("INSERT INTO merchants (name, email, password, api_key, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
    $stmt->execute([$name, $email, $password_hash, $api_key]);
    
    echo "Merchant cadastrado com sucesso!";
} catch (PDOException $e) {
    echo "Erro ao cadastrar merchant: " . $e->getMessage();
}
