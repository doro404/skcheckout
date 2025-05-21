<?php
session_start();
require_once '../config/database.php';
require_once '../config.php';

$page_title = 'Documentação Webhook';
$additional_css = '
<link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/themes/prism-okaidia.min.css" rel="stylesheet" />
<style>
    pre {
        background-color: #282c34;
        border-radius: 6px;
        padding: 15px;
        margin: 15px 0;
    }
    .copy-button {
        position: absolute;
        right: 10px;
        top: 10px;
    }
    .endpoint-url {
        background-color: #f8f9fa;
        padding: 10px;
        border-radius: 4px;
        border: 1px solid #dee2e6;
        margin: 10px 0;
    }
    .nav-pills .nav-link {
        margin-right: 5px;
    }
    .tab-content {
        padding: 20px;
        border: 1px solid #dee2e6;
        border-top: none;
        border-radius: 0 0 5px 5px;
    }
    .docs-quick-access {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1050;
        background: rgba(255, 255, 255, 0.95);
        padding: 10px 15px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        border: 1px solid #dee2e6;
    }
    .docs-quick-access .btn {
        white-space: nowrap;
    }
    @media (max-width: 768px) {
        .docs-quick-access {
            position: static;
            margin-bottom: 20px;
            text-align: center;
        }
    }
</style>';

require_once 'includes/header.php';
?>

    <!-- Botão de Acesso Rápido à Documentação -->
    <div class="docs-quick-access">
        <a href="#webhook-docs" class="btn btn-primary">
            <i class="bi bi-book"></i> Documentação Webhook
        </a>
    </div>

    <div class="container mt-4 animate-on-load">
        <div id="webhook-docs">
            <h2>Documentação do Webhook</h2>
            <p class="lead">Esta documentação explica como integrar e receber as notificações de pagamento via webhook.</p>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h4>URL do Webhook</h4>
                <p>Configure a URL do seu webhook no painel de configurações. Esta URL receberá as notificações POST quando houver atualizações nas transações.</p>
                
                <div class="endpoint-url">
                    <strong>Método:</strong> POST<br>
                    <strong>URL:</strong> <span class="text-primary">https://sua-url.com/webhook</span>
                </div>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> 
                    Todas as requisições webhook incluem um cabeçalho de segurança <code>X-Signature</code> para validação.
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pills-success-tab" data-bs-toggle="pill" 
                                data-bs-target="#pills-success" type="button">Pagamento Aprovado</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pills-pending-tab" data-bs-toggle="pill" 
                                data-bs-target="#pills-pending" type="button">Pagamento Pendente</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pills-failed-tab" data-bs-toggle="pill" 
                                data-bs-target="#pills-failed" type="button">Pagamento Falhou</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pills-validation-tab" data-bs-toggle="pill" 
                                data-bs-target="#pills-validation" type="button">Validação</button>
                    </li>
                </ul>

                <div class="tab-content" id="pills-tabContent">
                    <!-- Pagamento Aprovado -->
                    <div class="tab-pane fade show active" id="pills-success">
                        <h5>Pagamento Aprovado</h5>
                        <p>Quando um pagamento é aprovado, você receberá um payload com os seguintes dados:</p>
                        <div class="position-relative">
                            <button class="btn btn-sm btn-outline-secondary copy-button" 
                                    onclick="copyCode('success-code')">
                                <i class="bi bi-clipboard"></i>
                            </button>
<pre><code class="language-json" id="success-code">{
    "event": "payment.success",
    "data": {
        "transaction_id": "123e4567-e89b-12d3-a456-426614174000",
        "merchant_id": "MERCHANT123",
        "product_id": "PROD456",
        "status": "approved",
        "amount": 99.90,
        "payment_method": "pix",
        "payer": {
            "name": "João Silva",
            "email": "joao@email.com",
            "document": "123.456.789-00"
        },
        "payment_date": "2024-01-20T15:30:45Z",
        "metadata": {
            "order_id": "ORDER789"
        }
    }
}</code></pre>
                        </div>
                    </div>

                    <!-- Pagamento Pendente -->
                    <div class="tab-pane fade" id="pills-pending">
                        <h5>Pagamento Pendente</h5>
                        <p>Quando um pagamento está pendente (aguardando o pagamento do PIX), você receberá:</p>
                        <div class="position-relative">
                            <button class="btn btn-sm btn-outline-secondary copy-button" 
                                    onclick="copyCode('pending-code')">
                                <i class="bi bi-clipboard"></i>
                            </button>
<pre><code class="language-json" id="pending-code">{
    "event": "payment.pending",
    "data": {
        "transaction_id": "123e4567-e89b-12d3-a456-426614174000",
        "merchant_id": "MERCHANT123",
        "product_id": "PROD456",
        "status": "pending",
        "amount": 99.90,
        "payment_method": "pix",
        "pix_code": "00020126580014br.gov.bcb.pix0136...",
        "expiration_date": "2024-01-20T16:30:45Z",
        "metadata": {
            "order_id": "ORDER789"
        }
    }
}</code></pre>
                        </div>
                    </div>

                    <!-- Pagamento Falhou -->
                    <div class="tab-pane fade" id="pills-failed">
                        <h5>Pagamento Falhou</h5>
                        <p>Quando um pagamento falha ou expira, você receberá:</p>
                        <div class="position-relative">
                            <button class="btn btn-sm btn-outline-secondary copy-button" 
                                    onclick="copyCode('failed-code')">
                                <i class="bi bi-clipboard"></i>
                            </button>
<pre><code class="language-json" id="failed-code">{
    "event": "payment.failed",
    "data": {
        "transaction_id": "123e4567-e89b-12d3-a456-426614174000",
        "merchant_id": "MERCHANT123",
        "product_id": "PROD456",
        "status": "failed",
        "amount": 99.90,
        "payment_method": "pix",
        "failure_reason": "expired",
        "failure_date": "2024-01-20T16:30:45Z",
        "metadata": {
            "order_id": "ORDER789"
        }
    }
}</code></pre>
                        </div>
                    </div>

                    <!-- Validação -->
                    <div class="tab-pane fade" id="pills-validation">
                        <h5>Validação do Webhook</h5>
                        <p>Para garantir a segurança, você deve validar a assinatura do webhook. Exemplo em PHP:</p>
                        <div class="position-relative">
                            <button class="btn btn-sm btn-outline-secondary copy-button" 
                                    onclick="copyCode('validation-code')">
                                <i class="bi bi-clipboard"></i>
                            </button>
<pre><code class="language-php" id="validation-code"><?php
// Recebe o payload
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

// Sua chave webhook (configure no painel)
$webhookKey = 'seu_webhook_key';

// Calcula a assinatura
$expectedSignature = hash_hmac('sha256', $payload, $webhookKey);

// Valida a assinatura
if (hash_equals($expectedSignature, $signature)) {
    $data = json_decode($payload, true);
    
    // Processa o webhook baseado no evento
    switch ($data['event']) {
        case 'payment.success':
            // Processa pagamento aprovado
            break;
        case 'payment.pending':
            // Processa pagamento pendente
            break;
        case 'payment.failed':
            // Processa pagamento falho
            break;
    }
    
    http_response_code(200);
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
}
?></code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/components/prism-json.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/components/prism-php.min.js"></script>
    <script src="assets/js/transitions.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Adicionar animação de fade para os cards
        const cards = document.querySelectorAll('.card');
        cards.forEach((card, index) => {
            card.classList.add('fade-transition');
            setTimeout(() => {
                card.classList.add('active');
            }, index * 100); // Delay escalonado para cada card
        });

        // Highlight code on page load
        Prism.highlightAll();
    });

    function copyCode(elementId) {
        const el = document.getElementById(elementId);
        const text = el.textContent;
        
        navigator.clipboard.writeText(text).then(() => {
            const button = el.parentElement.querySelector('.copy-button');
            const icon = button.querySelector('i');
            
            icon.classList.remove('bi-clipboard');
            icon.classList.add('bi-check2');
            
            setTimeout(() => {
                icon.classList.remove('bi-check2');
                icon.classList.add('bi-clipboard');
            }, 2000);
        });
    }
    </script>
</body>
</html> 