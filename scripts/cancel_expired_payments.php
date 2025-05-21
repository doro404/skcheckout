<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

try {
    // Busca pagamentos pendentes com mais de 30 minutos
    $stmt = $pdo->prepare("
        SELECT 
            t.payment_id,
            t.payer_email,
            t.payer_name,
            t.product_name,
            t.amount,
            t.created_at
        FROM transactions t 
        WHERE t.status = 'pending' 
        AND t.created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ");
    
    $stmt->execute();
    $expired_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($expired_payments as $payment) {
        try {
            // Atualiza status no banco de dados
            $update_stmt = $pdo->prepare("
                UPDATE transactions 
                SET status = 'cancelled',
                    updated_at = CURRENT_TIMESTAMP,
                    cancellation_reason = 'Pagamento expirado após 30 minutos'
                WHERE payment_id = ?
            ");
            $update_stmt->execute([$payment['payment_id']]);
            
            // Envia email informando sobre o cancelamento
            $subject = "Pagamento PIX Expirado - " . $payment['product_name'];
            
            $message = "
            <html>
            <head>
                <title>Pagamento PIX Expirado</title>
                <style>
                    .container {
                        padding: 20px;
                        background-color: #f8f9fa;
                        border-radius: 10px;
                    }
                    .button {
                        display: inline-block;
                        padding: 10px 20px;
                        background-color: #0d6efd;
                        color: white;
                        text-decoration: none;
                        border-radius: 5px;
                        margin: 10px 0;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2>Seu Pagamento PIX Expirou</h2>
                    <p>Olá " . htmlspecialchars($payment['payer_name']) . ",</p>
                    <p>Infelizmente o tempo para realizar o pagamento do seu PIX expirou.</p>
                    
                    <h3>Detalhes do Pagamento:</h3>
                    <ul>
                        <li>Produto: " . htmlspecialchars($payment['product_name']) . "</li>
                        <li>Valor: R$ " . number_format($payment['amount'], 2, ',', '.') . "</li>
                        <li>Data de Criação: " . date('d/m/Y H:i:s', strtotime($payment['created_at'])) . "</li>
                    </ul>
                    
                    <p>Não se preocupe! Você pode gerar um novo pagamento a qualquer momento:</p>
                    
                    <a href='" . BASE_URL . "' class='button'>
                        Gerar Novo Pagamento
                    </a>
                    
                    <p><small>Se você já realizou o pagamento, por favor desconsidere este email.</small></p>
                </div>
            </body>
            </html>
            ";
            
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=utf-8',
                'From: ' . SMTP_FROM
            ];
            
            mail($payment['payer_email'], $subject, $message, implode("\r\n", $headers));
            
            echo "Pagamento {$payment['payment_id']} cancelado com sucesso.\n";
            
        } catch (Exception $e) {
            echo "Erro ao processar pagamento {$payment['payment_id']}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "Total de pagamentos cancelados: " . count($expired_payments) . "\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
} 