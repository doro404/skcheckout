<?php
session_start();
require_once 'config/database.php';
require_once 'config.php';
require_once 'vendor/autoload.php';

try {
    // Verifica se temos o payment_id
    if (!isset($_GET['payment_id'])) {
        throw new Exception("Payment ID não fornecido");
    }

    // Se for a primeira vez que acessa a página
    if (!isset($_GET['checked'])) {
        // Busca o pagamento no Mercado Pago
        $payment = MercadoPago\Payment::find_by_id($_GET['payment_id']);
        if (!$payment) {
            throw new Exception("Pagamento não encontrado no Mercado Pago");
        }

        // Atualiza o status na tabela de transações
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET status = ? 
            WHERE payment_id = ?
        ");
        $stmt->execute([$payment->status, $_GET['payment_id']]);

        // Se o pagamento não estiver aprovado, redireciona com delay
        if ($payment->status !== 'approved') {
            // Redireciona para a mesma página com parâmetro checked
            header("Refresh: 3; URL=payment_success.php?payment_id=" . $_GET['payment_id'] . "&checked=1");
            die("Verificando status do pagamento... Por favor, aguarde.");
        }
    }

    // Busca informações da transação
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            p.name as product_name,
            p.description as product_description,
            p.file_path,
            p.delivery_type,
            p.access_duration,
            p.delivery_info,
            m.name as merchant_name,
            t.callback_url
        FROM transactions t
        JOIN products p ON t.product_id = p.id
        JOIN merchants m ON t.merchant_id = m.id
        WHERE t.payment_id = ?
    ");
    
    $stmt->execute([$_GET['payment_id']]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new Exception("Transação não encontrada");
    }

    // Verifica se o status está aprovado
    if ($transaction['status'] !== 'approved') {
        throw new Exception("Pagamento ainda não foi aprovado. Status atual: " . $transaction['status']);
    }

    // Calcula data de expiração se houver
    $expiration_date = null;
    if ($transaction['access_duration']) {
        // Converte a duração em dias (exemplo: "30 dias", "1 ano", etc)
        $duration = strtolower($transaction['access_duration']);
        if (strpos($duration, 'dia') !== false) {
            $days = intval($duration);
        } elseif (strpos($duration, 'mes') !== false) {
            $days = intval($duration) * 30;
        } elseif (strpos($duration, 'ano') !== false) {
            $days = intval($duration) * 365;
        }
        
        if (isset($days) && $days > 0) {
            $expiration_date = date('Y-m-d H:i:s', strtotime("+{$days} days"));
            
            // Atualiza a transação com a data de expiração
            $stmt = $pdo->prepare("
                UPDATE transactions 
                SET updated_at = CURRENT_TIMESTAMP 
                WHERE payment_id = ?
            ");
            $stmt->execute([$_GET['payment_id']]);
        }
    }

    // Gera URL de download segura se for download direto
    $download_url = null;
    if ($transaction['delivery_type'] === 'download' && $transaction['file_path']) {
        $download_url = "download.php?payment_id=" . $transaction['payment_id'];
    }

    // Prepara informações de entrega adicionais
    $delivery_info = $transaction['delivery_info'] ? json_decode($transaction['delivery_info'], true) : null;

} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

// Se não houver dados do pagamento na sessão, redireciona para a home
if (!isset($_SESSION['payment_data'])) {
    header('Location: index.php');
    exit;
}

$payment_data = $_SESSION['payment_data'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Concluído</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .success-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
        }
        .success-icon {
            font-size: 4rem;
            color: #198754;
            margin-bottom: 1rem;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .expiration-badge {
            background-color: #fff3cd;
            color: #856404;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .download-section {
            background-color: #e7f5ff;
            border-radius: 10px;
            padding: 20px;
            margin: 1rem 0;
        }
        .delivery-info {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-container">
            <div class="card">
                <div class="card-body text-center">
                    <i class="bi bi-check-circle-fill success-icon"></i>
                    <h2 class="mb-4">Pagamento Concluído com Sucesso!</h2>
                    
                    <div class="text-start mb-4">
                        <h5>Detalhes da compra:</h5>
                        <p><strong>Produto:</strong> <?php echo htmlspecialchars($payment_data['product_name']); ?></p>
                        <p><strong>Valor:</strong> R$ <?php echo number_format($payment_data['amount'], 2, ',', '.'); ?></p>
                        <p><strong>Data:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
                        
                        <div class="alert alert-success" role="alert">
                            <i class="bi bi-envelope-check me-2"></i>
                            Enviamos todos os dados de acesso e informações do produto para seu email: <?php 
                                // Tenta pegar o email da sessão primeiro
                                $email = $payment_data['payer_email'] ?? null;
                                
                                // Se não encontrou na sessão, tenta pegar da transação
                                if (empty($email) && isset($transaction['payer_email'])) {
                                    $email = $transaction['payer_email'];
                                }
                                
                                echo htmlspecialchars($email);
                            ?>
                        </div>
                        
                        <?php if ($expiration_date): ?>
                        <div class="expiration-badge">
                            <i class="bi bi-clock me-2"></i>
                            Acesso válido até: <?php echo date('d/m/Y H:i:s', strtotime($expiration_date)); ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($payment_data['delivery_info']): ?>
                        <div class="delivery-info">
                            <h6><i class="bi bi-info-circle me-2"></i>Informações de Acesso:</h6>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($payment_data['delivery_info'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($payment_data['delivery_type'] === 'download' && $download_url): ?>
                    <div class="download-section">
                        <h5><i class="bi bi-cloud-download me-2"></i>Seu download está pronto!</h5>
                        <p>Clique no botão abaixo para baixar seu produto:</p>
                        <a href="<?php echo htmlspecialchars($download_url); ?>" class="btn btn-primary btn-lg">
                            <i class="bi bi-download me-2"></i>Baixar Produto
                        </a>
                        <?php if ($expiration_date): ?>
                        <p class="mt-2 small text-muted">
                            Este link de download expira em <?php echo date('d/m/Y H:i:s', strtotime($expiration_date)); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php elseif ($payment_data['delivery_type'] === 'email'): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-envelope-check me-2"></i>
                        Os detalhes do produto foram enviados para seu email: <?php 
                            // Tenta pegar o email da sessão primeiro
                            $email = $payment_data['payer_email'] ?? null;
                            
                            // Se não encontrou na sessão, tenta pegar da transação
                            if (empty($email) && isset($transaction['payer_email'])) {
                                $email = $transaction['payer_email'];
                            }
                            
                            echo htmlspecialchars($email);
                        ?>
                    </div>
                    <?php elseif ($payment_data['delivery_type'] === 'acesso_online'): ?>
                    <div class="alert alert-info" role="alert">
                        <i class="bi bi-globe me-2"></i>
                        Seu acesso online já está disponível! Verifique as informações de acesso acima.
                    </div>
                    <?php endif; ?>

                    <div class="mt-4">
                        <a href="<?php 
                            // Tenta pegar o callback da transação primeiro
                            $callback_url = $transaction['callback_url'] ?? null;
                            
                            // Se não encontrar na transação, tenta pegar da sessão como fallback
                            if (empty($callback_url) && isset($payment_data['callback_url'])) {
                                $callback_url = $payment_data['callback_url'];
                            }
                            
                            echo htmlspecialchars($callback_url);
                        ?>" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-left me-2"></i>Voltar à Loja
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// Limpa os dados da sessão após exibir e usar o callback
unset($_SESSION['payment_data']);
?> 