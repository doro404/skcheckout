<?php
session_start();
require_once 'config/database.php';
require_once 'config.php';

// Função para redirecionar com erro
function redirectWithError($callback_url, $error_message) {
    $separator = (parse_url($callback_url, PHP_URL_QUERY) ? '&' : '?');
    $redirect_url = $callback_url . $separator . http_build_query([
        'status' => 'error',
        'message' => $error_message
    ]);
    header("Location: " . $redirect_url);
    exit;
}

// Função para validar URL de callback
function isValidCallbackUrl($url) {
    // Log para debug
    error_log("[Checkout] Validando URL de callback: " . $url);
    
    // Verifica se a URL está presente
    if (empty($url)) {
        error_log("[Checkout] URL de callback não fornecida");
        return false;
    }
    
    // Valida a URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        error_log("[Checkout] URL inválida: não passou na validação FILTER_VALIDATE_URL");
        return false;
    }
    
    // Verifica se é HTTPS em produção
    if (isProduction()) {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme !== 'https') {
            error_log("[Checkout] URL inválida em produção: não é HTTPS");
            return false;
        }
    }
    
    return true;
}

try {
    // Log para debug
    error_log("[Checkout] Iniciando processamento. GET params: " . json_encode($_GET));
    
    // Verifica parâmetros obrigatórios
    $required_params = ['product_id', 'callback_url'];
    foreach ($required_params as $param) {
        if (!isset($_GET[$param]) || empty($_GET[$param])) {
            throw new Exception("Parâmetro {$param} é obrigatório");
        }
    }

    // Busca o produto e informações do comerciante
    $stmt = $pdo->prepare("
        SELECT p.*, m.id as merchant_id, m.name as merchant_name
        FROM products p 
        JOIN merchants m ON p.merchant_id = m.id 
        WHERE p.id = ? AND p.status = 'active'
    ");
    $stmt->execute([$_GET['product_id']]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        throw new Exception("Produto não encontrado ou inativo");
    }

    // Log do produto encontrado
    error_log("[Checkout] Produto encontrado: " . json_encode($product));

    // Prepara os parâmetros corretos baseados no produto
    $correct_params = [
        'product_id' => $product['id'],
        'merchant_id' => $product['merchant_id'],
        'amount' => $product['price'],
        'callback_url' => $_GET['callback_url'] ?? '',
        'product_name' => $product['name'],
        'merchant_name' => $product['merchant_name'],
        'product_image' => $product['image'] ?? $_GET['product_image'] ?? null
    ];

    // Verifica se precisamos redirecionar para corrigir os parâmetros
    $needs_redirect = false;
    $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $current_url .= "://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];

    // Verifica cada parâmetro
    foreach ($correct_params as $key => $value) {
        if (!isset($_GET[$key]) || $_GET[$key] != $value) {
            if (!in_array($key, ['callback_url', 'product_image'])) { // Ignora callback_url e product_image na comparação
                $needs_redirect = true;
                break;
            }
        }
    }

    // Verificação da URL de callback
    if (!isValidCallbackUrl($correct_params['callback_url'])) {
        throw new Exception("URL de callback inválida. Por favor, verifique se a URL está correta e use HTTPS em ambiente de produção.");
    }

    // Se precisar corrigir os parâmetros, redireciona
    if ($needs_redirect) {
        $redirect_url = $current_url . '?' . http_build_query($correct_params);
        error_log("[Checkout] Redirecionando para corrigir parâmetros: " . $redirect_url);
        header("Location: " . $redirect_url);
        exit;
    }

    // Se chegou até aqui, os parâmetros estão corretos
    // Armazena os dados na sessão para uso posterior
    $_SESSION['payment_data'] = [
        'amount' => floatval($product['price']),
        'product_id' => $product['id'],
        'callback_url' => $correct_params['callback_url'],
        'merchant_id' => $product['merchant_id'],
        'product_name' => $product['name'],
        'merchant_name' => $product['merchant_name'],
        'product_image' => $correct_params['product_image']
    ];

    // Log dos dados validados
    error_log("[Checkout] Dados validados com sucesso: " . json_encode($_SESSION['payment_data']));

} catch (Exception $e) {
    error_log("[Checkout] Erro na validação: " . $e->getMessage());
    
    // Se temos uma URL de callback, redirecionamos com o erro
    if (isset($_GET['callback_url']) && filter_var($_GET['callback_url'], FILTER_VALIDATE_URL)) {
        redirectWithError($_GET['callback_url'], $e->getMessage());
    } else {
        die("Erro: " . $e->getMessage());
    }
}

// Continua com o resto do código HTML
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento - <?php echo htmlspecialchars($_SESSION['payment_data']['merchant_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .product-image-container {
            position: relative;
            margin-bottom: 1.5rem;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            background: white;
        }
        .product-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
        }
        .product-info-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            color: white;
        }
        .merchant-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255,255,255,0.9);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .payment-method {
            padding: 15px;
            border-radius: 10px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .payment-method.selected {
            background-color: #e7f5ff;
            border-color: #0d6efd;
        }
        .form-control {
            padding: 0.75rem 1rem;
            border-radius: 8px;
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #495057;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .card-body {
            padding: 2rem;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
        }
        .btn-primary {
            background-color: #0d6efd;
            border: none;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
        }
        .payment-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            color: #212529;
        }
        .section-title i {
            color: #0d6efd;
        }
        .price-tag {
            font-size: 1.5rem;
            font-weight: 600;
            color: #198754;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="card">
            <div class="card-body">
                <div class="text-center mb-4">
                    <h4>Checkout Seguro</h4>
                    <div class="text-success d-flex align-items-center justify-content-center">
                        <i class="bi bi-shield-check me-2"></i>
                        Ambiente protegido
                    </div>
                </div>

                <?php if (!empty($_SESSION['payment_data']['product_image'])): ?>
                <div class="product-image-container">
                    <img src="<?php echo htmlspecialchars($_SESSION['payment_data']['product_image']); ?>" 
                         alt="<?php echo htmlspecialchars($_SESSION['payment_data']['product_name']); ?>"
                         class="product-image">
                    <div class="merchant-badge">
                        <i class="bi bi-shop"></i>
                        <?php echo htmlspecialchars($_SESSION['payment_data']['merchant_name']); ?>
                    </div>
                    <div class="product-info-overlay">
                        <h5 class="mb-2"><?php echo htmlspecialchars($_SESSION['payment_data']['product_name']); ?></h5>
                        <div class="price-tag">R$ <?php echo number_format($_SESSION['payment_data']['amount'], 2, ',', '.'); ?></div>
                    </div>
                </div>
                <?php else: ?>
                <div class="payment-section">
                    <div class="section-title">
                        <i class="bi bi-cart-check"></i>
                        <h5 class="mb-0">Detalhes do Pedido</h5>
                    </div>
                    <p class="mb-2">Produto: <?php echo htmlspecialchars($_SESSION['payment_data']['product_name']); ?></p>
                    <div class="price-tag">R$ <?php echo number_format($_SESSION['payment_data']['amount'], 2, ',', '.'); ?></div>
                </div>
                <?php endif; ?>

                <div class="payment-section">
                    <div class="section-title">
                        <i class="bi bi-credit-card"></i>
                        <h5 class="mb-0">Método de Pagamento</h5>
                    </div>
                    <div class="payment-method selected">
                        <img src="assets/icon/pix.png" alt="PIX" width="32" height="32">
                        <span>PIX - Pagamento Instantâneo</span>
                    </div>
                </div>

                <div class="payment-section">
                    <div class="section-title">
                        <i class="bi bi-person-lines-fill"></i>
                        <h5 class="mb-0">Dados para Faturamento</h5>
                    </div>

                    <form id="payment-form" action="process_payment.php" method="POST">
                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($_SESSION['payment_data']['product_id']); ?>">
                        <input type="hidden" name="callback_url" value="<?php echo htmlspecialchars($_SESSION['payment_data']['callback_url']); ?>">
                        <input type="hidden" name="merchant_id" value="<?php echo htmlspecialchars($_SESSION['payment_data']['merchant_id']); ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Nome</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="name" name="name" placeholder="Nome Completo" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="lastname" class="form-label">Sobrenome</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="lastname" name="lastname" placeholder="Sobrenome" required>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="cpf" class="form-label">CPF</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-card-text"></i></span>
                                    <input type="text" class="form-control" id="cpf" name="cpf" placeholder="000.000.000-00" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Telefone</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="(00) 00000-0000" required>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" placeholder="seu@email.com" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="cep" class="form-label">CEP</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                    <input type="text" class="form-control" id="cep" name="cep" placeholder="00000-000" required>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="address" class="form-label">Endereço</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-house"></i></span>
                                    <input type="text" class="form-control" id="address" name="address" placeholder="Rua, Avenida, etc" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label for="number" class="form-label">Número</label>
                                <input type="text" class="form-control" id="number" name="number" placeholder="Nº" required>
                            </div>
                            <div class="col-md-4">
                                <label for="neighborhood" class="form-label">Bairro</label>
                                <input type="text" class="form-control" id="neighborhood" name="neighborhood" placeholder="Bairro" required>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="city" class="form-label">Cidade</label>
                                <input type="text" class="form-control" id="city" name="city" placeholder="Cidade" required>
                            </div>
                            <div class="col-md-6">
                                <label for="state" class="form-label">Estado</label>
                                <input type="text" class="form-control" id="state" name="state" placeholder="Estado" required>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="<?php echo htmlspecialchars($_SESSION['payment_data']['callback_url']); ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Voltar à Loja
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-lock me-2"></i>Pagar com PIX
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-3 small text-muted">
            <i class="bi bi-shield-check me-1"></i>
            Pagamento 100% Seguro
            <span class="mx-2">|</span>
            Desenvolvido por Saika | 🇧🇷.
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <script>
        document.getElementById('payment-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            const formData = new FormData(form);
            const submitButton = form.querySelector('button[type="submit"]');
            
            // Mostrar loading
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="bi bi-arrow-repeat spin me-2"></i>Processando...';
            
            fetch('process_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.href = data.redirect;
                } else {
                    alert('Erro: ' + data.message);
                    submitButton.disabled = false;
                    submitButton.innerHTML = '<i class="bi bi-lock me-2"></i>Pagar com PIX';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao processar pagamento. Por favor, tente novamente.');
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="bi bi-lock me-2"></i>Pagar com PIX';
            });
        });

        // Máscaras para os campos
        document.getElementById('cpf').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                e.target.value = value;
            }
        });

        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                e.target.value = value;
            }
        });

        document.getElementById('cep').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 8) {
                value = value.replace(/(\d{5})(\d{3})/, '$1-$2');
                e.target.value = value;
            }
        });
    </script>
</body>
</html> 