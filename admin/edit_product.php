<?php
session_start();
require_once '../config/database.php';
require_once '../config.php';

// Verificar se o usuário está logado como comerciante
if (!isset($_SESSION['merchant_id'])) {
    $_SESSION['error_message'] = "Você precisa estar logado para editar produtos.";
    header('Location: login.php');
    exit;
}

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "ID do produto inválido.";
    header('Location: index.php');
    exit;
}

$product_id = (int)$_GET['id'];
$success_message = '';
$error_message = '';

// Buscar dados do produto
try {
    $stmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE id = :id AND merchant_id = :merchant_id
    ");
    $stmt->execute([
        'id' => $product_id,
        'merchant_id' => $_SESSION['merchant_id']
    ]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $_SESSION['error_message'] = "Produto não encontrado ou sem permissão para editar.";
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = "Erro ao carregar produto: " . $e->getMessage();
    header('Location: index.php');
    exit;
}

// Processar formulário de edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar dados obrigatórios
        $required_fields = [
            'name' => 'Nome do produto',
            'price' => 'Preço',
            'status' => 'Status',
            'type' => 'Tipo de produto',
            'delivery_type' => 'Tipo de entrega'
        ];

        foreach ($required_fields as $field => $label) {
            if (empty($_POST[$field])) {
                throw new Exception("O campo {$label} é obrigatório");
            }
        }

        // Validar tamanho do nome (varchar(100))
        if (strlen($_POST['name']) > 100) {
            throw new Exception("O nome do produto não pode ter mais de 100 caracteres");
        }

        // Validar preço (decimal(10,2))
        if (!is_numeric($_POST['price']) || $_POST['price'] <= 0 || strlen(intval($_POST['price'])) > 8) {
            throw new Exception("Preço inválido. Deve ser um valor positivo com no máximo 8 dígitos antes da vírgula e 2 depois");
        }

        // Validar duração de acesso (varchar(50))
        if (!empty($_POST['access_duration']) && strlen($_POST['access_duration']) > 50) {
            throw new Exception("A duração do acesso não pode ter mais de 50 caracteres");
        }

        // Processar upload da foto
        $file_path = $product['file_path'];
        
        // Se escolheu não ter imagem, remove a existente
        if (isset($_POST['has_image']) && $_POST['has_image'] === '0') {
            if ($file_path && file_exists('../uploads/' . $file_path)) {
                unlink('../uploads/' . $file_path);
            }
            $file_path = null;
        }
        // Se escolheu ter imagem e enviou uma nova
        elseif (isset($_POST['has_image']) && $_POST['has_image'] === '1' && 
                isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            
            // Validar tipo de arquivo
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($file_info, $_FILES['product_image']['tmp_name']);
            finfo_close($file_info);

            if (!in_array($mime_type, $allowed_types)) {
                throw new Exception("Tipo de arquivo não permitido. Use apenas JPG, PNG ou GIF.");
            }

            // Validar tamanho (2MB)
            if ($_FILES['product_image']['size'] > 2 * 1024 * 1024) {
                throw new Exception("O arquivo é muito grande. Tamanho máximo permitido: 2MB");
            }

            // Remove arquivo antigo se existir
            if ($file_path && file_exists('../uploads/' . $file_path)) {
                unlink('../uploads/' . $file_path);
            }

            // Gerar nome único para o arquivo
            $extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
            $file_path = 'product_' . uniqid() . '.' . $extension;

            // Mover o arquivo para a pasta de uploads
            if (!move_uploaded_file($_FILES['product_image']['tmp_name'], '../uploads/' . $file_path)) {
                throw new Exception("Erro ao fazer upload da imagem");
            }
        }

        // Atualizar produto
        $stmt = $pdo->prepare("
            UPDATE products SET
                name = :name,
                description = :description,
                price = :price,
                status = :status,
                type = :type,
                delivery_type = :delivery_type,
                delivery_info = :delivery_info,
                access_duration = :access_duration,
                file_path = :file_path,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND merchant_id = :merchant_id
        ");

        $stmt->execute([
            'id' => $product_id,
            'merchant_id' => $_SESSION['merchant_id'],
            'name' => $_POST['name'],
            'description' => $_POST['description'] ?: null,
            'price' => $_POST['price'],
            'status' => $_POST['status'],
            'type' => $_POST['type'],
            'delivery_type' => $_POST['delivery_type'],
            'delivery_info' => $_POST['delivery_info'] ?: null,
            'access_duration' => $_POST['access_duration'] ?: null,
            'file_path' => $file_path
        ]);

        $success_message = "Produto atualizado com sucesso!";
        
        // Atualizar dados do produto na variável
        $product = array_merge($product, $_POST);

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Produto - Painel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Editar Produto</h2>
            <a href="index.php" class="btn btn-secondary">Voltar</a>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="" class="needs-validation" enctype="multipart/form-data" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="name" class="form-label">Nome do Produto * <small class="text-muted">(máx. 100 caracteres)</small></label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($product['name']); ?>" 
                                   maxlength="100" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="description" class="form-label">Descrição</label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                            ><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label d-block">Foto do Produto</label>
                            <?php if (!empty($product['file_path'])): ?>
                                <div class="mb-2">
                                    <img src="../uploads/<?php echo htmlspecialchars($product['file_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         class="img-thumbnail" style="max-width: 150px;">
                                </div>
                            <?php endif; ?>
                            
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="has_image" id="has_image_no" 
                                       value="0" <?php echo empty($product['file_path']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="has_image_no">Sem foto</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="has_image" id="has_image_yes" 
                                       value="1" <?php echo !empty($product['file_path']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="has_image_yes">Com foto</label>
                            </div>
                            
                            <div id="image_upload_container" style="display: <?php echo !empty($product['file_path']) ? 'block' : 'none'; ?>; margin-top: 10px;">
                                <input type="file" class="form-control" id="product_image" name="product_image" accept="image/*">
                                <div class="form-text">Formatos aceitos: JPG, PNG, GIF. Tamanho máximo: 2MB</div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label for="price" class="form-label">Preço *</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" class="form-control" id="price" name="price" 
                                       value="<?php echo number_format($product['price'], 2, '.', ''); ?>"
                                       step="0.01" min="0.01" max="99999999.99" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="type" class="form-label">Tipo de Produto *</label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="">Selecione...</option>
                                <option value="ebook" <?php echo $product['type'] == 'ebook' ? 'selected' : ''; ?>>E-book</option>
                                <option value="curso" <?php echo $product['type'] == 'curso' ? 'selected' : ''; ?>>Curso Online</option>
                                <option value="software" <?php echo $product['type'] == 'software' ? 'selected' : ''; ?>>Software</option>
                                <option value="assinatura" <?php echo $product['type'] == 'assinatura' ? 'selected' : ''; ?>>Assinatura</option>
                                <option value="outro" <?php echo $product['type'] == 'outro' ? 'selected' : ''; ?>>Outro</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="delivery_type" class="form-label">Tipo de Entrega *</label>
                            <select class="form-select" id="delivery_type" name="delivery_type" required>
                                <option value="">Selecione...</option>
                                <option value="download" <?php echo $product['delivery_type'] == 'download' ? 'selected' : ''; ?>>Download Direto</option>
                                <option value="email" <?php echo $product['delivery_type'] == 'email' ? 'selected' : ''; ?>>Envio por Email</option>
                                <option value="acesso_online" <?php echo $product['delivery_type'] == 'acesso_online' ? 'selected' : ''; ?>>Acesso Online</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?php echo $product['status'] == 'active' ? 'selected' : ''; ?>>Ativo</option>
                                <option value="inactive" <?php echo $product['status'] == 'inactive' ? 'selected' : ''; ?>>Inativo</option>
                                <option value="cancelled" <?php echo $product['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelado</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="access_duration" class="form-label">Duração do Acesso <small class="text-muted">(máx. 50 caracteres)</small></label>
                            <input type="text" class="form-control" id="access_duration" name="access_duration" 
                                   value="<?php echo htmlspecialchars($product['access_duration'] ?? ''); ?>"
                                   maxlength="50" placeholder="Ex: 1 ano, Vitalício, 6 meses">
                        </div>
                        <div class="col-md-6">
                            <label for="delivery_info" class="form-label">Informações de Entrega</label>
                            <textarea class="form-control" id="delivery_info" name="delivery_info" rows="2"
                                    placeholder="Instruções específicas de entrega ou acesso"
                            ><?php echo htmlspecialchars($product['delivery_info'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validação do formulário
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()

        // Formatação de moeda
        document.getElementById('price').addEventListener('input', function(e) {
            let value = e.target.value;
            if (value === '') return;
            
            // Limita a 8 dígitos antes da vírgula e 2 depois
            let parts = value.split('.');
            if (parts[0] && parts[0].length > 8) {
                parts[0] = parts[0].slice(0, 8);
            }
            if (parts[1] && parts[1].length > 2) {
                parts[1] = parts[1].slice(0, 2);
            }
            value = parts.join('.');
            
            value = parseFloat(value).toFixed(2);
            if (!isNaN(value)) {
                e.target.value = value;
            }
        });

        // Toggle do upload de imagem
        document.querySelectorAll('input[name="has_image"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const container = document.getElementById('image_upload_container');
                const imageInput = document.getElementById('product_image');
                
                if (this.value === '1') {
                    container.style.display = 'block';
                } else {
                    container.style.display = 'none';
                    imageInput.value = ''; // Limpa o input quando desabilita
                }
            });
        });
    </script>
</body>
</html> 