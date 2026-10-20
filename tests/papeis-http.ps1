# Sobe o servidor embutido do PHP sobre a cópia sintética criada por
# tests/prepare-interface-web.ps1 e roda um teste HTTP (padrão: papeis-http.php;
# ex.: -Script funcionarios-http.php). Nunca usa o .env nem o banco da aplicação.
param([ValidatePattern('^[a-z-]+-http\.php$')][string]$Script = 'papeis-http.php')
$ErrorActionPreference = 'Stop'
$webRoot = Join-Path $env:TEMP 'onefit-interface-fixture/www'
$envFile = Join-Path $webRoot 'AN25/OneFit/.env'
if (-not (Select-String -Path $envFile -Pattern '^DB_HOST=127\.0\.0\.1$' -Quiet)) {
    throw 'A cópia sintética não aponta para o banco local. Rode tests/prepare-interface-web.ps1.'
}
foreach ($name in 'MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD') {
    if ([Environment]::GetEnvironmentVariable($name) -or (Select-String -Path $envFile -Pattern "^$name=." -Quiet)) {
        throw "$name está definido: teste abortado para não enviar e-mails."
    }
}
$port = 8765
$server = Start-Process -FilePath 'C:\xampp\php\php.exe' -ArgumentList @('-S', "127.0.0.1:$port", '-t', $webRoot) -PassThru -WindowStyle Hidden
try {
    $deadline = (Get-Date).AddSeconds(10)
    while (-not (Test-NetConnection 127.0.0.1 -Port $port -InformationLevel Quiet -WarningAction SilentlyContinue)) {
        if ((Get-Date) -gt $deadline) { throw 'O servidor de teste não respondeu.' }
    }
    & 'C:\xampp\php\php.exe' (Join-Path $PSScriptRoot $Script) "http://127.0.0.1:$port/AN25/OneFit/"
    if ($LASTEXITCODE -ne 0) { throw "tests/$Script falhou." }
} finally {
    Stop-Process -Id $server.Id -Force -ErrorAction SilentlyContinue
}
