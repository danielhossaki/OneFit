$ErrorActionPreference = 'Stop'
$sourceRoot = Split-Path $PSScriptRoot -Parent
$fixtureRoot = Join-Path $env:TEMP 'onefit-interface-fixture'
$webRoot = Join-Path $fixtureRoot 'www'
$appRoot = Join-Path $webRoot 'AN25/OneFit'
$fixture = Get-Content (Join-Path $fixtureRoot 'database.json') -Raw | ConvertFrom-Json
New-Item -ItemType Directory -Force -Path $appRoot | Out-Null
foreach ($folder in @('config','pages','components','database','vendor','assets/css','assets/js','assets/img/flags','assets/img/logo')) {
    $destination = Join-Path $appRoot $folder
    New-Item -ItemType Directory -Force -Path $destination | Out-Null
    Copy-Item -Path (Join-Path $sourceRoot "$folder/*") -Destination $destination -Recurse -Force
}
Copy-Item -LiteralPath (Join-Path $sourceRoot 'index.php') -Destination $appRoot -Force
$envText = "APP_URL=http://127.0.0.1:8765/AN25/OneFit`nDB_HOST=127.0.0.1`nDB_NAME=$($fixture.database)`nDB_USER=root`nDB_PASSWORD=`n"
[IO.File]::WriteAllText((Join-Path $appRoot '.env'), $envText, [Text.UTF8Encoding]::new($false))
$backupDir = Join-Path $fixtureRoot 'backups'
New-Item -ItemType Directory -Force -Path $backupDir | Out-Null
& C:\xampp\php\php.exe (Join-Path $appRoot 'database/migrate-interface.php') --development --host=127.0.0.1 "--database=$($fixture.database)" "--backup-dir=$backupDir"
if ($LASTEXITCODE -ne 0) { throw 'Fixture migration failed' }
Write-Output "Synthetic web root: $webRoot"
