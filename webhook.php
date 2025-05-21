<?php
require_once 'config/database.php';
require_once 'config.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Permitir apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode([
        'error' => 'Method not allowed',
        'message' => 'Only POST method is allowed'
    ]));
}

// Log da requisição
error_log("[Webhook] Recebido: " . file_get_contents("php://input"));

try {
    // Recebe o payload do webhook
    $payload = file_get_contents("php://input");
    error_log("[Webhook] Recebido: " . $payload);
    
    // Decodifica o payload
    $data = json_decode($payload);
    error_log("[Webhook] Payload decodificado: " . json_encode($data));
    
    if (!$data) {
        throw new Exception("Payload inválido");
    }

    // Se for notificação de recurso, busca o payment_id
    if (isset($data->resource) && isset($data->topic) && $data->topic === 'payment') {
        $payment_id = $data->resource;
    } 
    // Se for notificação de ação, pega o ID dos dados
    else if (isset($data->data->id)) {
        $payment_id = $data->data->id;
    }
    else {
        throw new Exception("Formato de payload inválido");
    }

    // Busca o pagamento no Mercado Pago
    $payment = MercadoPago\Payment::find_by_id($payment_id);
    if (!$payment) {
        throw new Exception("Pagamento não encontrado");
    }

    error_log("[Webhook] Status do pagamento " . $payment->id . ": " . $payment->status);

    // Atualiza o status na tabela de transações
    $stmt = $pdo->prepare("
        UPDATE transactions 
        SET status = ?, 
            updated_at = CURRENT_TIMESTAMP 
        WHERE payment_id = ?
    ");
    $stmt->execute([$payment->status, $payment->id]);

    // Se o pagamento foi aprovado, processa a entrega
    if ($payment->status === 'approved') {
        try {
            // Recupera os metadados do pagamento
            $metadata = $payment->metadata;
            error_log("[Webhook] Metadados do pagamento: " . json_encode($metadata));
            
            // Busca informações do produto
            $stmt = $pdo->prepare("
                SELECT p.*, m.email as merchant_email, m.name as merchant_name,
                       t.callback_url
                FROM products p 
                JOIN merchants m ON p.merchant_id = m.id 
                JOIN transactions t ON t.product_id = p.id AND t.payment_id = ?
                WHERE p.id = ?
            ");
            $stmt->execute([$payment->id, $metadata->product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new Exception("Produto não encontrado");
            }

            error_log("[Webhook] Dados do produto: " . json_encode($product));

            // Busca as configurações SMTP
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%'");
            $smtp_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            if (empty($smtp_settings['smtp_host']) || empty($smtp_settings['smtp_port']) || 
                empty($smtp_settings['smtp_user']) || empty($smtp_settings['smtp_pass']) || 
                empty($smtp_settings['smtp_from'])) {
                throw new Exception("Configurações SMTP incompletas");
            }

            // Configuração do PHPMailer
            $mail = new PHPMailer(true);
            
            try {
                // Configurações do servidor
                $mail->SMTPDebug = 0;
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

                // Email para o cliente
                $mail->setFrom($smtp_settings['smtp_from'], 'Sistema de Pagamento', false);
                $mail->addAddress($metadata->customer_email, $metadata->customer_name);
                $mail->addReplyTo($product['merchant_email'], $product['merchant_name']);

                $mail->isHTML(true);
                $mail->Subject = "Pagamento Aprovado - " . $metadata->product_name;
                $mail->Priority = 1;

                // Headers anti-spam
                $mail->addCustomHeader('X-MSMail-Priority', 'High');
                $mail->addCustomHeader('X-Mailer', 'PHP/' . phpversion());
                $mail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
                $mail->addCustomHeader('List-Unsubscribe', '<mailto:' . $smtp_settings['smtp_from'] . '>');
                $mail->addCustomHeader('Precedence', 'bulk');

                // Corpo do email para o cliente
                $mail->Body = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Pagamento Aprovado</title>
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
            <h2>Seu pagamento foi aprovado!</h2>
        </div>
        
        <p>Olá ' . htmlspecialchars($metadata->customer_name) . ',</p>
        <p>Seu pagamento para o produto <strong>' . htmlspecialchars($metadata->product_name) . '</strong> foi aprovado com sucesso!</p>
        
        <div class="details">
            <h3>Detalhes da compra:</h3>
            <ul>
                <li>Produto: ' . htmlspecialchars($metadata->product_name) . '</li>
                <li>Valor: R$ ' . number_format($payment->transaction_amount, 2, ',', '.') . '</li>
                <li>Data: ' . date('d/m/Y H:i:s') . '</li>
                <li>ID do Pagamento: ' . htmlspecialchars($payment->id) . '</li>
            </ul>
        </div>

        <div class="details">
            <h3>Informações de Acesso/Entrega:</h3>
            <div style="background-color: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin: 10px 0;">
                ' . nl2br(htmlspecialchars($metadata->delivery_info)) . '
            </div>
        </div>
        
        <p>Obrigado pela sua compra!</p>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="' . htmlspecialchars($product['callback_url'] ?? BASE_URL) . '" class="button">Voltar à Loja</a>
        </div>
    </div>
</body>
</html>';

                // Envia email para o cliente
                $mail->send();
                error_log("[Webhook] Email enviado com sucesso para o cliente: " . $metadata->customer_email);

                // Limpa os destinatários para o email do comerciante
                $mail->clearAddresses();
                $mail->clearReplyTos();

                // Email para o comerciante
                $mail->setFrom($smtp_settings['smtp_from'], 'Sistema de Pagamento', false);
                $mail->addAddress($product['merchant_email'], $product['merchant_name']);
                $mail->addReplyTo($metadata->customer_email, $metadata->customer_name);

                $mail->Subject = "Nova Venda - " . $metadata->product_name;

                // Corpo do email para o comerciante
                $mail->Body = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Nova Venda Realizada</title>
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
            <i class="bi bi-cart-check-fill success-icon"></i>
            <h2>Você tem uma nova venda!</h2>
        </div>
        
        <p>Olá ' . htmlspecialchars($product['merchant_name']) . ',</p>
        <p>Uma nova venda do produto <strong>' . htmlspecialchars($metadata->product_name) . '</strong> foi realizada com sucesso!</p>
        
        <div class="details">
            <h3>Detalhes da venda:</h3>
            <ul>
                <li>Produto: ' . htmlspecialchars($metadata->product_name) . '</li>
                <li>Valor: R$ ' . number_format($payment->transaction_amount, 2, ',', '.') . '</li>
                <li>Data: ' . date('d/m/Y H:i:s') . '</li>
                <li>ID do Pagamento: ' . htmlspecialchars($payment->id) . '</li>
            </ul>
        </div>
        
        <div class="details">
            <h3>Dados do cliente:</h3>
            <ul>
                <li>Nome: ' . htmlspecialchars($metadata->customer_name) . '</li>
                <li>Email: ' . htmlspecialchars($metadata->customer_email) . '</li>
                <li>Documento: ' . htmlspecialchars($metadata->customer_document) . '</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="' . htmlspecialchars(BASE_URL) . '/admin/transactions.php?payment_id=' . htmlspecialchars($payment->id) . '" class="button">Ver Detalhes da Venda</a>
        </div>
    </div>
</body>
</html>';

                // Envia email para o comerciante
                $mail->send();
                error_log("[Webhook] Email enviado com sucesso para o comerciante: " . $product['merchant_email']);

            } catch (Exception $e) {
                error_log("[Webhook] Erro ao enviar email: " . $e->getMessage());
                throw new Exception("Erro ao enviar email: " . $e->getMessage());
            }

        } catch (Exception $e) {
            error_log("[Webhook] Erro ao processar pagamento aprovado: " . $e->getMessage());
            throw $e;
        }
    }

    http_response_code(200);
    echo "OK";

} catch (Exception $e) {
    error_log("[Webhook] Erro: " . $e->getMessage());
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}