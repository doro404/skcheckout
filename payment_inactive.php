<?php
session_start();
require_once 'config/database.php';
require_once 'config.php';

// Pega os parâmetros da URL
$product_id = $_GET['product_id'] ?? null;
$merchant_id = $_GET['merchant_id'] ?? null;

// Busca informações do produto se disponível
$product_name = '';
$merchant_name = '';
if ($product_id && $merchant_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.name as product_name, m.name as merchant_name 
            FROM products p 
            JOIN merchants m ON p.merchant_id = m.id 
            WHERE p.id = ? AND m.id = ?
        ");
        $stmt->execute([$product_id, $merchant_id]);
        $result = $stmt->fetch();
        if ($result) {
            $product_name = $result['product_name'];
            $merchant_name = $result['merchant_name'];
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar informações do produto: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produto Indisponível</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            text-align: center;
            padding: 2rem;
            max-width: 600px;
            width: 100%;
        }
        .error-icon {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 1.5rem;
        }
        .error-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 1.5rem;
        }
        .merchant-info {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 1rem;
        }
        .support-link {
            color: #0d6efd;
            text-decoration: none;
            font-weight: 500;
        }
        .support-link:hover {
            text-decoration: underline;
        }
        .animated {
            animation: fadeInUp 0.5s ease-out;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-container animated">
            <div class="error-card">
                <i class="bi bi-exclamation-circle error-icon"></i>
                <h1 class="h3 mb-4">Link de Pagamento Indisponível</h1>
                
                <?php if ($product_name): ?>
                <p class="mb-4">
                    O produto <strong><?php echo htmlspecialchars($product_name); ?></strong> 
                    não está disponível para compra no momento.
                </p>
                <?php else: ?>
                <p class="mb-4">
                    Este link de pagamento está inativo ou o produto não está mais disponível para compra.
                </p>
                <?php endif; ?>

                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle me-2"></i>
                    Isso pode ocorrer pelos seguintes motivos:
                    <ul class="text-start mt-2 mb-0">
                        <li>O produto foi desativado pelo vendedor</li>
                        <li>O link de pagamento expirou</li>
                        <li>O produto está temporariamente indisponível</li>
                    </ul>
                </div>

                <?php if ($merchant_name): ?>
                <div class="merchant-info">
                    <p class="mb-2">Para mais informações, entre em contato com:</p>
                    <strong><?php echo htmlspecialchars($merchant_name); ?></strong>
                </div>
                <?php endif; ?>
            </div>

            <a href="<?php echo BASE_URL; ?>" class="btn btn-primary">
                <i class="bi bi-house-door me-2"></i>Voltar para Página Inicial
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 