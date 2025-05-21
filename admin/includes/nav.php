<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="#">Painel de Pagamentos</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="index.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'products.php' ? 'active' : ''; ?>" href="products.php">
                        <i class="bi bi-box"></i> Produtos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'transactions.php' ? 'active' : ''; ?>" href="transactions.php">
                        <i class="bi bi-credit-card"></i> Transações
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                        <i class="bi bi-gear"></i> Configurações
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'webhook_docs.php' ? 'active' : ''; ?>" href="webhook_docs.php">
                        <i class="bi bi-book"></i> Documentação API
                    </a>
                </li>
                <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'cron_instructions.php' ? 'active' : ''; ?>" href="cron_instructions.php">
                        <i class="bi bi-clock-history"></i> Configurar Cron
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <div class="navbar-nav">
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Sair
                </a>
            </div>
        </div>
    </div>
</nav> 