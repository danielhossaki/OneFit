# Evidencias do reconhecimento facial da catraca (OneFit.Turnstile): fotos reais das pessoas
# cadastradas (cada foto fora do modelo), rostos de 40 pessoas desconhecidas (base publica
# AT&T/ORL, AT&T Laboratories Cambridge, livre para pesquisa), imagem com dois rostos e o fluxo
# rosto -> identidade -> autorizacao num banco descartavel proprio, apagado no fim.
# Compila a catraca numa pasta temporaria (nao toca no bin\ do projeto). Exige antes
# tests/funcionarios-preparar.ps1 (so para copiar a estrutura das tabelas).
# Imprime so numeros agregados: nenhum nome de arquivo, imagem ou dado pessoal.
# (Mensagens sem acento: o PowerShell 5.1 le .ps1 sem BOM como ANSI.)
param(
    [string]$Projeto = (Join-Path $env:USERPROFILE 'source\repos\OneFit.Turnstile'),
    [string]$Fotos = (Join-Path ([Environment]::GetFolderPath('Desktop')) 'OneFitFaceTest'),
    [string]$Desconhecidos = (Join-Path $env:TEMP 'onefit-att\att')
)
$ErrorActionPreference = 'Stop'
$csproj = Join-Path $Projeto 'OneFit.Turnstile\OneFit.Turnstile.csproj'
if (-not (Test-Path $csproj)) { throw "Projeto da catraca nao encontrado: $csproj" }
foreach ($id in 125, 129) {
    if (-not (Test-Path (Join-Path $Fotos $id))) { throw "Pasta de fotos $id nao encontrada em $Fotos" }
}
$modelo = Join-Path $Fotos 'modelo_facial.yml'
if (-not (Test-Path $modelo)) { throw "Modelo facial nao encontrado: $modelo" }

# Base publica de rostos desconhecidos, baixada uma vez numa pasta propria.
if (-not (Test-Path $Desconhecidos)) {
    $pasta = Split-Path $Desconhecidos
    New-Item -ItemType Directory -Force $pasta | Out-Null
    $zip = Join-Path $pasta 'att_faces.zip'
    Invoke-WebRequest 'https://www.cl.cam.ac.uk/research/dtg/attarchive/pub/data/att_faces.zip' -OutFile $zip -UseBasicParsing
    Expand-Archive $zip -DestinationPath $Desconhecidos
}
if ((Get-ChildItem $Desconhecidos -Recurse -Filter *.pgm).Count -lt 400) { throw "Base de desconhecidos incompleta em $Desconhecidos" }

$vswhere = "${env:ProgramFiles(x86)}\Microsoft Visual Studio\Installer\vswhere.exe"
$msbuild = & $vswhere -latest -products * -requires Microsoft.Component.MSBuild -find 'MSBuild\**\Bin\MSBuild.exe' | Select-Object -First 1
if (-not $msbuild) { throw 'MSBuild nao encontrado.' }
$csc = Join-Path (Split-Path $msbuild) 'Roslyn\csc.exe'
$facades = "${env:ProgramFiles(x86)}\Reference Assemblies\Microsoft\Framework\.NETFramework\v4.7.2\Facades"
if (-not (Test-Path $facades)) { throw '.NET Framework 4.7.2 Developer Pack nao encontrado.' }

$fixtureRoot = Join-Path $env:TEMP 'onefit-interface-fixture'
$fixture = Get-Content (Join-Path $fixtureRoot 'database.json') -Raw | ConvertFrom-Json
if ($fixture.database -notmatch '^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$') { throw 'Banco de destino inesperado.' }
$saida = Join-Path $fixtureRoot 'catraca-build\'
$obj = Join-Path $fixtureRoot 'catraca-obj\'

& $msbuild $csproj /nologo /v:quiet /p:Configuration=Debug "/p:OutDir=$saida" "/p:BaseIntermediateOutputPath=$obj" "/p:IntermediateOutputPath=$obj"
if ($LASTEXITCODE -ne 0) { throw 'A catraca nao compilou.' }
$teste = Join-Path $saida 'catraca-reconhecimento.exe'
& $csc /nologo /nowarn:1702 /target:exe "/out:$teste" `
    "/reference:$(Join-Path $saida 'OneFit.Turnstile.exe')" "/reference:$(Join-Path $saida 'Emgu.CV.dll')" `
    "/reference:$(Join-Path $saida 'MySql.Data.dll')" "/reference:$(Join-Path $saida 'System.Memory.dll')" `
    /reference:System.Drawing.dll "/reference:$(Join-Path $facades 'netstandard.dll')" "/reference:$(Join-Path $facades 'System.Runtime.dll')" `
    (Join-Path $PSScriptRoot 'catraca-reconhecimento.cs')
if ($LASTEXITCODE -ne 0) { throw 'O teste do reconhecimento nao compilou.' }
Copy-Item (Join-Path $saida 'OneFit.Turnstile.exe.config') "$teste.config" -Force

& $teste $Fotos $Desconhecidos $modelo $fixture.database
if ($LASTEXITCODE -ne 0) { throw 'tests/catraca-reconhecimento.cs falhou.' }
