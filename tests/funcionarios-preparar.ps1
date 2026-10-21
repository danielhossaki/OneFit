# Prepara o ambiente isolado da Etapa 2: banco de teste novo, copia sintetica
# do app e migracao de funcionarios aplicada SOMENTE nesse banco local.
# Nunca usa o .env nem o banco configurados na aplicacao.
# (Mensagens sem acento: o PowerShell 5.1 le .ps1 sem BOM como ANSI.)
$ErrorActionPreference = 'Stop'
$php = 'C:\xampp\php\php.exe'
function Rodar([string[]]$argumentos) {
    & $php @argumentos
    if ($LASTEXITCODE -ne 0) { throw "Falhou: php $($argumentos -join ' ')" }
}

Rodar @((Join-Path $PSScriptRoot 'dashboard-fixture.php'))
& powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'prepare-interface-web.ps1')
if ($LASTEXITCODE -ne 0) { throw 'prepare-interface-web.ps1 falhou.' }

$fixtureRoot = Join-Path $env:TEMP 'onefit-interface-fixture'
$fixture = Get-Content (Join-Path $fixtureRoot 'database.json') -Raw | ConvertFrom-Json
if ($fixture.database -notmatch '^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$') { throw 'Banco de destino inesperado.' }
$copia = Join-Path $fixtureRoot 'www/AN25/OneFit'
$envCopia = Join-Path $copia '.env'
if (-not (Select-String -Path $envCopia -Pattern '^DB_HOST=127\.0\.0\.1$' -Quiet) -or
    -not (Select-String -Path $envCopia -SimpleMatch -Pattern "DB_NAME=$($fixture.database)" -Quiet)) {
    throw 'A copia sintetica nao aponta para o banco de teste local.'
}
$runner = Join-Path $copia 'database/migrate-funcionarios.php'
$alvo = @('--host=127.0.0.1', "--database=$($fixture.database)")
$backups = "--backup-dir=$(Join-Path $fixtureRoot 'backups')"

Rodar @((Join-Path $PSScriptRoot 'funcionarios-migracao.php'), '--antes')
Rodar (@($runner, '--inspecionar') + $alvo)
Rodar (@($runner, '--aplicar') + $alvo + @($backups))
$segunda = & $php $runner --aplicar @alvo $backups | Out-String
if ($LASTEXITCODE -ne 0 -or $segunda -notmatch 'aplicada; nada foi alterado') { throw 'A segunda execucao deveria ser idempotente.' }
Write-Output 'Segunda execucao: migracao ja aplicada, nada alterado.'
Rodar @((Join-Path $PSScriptRoot 'funcionarios-migracao.php'), '--depois')
# Mesmo script num banco descartavel com a estrutura do banco remoto (ENUM sem vendedor).
Rodar @((Join-Path $PSScriptRoot 'funcionarios-migracao-remota.php'))
# Cargo diretor (20261020_diretor_v1): so permite gravar 'diretor'; nenhuma conta muda de cargo.
$runnerDiretor = Join-Path $copia 'database/migrate-diretor.php'
Rodar (@($runnerDiretor, '--inspecionar') + $alvo)
Rodar (@($runnerDiretor, '--aplicar') + $alvo + @($backups))
$segundaDiretor = & $php $runnerDiretor --aplicar @alvo $backups | Out-String
if ($LASTEXITCODE -ne 0 -or $segundaDiretor -notmatch 'aplicada; nada foi alterado') { throw 'A segunda execucao da migracao do diretor deveria ser idempotente.' }
Write-Output 'Migracao do diretor: segunda execucao nao altera nada.'
# Migracao, recuperacao e reversao num banco com a estrutura do remoto.
Rodar @((Join-Path $PSScriptRoot 'diretor-migracao.php'))
