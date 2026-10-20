# Testa a autorizacao de entrada da catraca (OneFit.Turnstile, C#) contra o banco
# descartavel criado por tests/funcionarios-preparar.ps1. Compila a catraca numa
# pasta temporaria (nao toca no bin\ do projeto), compila tests/catraca-autorizacao.cs
# junto e roda os casos preparados por tests/catraca-autorizacao.php.
# Nunca usa a senha nem o banco configurados para a catraca.
# (Mensagens sem acento: o PowerShell 5.1 le .ps1 sem BOM como ANSI.)
param([string]$Projeto = (Join-Path $env:USERPROFILE 'source\repos\OneFit.Turnstile'))
$ErrorActionPreference = 'Stop'
$csproj = Join-Path $Projeto 'OneFit.Turnstile\OneFit.Turnstile.csproj'
if (-not (Test-Path $csproj)) { throw "Projeto da catraca nao encontrado: $csproj" }
$vswhere = "${env:ProgramFiles(x86)}\Microsoft Visual Studio\Installer\vswhere.exe"
$msbuild = & $vswhere -latest -products * -requires Microsoft.Component.MSBuild -find 'MSBuild\**\Bin\MSBuild.exe' | Select-Object -First 1
if (-not $msbuild) { throw 'MSBuild nao encontrado.' }
$csc = Join-Path (Split-Path $msbuild) 'Roslyn\csc.exe'

$fixtureRoot = Join-Path $env:TEMP 'onefit-interface-fixture'
$fixture = Get-Content (Join-Path $fixtureRoot 'database.json') -Raw | ConvertFrom-Json
if ($fixture.database -notmatch '^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$') { throw 'Banco de destino inesperado.' }
$saida = Join-Path $fixtureRoot 'catraca-build\'
$obj = Join-Path $fixtureRoot 'catraca-obj\'

& $msbuild $csproj /nologo /v:quiet /p:Configuration=Debug "/p:OutDir=$saida" "/p:BaseIntermediateOutputPath=$obj" "/p:IntermediateOutputPath=$obj"
if ($LASTEXITCODE -ne 0) { throw 'A catraca nao compilou.' }
$teste = Join-Path $saida 'catraca-autorizacao.exe'
& $csc /nologo /target:exe "/out:$teste" "/reference:$(Join-Path $saida 'OneFit.Turnstile.exe')" "/reference:$(Join-Path $saida 'MySql.Data.dll')" (Join-Path $PSScriptRoot 'catraca-autorizacao.cs')
if ($LASTEXITCODE -ne 0) { throw 'O teste da catraca nao compilou.' }
# Mesmos redirecionamentos de assembly da catraca.
Copy-Item (Join-Path $saida 'OneFit.Turnstile.exe.config') "$teste.config" -Force

& 'C:\xampp\php\php.exe' (Join-Path $PSScriptRoot 'catraca-autorizacao.php')
if ($LASTEXITCODE -ne 0) { throw 'Preparacao dos casos falhou.' }
& $teste $fixture.database (Join-Path $fixtureRoot 'catraca-casos.txt')
if ($LASTEXITCODE -ne 0) { throw 'tests/catraca-autorizacao.cs falhou.' }
