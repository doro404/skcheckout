<?php
session_start();
require_once 'config/database.php';

// Log para debug
error_log("[Payment Pending] Iniciando página de pagamento pendente");
error_log("[Payment Pending] Payment ID: " . ($_GET['payment_id'] ?? 'não fornecido'));
error_log("[Payment Pending] Dados da sessão: " . json_encode($_SESSION['payment_data'] ?? 'não encontrado'));

// Verifica se temos o payment_id
if (!isset($_GET['payment_id'])) {
    die("Payment ID não fornecido");
}

// Verifica se temos os dados do pagamento na sessão
if (!isset($_SESSION['payment_data']) || $_SESSION['payment_data']['payment_id'] != $_GET['payment_id']) {
    die("Dados do pagamento não encontrados");
}

$payment_data = $_SESSION['payment_data'];

// Log dos dados do pagamento
error_log("[Payment Pending] Dados do pagamento carregados: " . json_encode($payment_data));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX Pendente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .payment-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 30px;
        }
        .qr-code-container {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .qr-code-image {
            max-width: 300px;
            margin: 0 auto 20px;
        }
        .copy-button {
            margin-top: 10px;
        }
        .timer {
            font-size: 1.2rem;
            color: #dc3545;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="payment-container">
            <div class="card">
                <div class="card-body">
                    <h2 class="text-center mb-4">Pagamento PIX</h2>
                    
                    <div class="alert alert-info" role="alert">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        Escaneie o QR Code ou copie o código PIX abaixo para realizar o pagamento
                    </div>

                    <div class="text-center mb-4">
                        <h5>Valor a pagar:</h5>
                        <h3 class="text-success">R$ <?php echo number_format($payment_data['amount'], 2, ',', '.'); ?></h3>
                    </div>

                    <div class="qr-code-container">
                        <?php if (isset($payment_data['qr_code_base64'])): ?>
                        <img src="<?php echo $payment_data['qr_code_base64']; ?>" 
                             alt="QR Code PIX" 
                             class="qr-code-image img-fluid mb-3"
                             onerror="this.onerror=null; this.src='data:image/png;base64,<?php echo $payment_data['qr_code_base64']; ?>'" />
                        <?php else: ?>
                        <div class="alert alert-danger">QR Code não disponível</div>
                        <?php endif; ?>
                        
                        <div class="input-group">
                            <input type="text" class="form-control" id="pixCode" 
                                   value="<?php echo htmlspecialchars($payment_data['qr_code']); ?>" readonly>
                            <button class="btn btn-primary" onclick="copyPixCode()">
                                <i class="bi bi-clipboard"></i> Copiar
                            </button>
                        </div>
                    </div>

                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-clock-history me-2"></i>
                        Este QR Code é válido por 30 minutos
                    </div>

                    <div class="text-center mt-4">
                        <p>Após realizar o pagamento, você será redirecionado automaticamente</p>
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Aguardando pagamento...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function copyPixCode() {
        var pixCode = document.getElementById('pixCode');
        pixCode.select();
        document.execCommand('copy');
        alert('Código PIX copiado!');
    }

    // Função para verificar o status do pagamento
    function checkPaymentStatus() {
        fetch('check_payment.php?payment_id=<?php echo $_GET['payment_id']; ?>')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'approved') {
                    window.location.href = 'payment_success.php?payment_id=<?php echo $_GET['payment_id']; ?>';
                }
            })
            .catch(error => console.error('Erro:', error));
    }

    // Verifica o status a cada 5 segundos
    setInterval(checkPaymentStatus, 5000);
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 