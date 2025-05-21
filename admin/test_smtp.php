<?php
// Inicia a sessão antes de qualquer output
session_start();

// Inclui os arquivos necessários
require_once '../config/database.php';
require_once '../config.php';

// Debug da sessão - remova após resolver o problema
error_log('Session merchant_id: ' . (isset($_SESSION['merchant_id']) ? $_SESSION['merchant_id'] : 'não definido'));

// Verifica se o usuário está logado
if (!isset($_SESSION['merchant_id'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'message' => 'Acesso negado - Usuário não está logado',
        'debug' => [
            'session_id' => session_id(),
            'session_status' => session_status(),
            'session_data' => $_SESSION
        ]
    ]);
    exit;
}

// Busca as informações do usuário para verificar se é admin
$stmt = $pdo->prepare("SELECT is_admin FROM merchants WHERE id = ?");
$stmt->execute([$_SESSION['merchant_id']]);
$merchant = $stmt->fetch();

// Debug do usuário - remova após resolver o problema
error_log('Merchant data: ' . json_encode($merchant));

// Verifica se é admin
if (!$merchant || !$merchant['is_admin']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado - Usuário não é administrador']);
    exit;
}

// Verifica se é uma requisição AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Requisição inválida']);
    exit;
}

try {
    // Busca as configurações SMTP do banco
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%'");
    $smtp_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Tenta estabelecer uma conexão SMTP
    $smtp = new stdClass();
    $smtp->host = $smtp_settings['smtp_host'] ?? '';
    $smtp->port = $smtp_settings['smtp_port'] ?? '';
    $smtp->user = $smtp_settings['smtp_user'] ?? '';
    $smtp->pass = $smtp_settings['smtp_pass'] ?? '';

    // Verifica se todas as configurações necessárias estão presentes
    if (empty($smtp->host) || empty($smtp->port) || empty($smtp->user) || empty($smtp->pass)) {
        throw new Exception("Configurações SMTP incompletas. Por favor, preencha todos os campos necessários.");
    }

    // Tenta abrir uma conexão com o servidor SMTP
    $errno = 0;
    $errstr = '';
    $timeout = 10;

    $connection = @fsockopen($smtp->host, $smtp->port, $errno, $errstr, $timeout);

    if ($connection) {
        // Tenta autenticar
        $response = fgets($connection);
        fputs($connection, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
        $response = fgets($connection);

        // Tenta autenticação
        fputs($connection, "AUTH LOGIN\r\n");
        $response = fgets($connection);
        
        fputs($connection, base64_encode($smtp->user) . "\r\n");
        $response = fgets($connection);
        
        fputs($connection, base64_encode($smtp->pass) . "\r\n");
        $response = fgets($connection);

        // Fecha a conexão
        fputs($connection, "QUIT\r\n");
        fclose($connection);

        echo json_encode([
            'success' => true,
            'message' => 'Conexão SMTP estabelecida com sucesso!'
        ]);
    } else {
        throw new Exception("Não foi possível conectar ao servidor SMTP: $errstr ($errno)");
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao testar conexão SMTP: ' . $e->getMessage()
    ]);
} 