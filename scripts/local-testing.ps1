[CmdletBinding()]
param(
    [Parameter(Mandatory = $true, Position = 0)]
    [ValidateSet('initialize', 'verify', 'migrate', 'seed', 'serve')]
    [string] $Action,
    [switch] $ProbeMissingEnvironment
)

$ErrorActionPreference = 'Stop'
$root = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$environmentFile = Join-Path $root '.env.local-testing'
$databaseFile = Join-Path $root 'database\local-testing.sqlite'
Set-Location $root

function Assert-GitIgnored([string] $Path) {
    & git -C $root check-ignore --quiet --no-index -- $Path
    if ($LASTEXITCODE -ne 0) {
        throw "Refusing local setup because '$Path' is not ignored by Git."
    }
}

function New-RandomHex([int] $ByteCount) {
    $bytes = New-Object byte[] $ByteCount
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $generator.GetBytes($bytes)
        return [System.BitConverter]::ToString($bytes).Replace('-', '')
    } finally {
        $generator.Dispose()
    }
}

if ($Action -eq 'initialize') {
    if ($ProbeMissingEnvironment) {
        throw 'The missing-environment probe is only available with verify.'
    }
    if (Test-Path -LiteralPath $environmentFile) {
        throw 'Refusing to overwrite the existing local environment file.'
    }

    Assert-GitIgnored '.env.local-testing'
    Assert-GitIgnored 'database/local-testing.sqlite'

    $appKeyBytes = New-Object byte[] 32
    $keyGenerator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $keyGenerator.GetBytes($appKeyBytes)
        $appKey = 'base64:' + [Convert]::ToBase64String($appKeyBytes)
    } finally {
        $keyGenerator.Dispose()
    }

    $databaseEnvPath = $databaseFile.Replace('\', '/')
    $lines = @(
        'APP_NAME="CPSU Motorpool Local Testing"'
        'APP_ENV=local-testing'
        "APP_KEY=$appKey"
        'APP_DEBUG=true'
        'APP_URL=http://127.0.0.1:8000'
        'APP_MAINTENANCE_DRIVER=file'
        'BCRYPT_ROUNDS=4'
        'LOG_CHANNEL=single'
        'LOG_LEVEL=debug'
        'DB_CONNECTION=sqlite'
        "DB_DATABASE=$databaseEnvPath"
        'DB_URL='
        'DATABASE_URL='
        'DB_HOST=127.0.0.1'
        'DB_PORT=1'
        'DB_USERNAME='
        'DB_PASSWORD='
        'DB_SOCKET='
        'DB_SSLMODE='
        'DB_FOREIGN_KEYS=true'
        'CACHE_STORE=array'
        'SESSION_DRIVER=array'
        'SESSION_LIFETIME=120'
        'QUEUE_CONNECTION=sync'
        'BROADCAST_CONNECTION=null'
        'FILESYSTEM_DISK=local'
        'MAIL_MAILER=array'
        'MAIL_URL='
        'MAIL_HOST='
        'MAIL_USERNAME='
        'MAIL_PASSWORD='
        'BREVO_API_KEY='
        'RESEND_API_KEY='
        'POSTMARK_API_KEY='
        'AWS_ACCESS_KEY_ID='
        'AWS_SECRET_ACCESS_KEY='
        'AWS_BUCKET='
        'SLACK_BOT_USER_OAUTH_TOKEN='
        'SLACK_BOT_USER_DEFAULT_CHANNEL='
        'GATEWAY_API_KEY='
        'TRACKER_LEGACY_API_KEY='
        'TRACKER_LEGACY_AUTH_ENABLED=false'
        'TRACKER_LEGACY_DEVICE_ID='
        'SUPABASE_URL='
        'SUPABASE_KEY='
        'SUPABASE_SERVICE_ROLE_KEY='
        'SUPABASE_DB_URL='
        'PGHOST='
        'PGPORT='
        'PGDATABASE='
        'PGUSER='
        'PGPASSWORD='
        'PGSSLMODE='
        'PGSERVICE='
        'PGSERVICEFILE='
        'APP_CONFIG_CACHE='
        'PASSWORD_RESET_TEST_MODE=false'
        'PASSWORD_RESET_WHITELIST='
        'LOCAL_TEST_ADMIN_EMAIL=admin@local.test'
        "LOCAL_TEST_ADMIN_PASSWORD=$(New-RandomHex 24)"
        'LOCAL_TEST_DRIVER_EMAIL=driver@local.test'
        "LOCAL_TEST_DRIVER_PASSWORD=$(New-RandomHex 24)"
    )
    [System.IO.File]::WriteAllLines(
        $environmentFile,
        $lines,
        [System.Text.Encoding]::ASCII
    )
    Write-Output 'Created ignored local configuration with unique local-only secrets.'
    exit 0
}

if ($ProbeMissingEnvironment -and $Action -ne 'verify') {
    throw 'The missing-environment probe is only available with verify.'
}
if (-not (Test-Path -LiteralPath $environmentFile -PathType Leaf)) {
    throw 'Required .env.local-testing is missing; no Artisan command was started.'
}

Assert-GitIgnored '.env.local-testing'
Assert-GitIgnored 'database/local-testing.sqlite'

$defaultConfigCache = Join-Path $root 'bootstrap\cache\config.php'
if (Test-Path -LiteralPath $defaultConfigCache) {
    throw 'Refusing local commands while bootstrap/cache/config.php exists.'
}

$phpBinary = (Get-Command php -ErrorAction Stop).Source
$extensionPath = Join-Path (Split-Path $phpBinary) 'ext'
$phpOptions = @(
    '-n',
    '-d', "extension_dir=$extensionPath",
    '-d', 'extension=mbstring',
    '-d', 'extension=openssl',
    '-d', 'extension=fileinfo',
    '-d', 'extension=gd',
    '-d', 'extension=pdo_sqlite',
    '-d', 'extension=sqlite3'
)

$variablesToClear = @(
    'APP_ENV', 'APP_KEY', 'APP_DEBUG', 'APP_URL', 'APP_CONFIG_CACHE',
    'DB_CONNECTION', 'DB_DATABASE', 'DB_URL', 'DB_HOST', 'DB_PORT',
    'DB_USERNAME', 'DB_PASSWORD', 'DB_SOCKET', 'DB_SSLMODE',
    'DATABASE_URL', 'PGHOST', 'PGPORT', 'PGDATABASE', 'PGUSER',
    'PGPASSWORD', 'PGSSLMODE', 'PGSERVICE', 'PGSERVICEFILE',
    'SUPABASE_URL', 'SUPABASE_KEY', 'SUPABASE_SERVICE_ROLE_KEY',
    'SUPABASE_DB_URL', 'MAIL_MAILER', 'MAIL_URL', 'MAIL_HOST',
    'MAIL_USERNAME', 'MAIL_PASSWORD', 'BREVO_API_KEY', 'RESEND_API_KEY',
    'POSTMARK_API_KEY', 'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY',
    'AWS_BUCKET', 'SLACK_BOT_USER_OAUTH_TOKEN',
    'SLACK_BOT_USER_DEFAULT_CHANNEL', 'GATEWAY_API_KEY',
    'TRACKER_LEGACY_API_KEY', 'TRACKER_LEGACY_AUTH_ENABLED',
    'TRACKER_LEGACY_DEVICE_ID', 'LOCAL_TEST_ADMIN_EMAIL',
    'LOCAL_TEST_ADMIN_PASSWORD', 'LOCAL_TEST_DRIVER_EMAIL',
    'LOCAL_TEST_DRIVER_PASSWORD', 'CACHE_STORE', 'SESSION_DRIVER',
    'QUEUE_CONNECTION', 'BROADCAST_CONNECTION', 'FILESYSTEM_DISK',
    'SANCTUM_STATEFUL_DOMAINS', 'REDIS_URL', 'REDIS_HOST',
    'REDIS_PASSWORD', 'REDIS_PORT'
)
foreach ($name in $variablesToClear) {
    [Environment]::SetEnvironmentVariable($name, $null, 'Process')
}

$env:APP_ENV = 'local-testing'
$env:APP_CONFIG_CACHE = Join-Path ([System.IO.Path]::GetTempPath()) "cpsu-local-testing-config-$PID.php"
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = $databaseFile
$env:DB_URL = ''
$env:DATABASE_URL = ''
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '1'
$env:DB_USERNAME = ''
$env:DB_PASSWORD = ''
$env:PGHOST = ''
$env:PGPORT = ''
$env:PGDATABASE = ''
$env:PGUSER = ''
$env:PGPASSWORD = ''
$env:PGSSLMODE = ''
$env:PGSERVICE = ''
$env:PGSERVICEFILE = ''
$env:SUPABASE_URL = ''
$env:SUPABASE_KEY = ''
$env:SUPABASE_SERVICE_ROLE_KEY = ''
$env:SUPABASE_DB_URL = ''

if (Test-Path -LiteralPath $env:APP_CONFIG_CACHE) {
    throw 'Refusing local commands because the local config-cache path already exists.'
}

if ($ProbeMissingEnvironment) {
    $missingEnvironment = Join-Path $root '.env.local-testing.missing-probe'
    $probeArguments = @(
        'scripts\verify-local-sqlite.php',
        '--environment-file',
        $missingEnvironment
    )
    $previousErrorActionPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $null = & $phpBinary @phpOptions @probeArguments 2>&1
        $probeExitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }
    if ($probeExitCode -eq 0) {
        throw 'The verifier accepted a missing local environment file.'
    }
    Write-Output 'Missing local environment file was rejected as expected.'
}

& $phpBinary @phpOptions 'scripts\verify-local-sqlite.php'
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

switch ($Action) {
    'verify' {
        exit 0
    }
    'migrate' {
        & $phpBinary @phpOptions 'artisan' 'migrate' '--env=local-testing' '--database=sqlite'
        exit $LASTEXITCODE
    }
    'seed' {
        & $phpBinary @phpOptions 'artisan' 'db:seed' '--env=local-testing' '--database=sqlite' '--class=Database\Seeders\LocalTrackerTestingSeeder'
        exit $LASTEXITCODE
    }
    'serve' {
        Write-Output 'Serving Laravel at http://127.0.0.1:8000 using PHP -n and SQLite-only extensions.'
        & $phpBinary @phpOptions '-S' '127.0.0.1:8000' '-t' 'public' 'public/index.php'
        exit $LASTEXITCODE
    }
}
