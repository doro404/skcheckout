<?php
session_start();
require_once '../config/database.php';
require_once '../config.php';

// Verificar se o usuário está logado como comerciante
if (!isset($_SESSION['merchant_id'])) {
    header('Location: login.php');
    exit;
}

$success_message = '';
$error_message = '';

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
        $file_path = null;
        if (isset($_POST['has_image']) && $_POST['has_image'] === '1' && 
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

            // Gerar nome único para o arquivo
            $extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
            $file_path = 'product_' . uniqid() . '.' . $extension;

            // Mover o arquivo para a pasta de uploads
            if (!move_uploaded_file($_FILES['product_image']['tmp_name'], '../uploads/' . $file_path)) {
                throw new Exception("Erro ao fazer upload da imagem");
            }
        }

        // Inserir produto
        $stmt = $pdo->prepare("
            INSERT INTO products (
                merchant_id,
                name,
                description,
                price,
                status,
                type,
                delivery_type,
                delivery_info,
                access_duration,
                file_path,
                created_at,
                updated_at
            ) VALUES (
                :merchant_id,
                :name,
                :description,
                :price,
                :status,
                :type,
                :delivery_type,
                :delivery_info,
                :access_duration,
                :file_path,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
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

        $success_message = "Produto digital adicionado com sucesso!";

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
    <title>Adicionar Produto Digital - Painel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Adicionar Novo Produto Digital</h2>
            <a href="index.php" class="btn btn-secondary">Voltar</a>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="" class="needs-validation" enctype="multipart/form-data" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="name" class="form-label">Nome do Produto * <small class="text-muted">(máx. 100 caracteres)</small></label>
                            <input type="text" class="form-control" id="name" name="name" maxlength="100" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="description" class="form-label">Descrição</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label d-block">Adicionar Foto do Produto?</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="has_image" id="has_image_no" value="0" checked>
                                <label class="form-check-label" for="has_image_no">Não</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="has_image" id="has_image_yes" value="1">
                                <label class="form-check-label" for="has_image_yes">Sim</label>
                            </div>
                            <div id="image_upload_container" style="display: none; margin-top: 10px;">
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
                                       step="0.01" min="0.01" max="99999999.99" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="type" class="form-label">Tipo de Produto *</label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="">Selecione...</option>
                                <option value="ebook">E-book</option>
                                <option value="curso">Curso Online</option>
                                <option value="software">Software</option>
                                <option value="assinatura">Assinatura</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="delivery_type" class="form-label">Tipo de Entrega *</label>
                            <select class="form-select" id="delivery_type" name="delivery_type" required>
                                <option value="">Selecione...</option>
                                <option value="download">Download Direto</option>
                                <option value="email">Envio por Email</option>
                                <option value="acesso_online">Acesso Online</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active">Ativo</option>
                                <option value="inactive">Inativo</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="access_duration" class="form-label">Duração do Acesso <small class="text-muted">(máx. 50 caracteres)</small></label>
                            <input type="text" class="form-control" id="access_duration" name="access_duration" 
                                   maxlength="50" placeholder="Ex: 1 ano, Vitalício, 6 meses">
                        </div>
                        <div class="col-md-6">
                            <label for="delivery_info" class="form-label">Informações de Entrega</label>
                            <textarea class="form-control" id="delivery_info" name="delivery_info" rows="2"
                                    placeholder="Instruções específicas de entrega ou acesso"></textarea>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Adicionar Produto Digital
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