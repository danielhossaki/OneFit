$ErrorActionPreference = 'Stop'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$toolRoot = Join-Path $env:TEMP 'onefit-interface-tools'
New-Item -ItemType Directory -Force -Path $toolRoot | Out-Null
$nodeVersion = 'v22.14.0'
$archive = "node-$nodeVersion-win-x64.zip"
$zip = Join-Path $toolRoot $archive
Invoke-WebRequest "https://nodejs.org/dist/$nodeVersion/$archive" -UseBasicParsing -OutFile $zip
$sums = (Invoke-WebRequest "https://nodejs.org/dist/$nodeVersion/SHASUMS256.txt" -UseBasicParsing).Content
$expected = (($sums -split "`n" | Where-Object { $_ -match ([regex]::Escape($archive) + '$') }) -split '\s+')[0]
if (!$expected -or (Get-FileHash $zip -Algorithm SHA256).Hash -ne $expected) { throw 'Node checksum mismatch' }
Expand-Archive -LiteralPath $zip -DestinationPath $toolRoot -Force
$nodeDir = Join-Path $toolRoot "node-$nodeVersion-win-x64"
$env:PATH = "$nodeDir;$env:PATH"
& (Join-Path $nodeDir 'npm.cmd') install --prefix $toolRoot --ignore-scripts --no-audit --no-fund playwright-core@1.51.1
if ($LASTEXITCODE -ne 0) { throw 'Browser test dependency install failed' }
Write-Output "Portable browser tools: $toolRoot"
