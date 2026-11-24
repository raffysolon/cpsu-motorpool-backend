<?php
// Verify an overridden cache path is refused before the cache can execute.
$cacheProbe = tempnam(sys_get_temp_dir(), 'motorpool-test-cache-');
file_put_contents($cacheProbe, '<?php throw new RuntimeException("Cache executed");');
putenv('APP_CONFIG_CACHE='.$cacheProbe);
try {
    try {
        require __DIR__.'/bootstrap.php';
        throw new RuntimeException('Cached configuration was not refused.');
    } catch (RuntimeException $error) {
        if (!str_starts_with($error->getMessage(), 'Refusing tests with cached configuration.')) {
            throw $error;
        }
    }
} finally {
    unlink($cacheProbe);
    putenv('APP_CONFIG_CACHE');
}
// Deliberately hostile inherited values must be replaced before Laravel boots.
putenv('APP_ENV=production');
putenv('DB_CONNECTION=pgsql');
putenv('DB_DATABASE=must-not-be-used');
putenv('DB_URL=pgsql://invalid:invalid@production.invalid/never');
require __DIR__.'/bootstrap.php';
if (PDO::getAvailableDrivers() !== ['sqlite'] || extension_loaded('pgsql')) {
    throw new RuntimeException('Run using the SQLite-only PHP runtime.');
}
$test = new class('testIsolation') extends Tests\TestCase {};
$app = $test->createApplication();
$db = $app['db']->connection();
if ($db->getDriverName() !== 'sqlite' || $db->getDatabaseName() !== ':memory:') {
    throw new RuntimeException('Not an in-memory SQLite connection.');
}
$db->getPdo();
try {
    $app['db']->connection('pgsql');
    throw new RuntimeException('PostgreSQL unexpectedly available.');
} catch (InvalidArgumentException $expected) {
    echo "Isolation PASS: inherited overrides neutralized; uncached testing config; SQLite :memory: connected; named pgsql blocked; PostgreSQL drivers unavailable.\n";
}
