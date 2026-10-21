# Confere a decisao real da catraca (OneFit.Turnstile, C#) para cada pessoa de uma base de
# demonstracao LOCAL, a partir de um arquivo de casos "id;liberado;motivo;rotulo".
# Compila a catraca numa pasta temporaria (nao toca no bin\ do projeto). So le o banco.
# Uso: powershell -File tests/catraca-demonstracao.ps1 -Banco onefit_ensaio_reset_... -Casos C:\caminho\casos.txt
# (Mensagens sem acento: o PowerShell 5.1 le .ps1 sem BOM como ANSI.)
param(
    [Parameter(Mandatory = $true)][ValidatePattern('^onefit_(ensaio_reset_\d{8}_\d{6}|interface_test_\d{8}_\d{6}_[a-f0-9]{4})$')][string]$Banco,
    [Parameter(Mandatory = $true)][string]$Casos,
    [string]$Projeto = (Join-Path $env:USERPROFILE 'source\repos\OneFit.Turnstile')
)
$ErrorActionPreference = 'Stop'
$csproj = Join-Path $Projeto 'OneFit.Turnstile\OneFit.Turnstile.csproj'
if (-not (Test-Path $csproj)) { throw "Projeto da catraca nao encontrado: $csproj" }
if (-not (Test-Path $Casos)) { throw "Arquivo de casos nao encontrado: $Casos" }
$vswhere = "${env:ProgramFiles(x86)}\Microsoft Visual Studio\Installer\vswhere.exe"
$msbuild = & $vswhere -latest -products * -requires Microsoft.Component.MSBuild -find 'MSBuild\**\Bin\MSBuild.exe' | Select-Object -First 1
if (-not $msbuild) { throw 'MSBuild nao encontrado.' }
$csc = Join-Path (Split-Path $msbuild) 'Roslyn\csc.exe'

$raiz = Join-Path $env:TEMP 'onefit-catraca-demonstracao'
$saida = Join-Path $raiz 'build\'
$obj = Join-Path $raiz 'obj\'
& $msbuild $csproj /nologo /v:quiet /p:Configuration=Debug "/p:OutDir=$saida" "/p:BaseIntermediateOutputPath=$obj" "/p:IntermediateOutputPath=$obj"
if ($LASTEXITCODE -ne 0) { throw 'A catraca nao compilou.' }
$teste = Join-Path $saida 'catraca-demonstracao.exe'
& $csc /nologo /target:exe "/out:$teste" "/reference:$(Join-Path $saida 'OneFit.Turnstile.exe')" "/reference:$(Join-Path $saida 'MySql.Data.dll')" (Join-Path $PSScriptRoot 'catraca-demonstracao.cs')
if ($LASTEXITCODE -ne 0) { throw 'O teste nao compilou.' }
Copy-Item (Join-Path $saida 'OneFit.Turnstile.exe.config') "$teste.config" -Force
& $teste $Banco $Casos
if ($LASTEXITCODE -ne 0) { throw 'tests/catraca-demonstracao.cs falhou.' }
