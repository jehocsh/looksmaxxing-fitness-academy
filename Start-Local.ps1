$ErrorActionPreference = 'Stop'
$workspaceRoot = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$runtimeDir = Join-Path $workspaceRoot 'work\runtime'
$phpDir = Join-Path $runtimeDir 'php'
$phpPath = Join-Path $phpDir 'php.exe'
$mysqlRoot = Join-Path $runtimeDir 'mysql-8.4.13.1-winx64'
$mysqlPath = Join-Path $mysqlRoot 'bin\mysqld.exe'
$dataDir = Join-Path $runtimeDir 'mysql-data'
if (-not (Test-Path -LiteralPath $phpPath) -or -not (Test-Path -LiteralPath $mysqlPath)) {
    throw 'This launcher uses the portable runtimes prepared in this workspace. See README.md for a standard PHP/MySQL installation.'
}
$env:PATH = $phpDir + ';' + $env:PATH
if (-not (Get-NetTCPConnection -State Listen -LocalPort 3307 -ErrorAction SilentlyContinue)) {
    $databaseArgs = @('--no-defaults', "--basedir=$mysqlRoot", "--datadir=$dataDir", '--bind-address=127.0.0.1', '--port=3307', '--mysqlx=0', '--console')
    Start-Process -FilePath $mysqlPath -ArgumentList $databaseArgs -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtimeDir 'mysql.stdout.log') -RedirectStandardError (Join-Path $runtimeDir 'mysql.stderr.log') | Out-Null
}
if (-not (Get-NetTCPConnection -State Listen -LocalPort 8000 -ErrorAction SilentlyContinue)) {
    $routerPath = Join-Path $PSScriptRoot 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
    $serverArgs = @('-S', '127.0.0.1:8000', '-t', '.', ('"' + $routerPath + '"'))
    Start-Process -FilePath $phpPath -ArgumentList $serverArgs -WorkingDirectory (Join-Path $PSScriptRoot 'public') -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtimeDir 'web.stdout.log') -RedirectStandardError (Join-Path $runtimeDir 'web.stderr.log') | Out-Null
}
Write-Output 'LooksMaxxing local preview: http://127.0.0.1:8000'
Write-Output 'Staff sign-in: http://127.0.0.1:8000/login'
