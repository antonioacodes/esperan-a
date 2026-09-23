<?php
declare(strict_types=1);

// Never expose this diagnostic through the website or print credentials.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/sibs.php';

function probe_invalid_checkout(string $label, string $baseUrl, array $config): void {
    // An empty JSON object cannot create a checkout: merchant and transaction are mandatory.
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'X-IBM-Client-Id: ' . $config['client_id'],
        'Authorization: Bearer ' . $config['auth_token'],
    ];
    $curl = curl_init($baseUrl . '/payments');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => '{}',
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
        echo $label . '/invalid_checkout: erro de rede (' . $curlError . ")\n";
    } else {
        echo $label . '/invalid_checkout: HTTP ' . $httpCode . "\n";
    }
}

try {
    $config = sibs_config();
    if (!function_exists('curl_init')) throw new RuntimeException('Extensão cURL ausente.');
    $secret = sibs_setting('SIBS_CLIENT_SECRET');
    $environments = ['configured' => $config['base_url']];
    $productionUrl = 'https://api.sibspayments.com/api/v2';
    if (rtrim($config['base_url'], '/') !== $productionUrl) {
        $environments['production'] = $productionUrl;
    }

    foreach ($environments as $environment => $baseUrl) {
        $url = $baseUrl . '/payments/auth-probe-' . bin2hex(random_bytes(8)) . '/status';
        foreach (['documented' => false, 'with_client_secret' => true] as $label => $withSecret) {
            if ($withSecret && $secret === '') {
                echo $environment . '/with_client_secret: não configurado' . "\n";
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
                echo $environment . '/' . $label . ': erro de rede (' . $curlError . ")\n";
            } else {
                echo $environment . '/' . $label . ': HTTP ' . $httpCode . "\n";
            }
        }
        probe_invalid_checkout($environment, $baseUrl, $config);
    }
    probe_invalid_checkout('legacy_test_v1', 'https://stargate.qly.site1.sibs.pt/api/v1', $config);
    probe_invalid_checkout('legacy_production_v1', 'https://api.sibsgateway.com/api/v1', $config);
    echo "Este teste consulta uma transação inexistente e envia um checkout sem dados obrigatórios; nenhum pagamento MB WAY é solicitado.\n";
} catch (Throwable $error) {
    echo 'Configuração local: ' . $error->getMessage() . "\n";
    exit(1);
}
