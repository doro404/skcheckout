<?php
session_start();
require_once 'config/database.php';
require_once 'config.php';
require_once 'vendor/autoload.php';

// Garantir que a resposta será sempre JSON
header('Content-Type: application/json');

try {
    error_log("[Payment] Iniciando processamento de pagamento");
    
    // Debug dos dados da sessão
    error_log("[Payment] Dados da sessão: " . json_encode($_SESSION['payment_data'] ?? 'Sem dados'));
    
    // Verifica se os dados da sessão existem
    if (!isset($_SESSION['payment_data'])) {
        throw new Exception("Dados do pagamento não encontrados na sessão");
    }

    // Debug dos dados do POST
    error_log("[Payment] Dados do POST: " . json_encode($_POST));

    // Validação do produto
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND merchant_id = ?");
    $stmt->execute([
        $_SESSION['payment_data']['product_id'],
        $_SESSION['payment_data']['merchant_id']
    ]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    // Debug do produto encontrado
    error_log("[Payment] Produto encontrado: " . json_encode($product));

    if (!$product) {
        throw new Exception("Produto não encontrado ou não pertence ao comerciante especificado");
    }

    // Validação do valor
    if ($product['price'] != $_SESSION['payment_data']['amount']) {
        throw new Exception("Valor do pagamento não corresponde ao valor do produto");
    }

    // Pega os dados do POST
    $name = $_POST['name'] ?? '';
    $lastname = $_POST['lastname'] ?? '';
    $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $neighborhood = $_POST['neighborhood'] ?? '';
    $number = $_POST['number'] ?? '';
    $cep = $_POST['cep'] ?? '';
    
    // Validações básicas
    if (empty($name) || empty($lastname) || empty($cpf) || empty($email)) {
        throw new Exception("Todos os campos são obrigatórios");
    }

    if (strlen($cpf) !== 11) {
        throw new Exception("CPF inválido");
    }

    // Criar o pagamento no Mercado Pago
    $payment = new MercadoPago\Payment();
    
    // Configuração básica do pagamento
    $payment->transaction_amount = (float)$_SESSION['payment_data']['amount'];
    $payment->description = $_SESSION['payment_data']['product_name'];
    $payment->payment_method_id = "pix";
    
    // Configurar URLs de notificação e retorno
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $baseUrl .= "://" . $_SERVER['HTTP_HOST'];
    
    // Dados do pagador
    $payment->payer = [
        "email" => $email,
        "first_name" => $name,
        "last_name" => $lastname,
        "identification" => [
            "type" => "CPF",
            "number" => $cpf
        ],
        "address" => [
            "zip_code" => $cep,
            "street_name" => $address,
            "street_number" => $number,
            "neighborhood" => $neighborhood,
            "city" => $city,
            "federal_unit" => $state
        ]
    ];

    // Configurações adicionais
    $payment->notification_url = $baseUrl . "/webhook.php";
    $payment->external_reference = uniqid('PIX_');
    
    // Metadados para rastreamento com todas as informações necessárias
    $payment->metadata = [
        "merchant_id" => $_SESSION['payment_data']['merchant_id'],
        "product_id" => $_SESSION['payment_data']['product_id'],
        "product_name" => $product['name'],
        "merchant_name" => $_SESSION['payment_data']['merchant_name'],
        "merchant_webhook" => $_SESSION['payment_data']['callback_url'],
        "customer_email" => $email,
        "customer_name" => $name . ' ' . $lastname,
        "customer_document" => $cpf,
        "product_type" => $product['type'],
        "delivery_type" => $product['delivery_type'],
        "delivery_info" => $product['delivery_info'],
        "access_duration" => $product['access_duration']
    ];

    error_log("[Payment] Tentando criar pagamento com dados: " . json_encode($payment->toArray()));
    
    // Salvar pagamento
    if (!$payment->save()) {
        $error = "Erro ao salvar pagamento: ";
        foreach ($payment->error->causes as $cause) {
            $error .= $cause->description . ". ";
        }
        throw new Exception($error);
    }
    
    error_log("[Payment] Pagamento criado com sucesso. ID: " . $payment->id);
    
    // Log dos dados do QR code
    error_log("[Payment] Dados do QR Code:");
    error_log("[Payment] QR Code: " . ($payment->point_of_interaction->transaction_data->qr_code ?? 'não disponível'));
    error_log("[Payment] QR Code Base64: " . ($payment->point_of_interaction->transaction_data->qr_code_base64 ?? 'não disponível'));
    
    // Salvar na tabela de transações com mais detalhes
    $stmt = $pdo->prepare("
        INSERT INTO transactions (
            payment_id, 
            merchant_id, 
            product_id, 
            amount, 
            status, 
            payer_email,
            payer_document,
            payer_name,
            product_name,
            callback_url,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    
    $stmt->execute([
        $payment->id,
        $_SESSION['payment_data']['merchant_id'],
        $_SESSION['payment_data']['product_id'],
        $_SESSION['payment_data']['amount'],
        $payment->status,
        $email,
        $cpf,
        $name . ' ' . $lastname,
        $product['name'],
        $_SESSION['payment_data']['callback_url']
    ]);
    
    // Atualiza os dados da sessão com as informações do PIX
    $_SESSION['payment_data'] = array_merge($_SESSION['payment_data'], [
        'payment_id' => $payment->id,
        'qr_code' => $payment->point_of_interaction->transaction_data->qr_code,
        'qr_code_base64' => $payment->point_of_interaction->transaction_data->qr_code_base64,
        'payer_email' => $email,
        'payer_name' => $name . ' ' . $lastname,
        'payer_document' => $cpf,
        'delivery_type' => $product['delivery_type'],
        'delivery_info' => $product['delivery_info'],
        'callback_url' => $_SESSION['payment_data']['callback_url'] // Mantém o callback original
    ]);
    
    // Envia email para o cliente sobre o pagamento pendente
    $subject = "Pagamento PIX Pendente - " . $payment->description;
    
    $message = "
    <html>
    <head>
        <title>Pagamento PIX Pendente</title>
        <style>
            .qr-code {
                text-align: center;
                margin: 20px 0;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 10px;
            }
            .pix-code {
                background: #e9ecef;
                padding: 15px;
                margin: 10px 0;
                border-radius: 5px;
                word-break: break-all;
            }
            .timer {
                color: #dc3545;
                font-weight: bold;
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
        <h2>Seu Pagamento PIX foi Gerado!</h2>
        <p>Olá " . htmlspecialchars($name) . ",</p>
        <p>Seu pagamento PIX para <strong>" . htmlspecialchars($payment->description) . "</strong> foi gerado com sucesso!</p>
        
        <div class='qr-code'>
            <h3>Detalhes do Pagamento:</h3>
            <p><strong>Valor:</strong> R$ " . number_format($payment->transaction_amount, 2, ',', '.') . "</p>
            <p><strong>ID do Pagamento:</strong> " . $payment->id . "</p>
            
            <h4>Escaneie o QR Code abaixo:</h4>
            <img src='" . $payment->point_of_interaction->transaction_data->qr_code_base64 . "' alt='QR Code PIX'>
            
            <div class='pix-code'>
                <p><strong>Código PIX para copiar e colar:</strong></p>
                <code>" . $payment->point_of_interaction->transaction_data->qr_code . "</code>
            </div>
            
            <p class='timer'>⚠️ Este QR Code é válido por 30 minutos</p>
        </div>

        <p><strong>Como pagar:</strong></p>
        <ol>
            <li>Abra o app do seu banco</li>
            <li>Escolha pagar via PIX</li>
            <li>Escaneie o QR Code ou cole o código PIX</li>
            <li>Confirme o pagamento</li>
        </ol>

        <p>Após o pagamento, você receberá um email de confirmação com os dados de acesso ao produto.</p>
        
        <a href='" . $baseUrl . "/payment_pending.php?payment_id=" . $payment->id . "' class='button'>
            Visualizar Pagamento no Site
        </a>

        <p><small>Se você já realizou o pagamento, por favor desconsidere este email.</small></p>
    </body>
    </html>
    ";

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: ' . SMTP_FROM
    ];

    if(!mail($email, $subject, $message, implode("\r\n", $headers))) {
        error_log("[Payment] Erro ao enviar email de pagamento pendente: " . error_get_last()['message']);
    } else {
        error_log("[Payment] Email de pagamento pendente enviado com sucesso para: " . $email);
    }
    
    // Retorna sucesso em JSON
    echo json_encode([
        'status' => 'success',
        'redirect' => 'payment_pending.php?payment_id=' . $payment->id
    ]);
    
} catch (Exception $e) {
    error_log("[Payment] Erro: " . $e->getMessage());
    error_log("[Payment] Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao gerar o PIX: ' . $e->getMessage()
    ]);
}
