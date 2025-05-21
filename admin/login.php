<?php
// Inicia a sessão antes de qualquer output
session_start();

// Se já estiver logado, redireciona para o dashboard
if (isset($_SESSION['merchant_id'])) {
    header('Location: index.php');
    exit;
}

require_once '../config/database.php';
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM merchants WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Regenera o ID da sessão por segurança
            session_regenerate_id(true);
            
            // Configura os dados da sessão
            $_SESSION['merchant_id'] = $user['id'];
            $_SESSION['merchant_name'] = $user['name'];
            $_SESSION['is_admin'] = $user['is_admin'];
            
            // Log para debug - remova após resolver o problema
            error_log('Login successful - Session data: ' . json_encode($_SESSION));
            
            header('Location: index.php');
            exit;
        } else {
            $error = "Email ou senha inválidos";
            error_log('Login failed for email: ' . $email);
        }
    } catch (Exception $e) {
        $error = "Erro ao fazer login. Tente novamente.";
        error_log('Login error: ' . $e->getMessage());
    }
}

// Configurações adicionais de sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Painel Administrativo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4">Login</h3>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       value="<?php echo htmlspecialchars($email ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Senha</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Entrar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 