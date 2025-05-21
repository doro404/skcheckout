<?php
session_start();
require_once '../config/database.php';
require_once '../config.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['merchant_id'])) {
    header('Location: login.php');
    exit;
}

// Processa o formulário de atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Prepara os dados do callback baseado nas escolhas do usuário
        $use_dynamic_callback = isset($_POST['use_dynamic_callback']) ? 1 : 0;
        $default_callback_url = null;

        // Se não usar callback dinâmico e tiver URL definida, usa ela
        if (!$use_dynamic_callback && !empty($_POST['default_callback_url'])) {
            $default_callback_url = $_POST['default_callback_url'];
        }

        // Atualiza as configurações do comerciante
        $stmt = $pdo->prepare("
            UPDATE merchants 
            SET 
                name = :name,
                webhook_url = :webhook_url,
                email = :email,
                default_callback_url = :default_callback_url,
                use_dynamic_callback = :use_dynamic_callback
            WHERE id = :merchant_id
        ");
        
        $stmt->execute([
            'name' => $_POST['merchant_name'],
            'webhook_url' => $_POST['webhook_url'],
            'email' => $_POST['email'],
            'default_callback_url' => $default_callback_url,
            'use_dynamic_callback' => $use_dynamic_callback,
            'merchant_id' => $_SESSION['merchant_id']
        ]);

        // Se uma nova senha foi fornecida, atualiza a senha
        if (!empty($_POST['new_password'])) {
            $stmt = $pdo->prepare("UPDATE merchants SET password = :password WHERE id = :merchant_id");
            $stmt->execute([
                'password' => password_hash($_POST['new_password'], PASSWORD_DEFAULT),
                'merchant_id' => $_SESSION['merchant_id']
            ]);
        }

        // Se for admin, atualiza as configurações do sistema
        if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] && isset($_POST['settings'])) {
            $stmt = $pdo->prepare("
                UPDATE system_settings 
                SET setting_value = :value 
                WHERE setting_key = :key
            ");

            foreach ($_POST['settings'] as $key => $value) {
                $stmt->execute([
                    'key' => $key,
                    'value' => $value
                ]);
            }
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Configurações atualizadas com sucesso!";
        header('Location: settings.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Erro ao atualizar configurações: " . $e->getMessage();
    }
}

// Busca as configurações do comerciante
$stmt = $pdo->prepare("SELECT * FROM merchants WHERE id = ?");
$stmt->execute([$_SESSION['merchant_id']]);
$merchant = $stmt->fetch();

// Verifica se é admin
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

// Se for admin, busca as configurações do sistema
$system_settings = [];
if ($is_admin) {
    $stmt = $pdo->query("SELECT * FROM system_settings ORDER BY setting_key");
    while ($row = $stmt->fetch()) {
        $system_settings[$row['setting_key']] = $row;
    }
}

include 'includes/header.php';
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Painel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/transitions.css" rel="stylesheet">
</head>
<body>


    <div class="container mt-4 animate-on-load">
        <h2 class="mb-4">Configurações</h2>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <!-- Configurações do Comerciante -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Configurações do Comerciante</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="merchant_name" class="form-label">Nome do Comerciante</label>
                        <input type="text" class="form-control" id="merchant_name" name="merchant_name" value="<?php echo htmlspecialchars($merchant['name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($merchant['email']); ?>" required>
                    </div>

                    <!-- Configurações de Callback -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Configurações de Retorno (Callback)</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="default_callback_url" class="form-label">URL de Retorno Padrão</label>
                                <input type="url" class="form-control" id="default_callback_url" name="default_callback_url" 
                                       value="<?php echo htmlspecialchars($merchant['default_callback_url'] ?? ''); ?>"
                                       placeholder="https://seu-site.com/retorno">
                                <small class="text-muted">URL padrão para onde o cliente será redirecionado após o pagamento</small>
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="use_dynamic_callback" 
                                           name="use_dynamic_callback" 
                                           <?php echo ($merchant['use_dynamic_callback'] ?? false) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="use_dynamic_callback">
                                        Usar Retorno Dinâmico
                                    </label>
                                </div>
                                <small class="text-muted">
                                    Se ativado, o sistema usará o Referer ou parâmetro de URL como endereço de retorno. 
                                    Caso contrário, usará sempre a URL padrão acima.
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="webhook_url" class="form-label">URL do Webhook (Opcional)</label>
                        <input type="url" class="form-control" id="webhook_url" name="webhook_url" value="<?php echo htmlspecialchars($merchant['webhook_url']); ?>">
                        <small class="text-muted">URL para receber notificações de pagamento em seu site ou integração recebendo um webhook com dados quando um cliente compra</small>
                    </div>

                    <!-- Seção de Alteração de Senha -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Alterar Senha</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Nova Senha</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                                <small class="text-muted">Deixe em branco se não desejar alterar a senha</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($is_admin): ?>
            <!-- Configurações do Sistema (apenas para admin) -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Configurações do Sistema</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($system_settings as $key => $setting): ?>
                    <div class="mb-3">
                        <label for="<?php echo $key; ?>" class="form-label">
                            <?php echo htmlspecialchars($setting['setting_description']); ?>
                        </label>
                        <?php if ($key === 'environment'): ?>
                        <select class="form-select" id="<?php echo $key; ?>" name="settings[<?php echo $key; ?>]">
                            <option value="development" <?php echo $setting['setting_value'] === 'development' ? 'selected' : ''; ?>>Development</option>
                            <option value="production" <?php echo $setting['setting_value'] === 'production' ? 'selected' : ''; ?>>Production</option>
                        </select>
                        <?php else: ?>
                        <input type="<?php echo $setting['setting_key'] === 'mp_access_token' ? 'password' : 'text'; ?>" 
                               class="form-control" 
                               id="<?php echo $key; ?>" 
                               name="settings[<?php echo $key; ?>]" 
                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                               <?php echo $setting['is_public'] ? '' : 'autocomplete="off"'; ?>>
                        <?php endif; ?>
                        <?php if (!$setting['is_public']): ?>
                        <small class="text-muted">Este é um valor sensível. Mantenha-o seguro.</small>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Salvar Configurações
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

        // Controle do campo de URL padrão baseado no checkbox de callback dinâmico
        const useDynamicCallback = document.getElementById('use_dynamic_callback');
        const defaultCallbackUrl = document.getElementById('default_callback_url');

        function updateUrlField() {
            if (useDynamicCallback.checked) {
                defaultCallbackUrl.value = ''; // Limpa o campo
                defaultCallbackUrl.disabled = true;
                defaultCallbackUrl.placeholder = 'URL de retorno será definida dinamicamente';
            } else {
                defaultCallbackUrl.disabled = false;
                defaultCallbackUrl.placeholder = 'https://seu-site.com/retorno';
            }
        }

        // Executa quando a página carrega
        updateUrlField();

        // Executa quando o checkbox muda
        useDynamicCallback.addEventListener('change', updateUrlField);
    });
    </script>
</body>

<?php include 'includes/footer.php'; ?> 