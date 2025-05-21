<?php
session_start();
require_once '../config/database.php';
require_once '../config.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['merchant_id'])) {
    header('Location: login.php');
    exit;
}

// Verifica se é uma edição
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;

if ($product_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND merchant_id = ?");
    $stmt->execute([$product_id, $_SESSION['merchant_id']]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header('Location: products.php');
        exit;
    }
}

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = $_POST['name'];
        $description = $_POST['description'];
        $price = str_replace(',', '.', $_POST['price']);
        $delivery_type = $_POST['delivery_type'];
        $product_callback_url = $_POST['product_callback_url'] ?? null;
        $use_custom_callback = isset($_POST['use_custom_callback']) ? 1 : 0;

        // Validações básicas
        if (empty($name) || empty($price)) {
            throw new Exception("Nome e preço são obrigatórios");
        }

        if (empty($delivery_type)) {
            throw new Exception("Tipo de entrega é obrigatório");
        }

        // Processa upload do arquivo apenas se for tipo download
        $file_path = $product['file_path'] ?? null;
        $file_name = $product['file_name'] ?? null;

        if ($delivery_type === 'download') {
            // Se é um novo produto ou foi enviado um novo arquivo
            if (!$product_id || (isset($_FILES['product_file']) && $_FILES['product_file']['error'] === UPLOAD_ERR_OK)) {
                if (!isset($_FILES['product_file']) || $_FILES['product_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Arquivo é obrigatório para entrega por download");
                }

                $temp_name = $_FILES['product_file']['tmp_name'];
                $original_name = $_FILES['product_file']['name'];
                
                // Gera um nome único para o arquivo
                $file_name = $original_name;
                $file_path = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $original_name);
                
                // Move o arquivo para a pasta de uploads
                if (!move_uploaded_file($temp_name, UPLOAD_PATH . '/' . $file_path)) {
                    throw new Exception("Erro ao fazer upload do arquivo");
                }
            }
        } else {
            // Se não é download, remove arquivo existente
            if ($file_path && file_exists(UPLOAD_PATH . '/' . $file_path)) {
                unlink(UPLOAD_PATH . '/' . $file_path);
            }
            $file_path = null;
            $file_name = null;
        }

        if ($product_id > 0) {
            // Atualização
            $stmt = $pdo->prepare("
                UPDATE products 
                SET name = ?, description = ?, price = ?, delivery_type = ?, 
                    file_path = ?, file_name = ?, updated_at = CURRENT_TIMESTAMP,
                    product_callback_url = ?, use_custom_callback = ?
                WHERE id = ? AND merchant_id = ?
            ");
            $stmt->execute([
                $name, $description, $price, $delivery_type,
                $file_path, $file_name, $product_callback_url, $use_custom_callback,
                $product_id, $_SESSION['merchant_id']
            ]);
        } else {
            // Inserção
            $stmt = $pdo->prepare("
                INSERT INTO products (
                    merchant_id, name, description, price, 
                    delivery_type, file_path, file_name,
                    product_callback_url, use_custom_callback
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['merchant_id'], $name, $description, $price,
                $delivery_type, $file_path, $file_name,
                $product_callback_url, $use_custom_callback
            ]);
        }

        $_SESSION['success_message'] = "Produto " . ($product_id ? "atualizado" : "cadastrado") . " com sucesso!";
        header('Location: products.php');
        exit;

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product_id ? 'Editar' : 'Novo'; ?> Produto - Painel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <h2 class="mb-4">
            <i class="bi bi-<?php echo $product_id ? 'pencil-square' : 'plus-circle'; ?> me-2"></i>
            <?php echo $product_id ? 'Editar' : 'Novo'; ?> Produto
        </h2>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php 
                echo htmlspecialchars($_SESSION['error_message']);
                unset($_SESSION['error_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">Informações do Produto</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label for="name" class="form-label fw-bold">Nome do Produto</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required>
                        <small class="text-muted">
                            Nome que será exibido na página de pagamento e nas notificações enviadas ao cliente.
                        </small>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label fw-bold">Descrição</label>
                        <textarea class="form-control" id="description" name="description" rows="4"
                        ><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                        <small class="text-muted">
                            Descreva detalhes sobre o produto, suas características e benefícios. Esta descrição será exibida na página de pagamento.
                        </small>
                    </div>

                    <div class="mb-4">
                        <label for="price" class="form-label fw-bold">Preço</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" class="form-control" id="price" name="price" 
                                   value="<?php echo htmlspecialchars(number_format($product['price'] ?? 0, 2, ',', '.')); ?>"
                                   required>
                        </div>
                        <small class="text-muted">
                            Use vírgula como separador decimal (ex: 99,90). Este é o valor que será cobrado via PIX.
                        </small>
                    </div>

                    <div class="mb-4">
                        <label for="delivery_type" class="form-label fw-bold">Tipo de Entrega</label>
                        <select class="form-select" id="delivery_type" name="delivery_type" required>
                            <option value="">Selecione o tipo de entrega</option>
                            <option value="download" <?php echo ($product['delivery_type'] ?? '') === 'download' ? 'selected' : ''; ?>>
                                Download Digital
                            </option>
                            <option value="physical" <?php echo ($product['delivery_type'] ?? '') === 'physical' ? 'selected' : ''; ?>>
                                Entrega Física
                            </option>
                            <option value="service" <?php echo ($product['delivery_type'] ?? '') === 'service' ? 'selected' : ''; ?>>
                                Serviço
                            </option>
                        </select>
                        <small class="text-muted">
                            <ul class="mt-2 ps-3">
                                <li><strong>Download Digital:</strong> Arquivo enviado automaticamente por email após a aprovação do pagamento</li>
                                <li><strong>Entrega Física:</strong> Produtos que precisam ser enviados fisicamente ao cliente</li>
                                <li><strong>Serviço:</strong> Contratação de serviços ou assinaturas</li>
                            </ul>
                        </small>
                    </div>

                    <!-- Configurações de Callback do Produto -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Configurações de Retorno (Callback)</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="use_custom_callback" 
                                       name="use_custom_callback" 
                                       <?php echo ($product['use_custom_callback'] ?? false) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="use_custom_callback">
                                    Usar URL de retorno específica para este produto
                                </label>
                            </div>

                            <div id="callbackUrlSection" class="mb-3" style="display: none;">
                                <label for="product_callback_url" class="form-label">URL de Retorno do Produto</label>
                                <input type="url" class="form-control" id="product_callback_url" 
                                       name="product_callback_url" 
                                       value="<?php echo htmlspecialchars($product['product_callback_url'] ?? ''); ?>"
                                       placeholder="https://seu-site.com/retorno-produto">
                                <small class="text-muted">
                                    Esta URL terá prioridade sobre as configurações gerais de callback do comerciante.
                                </small>
                            </div>

                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Se não configurar uma URL específica, o sistema usará as configurações de retorno definidas no painel do comerciante.
                            </div>
                        </div>
                    </div>

                    <div id="fileUploadSection" class="mb-4 p-3 bg-light rounded" style="display: none;">
                        <label for="product_file" class="form-label fw-bold">Arquivo do Produto</label>
                        <input type="file" class="form-control" id="product_file" name="product_file">
                        <?php if (!empty($product['file_name'])): ?>
                            <div class="alert alert-info mt-2">
                                <i class="bi bi-file-earmark me-2"></i>
                                Arquivo atual: <strong><?php echo htmlspecialchars($product['file_name']); ?></strong>
                                <small>(Só será substituído se você enviar um novo arquivo)</small>
                            </div>
                        <?php endif; ?>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            Este arquivo será enviado automaticamente ao cliente por email após a confirmação do pagamento.
                            Formatos suportados: PDF, ZIP, RAR, DOC, DOCX, XLS, XLSX, JPG, PNG (Máx: 20MB)
                        </small>
                    </div>

                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <a href="products.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Voltar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-<?php echo $product_id ? 'check2-circle' : 'plus-lg'; ?> me-2"></i>
                            <?php echo $product_id ? 'Atualizar' : 'Cadastrar'; ?> Produto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Formata o campo de preço
        document.getElementById('price').addEventListener('blur', function(e) {
            const value = this.value.replace(/[^\d,]/g, '').replace(',', '.');
            if (value && !isNaN(parseFloat(value))) {
                const formatted = parseFloat(value).toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                this.value = formatted;
            }
        });
        
        // Controla a visibilidade do campo de upload
        document.getElementById('delivery_type').addEventListener('change', function() {
            const fileSection = document.getElementById('fileUploadSection');
            const fileInput = document.getElementById('product_file');
            
            if (this.value === 'download') {
                fileSection.style.display = 'block';
                <?php if (!$product_id): ?>
                fileInput.required = true;
                <?php endif; ?>
            } else {
                fileSection.style.display = 'none';
                fileInput.required = false;
            }
        });

        // Dispara o evento change ao carregar a página
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('delivery_type').dispatchEvent(new Event('change'));
        });

        // Controla a visibilidade da seção de callback
        document.getElementById('use_custom_callback').addEventListener('change', function() {
            const callbackSection = document.getElementById('callbackUrlSection');
            callbackSection.style.display = this.checked ? 'block' : 'none';
            
            // Se desmarcar, limpa o campo
            if (!this.checked) {
                document.getElementById('product_callback_url').value = '';
            }
        });

        // Inicializa o estado da seção de callback
        document.addEventListener('DOMContentLoaded', function() {
            const useCustomCallback = document.getElementById('use_custom_callback');
            const callbackSection = document.getElementById('callbackUrlSection');
            callbackSection.style.display = useCustomCallback.checked ? 'block' : 'none';
        });
    </script>
</body>
</html> 