<?php
session_start();
require_once '../config/database.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['merchant_id'])) {
    header('Location: login.php');
    exit;
}

try {
    // Busca os produtos do usuário com informações do comerciante
    $stmt = $pdo->prepare("
        SELECT p.*, m.default_callback_url, m.use_dynamic_callback
        FROM products p 
        LEFT JOIN merchants m ON p.merchant_id = m.id 
        WHERE p.merchant_id = ? 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$_SESSION['merchant_id']]);
    $products = $stmt->fetchAll();

    // Busca as últimas transações
    $stmt = $pdo->prepare("
        SELECT t.*, p.name as product_name 
        FROM transactions t 
        JOIN products p ON t.product_id = p.id 
        WHERE t.merchant_id = ? 
        ORDER BY t.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['merchant_id']]);
    $transactions = $stmt->fetchAll();

    // Busca a URL do webhook nas configurações
    try {
        $stmt = $pdo->prepare("
            SELECT setting_value 
            FROM system_settings 
            WHERE setting_key = 'webhook_url'
            LIMIT 1
        ");
        $stmt->execute();
        $webhook_config = $stmt->fetch(PDO::FETCH_ASSOC);
        $webhook_url = null;
        
        if ($webhook_config && !empty($webhook_config['setting_value'])) {
            $webhook_url = trim($webhook_config['setting_value']);
        } else {
            // Se não encontrar na system_settings, usa a URL base + webhook.php
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
            $baseUrl .= "://" . $_SERVER['HTTP_HOST'];
            $webhook_url = $baseUrl . "/webhook.php";
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar webhook_url: " . $e->getMessage());
        // Em caso de erro, usa a URL padrão
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $baseUrl .= "://" . $_SERVER['HTTP_HOST'];
        $webhook_url = $baseUrl . "/webhook.php";
    }

    // Busca a URL do webhook do comerciante
    try {
        $stmt = $pdo->prepare("
            SELECT webhook_url 
            FROM merchants 
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['merchant_id']]);
        $merchant_webhook = $stmt->fetch(PDO::FETCH_ASSOC);
        $merchant_webhook_url = $merchant_webhook['webhook_url'] ?? null;
    } catch (PDOException $e) {
        error_log("Erro ao buscar webhook_url do comerciante: " . $e->getMessage());
        $merchant_webhook_url = null;
    }

} catch (PDOException $e) {
    error_log("Erro no banco de dados: " . $e->getMessage());
    $_SESSION['error_message'] = "Erro ao carregar os dados. Por favor, tente novamente.";
    $products = [];
    $transactions = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Painel de Pagamentos</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">Produtos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transactions.php">Transações</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">Configurações</a>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <a class="nav-link" href="logout.php">Sair</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h2>Bem-vindo, <?php echo htmlspecialchars($_SESSION['merchant_name']); ?>!</h2>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-tabs mb-3" id="productTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="active-tab" data-bs-toggle="tab" 
                                        data-bs-target="#active-products" type="button" role="tab">
                                    Produtos Ativos
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="inactive-tab" data-bs-toggle="tab" 
                                        data-bs-target="#inactive-products" type="button" role="tab">
                                    Produtos Inativos
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="cancelled-tab" data-bs-toggle="tab" 
                                        data-bs-target="#cancelled-products" type="button" role="tab">
                                    Produtos Cancelados
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="webhook-docs-tab" data-bs-toggle="tab" 
                                        data-bs-target="#webhook-docs" type="button" role="tab">
                                    <i class="bi bi-book"></i> Documentação API
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="productTabsContent">
                            <!-- Produtos Ativos -->
                            <div class="tab-pane fade show active" id="active-products" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>Preço</th>
                                                <th>Status</th>
                                                <th>Link de Pagamento</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($products as $product): ?>
                                                <?php if ($product['status'] == 'active'): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                    <td>R$ <?php echo number_format($product['price'], 2, ',', '.'); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getStatusBadgeClass($product['status']); ?>">
                                                            <?php echo getStatusLabel($product['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="input-group">
                                                            <input type="text" class="form-control form-control-sm" 
                                                                value="<?php echo getPaymentLink($product); ?>" readonly>
                                                            <button class="btn btn-outline-secondary btn-sm copy-link" type="button" data-link="<?php echo getPaymentLink($product); ?>">
                                                                <i class="bi bi-clipboard"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <a href="edit_product.php?id=<?php echo $product['id']; ?>" 
                                                           class="btn btn-sm btn-primary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-danger delete-product" 
                                                                data-id="<?php echo $product['id']; ?>">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Produtos Inativos -->
                            <div class="tab-pane fade" id="inactive-products" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>Preço</th>
                                                <th>Data de Inativação</th>
                                                <th>Transações</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($products as $product): ?>
                                                <?php if ($product['status'] == 'inactive'): ?>
                                                <tr class="table-warning">
                                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                    <td>R$ <?php echo number_format($product['price'], 2, ',', '.'); ?></td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($product['updated_at'])); ?></td>
                                                    <td>
                                                        <a href="transactions.php?product_id=<?php echo $product['id']; ?>" 
                                                           class="btn btn-sm btn-info">
                                                            <i class="bi bi-list-ul"></i> Ver Transações
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-success reactivate-product" data-id="<?php echo $product['id']; ?>">
                                                            <i class="bi bi-arrow-counterclockwise"></i> Reativar
                                                        </button>
                                                        <button class="btn btn-sm btn-danger delete-permanent" 
                                                                data-id="<?php echo $product['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($product['name']); ?>">
                                                            <i class="bi bi-trash-fill"></i> Excluir Permanentemente
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Produtos Cancelados -->
                            <div class="tab-pane fade" id="cancelled-products" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>Preço</th>
                                                <th>Data de Cancelamento</th>
                                                <th>Transações</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($products as $product): ?>
                                                <?php if ($product['status'] == 'cancelled'): ?>
                                                <tr class="table-secondary">
                                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                    <td>R$ <?php echo number_format($product['price'], 2, ',', '.'); ?></td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($product['updated_at'])); ?></td>
                                                    <td>
                                                        <a href="transactions.php?product_id=<?php echo $product['id']; ?>" 
                                                           class="btn btn-sm btn-info">
                                                            <i class="bi bi-list-ul"></i> Ver Transações
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-success reactivate-product" data-id="<?php echo $product['id']; ?>">
                                                            <i class="bi bi-arrow-counterclockwise"></i> Reativar
                                                        </button>
                                                        <button class="btn btn-sm btn-danger delete-permanent" 
                                                                data-id="<?php echo $product['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($product['name']); ?>">
                                                            <i class="bi bi-trash-fill"></i> Excluir Permanentemente
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Documentação Webhook -->
                            <div class="tab-pane fade" id="webhook-docs" role="tabpanel">
                                <div class="row">
                                    <div class="col-12">
                                        <h4 class="mb-4">Documentação do Webhook</h4>
                                        <p class="lead">Esta documentação explica como integrar e receber as notificações de pagamento via webhook.</p>

                                        <!-- URL do Webhook -->
                                        <div class="card mb-4">
                                            <div class="card-body">
                                                <h5>Integrando com seu Site</h5>
                                                <p>Configure a URL do seu webhook nas configurações para receber atualizações automáticas sobre o status dos pagamentos. Seu sistema receberá uma requisição POST com as informações da transação sempre que houver uma atualização.</p>

                                                <!-- URL de Callback Configurada -->
                                                <div class="card mb-4">
                                                    <div class="card-header bg-primary text-white">
                                                        <strong>Sua URL de Callback</strong>
                                                    </div>
                                                    <div class="card-body">
                                                        <?php if (!empty($merchant_webhook_url)): ?>
                                                            <p>Seu endpoint para receber as notificações está configurado como:</p>
                                                            <div class="alert alert-success">
                                                                <strong>URL:</strong> <code><?php echo htmlspecialchars($merchant_webhook_url); ?></code>
                                                            </div>
                                                            <p class="mb-0">
                                                                <i class="bi bi-info-circle"></i> 
                                                                Todas as notificações de pagamento serão enviadas para este endpoint via POST.
                                                            </p>
                                                        <?php else: ?>
                                                            <div class="alert alert-warning">
                                                                <i class="bi bi-exclamation-triangle"></i>
                                                                <strong>Atenção:</strong> Você ainda não configurou uma URL de callback.
                                                                <a href="settings.php" class="alert-link">Configure agora</a> para começar a receber 
                                                                notificações de pagamento.
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="alert alert-info mb-4">
                                                    <i class="bi bi-info-circle"></i> 
                                                    <strong>Importante:</strong> Configure sua URL de webhook em 
                                                    <a href="settings.php" class="alert-link">Configurações</a>. 
                                                    Esta URL deve estar acessível publicamente para receber nossas notificações.
                                                </div>

                                                <h6 class="mb-3">Notificações de Status</h6>
                                                <p>Seu sistema receberá as seguintes notificações:</p>

                                                <!-- Status: Aprovado -->
                                                <div class="card mb-3">
                                                    <div class="card-header bg-success text-white">
                                                        <strong>Pagamento Aprovado</strong>
                                                    </div>
                                                    <div class="card-body">
                                                        <pre class="bg-light p-3 rounded"><code>{
    "event": "payment.success",
    "data": {
        "transaction_id": "123e4567-e89b-12d3-a456-426614174000",
        "merchant_id": "<?php echo htmlspecialchars($_SESSION['merchant_id']); ?>",
        "product_id": "PROD123",
        "status": "approved",
        "amount": 99.90,
        "payment_method": "pix",
        "payer": {
            "name": "João Silva",
            "email": "joao@email.com",
            "document": "123.456.789-00"
        },
        "payment_date": "2024-01-20T15:30:45Z",
        "verification_token": "hash_de_verificacao"
    }
}</code></pre>
                                                    </div>
                                                </div>

                                                <!-- Status: Pendente -->
                                                <div class="card mb-3">
                                                    <div class="card-header bg-warning">
                                                        <strong>Pagamento Pendente</strong>
                                                    </div>
                                                    <div class="card-body">
                                                        <pre class="bg-light p-3 rounded"><code>{
    "event": "payment.pending",
    "data": {
        "transaction_id": "123e4567-e89b-12d3-a456-426614174000",
        "merchant_id": "<?php echo htmlspecialchars($_SESSION['merchant_id']); ?>",
        "product_id": "PROD123",
        "status": "pending",
        "amount": 99.90,
        "payment_method": "pix",
        "pix_expiration_date": "2024-01-20T16:30:45Z",
        "verification_token": "hash_de_verificacao"
    }
}</code></pre>
                                                    </div>
                                                </div>

                                                <!-- Status: Falha -->
                                                <div class="card mb-3">
                                                    <div class="card-header bg-danger text-white">
                                                        <strong>Pagamento Falhou</strong>
                                                    </div>
                                                    <div class="card-body">
                                                        <pre class="bg-light p-3 rounded"><code>{
    "event": "payment.failed",
    "data": {
        "transaction_id": "123e4567-e89b-12d3-a456-426614174000",
        "merchant_id": "<?php echo htmlspecialchars($_SESSION['merchant_id']); ?>",
        "product_id": "PROD123",
        "status": "failed",
        "amount": 99.90,
        "payment_method": "pix",
        "failure_reason": "expired",
        "verification_token": "hash_de_verificacao"
    }
}</code></pre>
                                                    </div>
                                                </div>

                                                <!-- Verificação de Segurança -->
                                                <div class="card mb-4">
                                                    <div class="card-header bg-primary text-white">
                                                        <strong>Verificação de Segurança</strong>
                                                    </div>
                                                    <div class="card-body">
                                                        <p>Para garantir a autenticidade das notificações, cada requisição inclui um token de verificação. Você deve validar este token usando o seguinte código:</p>
                                                        <pre class="bg-light p-3 rounded"><code><?php
// Recebe o payload
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Verifica o token de segurança
$expectedToken = hash_hmac(
    'sha256',
    $data['data']['transaction_id'] . $data['data']['merchant_id'],
    'sua_chave_secreta' // Configure nas suas variáveis de ambiente
);

if (hash_equals($expectedToken, $data['data']['verification_token'])) {
    // Token válido, processa a notificação
    switch ($data['event']) {
        case 'payment.success':
            // Pagamento aprovado - libere o produto/serviço
            break;
        case 'payment.pending':
            // Pagamento pendente - aguarde o PIX
            break;
        case 'payment.failed':
            // Pagamento falhou - cancele a transação
            break;
    }
    http_response_code(200);
    echo json_encode(['status' => 'success']);
} else {
    // Token inválido
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido']);
}
?></code></pre>
                                                    </div>
                                                </div>

                                                <div class="alert alert-warning">
                                                    <i class="bi bi-shield-check"></i> 
                                                    <strong>Dica de Segurança:</strong> Sempre valide o token de verificação antes de processar a notificação. 
                                                    Isso garante que a notificação realmente veio do nosso sistema.
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Exemplo de Implementação -->
                                        <div class="card mt-4">
                                            <div class="card-body">
                                                <h5>Exemplo de Implementação</h5>
                                                <p>Para validar e processar o webhook, use o seguinte código PHP:</p>
                                                <pre class="bg-light p-3 rounded"><code><?php
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

                        <a href="add_product.php" class="btn btn-success mt-3">
                            <i class="bi bi-plus-circle"></i> Adicionar Produto
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Últimas Transações</h5>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Produto</th>
                                        <th>Valor</th>
                                        <th>Status</th>
                                        <th>Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo $transaction['payment_id']; ?></td>
                                        <td><?php echo htmlspecialchars($transaction['product_name']); ?></td>
                                        <td>R$ <?php echo number_format($transaction['amount'], 2, ',', '.'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo getPaymentStatusBadgeClass($transaction['status']); ?>">
                                                <?php echo $transaction['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="transactions.php" class="btn btn-primary">Ver Todas as Transações</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
            <?php 
            echo htmlspecialchars($_SESSION['success_message']);
            unset($_SESSION['success_message']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
            <?php 
            echo htmlspecialchars($_SESSION['error_message']);
            unset($_SESSION['error_message']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Função para copiar link do checkout
            document.querySelectorAll('.copy-link').forEach(button => {
                button.addEventListener('click', async function() {
                    try {
                        const link = this.dataset.link;
                        await navigator.clipboard.writeText(link);
                        
                        // Mudar o ícone temporariamente para indicar sucesso
                        const icon = this.querySelector('i');
                        const originalClass = icon.className;
                        
                        // Atualizar o ícone e texto
                        icon.className = 'bi bi-clipboard-check';
                        this.title = 'Link copiado!';
                        
                        // Adicionar classe de sucesso
                        this.classList.add('btn-success');
                        this.classList.remove('btn-outline-primary');
                        
                        // Restaurar após 2 segundos
                        setTimeout(() => {
                            icon.className = originalClass;
                            this.title = 'Copiar Link';
                            this.classList.remove('btn-success');
                            this.classList.add('btn-outline-primary');
                        }, 2000);
                    } catch (err) {
                        console.error('Erro ao copiar:', err);
                        alert('Erro ao copiar o link. Por favor, tente novamente.');
                    }
                });
            });
        });

        // Função para confirmar e deletar produto
        document.querySelectorAll('.delete-product').forEach(button => {
            button.addEventListener('click', async function(e) {
                e.preventDefault();
                const productId = this.getAttribute('data-id');
                
                if (confirm('Tem certeza que deseja desativar/excluir este produto? Esta ação não pode ser desfeita.')) {
                    try {
                        const response = await fetch(`delete_product.php?id=${productId}`, {
                            method: 'GET',
                            headers: {
                                'Content-Type': 'application/json'
                            }
                        });

                        const result = await response.json();
                        if (response.ok) {
                            alert(result.message);
                            window.location.reload();
                        } else {
                            alert('Erro: ' + result.message);
                        }
                    } catch (error) {
                        alert('Erro ao processar a requisição: ' + error.message);
                    }
                }
            });
        });

        // Função para reativar produto
        document.querySelectorAll('.reactivate-product').forEach(button => {
            button.addEventListener('click', async function(e) {
                e.preventDefault();
                const productId = this.getAttribute('data-id');
                
                if (confirm('Deseja reativar este produto?')) {
                    try {
                        const formData = new FormData();
                        formData.append('product_id', productId);
                        
                        const response = await fetch('reactivate_product.php', {
                            method: 'POST',
                            body: formData
                        });

                        if (response.ok) {
                            window.location.reload();
                        } else {
                            const result = await response.text();
                            alert('Erro ao reativar o produto: ' + result);
                        }
                    } catch (error) {
                        alert('Erro ao reativar o produto: ' + error.message);
                    }
                }
            });
        });

        // Garantir que os links de edição funcionem
        document.querySelectorAll('a[href^="edit_product.php"]').forEach(link => {
            link.addEventListener('click', function(e) {
                const productId = this.getAttribute('href').split('=')[1];
                if (!productId) {
                    e.preventDefault();
                    alert('ID do produto não encontrado');
                }
            });
        });

        // Função para exclusão permanente
        document.querySelectorAll('.delete-permanent').forEach(button => {
            button.addEventListener('click', async function(e) {
                e.preventDefault();
                const productId = this.getAttribute('data-id');
                const productName = this.getAttribute('data-name');
                
                if (confirm(`ATENÇÃO: Você está prestes a excluir permanentemente o produto "${productName}" e todas as suas transações.\n\nEsta ação não pode ser desfeita. Deseja continuar?`)) {
                    try {
                        const response = await fetch(`delete_permanent.php?id=${productId}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            }
                        });

                        const result = await response.json();
                        if (response.ok) {
                            alert(result.message);
                            window.location.reload();
                        } else {
                            alert('Erro: ' + result.message);
                        }
                    } catch (error) {
                        alert('Erro ao excluir permanentemente o produto: ' + error.message);
                    }
                }
            });
        });
    </script>
</body>
</html>

<?php
function getPaymentLink($product) {
    $baseUrl = 'https://' . $_SERVER['HTTP_HOST'];
    
    // Define o callback_url apenas se não for dinâmico
    $callback_url = null;
    if (!empty($product['use_custom_callback']) && !empty($product['product_callback_url'])) {
        // Se o produto tem callback próprio configurado, usa ele
        $callback_url = $product['product_callback_url'];
    } elseif (empty($product['use_dynamic_callback']) && !empty($product['default_callback_url'])) {
        // Se não usa dinâmico, usa a URL padrão do comerciante
        $callback_url = $product['default_callback_url'];
    }
    
    $params = [
        'amount' => $product['price'],
        'product_id' => $product['id'],
        'merchant_id' => $_SESSION['merchant_id'],
        'product_name' => $product['name'],
        'merchant_name' => $_SESSION['merchant_name']
    ];
    
    // Adiciona o callback_url apenas se não for dinâmico
    if ($callback_url) {
        $params['callback_url'] = $callback_url;
    }
    
    // Adiciona a imagem apenas se existir
    if (!empty($product['file_path'])) {
        $params['product_image'] = $baseUrl . '/uploads/' . $product['file_path'];
    }
    
    return "{$baseUrl}/payment.php?" . http_build_query($params);
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'active':
            return 'success';
        case 'inactive':
            return 'secondary';
        case 'out_of_stock':
            return 'warning';
        default:
            return 'secondary';
    }
}

function getStatusLabel($status) {
    switch ($status) {
        case 'active':
            return 'Ativo';
        case 'inactive':
            return 'Inativo';
        case 'out_of_stock':
            return 'Fora de Estoque';
        default:
            return 'Desconhecido';
    }
}

function getPaymentStatusBadgeClass($status) {
    switch ($status) {
        case 'approved':
            return 'success';
        case 'pending':
            return 'warning';
        case 'rejected':
            return 'danger';
        default:
            return 'secondary';
    }
} 