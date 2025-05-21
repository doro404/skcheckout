<?php
if (!isset($_SESSION['merchant_id'])) {
    header('Location: login.php');
    exit;
}

// Define a página atual para destacar o item correto no menu
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Painel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/transitions.css" rel="stylesheet">
    <?php if (isset($additional_css)) echo $additional_css; ?>
</head>
<body>
    <?php include 'nav.php'; ?>
</body>
</html> 