<?php
session_start();
require_once '../config/database.php';
require_once '../config.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['merchant_id'])) {
    header('Location: login.php');
    exit;
}

// Configuração da paginação
$itens_por_pagina = 15;
$pagina_atual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Adiciona parâmetros de busca e ordenação
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC';

// Modifica a query de contagem para incluir filtros
$sql_count = "SELECT COUNT(*) as total FROM products p WHERE p.merchant_id = :merchant_id";
$params = ['merchant_id' => $_SESSION['merchant_id']];

if ($search) {
    $sql_count .= " AND (p.name LIKE :search OR p.description LIKE :search)";
    $params['search'] = "%{$search}%";
}

if ($status_filter) {
    $sql_count .= " AND p.status = :status";
    $params['status'] = $status_filter;
}

$stmt = $pdo->prepare($sql_count);
foreach ($params as $key => $value) {
    $stmt->bindValue(":{$key}", $value);
}
$stmt->execute();
$total_registros = $stmt->fetch()['total'];
$total_paginas = ceil($total_registros / $itens_por_pagina);

// Modifica a query principal para incluir filtros e ordenação
$sql_products = "
    SELECT p.*, m.default_callback_url, m.use_dynamic_callback
    FROM products p 
    LEFT JOIN merchants m ON p.merchant_id = m.id 
    WHERE p.merchant_id = :merchant_id";

if ($search) {
    $sql_products .= " AND (p.name LIKE :search OR p.description LIKE :search)";
}

if ($status_filter) {
    $sql_products .= " AND p.status = :status";
}

$sql_products .= " ORDER BY {$sort_by} {$sort_order} LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql_products);
foreach ($params as $key => $value) {
    $stmt->bindValue(":{$key}", $value);
}
$stmt->bindValue(':limit', $itens_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// Função para gerar links de paginação
function gerarLinksPaginacao($pagina_atual, $total_paginas) {
    $links = [];
    
    // Sempre mostra primeira página
    $links[] = 1;
    
    // Páginas próximas à atual
    for($i = max(2, $pagina_atual - 2); $i <= min($total_paginas - 1, $pagina_atual + 2); $i++) {
        $links[] = $i;
    }
    
    // Sempre mostra última página
    if($total_paginas > 1) {
        $links[] = $total_paginas;
    }
    
    return $links;
}

// Função para formatar o status do produto
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'active':
            return 'success';
        case 'inactive':
            return 'warning';
        case 'cancelled':
            return 'danger';
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
        case 'cancelled':
            return 'Cancelado';
        default:
            return 'Desconhecido';
    }
}

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

// Processa a exclusão do produto
if (isset($_POST['delete_product'])) {
    $product_id = $_POST['product_id'];
    
    // Verifica se o produto pertence ao merchant
    $stmt = $pdo->prepare("SELECT status FROM products WHERE id = ? AND merchant_id = ?");
    $stmt->execute([$product_id, $_SESSION['merchant_id']]);
    $product = $stmt->fetch();
    
    if ($product) {
        if ($product['status'] === 'cancelled') {
            // Se já estiver cancelado, chama o delete_permanent.php
            define('INCLUDED', true);
            require_once 'delete_permanent.php';
        } else {
            // Se não estiver cancelado, apenas marca como cancelado
            $stmt = $pdo->prepare("UPDATE products SET status = 'cancelled' WHERE id = ? AND merchant_id = ?");
            if ($stmt->execute([$product_id, $_SESSION['merchant_id']])) {
                $_SESSION['success'] = "Produto cancelado com sucesso!";
            } else {
                $_SESSION['error'] = "Erro ao cancelar o produto.";
            }
        }
    } else {
        $_SESSION['error'] = "Produto não encontrado ou você não tem permissão para excluí-lo.";
    }
    
    header('Location: products.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos - Painel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/transitions.css" rel="stylesheet">
    <style>
        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loading-text {
            margin-top: 15px;
            color: #007bff;
            font-weight: 500;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Fade Transition */
        .fade-transition {
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
        }

        .fade-transition.active {
            opacity: 1;
            transform: translateY(0);
        }

        /* Tab Content Animation */
        .tab-content > .tab-pane {
            transition: all 0.3s ease;
        }

        .tab-content > .active {
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
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
                        <a class="nav-link" href="index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="products.php">Produtos</a>
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

    <div class="container mt-4 animate-on-load">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Produtos</h2>
            <a href="add_product.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Adicionar Produto
            </a>
        </div>

        <!-- Filtros de Busca -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Buscar por nome ou descrição...">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-search"></i> Buscar
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">Todos os Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Ativo</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inativo</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="reset" class="btn btn-secondary" onclick="window.location.href='products.php'">
                            <i class="bi bi-x-circle"></i> Limpar Filtros
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo htmlspecialchars($_SESSION['success_message']);
                unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo htmlspecialchars($_SESSION['error_message']);
                unset($_SESSION['error_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <?php if (empty($products)): ?>
                    <div class="alert alert-info">
                        Nenhum produto encontrado.
                        <?php if ($search || $status_filter): ?>
                            <a href="products.php" class="alert-link">Limpar filtros</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Foto</th>
                                <th>
                                    <a href="?sort=name&order=<?php echo $sort_by === 'name' && $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?><?php echo $search ? "&search={$search}" : ''; ?><?php echo $status_filter ? "&status={$status_filter}" : ''; ?>">
                                        Nome
                                        <?php if ($sort_by === 'name'): ?>
                                            <i class="bi bi-arrow-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Tipo</th>
                                <th>
                                    <a href="?sort=price&order=<?php echo $sort_by === 'price' && $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?><?php echo $search ? "&search={$search}" : ''; ?><?php echo $status_filter ? "&status={$status_filter}" : ''; ?>">
                                        Preço
                                        <?php if ($sort_by === 'price'): ?>
                                            <i class="bi bi-arrow-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Status</th>
                                <th>Entrega</th>
                                <th>Link do Checkout</th>
                                <th>
                                    <a href="?sort=created_at&order=<?php echo $sort_by === 'created_at' && $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?><?php echo $search ? "&search={$search}" : ''; ?><?php echo $status_filter ? "&status={$status_filter}" : ''; ?>">
                                        Data de Criação
                                        <?php if ($sort_by === 'created_at'): ?>
                                            <i class="bi bi-arrow-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <?php if ($product['file_path']): ?>
                                        <img src="../uploads/<?php echo htmlspecialchars($product['file_path']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             class="img-thumbnail" style="max-width: 50px;">
                                    <?php else: ?>
                                        <i class="bi bi-image text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo ucfirst($product['type']); ?></td>
                                <td>R$ <?php echo number_format($product['price'], 2, ',', '.'); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo getStatusBadgeClass($product['status']); ?>">
                                        <?php echo getStatusLabel($product['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $product['delivery_type'])); ?></td>
                                <td>
                                    <?php if ($product['status'] == 'active'): ?>
                                        <div class="input-group">
                                            <input type="text" class="form-control form-control-sm" 
                                                   value="<?php echo getPaymentLink($product); ?>" 
                                                   readonly>
                                            <button class="btn btn-sm btn-outline-primary copy-link" 
                                                    data-link="<?php echo getPaymentLink($product); ?>"
                                                    title="Copiar Link">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Produto inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($product['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="edit_product.php?id=<?php echo $product['id']; ?>" 
                                           class="btn btn-sm btn-primary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($product['status'] === 'cancelled'): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir permanentemente este produto?');">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <button type="submit" name="delete_product" class="btn btn-sm btn-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja cancelar este produto?');">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <button type="submit" name="delete_product" class="btn btn-sm btn-warning">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_paginas > 1): ?>
                <nav aria-label="Navegação de páginas" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <!-- Botão Anterior -->
                        <li class="page-item <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $pagina_atual - 1; ?>" tabindex="-1">Anterior</a>
                        </li>

                        <!-- Links das Páginas -->
                        <?php 
                        $links = gerarLinksPaginacao($pagina_atual, $total_paginas);
                        $ultimo_link = 0;
                        foreach ($links as $pagina):
                            if ($ultimo_link && $pagina > $ultimo_link + 1): 
                        ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php 
                            endif;
                            $ultimo_link = $pagina;
                        ?>
                            <li class="page-item <?php echo $pagina == $pagina_atual ? 'active' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $pagina; ?>"><?php echo $pagina; ?></a>
                            </li>
                        <?php endforeach; ?>

                        <!-- Botão Próximo -->
                        <li class="page-item <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $pagina_atual + 1; ?>">Próximo</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação de Reativação -->
    <div class="modal fade" id="reactivateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Reativação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Deseja reativar o produto <strong id="reactivateProductName"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form id="reactivateForm" method="POST" action="reactivate_product.php" class="d-inline">
                        <input type="hidden" name="product_id" id="reactivateProductId">
                        <button type="submit" class="btn btn-success">Reativar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay">
        <div class="text-center">
            <div class="loading-spinner"></div>
            <div class="loading-text">Carregando...</div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/transitions.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const loadingOverlay = document.querySelector('.loading-overlay');
        const tabLinks = document.querySelectorAll('[data-bs-toggle="tab"]');
        
        // Função para mostrar o loading
        function showLoading() {
            loadingOverlay.classList.add('active');
        }
        
        // Função para esconder o loading
        function hideLoading() {
            loadingOverlay.classList.remove('active');
        }

        // Adicionar evento de clique em todos os links de tab
        tabLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                showLoading();
                
                // Simular um pequeno delay para mostrar o loading
                setTimeout(() => {
                    hideLoading();
                }, 500);
            });
        });

        // Adicionar animação de fade para os elementos da tabela
        const tableRows = document.querySelectorAll('tbody tr');
        tableRows.forEach((row, index) => {
            row.classList.add('fade-transition');
            setTimeout(() => {
                row.classList.add('active');
            }, index * 50); // Delay escalonado para cada linha
        });

        // Função para copiar link do checkout
        document.querySelectorAll('.copy-link').forEach(button => {
            button.addEventListener('click', function() {
                const link = this.dataset.link;
                navigator.clipboard.writeText(link).then(() => {
                    // Mudar o ícone temporariamente para indicar sucesso
                    const icon = this.querySelector('i');
                    icon.classList.remove('bi-clipboard');
                    icon.classList.add('bi-clipboard-check');
                    
                    setTimeout(() => {
                        icon.classList.remove('bi-clipboard-check');
                        icon.classList.add('bi-clipboard');
                    }, 2000);
                });
            });
        });

        // Manter os parâmetros de ordenação e filtros na paginação
        document.querySelectorAll('.pagination .page-link').forEach(link => {
            if (!link.href.includes('?')) return;
            
            const url = new URL(link.href);
            if (<?php echo json_encode($search) ?>) {
                url.searchParams.set('search', <?php echo json_encode($search) ?>);
            }
            if (<?php echo json_encode($status_filter) ?>) {
                url.searchParams.set('status', <?php echo json_encode($status_filter) ?>);
            }
            if (<?php echo json_encode($sort_by) ?>) {
                url.searchParams.set('sort', <?php echo json_encode($sort_by) ?>);
                url.searchParams.set('order', <?php echo json_encode($sort_order) ?>);
            }
            link.href = url.toString();
        });
    });
    </script>
</body>
</html> 