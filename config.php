<?php
/**
 * Configurações Principais do Sistema de Pagamento PIX
 */

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Função para carregar configurações do banco de dados
function loadSystemSettings($pdo) {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        if (!$stmt) {
            throw new Exception("Erro ao executar consulta de configurações");
        }
        
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        if ($settings === false) {
            throw new Exception("Erro ao buscar configurações do banco");
        }
        
        foreach ($settings as $key => $value) {
            if (!defined(strtoupper($key))) {
                define(strtoupper($key), $value);
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Erro ao carregar configurações: " . $e->getMessage());
        return false;
    }
}

// Conexão com o banco de dados
require_once __DIR__ . '/config/database.php';

// Carrega configurações do banco de dados
loadSystemSettings($pdo);

// Inicialização do SDK do Mercado Pago
require_once __DIR__ . '/vendor/autoload.php';
if (defined('MP_ACCESS_TOKEN')) {
    MercadoPago\SDK::setAccessToken(MP_ACCESS_TOKEN);
}

// Configurações que não devem ir para o banco
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', __DIR__ . '/uploads');
if (!defined('LOG_PATH')) define('LOG_PATH', __DIR__ . '/logs');
if (!defined('SESSION_NAME')) define('SESSION_NAME', 'PIX_SESSION');
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 7200);

// Erros
if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Sessão segura
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', defined('ENVIRONMENT') && ENVIRONMENT === 'production' ? '1' : '0');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    session_name(SESSION_NAME);
    session_start();
}

// Funções utilitárias
function isProduction() {
    return defined('ENVIRONMENT') && ENVIRONMENT === 'production';
}

function getConfig($key) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        return defined(strtoupper($key)) ? constant(strtoupper($key)) : null;
    }
}

// Autoload de classes
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/classes/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Criar pastas necessárias
if (!file_exists(LOG_PATH)) {
    mkdir(LOG_PATH, 0777, true);
}
if (!file_exists(LOG_PATH . '/mercadopago')) {
    mkdir(LOG_PATH . '/mercadopago', 0777, true);
}
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0777, true);
}

// Validação de credenciais Mercado Pago
function validateMercadoPagoCredentials() {
    try {
        $payment = new MercadoPago\Payment();
        return true;
    } catch (Exception $e) {
        error_log("Erro na validação das credenciais do Mercado Pago: " . $e->getMessage());
        return false;
    }
}

if (!validateMercadoPagoCredentials()) {
    error_log("AVISO: Credenciais do Mercado Pago inválidas ou mal configuradas!");
}
