$ErrorActionPreference = 'Stop'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$projectRoot = Split-Path $PSScriptRoot -Parent
$flagRoot = Join-Path $projectRoot 'assets/img/flags/states'
New-Item -ItemType Directory -Force -Path $flagRoot | Out-Null
$headers = @{ 'User-Agent' = 'OneFit-asset-import' }
$commit = (Invoke-RestMethod 'https://api.github.com/repos/akagabi/bandeira-dos-estados-do-brasil/commits/master' -Headers $headers).sha
$base = "https://raw.githubusercontent.com/akagabi/bandeira-dos-estados-do-brasil/$commit"
$states = 'ac al ap am ba ce df es go ma mt ms mg pa pb pr pe pi rj rn rs ro rr sc sp se to'.Split(' ')
$manifest = @()
foreach ($uf in $states) {
    $url = "$base/$uf.svg"
    $file = Join-Path $flagRoot "$uf.svg"
    Invoke-WebRequest $url -UseBasicParsing -OutFile $file
    $svg = [IO.File]::ReadAllText($file)
    if ($svg -match '<script|<foreignObject|\bon\w+\s*=|(?:href|src)\s*=\s*["''](?:https?:|//|data:)' -or $svg -notmatch '<svg') { throw "Unsafe SVG: $uf" }
    # Strip comments/editor metadata without changing artwork or proportions.
    $svg = [regex]::Replace($svg, '(?s)<!--.*?-->', '')
    $svg = [regex]::Replace($svg, '(?s)<metadata\b.*?</metadata>', '')
    [IO.File]::WriteAllText($file, $svg, [Text.UTF8Encoding]::new($false))
    $manifest += @{ uf = $uf.ToUpper(); source = $url; sha256 = (Get-FileHash $file -Algorithm SHA256).Hash }
}
Invoke-WebRequest "$base/LICENSE" -UseBasicParsing -OutFile (Join-Path $flagRoot 'LICENSE.txt')
$manifest | ConvertTo-Json -Depth 4 | Set-Content (Join-Path $flagRoot 'sources.json') -Encoding UTF8
$iso = Invoke-RestMethod 'https://raw.githubusercontent.com/lukes/ISO-3166-Countries-with-Regional-Codes/master/all/all.json'
$cldr = Invoke-RestMethod 'https://raw.githubusercontent.com/unicode-org/cldr-json/main/cldr-json/cldr-localenames-full/main/pt/territories.json'
$countries = [ordered]@{}
foreach ($country in $iso) {
    $code = $country.'alpha-2'
    $label = $cldr.main.pt.localeDisplayNames.territories.$code
    if (!$label) { throw "Missing country label: $code" }
    $countries[$code] = $label
}
$json = $countries | ConvertTo-Json
[IO.File]::WriteAllText((Join-Path $projectRoot 'config/countries.json'), $json, [Text.UTF8Encoding]::new($false))
Write-Output "Imported $($states.Count) state flags and $($countries.Count) ISO countries. Commit: $commit"
