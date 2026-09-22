<?php
declare(strict_types=1);
require __DIR__ . '/sibs.php';

try {
    $input = require_post_json();
    $amount = filter_var($input['amount'] ?? null, FILTER_VALIDATE_FLOAT);
    $phone = preg_replace('/\s+/', '', (string) ($input['phone'] ?? ''));
    if ($amount === false || $amount < 1 || $amount > 1000 || round($amount, 2) !== $amount) {
        json_response(['success' => false, 'message' => 'Valor de donativo inválido.'], 422);
    }
    if (!preg_match('/^9\d{8}$/', $phone)) {
        json_response(['success' => false, 'message' => 'Indica um telemóvel português válido.'], 422);
    }

    $config = sibs_config();
    $merchantTransactionId = 'don-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(5));
    $checkout = sibs_request('POST', $config['base_url'] . '/payments', [
        'X-IBM-Client-Id: ' . $config['client_id'],
        'Authorization: Bearer ' . $config['auth_token'],
    ], [
        'merchant' => [
            'terminalId' => (int) $config['terminal_id'],
            'channel' => 'web',
            'merchantTransactionId' => $merchantTransactionId,
        ],
        'transaction' => [
            'transactionTimestamp' => gmdate('Y-m-d\\TH:i:s.000\\Z'),
            'description' => 'Donativo Esperança para Todos',
            'moto' => false,
            'paymentType' => 'PURS',
            'amount' => ['value' => round($amount, 2), 'currency' => 'EUR'],
            'paymentMethod' => ['MBWAY'],
        ],
    ]);
    $transactionId = (string) ($checkout['transactionID'] ?? '');
    $signature = (string) ($checkout['transactionSignature'] ?? '');
    if ($transactionId === '' || $signature === '') throw new RuntimeException('Resposta de checkout SIBS incompleta.');

    $purchase = sibs_request('POST', $config['base_url'] . '/payments/' . rawurlencode($transactionId) . '/mbway-id/purchase', [
        'X-IBM-Client-Id: ' . $config['client_id'],
        'Authorization: Digest ' . $signature,
    ], ['customerPhone' => '351#' . $phone, 'inApp' => false]);

    save_transaction($transactionId, [
        'merchantTransactionId' => $merchantTransactionId,
        'createdAt' => gmdate(DATE_ATOM),
        'amount' => round($amount, 2),
        'purchaseStatus' => $purchase['paymentStatus'] ?? 'Pending',
    ]);
    json_response(['success' => true, 'saleId' => $transactionId, 'message' => 'Confirma o donativo na aplicação MB WAY.']);
} catch (Throwable $error) {
    error_log('SIBS checkout: ' . $error->getMessage());
    json_response(['success' => false, 'message' => 'Não foi possível iniciar o pagamento. Tenta novamente.'], 500);
}
