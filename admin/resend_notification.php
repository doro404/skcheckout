<?php
session_start();
require_once '../config/database.php';
require_once '../config.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Função para enviar resposta JSON
function sendJsonResponse($success, $message, $code = 200) {
    // Limpa qualquer saída anterior
    if (ob_get_length()) ob_clean();
    
    // Define os headers
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    
    // Prepara a resposta
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    // Envia a resposta
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
    exit;
}

// Função para log detalhado
function logError($message, $context = []) {
    $logMessage = "[Manual Notification] " . $message;
    if (!empty($context)) {
        $logMessage .= " Context: " . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    error_log($logMessage);
}

// Verifica se o usuário está logado
if (!isset($_SESSION['merchant_id'])) {
    logError("Tentativa de acesso não autorizado");
    sendJsonResponse(false, 'Não autorizado', 401);
}

// Verifica se o payment_id foi fornecido
if (!isset($_POST['payment_id'])) {
    logError("Payment ID não fornecido");
    sendJsonResponse(false, 'ID do pagamento não fornecido', 400);
}

try {
    // Busca as configurações SMTP
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%'");
    $smtp_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    if (empty($smtp_settings['smtp_host']) || empty($smtp_settings['smtp_port']) || 
        empty($smtp_settings['smtp_user']) || empty($smtp_settings['smtp_pass']) || 
        empty($smtp_settings['smtp_from'])) {
        logError("Configurações SMTP incompletas", $smtp_settings);
        throw new Exception("Configurações SMTP incompletas. Verifique as configurações do sistema.");
    }

    // Log das configurações SMTP (sem a senha)
    $logSettings = $smtp_settings;
    unset($logSettings['smtp_pass']);
    logError("Configurações SMTP carregadas", $logSettings);

    // Busca informações da transação
    $stmt = $pdo->prepare("
        SELECT t.*, p.name as product_name, p.delivery_info, p.id as product_id,
               m.email as merchant_email, m.name as merchant_name
        FROM transactions t
        JOIN products p ON t.product_id = p.id
        JOIN merchants m ON t.merchant_id = m.id
        WHERE t.payment_id = ? AND t.merchant_id = ?
    ");
    $stmt->execute([$_POST['payment_id'], $_SESSION['merchant_id']]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        logError("Transação não encontrada", [
            'payment_id' => $_POST['payment_id'],
            'merchant_id' => $_SESSION['merchant_id']
        ]);
        throw new Exception("Transação não encontrada ou não pertence ao comerciante");
    }

    if (empty($transaction['payer_email'])) {
        logError("Email do cliente não encontrado", $transaction);
        throw new Exception("Email do cliente não encontrado na transação");
    }

    // Configuração do PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Configurações do servidor
        $mail->SMTPDebug = 0; // Desativa o debug para não interferir na resposta JSON
        $mail->isSMTP();
        $mail->Host = $smtp_settings['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_settings['smtp_user'];
        $mail->Password = $smtp_settings['smtp_pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = $smtp_settings['smtp_port'];
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->WordWrap = 78;
        $mail->XMailer = 'PHP/' . phpversion();
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Configurações de remetente e destinatário
        $mail->setFrom($smtp_settings['smtp_from'], 'Sistema de Pagamento', false);
        $mail->addAddress($transaction['payer_email'], $transaction['payer_name']);
        $mail->addReplyTo($transaction['merchant_email'], $transaction['merchant_name']);

        // Configurações da mensagem
        $mail->isHTML(true);
        $mail->Subject = "Confirmação de Pagamento - " . $transaction['product_name'];
        $mail->Priority = 1; // Alta prioridade

        // Headers anti-spam
        $mail->addCustomHeader('X-MSMail-Priority', 'High');
        $mail->addCustomHeader('X-Mailer', 'PHP/' . phpversion());
        $mail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
        $mail->addCustomHeader('List-Unsubscribe', '<mailto:' . $smtp_settings['smtp_from'] . '>');
        $mail->addCustomHeader('Precedence', 'bulk');

        // Corpo do email
        $mail->Body = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Confirmação de Pagamento</title>
    <meta name="x-spam-status" content="No, score=0.0">
    <meta name="x-spam-score" content="0">
    <style>
        .container { padding: 20px; background-color: #f8f9fa; border-radius: 10px; }
        .success-icon { color: #28a745; font-size: 48px; }
        .details { background-color: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .button { display: inline-block; padding: 10px 20px; background-color: #0d6efd; color: white !important; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div style="text-align: center; margin-bottom: 20px;">
            <i class="bi bi-check-circle-fill success-icon"></i>
            <h2>Confirmação de Pagamento</h2>
        </div>
        
        <p>Olá ' . htmlspecialchars($transaction['payer_name']) . ',</p>
        <p>Esta é uma confirmação do seu pagamento para o produto <strong>' . htmlspecialchars($transaction['product_name']) . '</strong>.</p>
        
        <div class="details">
            <h3>Detalhes da compra:</h3>
            <ul>
                <li>Produto: ' . htmlspecialchars($transaction['product_name']) . '</li>
                <li>Valor: R$ ' . number_format($transaction['amount'], 2, ',', '.') . '</li>
                <li>Data: ' . date('d/m/Y H:i:s', strtotime($transaction['created_at'])) . '</li>
                <li>ID do Pagamento: ' . htmlspecialchars($transaction['payment_id']) . '</li>
            </ul>
        </div>

        <div class="details">
            <h3>Informações de Acesso/Entrega:</h3>
            <div style="background-color: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin: 10px 0;">
                ' . nl2br(htmlspecialchars($transaction['delivery_info'])) . '
            </div>
        </div>
        
        <p>Obrigado pela sua compra!</p>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="' . htmlspecialchars($transaction['callback_url'] ?? BASE_URL) . '" class="button" style="color: #FFF; text-decoration: none;">Voltar à Loja</a>
        </div>

    </div>
</body>
</html>';

        // Envio do email
        logError("Tentando enviar email", [
            'to' => $transaction['payer_email'],
            'payment_id' => $transaction['payment_id']
        ]);
        
        $mail->send();
        
        logError("Email enviado com sucesso", [
            'to' => $transaction['payer_email'],
            'payment_id' => $transaction['payment_id'],
            'product_id' => $transaction['product_id']
        ]);
        
        // Limpa qualquer saída anterior antes de enviar a resposta JSON
        if (ob_get_length()) ob_clean();
        
        sendJsonResponse(true, 'Notificação reenviada com sucesso!');
        
    } catch (Exception $e) {
        logError("Erro ao enviar email: " . $e->getMessage());
        throw new Exception("Erro ao enviar email: " . $e->getMessage());
    }

} catch (Exception $e) {
    logError("Erro: " . $e->getMessage());
    sendJsonResponse(false, $e->getMessage(), 500);
}
