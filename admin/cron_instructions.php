<?php
session_start();
require_once '../config/database.php';
require_once '../config.php';
require_once 'includes/check_admin.php';

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

// Busca informações do comerciante
$stmt = $pdo->prepare("SELECT * FROM merchants WHERE id = ?");
$stmt->execute([$_SESSION['merchant_id']]);
$merchant = $stmt->fetch(PDO::FETCH_ASSOC);

// Define o caminho absoluto baseado no domínio do comerciante
$domain = $_SERVER['HTTP_HOST'];
$scriptPath = dirname($_SERVER['DOCUMENT_ROOT']) . '/scripts/cancel_expired_payments.php';
$logPath = dirname($_SERVER['DOCUMENT_ROOT']) . '/logs/cron.log';

// Include header
include 'includes/header.php';
?>

<!-- Begin Page Content -->
<div class="container-flui mt-4 container">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Configuração do Cron Job</h1>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill"></i>
                O cron job é necessário para cancelar automaticamente pagamentos PIX expirados após 30 minutos.
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">1. Para Linux/Unix (cPanel, Plesk, VPS)</h6>
                </div>
                <div class="card-body">
                    <p>Execute o comando abaixo no terminal do seu servidor:</p>
                    <div class="code-block">
                        <button class="btn btn-sm btn-outline-primary copy-btn" onclick="copyToClipboard('linux-command')">
                            <i class="bi bi-clipboard"></i> Copiar
                        </button>
                        <pre id="linux-command">crontab -e

# Adicione a linha abaixo:
*/5 * * * * php <?php echo $scriptPath; ?> >> <?php echo $logPath; ?> 2>&1</pre>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">2. Para Windows Server</h6>
                </div>
                <div class="card-body">
                    <p>Configure no Agendador de Tarefas do Windows:</p>
                    <ol>
                        <li>Abra o Agendador de Tarefas (Task Scheduler)</li>
                        <li>Clique em "Criar Tarefa Básica"</li>
                        <li>Nome: <code>PIX Payment Cancellation</code></li>
                        <li>Descrição: <code>Cancela pagamentos PIX expirados após 30 minutos</code></li>
                        <li>Trigger: Diariamente</li>
                        <li>Recorrência: A cada 5 minutos</li>
                        <li>Ação: Iniciar um programa</li>
                        <li>Programa/script: <code>php</code></li>
                        <li>Argumentos: <code><?php echo $scriptPath; ?></code></li>
                    </ol>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">3. Para cPanel</h6>
                </div>
                <div class="card-body">
                    <p>Configure através da interface do cPanel:</p>
                    <ol>
                        <li>Acesse seu cPanel</li>
                        <li>Procure por "Cron Jobs" ou "Tarefas Agendadas"</li>
                        <li>Selecione "A cada 5 minutos" em "Common Settings"</li>
                        <li>Cole o comando abaixo em "Command":</li>
                    </ol>
                    <div class="code-block">
                        <button class="btn btn-sm btn-outline-primary copy-btn" onclick="copyToClipboard('cpanel-command')">
                            <i class="bi bi-clipboard"></i> Copiar
                        </button>
                        <pre id="cpanel-command">php <?php echo $scriptPath; ?> >> <?php echo $logPath; ?> 2>&1</pre>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">4. Verificação</h6>
                </div>
                <div class="card-body">
                    <p>Para verificar se o cron job está funcionando:</p>
                    <ol>
                        <li>Aguarde 5 minutos após a configuração</li>
                        <li>Verifique o arquivo de log em: <code><?php echo $logPath; ?></code></li>
                        <li>Você deve ver mensagens indicando a execução do script</li>
                    </ol>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>Importante:</strong>
                <ul>
                    <li>Certifique-se de que o PHP está instalado e acessível via linha de comando</li>
                    <li>O script e o diretório de logs precisam ter permissões de escrita (chmod 755)</li>
                    <li>Em caso de dúvidas, contate o suporte do seu servidor de hospedagem</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!-- End of Main Content -->

<script>
function copyToClipboard(elementId) {
    const el = document.getElementById(elementId);
    const text = el.textContent;
    navigator.clipboard.writeText(text).then(() => {
        const button = el.parentElement.querySelector('.copy-btn');
        button.innerHTML = '<i class="bi bi-check"></i> Copiado!';
        setTimeout(() => {
            button.innerHTML = '<i class="bi bi-clipboard"></i> Copiar';
        }, 2000);
    });
}
</script>

<?php
// Include footer
include 'includes/footer.php';
?> 