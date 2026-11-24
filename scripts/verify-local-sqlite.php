<?php

use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__);
$expectedEnvironmentFile = $root.'/.env.local-testing';
$expectedDatabase = realpath($root.'/database').DIRECTORY_SEPARATOR.'local-testing.sqlite';
$expectedDatabaseConfig = str_replace('\\', '/', $expectedDatabase);
$normalizePath = static fn (string $path): string => strtolower(str_replace('\\', '/', $path));

$environmentFile = $expectedEnvironmentFile;
if (($argv[1] ?? null) === '--environment-file') {
    $environmentFile = $argv[2] ?? '';
}

if (!is_file($environmentFile)) {
    fwrite(STDERR, "Required local environment file is missing.\n");
    exit(2);
}

if (realpath($environmentFile) !== realpath($expectedEnvironmentFile)) {
    fwrite(STDERR, "Only the dedicated local-testing environment file is allowed.\n");
    exit(2);
}

require $root.'/vendor/autoload.php';

try {
    if (is_file($root.'/bootstrap/cache/config.php')) {
        throw new RuntimeException('Default Laravel config cache exists.');
    }

    $configCache = getenv('APP_CONFIG_CACHE');
    if (!is_string($configCache) || $configCache === '' || is_file($configCache)) {
        throw new RuntimeException('Local config cache path is missing or cached.');
    }

    if (php_ini_loaded_file() !== false || php_ini_scanned_files() !== false) {
        throw new RuntimeException('PHP loaded a normal or scanned ini file.');
    }

    if (
        PDO::getAvailableDrivers() !== ['sqlite'] ||
        extension_loaded('pdo_pgsql') ||
        extension_loaded('pgsql') ||
        extension_loaded('pdo_mysql') ||
        extension_loaded('pdo_sqlsrv')
    ) {
        throw new RuntimeException('PHP is not running with SQLite-only database drivers.');
    }

    $settings = Dotenv::parse((string) file_get_contents($expectedEnvironmentFile));
    $requiredSettings = [
        'APP_ENV' => 'local-testing',
        'APP_DEBUG' => 'true',
        'APP_URL' => 'http://127.0.0.1:8000',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $expectedDatabaseConfig,
        'DB_URL' => '',
        'DATABASE_URL' => '',
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'BROADCAST_CONNECTION' => 'null',
        'FILESYSTEM_DISK' => 'local',
        'MAIL_MAILER' => 'array',
        'TRACKER_LEGACY_AUTH_ENABLED' => 'false',
        'APP_CONFIG_CACHE' => '',
    ];

    foreach ($requiredSettings as $key => $expected) {
        $actual = $settings[$key] ?? '';
        $actual = $actual === null ? '' : (string) $actual;
        if ($key === 'DB_DATABASE') {
            $actual = str_replace('\\', '/', $actual);
        }
        if ($actual !== $expected) {
            throw new RuntimeException("Local environment setting '$key' is unsafe.");
        }
    }

    $localKey = (string) ($settings['APP_KEY'] ?? '');
    if (!preg_match('/^base64:[A-Za-z0-9+\/]+=*$/', $localKey)) {
        throw new RuntimeException('A generated local APP_KEY is required.');
    }

    $credentialSettings = [
        'LOCAL_TEST_ADMIN_EMAIL' => 'admin@local.test',
        'LOCAL_TEST_DRIVER_EMAIL' => 'driver@local.test',
    ];
    foreach ($credentialSettings as $key => $expected) {
        if (($settings[$key] ?? null) !== $expected) {
            throw new RuntimeException("Local account setting '$key' is unsafe.");
        }
    }
    $adminPassword = (string) ($settings['LOCAL_TEST_ADMIN_PASSWORD'] ?? '');
    $driverPassword = (string) ($settings['LOCAL_TEST_DRIVER_PASSWORD'] ?? '');
    if (
        strlen($adminPassword) < 32 ||
        strlen($driverPassword) < 32 ||
        hash_equals($adminPassword, $driverPassword)
    ) {
        throw new RuntimeException('Separate generated local account passwords are required.');
    }

    $integrationKeys = [
        'SUPABASE_URL',
        'SUPABASE_KEY',
        'SUPABASE_SERVICE_ROLE_KEY',
        'SUPABASE_DB_URL',
        'BREVO_API_KEY',
        'RESEND_API_KEY',
        'POSTMARK_API_KEY',
        'AWS_ACCESS_KEY_ID',
        'AWS_SECRET_ACCESS_KEY',
        'AWS_BUCKET',
        'SLACK_BOT_USER_OAUTH_TOKEN',
        'SLACK_BOT_USER_DEFAULT_CHANNEL',
        'GATEWAY_API_KEY',
        'TRACKER_LEGACY_API_KEY',
        'TRACKER_LEGACY_DEVICE_ID',
    ];
    foreach ($integrationKeys as $key) {
        $value = $settings[$key] ?? '';
        if ($value !== null && trim((string) $value) !== '') {
            throw new RuntimeException("External integration setting '$key' must be empty.");
        }
    }

    foreach (['DB_URL', 'DATABASE_URL'] as $name) {
        $value = getenv($name);
        if ($value !== false && trim($value) !== '') {
            throw new RuntimeException("Inherited database URL '$name' was not cleared.");
        }
    }
    foreach (getenv() as $name => $value) {
        if (
            preg_match('/^(?:PG[A-Z0-9_]*|SUPABASE_[A-Z0-9_]*)$/', $name) &&
            trim((string) $value) !== ''
        ) {
            throw new RuntimeException("Inherited external database setting '$name' was not cleared.");
        }
    }
    if (getenv('APP_ENV') !== 'local-testing' || getenv('DB_CONNECTION') !== 'sqlite') {
        throw new RuntimeException('Local environment overrides were not applied.');
    }
    if ($normalizePath((string) getenv('DB_DATABASE')) !== $normalizePath($expectedDatabase)) {
        throw new RuntimeException('Effective DB_DATABASE does not match the approved local file.');
    }

    $nativePdo = new PDO('sqlite:'.$expectedDatabase);
    $nativePdo->exec('PRAGMA foreign_keys = ON');
    $nativePdo = null;

    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    if (
        $app->environment() !== 'local-testing' ||
        config('database.default') !== 'sqlite' ||
        config('database.connections.sqlite.driver') !== 'sqlite' ||
        $normalizePath((string) config('database.connections.sqlite.database')) !==
            $normalizePath($expectedDatabase)
    ) {
        throw new RuntimeException('Effective Laravel database configuration is not the approved local SQLite database.');
    }
    if (config('database.connections.sqlite.url')) {
        throw new RuntimeException('SQLite URL override is not empty.');
    }

    $connection = $app['db']->connection();
    if ($connection->getDriverName() !== 'sqlite') {
        throw new RuntimeException('Laravel selected a non-SQLite database driver.');
    }
    $connection->getPdo();
    $databaseList = $connection->select('PRAGMA database_list');
    $mainDatabase = null;
    foreach ($databaseList as $database) {
        if ($database->name === 'main') {
            $mainDatabase = (string) $database->file;
            break;
        }
    }
    if ($mainDatabase === null || $normalizePath($mainDatabase) !== $normalizePath($expectedDatabase)) {
        throw new RuntimeException('SQLite connected to an unexpected database file.');
    }

    fwrite(
        STDOUT,
        "Isolation PASS: APP_ENV=local-testing; Laravel default=sqlite; exact local database verified; PHP ini disabled; PDO driver=sqlite only; external DB/mail integrations disabled.\n"
    );
} catch (Throwable $error) {
    fwrite(STDERR, 'Local SQLite preflight failed: '.$error->getMessage()."\n");
    exit(1);
}
