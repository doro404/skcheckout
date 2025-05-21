<?php
session_start();
require_once 'config/database.php';
require_once 'config.php';

// Verifica se tem o ID do pagamento
if (!isset($_GET['payment_id'])) {
    die("ID do pagamento não fornecido");
}

try {
    // Busca a transação
    $stmt = $pdo->prepare("
        SELECT t.*, p.file_path, p.file_name 
        FROM transactions t
        JOIN products p ON t.product_id = p.id
        WHERE t.payment_id = ? AND t.status = 'approved'
    ");
    $stmt->execute([$_GET['payment_id']]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verifica se encontrou a transação e se está aprovada
    if (!$transaction || !$transaction['file_path']) {
        die("Arquivo não disponível ou pagamento não aprovado");
    }

    // Caminho completo do arquivo
    $file_path = UPLOAD_PATH . '/' . $transaction['file_path'];

    // Verifica se o arquivo existe
    if (!file_exists($file_path)) {
        die("Arquivo não encontrado");
    }

    // Define os headers para download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $transaction['file_name'] . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');

    // Lê e envia o arquivo
    readfile($file_path);
    exit;

} catch (Exception $e) {
    die("Erro ao processar download: " . $e->getMessage());
} 