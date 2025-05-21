<?php
require_once 'config/database.php';
require_once 'config.php';
require_once 'vendor/autoload.php';

header('Content-Type: application/json');

try {
    if (!isset($_GET['payment_id'])) {
        throw new Exception("Payment ID não fornecido");
    }

    // Busca o pagamento no Mercado Pago
    $payment = MercadoPago\Payment::find_by_id($_GET['payment_id']);
    if (!$payment) {
        throw new Exception("Pagamento não encontrado");
    }

    // Retorna o status
    echo json_encode([
        'status' => $payment->status
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
} 