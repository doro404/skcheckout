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

// Busca o total de transações
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM transactions 
    WHERE merchant_id = ?
");
$stmt->execute([$_SESSION['merchant_id']]);
$total_registros = $stmt->fetch()['total'];
$total_paginas = ceil($total_registros / $itens_por_pagina);

// Busca as transações da página atual
$stmt = $pdo->prepare("
    SELECT t.*, p.name as product_name 
    FROM transactions t 
    JOIN products p ON t.product_id = p.id 
    WHERE t.merchant_id = :merchant_id 
    ORDER BY t.created_at DESC 
    LIMIT :limit OFFSET :offset
");

$stmt->bindValue(':merchant_id', $_SESSION['merchant_id'], PDO::PARAM_INT);
$stmt->bindValue(':limit', $itens_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$transactions = $stmt->fetchAll();

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

// Função para formatar o status do pagamento
function getStatusBadgeClass($status) {
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

function getStatusLabel($status) {
    switch ($status) {
        case 'approved':
            return 'Aprovado';
        case 'pending':
            return 'Pendente';
        case 'rejected':
            return 'Rejeitado';
        default:
            return 'Desconhecido';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transações - Painel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/transitions.css" rel="stylesheet">
    <style>
        /* Estilo para impressão */
        @media print {
            body * {
                visibility: hidden;
            }
            #receiptModal, #receiptModal * {
                visibility: visible;
            }
            #receiptModal {
                position: absolute;
                left: 0;
                top: 0;
            }
            .modal-footer {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container mt-4 animate-on-load">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Transações</h2>
            <div class="text-muted">
                Total de registros: <?php echo $total_registros; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID Pagamento</th>
                                <th>Produto</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>E-mail do Pagador</th>
                                <th>Documento</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($transaction['payment_id']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['product_name']); ?></td>
                                <td>R$ <?php echo number_format($transaction['amount'], 2, ',', '.'); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo getStatusBadgeClass($transaction['status']); ?>">
                                        <?php echo getStatusLabel($transaction['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($transaction['payer_email']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['payer_document']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <?php if ($transaction['status'] === 'approved'): ?>
                                            <button class="btn btn-sm btn-info resend-notification" 
                                                    data-payment-id="<?php echo $transaction['payment_id']; ?>"
                                                    title="Reenviar Notificação">
                                                <i class="bi bi-envelope"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-primary view-receipt" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#receiptModal"
                                                data-payment-id="<?php echo $transaction['payment_id']; ?>"
                                                data-product-name="<?php echo htmlspecialchars($transaction['product_name']); ?>"
                                                data-amount="<?php echo number_format($transaction['amount'], 2, ',', '.'); ?>"
                                                data-status="<?php echo getStatusLabel($transaction['status']); ?>"
                                                data-status-class="<?php echo getStatusBadgeClass($transaction['status']); ?>"
                                                data-payer-email="<?php echo htmlspecialchars($transaction['payer_email']); ?>"
                                                data-payer-document="<?php echo htmlspecialchars($transaction['payer_document']); ?>"
                                                data-created-at="<?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?>"
                                                title="Ver Comprovante">
                                            <i class="bi bi-receipt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="7" class="text-center">Nenhuma transação encontrada.</td>
                            </tr>
                            <?php endif; ?>
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

                <!-- Modal do Comprovante -->
                <div class="modal fade" id="receiptModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Comprovante de Venda</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-center mb-4">
                                            <h4>Comprovante de Pagamento</h4>
                                            <p class="text-muted">ID da Transação: <span id="modalPaymentId"></span></p>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <strong>Produto:</strong>
                                                <p id="modalProductName"></p>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Valor:</strong>
                                                <p>R$ <span id="modalAmount"></span></p>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <strong>Status:</strong>
                                                <p><span id="modalStatus" class="badge"></span></p>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Data da Transação:</strong>
                                                <p id="modalCreatedAt"></p>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <strong>E-mail do Pagador:</strong>
                                                <p id="modalPayerEmail"></p>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Documento:</strong>
                                                <p id="modalPayerDocument"></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" onclick="window.print()">
                                    <i class="bi bi-printer"></i> Imprimir
                                </button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/transitions.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Adicionar animação de fade para os elementos da tabela
        const tableRows = document.querySelectorAll('tbody tr');
        tableRows.forEach((row, index) => {
            row.classList.add('fade-transition');
            setTimeout(() => {
                row.classList.add('active');
            }, index * 50);
        });

        // Função para reenviar notificação
        document.querySelectorAll('.resend-notification').forEach(button => {
            button.addEventListener('click', async function() {
                const paymentId = this.dataset.paymentId;
                
                if (confirm('Deseja reenviar a notificação de compra para o cliente?')) {
                    try {
                        const response = await fetch('resend_notification.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `payment_id=${paymentId}`
                        });

                        const result = await response.json();
                        
                        if (result.success) {
                            alert('Notificação reenviada com sucesso!');
                        } else {
                            alert('Erro: ' + result.message);
                        }
                    } catch (error) {
                        alert('Erro ao reenviar notificação: ' + error.message);
                    }
                }
            });
        });

        // Função para preencher o modal do comprovante
        document.querySelectorAll('.view-receipt').forEach(button => {
            button.addEventListener('click', function() {
                const data = this.dataset;
                
                document.getElementById('modalPaymentId').textContent = data.paymentId;
                document.getElementById('modalProductName').textContent = data.productName;
                document.getElementById('modalAmount').textContent = data.amount;
                document.getElementById('modalStatus').textContent = data.status;
                document.getElementById('modalStatus').className = `badge bg-${data.statusClass}`;
                document.getElementById('modalPayerEmail').textContent = data.payerEmail;
                document.getElementById('modalPayerDocument').textContent = data.payerDocument;
                document.getElementById('modalCreatedAt').textContent = data.createdAt;
            });
        });

        // Removendo o estilo de impressão antigo pois agora está no head
        const existingStyle = document.querySelector('style');
        if (existingStyle && existingStyle.textContent.includes('@media print')) {
            existingStyle.remove();
        }
    });
    </script>
    <style>
        /* Estilos do Modal */
        .modal {
            background-color: rgba(0, 0, 0, 0.5);
        }
        .modal-dialog {
            margin: 1.75rem auto;
        }
        .modal-content {
            background-color: #fff;
            border-radius: 0.3rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .modal-backdrop {
            display: none;
        }

        .btn-group {
            display: flex;
            gap: 0.5rem;
        }

        .btn-group .btn {
            width: 2rem;
            height: 2rem;
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }

        .btn-group .btn i {
            font-size: 1rem;
        }
    </style>
</body>
</html> 