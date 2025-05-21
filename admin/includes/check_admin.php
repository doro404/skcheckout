<?php
// Verifica se o usuário está logado
if (!isset($_SESSION['merchant_id'])) {
    header('Location: login.php');
    exit;
}

// Verifica se o usuário é admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: index.php');
    $_SESSION['error_message'] = "Acesso negado. Você precisa ser um administrador para acessar esta página.";
    exit;
}
?> 