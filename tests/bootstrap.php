<?php

// Test-only safety boundary: never inherit database credentials or configuration.
$root = dirname(__DIR__);
foreach ([$root.'/bootstrap/cache/config.php', getenv('APP_CONFIG_CACHE')] as $cache) {
    if ($cache && is_file($cache)) {
        throw new RuntimeException('Refusing tests with cached configuration. Remove the local cache explicitly first.');
    }
}
$settings = [
    'APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('t', 32)),
    'APP_CONFIG_CACHE' => $root.'/bootstrap/cache/testing-config.php',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
    'DATABASE_URL' => '', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '1',
    'DB_USERNAME' => '', 'DB_PASSWORD' => '',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
    'BROADCAST_CONNECTION' => 'null', 'APP_URL' => 'http://localhost',
];
if (is_file($settings['APP_CONFIG_CACHE'])) {
    throw new RuntimeException('Refusing tests with cached testing configuration.');
}
foreach ($settings as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require $root.'/vendor/autoload.php';
