# Cache only the public dependencies already referenced by the application.
$ErrorActionPreference = 'Stop'
$assetDir = Join-Path $env:TEMP 'onefit-dashboard-review/vendor'
New-Item -ItemType Directory -Force -Path $assetDir | Out-Null
$assets = @{
    'bootstrap.min.css' = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'
    'bootstrap.bundle.min.js' = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'
    'bootstrap-icons.css' = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css'
    'bootstrap-icons.woff2' = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff2'
    'manrope.css' = 'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap'
}
foreach ($entry in $assets.GetEnumerator()) {
    Invoke-WebRequest $entry.Value -UseBasicParsing -OutFile (Join-Path $assetDir $entry.Key)
}
$fontCss = Get-Content (Join-Path $assetDir 'manrope.css') -Raw
$fontUrls = [regex]::Matches($fontCss, 'https://[^)]+') | ForEach-Object { $_.Value } | Select-Object -Unique
foreach ($fontUrl in $fontUrls) {
    Invoke-WebRequest $fontUrl -UseBasicParsing -OutFile (Join-Path $assetDir ([IO.Path]::GetFileName(([uri]$fontUrl).AbsolutePath)))
}