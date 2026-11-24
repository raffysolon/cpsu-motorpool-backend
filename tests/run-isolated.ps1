param([string[]]$TestPath = @())
$ErrorActionPreference = 'Stop'
$phpBinary = (Get-Command php -ErrorAction Stop).Source
$extensionPath = Join-Path (Split-Path $phpBinary) 'ext'
$phpOptions = @('-n', '-d', "extension_dir=$extensionPath", '-d', 'extension=mbstring', '-d', 'extension=openssl', '-d', 'extension=fileinfo', '-d', 'extension=gd', '-d', 'extension=pdo_sqlite', '-d', 'extension=sqlite3')
Push-Location (Split-Path $PSScriptRoot)
try {
    & $phpBinary @phpOptions tests/check-isolation.php
    if ($LASTEXITCODE -ne 0) { throw 'Isolation check failed; tests were not started.' }
    $phpunitArguments = @('--configuration', 'phpunit.xml') + $TestPath
    & $phpBinary @phpOptions vendor/phpunit/phpunit/phpunit @phpunitArguments
    $testExitCode = $LASTEXITCODE
} finally { Pop-Location }
exit $testExitCode
