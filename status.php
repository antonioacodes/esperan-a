<?php
declare(strict_types=1);
require __DIR__ . '/sibs.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(['status' => 'failed'], 405);
    $transactionId = (string) ($_GET['saleId'] ?? '');
    if ($transactionId === '' || load_transaction($transactionId) === null) {
        json_response(['status' => 'failed'], 404);
    }
    $config = sibs_config();
    $result = sibs_request('GET', $config['base_url'] . '/payments/' . rawurlencode($transactionId) . '/status', [
        'X-IBM-Client-Id: ' . $config['client_id'],
        'Authorization: Bearer ' . $config['auth_token'],
    ]);
    json_response(['status' => public_status((string) ($result['paymentStatus'] ?? 'Pending'))]);
} catch (Throwable $error) {
    error_log('SIBS status: ' . $error->getMessage());
    json_response(['status' => 'pending'], 503);
}
