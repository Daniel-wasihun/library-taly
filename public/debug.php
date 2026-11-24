<?php
header('Content-Type: application/json');

$env = [
    'php_version' => PHP_VERSION,
    'loaded_extensions' => [
        'pdo' => extension_loaded('pdo'),
        'pdo_pgsql' => extension_loaded('pdo_pgsql'),
        'pgsql' => extension_loaded('pgsql'),
    ],
    'app_env' => getenv('APP_ENV') ?: $_ENV['APP_ENV'] ?? null,
    'app_debug' => getenv('APP_DEBUG') ?: $_ENV['APP_DEBUG'] ?? null,
    'app_key_set' => !empty(getenv('APP_KEY') ?: $_ENV['APP_KEY'] ?? null),
    'app_key_prefix' => substr((string)(getenv('APP_KEY') ?: $_ENV['APP_KEY'] ?? ''), 0, 10),
    'db_host' => getenv('DB_HOST') ?: $_ENV['DB_HOST'] ?? null,
    'db_port' => getenv('DB_PORT') ?: $_ENV['DB_PORT'] ?? null,
    'db_database' => getenv('DB_DATABASE') ?: $_ENV['DB_DATABASE'] ?? null,
    'db_username' => getenv('DB_USERNAME') ?: $_ENV['DB_USERNAME'] ?? null,
    'db_password_length' => strlen((string)(getenv('DB_PASSWORD') ?: $_ENV['DB_PASSWORD'] ?? '')),
    'db_sslmode' => getenv('DB_SSLMODE') ?: $_ENV['DB_SSLMODE'] ?? 'require',
    'vite_manifest_exists' => file_exists(__DIR__ . '/build/manifest.json'),
    'storage_writable' => is_writable(__DIR__ . '/../storage/framework/sessions'),
];

$host = $env['db_host'];
$port = $env['db_port'] ?: 5432;
$dbname = $env['db_database'] ?: 'postgres';
$user = $env['db_username'] ?: 'postgres';
$pass = getenv('DB_PASSWORD') ?: $_ENV['DB_PASSWORD'] ?? '';
$sslmode = $env['db_sslmode'];

$pdoStatus = [];

if (empty($host)) {
    $pdoStatus['status'] = 'error';
    $pdoStatus['message'] = 'DB_HOST environment variable is not set in Render!';
} else {
    try {
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslmode}";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdoStatus['status'] = 'connected';
        $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $pdoStatus['tables_count'] = count($tables);
        $pdoStatus['has_sessions_table'] = in_array('sessions', $tables);
        $pdoStatus['has_users_table'] = in_array('users', $tables);
        $pdoStatus['tables'] = $tables;
    } catch (\Throwable $e) {
        $pdoStatus['status'] = 'connection_failed';
        $pdoStatus['error'] = $e->getMessage();
        $pdoStatus['code'] = $e->getCode();
    }
}

$env['database_test'] = $pdoStatus;

echo json_encode($env, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
