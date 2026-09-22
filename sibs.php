<?php
declare(strict_types=1);

/** Shared server-only helpers for SIBS SPG MB WAY payments. */

function json_response(array $body, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function require_post_json(): array {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['success' => false, 'message' => 'Método não permitido.'], 405);
    }
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        json_response(['success' => false, 'message' => 'Pedido inválido.'], 400);
    }
    return $payload;
}

function sibs_environment(): array {
    static $values = null;
    if ($values !== null) return $values;
    $values = [];
    // On the VPS this resolves to /home/acodes-406/private/sibs.env.
    $path = getenv('SIBS_ENV_FILE') ?: dirname(__DIR__, 2) . '/private/sibs.env';
    if (!is_file($path)) return $values;
    $realPath = realpath($path);
    $webRoot = realpath(__DIR__);
    if ($realPath === false || $webRoot === false || str_starts_with($realPath, $webRoot . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('O arquivo SIBS deve ficar fora do diretório público.');
    }
    if (!is_readable($realPath)) {
        throw new RuntimeException('O arquivo SIBS não pode ser lido pelo PHP.');
    }
    $lines = file($realPath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) throw new RuntimeException('Não foi possível ler o arquivo SIBS.');
    $allowed = ['SIBS_API_BASE_URL', 'SIBS_CLIENT_ID', 'SIBS_CLIENT_SECRET', 'SIBS_AUTH_TOKEN', 'SIBS_TERMINAL_ID', 'SIBS_STORAGE_DIR'];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $parts = explode('=', $line, 2);
        $name = trim($parts[0]);
        if (count($parts) !== 2 || !in_array($name, $allowed, true)) {
            throw new RuntimeException('Entrada inválida no arquivo SIBS.');
        }
        $value = trim($parts[1]);
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
            $value = substr($value, 1, -1);
        }
        $values[$name] = $value;
    }
    return $values;
}

function sibs_setting(string $name): string {
    $environment = getenv($name);
    if ($environment !== false && $environment !== '') return $environment;
    return sibs_environment()[$name] ?? '';
}

function sibs_config(): array {
    $config = [
        'base_url' => rtrim(sibs_setting('SIBS_API_BASE_URL'), '/'),
        'client_id' => sibs_setting('SIBS_CLIENT_ID'),
        'auth_token' => sibs_setting('SIBS_AUTH_TOKEN'),
        'terminal_id' => sibs_setting('SIBS_TERMINAL_ID'),
    ];
    foreach ($config as $key => $value) {
        if ($value === '') {
            throw new RuntimeException("Configuração SIBS ausente: {$key}");
        }
    }
    if (!filter_var($config['base_url'], FILTER_VALIDATE_URL) || parse_url($config['base_url'], PHP_URL_SCHEME) !== 'https') {
        throw new RuntimeException('SIBS_API_BASE_URL inválida.');
    }
    return $config;
}

function sibs_request(string $method, string $url, array $headers, ?array $body = null): array {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('A extensão cURL do PHP é obrigatória.');
    }
    $curl = curl_init($url);
    $requestHeaders = array_merge(['Accept: application/json', 'Content-Type: application/json'], $headers);
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
    ]);
    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
    }
    $raw = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($raw === false) {
        throw new RuntimeException('Não foi possível contactar a SIBS: ' . $error);
    }
    $decoded = json_decode($raw, true);
    if ($status < 200 || $status >= 300 || !is_array($decoded)) {
        error_log('SIBS API error: HTTP ' . $status . ' ' . substr($raw, 0, 1000));
        throw new RuntimeException('A SIBS não conseguiu processar o pagamento.');
    }
    return $decoded;
}

function transaction_store_path(string $transactionId): string {
    if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/', $transactionId)) {
        throw new RuntimeException('Identificador de transação inválido.');
    }
    $directory = sibs_setting('SIBS_STORAGE_DIR');
    if ($directory === '' || !str_starts_with($directory, '/')) {
        throw new RuntimeException('SIBS_STORAGE_DIR deve ser um caminho absoluto fora do diretório público.');
    }
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Não foi possível preparar o armazenamento de transações.');
    }
    $realDirectory = realpath($directory);
    $webRoot = realpath(__DIR__);
    if ($realDirectory === false || $webRoot === false || str_starts_with($realDirectory, $webRoot . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('SIBS_STORAGE_DIR deve ficar fora do diretório público.');
    }
    return rtrim($directory, '/') . '/' . $transactionId . '.json';
}

function save_transaction(string $transactionId, array $record): void {
    $path = transaction_store_path($transactionId);
    $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (file_put_contents($temporary, json_encode($record, JSON_UNESCAPED_UNICODE), LOCK_EX) === false || !rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Não foi possível guardar a transação.');
    }
    @chmod($path, 0600);
}

function load_transaction(string $transactionId): ?array {
    $path = transaction_store_path($transactionId);
    if (!is_file($path)) return null;
    $record = json_decode((string) file_get_contents($path), true);
    return is_array($record) ? $record : null;
}

function public_status(string $paymentStatus): string {
    return match (strtolower($paymentStatus)) {
        'success' => 'paid',
        'declined', 'error' => 'failed',
        'timeout', 'cancelled', 'canceled' => 'cancelled',
        default => 'pending',
    };
}
