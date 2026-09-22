<?php
declare(strict_types=1);

// Never expose this diagnostic through the website or print credentials.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/sibs.php';

try {
    $config = sibs_config();
    if (!function_exists('curl_init')) throw new RuntimeException('Extensão cURL ausente.');
    $secret = sibs_setting('SIBS_CLIENT_SECRET');
    $url = $config['base_url'] . '/payments/auth-probe-' . bin2hex(random_bytes(8)) . '/status';

    foreach (['documented' => false, 'with_client_secret' => true] as $label => $withSecret) {
        if ($withSecret && $secret === '') {
            echo "with_client_secret: não configurado\n";
            continue;
        }
        $headers = [
            'Accept: application/json',
            'X-IBM-Client-Id: ' . $config['client_id'],
            'Authorization: Bearer ' . $config['auth_token'],
        ];
        if ($withSecret) $headers[] = 'X-IBM-Client-Secret: ' . $secret;
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
        ]);
        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);
        if ($response === false) {
            echo $label . ': erro de rede (' . $curlError . ")\n";
        } else {
            echo $label . ': HTTP ' . $httpCode . "\n";
        }
    }
    echo "Este teste apenas consulta uma transação inexistente; nenhum pagamento é criado.\n";
} catch (Throwable $error) {
    echo 'Configuração local: ' . $error->getMessage() . "\n";
    exit(1);
}
