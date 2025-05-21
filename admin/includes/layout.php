<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/loading.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
            padding-top: 20px;
        }
        .sidebar a {
            color: #fff;
            text-decoration: none;
            padding: 10px 15px;
            display: block;
        }
        .sidebar a:hover {
            background-color: #495057;
        }
        .sidebar .active {
            background-color: #0d6efd;
        }
        .content {
            padding: 20px;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-text">Carregando...</div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <h3 class="text-white text-center mb-4">Admin</h3>
                <nav>
                    <a href="index.php" class="<?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                        <i class="bi bi-speedometer2 me-2"></i>Dashboard
                    </a>
                    <a href="products.php" class="<?php echo $current_page === 'products' ? 'active' : ''; ?>">
                        <i class="bi bi-box me-2"></i>Produtos
                    </a>
                    <a href="transactions.php" class="<?php echo $current_page === 'transactions' ? 'active' : ''; ?>">
                        <i class="bi bi-credit-card me-2"></i>Transações
                    </a>
                    <a href="merchants.php" class="<?php echo $current_page === 'merchants' ? 'active' : ''; ?>">
                        <i class="bi bi-shop me-2"></i>Lojistas
                    </a>
                    <a href="settings.php" class="<?php echo $current_page === 'settings' ? 'active' : ''; ?>">
                        <i class="bi bi-gear me-2"></i>Configurações
                    </a>
                    <a href="logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i>Sair
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 content">
                <?php if (isset($page_title)): ?>
                    <h2 class="mb-4"><?php echo $page_title; ?></h2>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Page Content -->
                <?php echo $content ?? ''; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Função para mostrar o loading
        function showLoading() {
            document.getElementById('loading-overlay').classList.add('active');
        }

        // Função para esconder o loading
        function hideLoading() {
            document.getElementById('loading-overlay').classList.remove('active');
        }

        // Adiciona o loading em todos os links do menu
        document.querySelectorAll('.sidebar a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!this.classList.contains('active')) {
                    showLoading();
                }
            });
        });

        // Esconde o loading quando a página terminar de carregar
        window.addEventListener('load', function() {
            hideLoading();
        });
    </script>
</body>
</html> 