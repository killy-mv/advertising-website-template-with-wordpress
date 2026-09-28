# Rebuild benchmarks/results.md from scratch: turn each layer on in order and measure.
#   1. No cache          (Redis object cache off, page cache bypassed)
#   2. + Redis           (object cache on, page cache bypassed)
#   3. + Nginx page cache
#   4. 2 PHP backends    (page cache bypassed; compare with row 2)
# Usage:  powershell -ExecutionPolicy Bypass -File scripts\benchmark-suite.ps1 [-Vus 20] [-Duration 30s]
param([int]$Vus = 20, [string]$Duration = '30s')
# Not 'Stop': Windows PowerShell treats docker's progress output on stderr as an error.
$ErrorActionPreference = 'Continue'
Set-Location (Split-Path $PSScriptRoot -Parent)

function bench($label, [switch]$Bypass) {
    & "$PSScriptRoot\benchmark.ps1" -Label $label -Vus $Vus -Duration $Duration -Bypass:$Bypass | Select-Object -Last 1
}
function wait-ready {
    for ($i = 0; $i -lt 30; $i++) {
        if ((curl.exe -s -o NUL -w '%{http_code}' -b 'wordpress_no_cache=1' http://localhost:8080/) -eq '200') { return }
        Start-Sleep 2
    }
    throw 'site not ready'
}

Remove-Item benchmarks/results.md -ErrorAction SilentlyContinue

Write-Host '### 1 PHP backend' -ForegroundColor Yellow
docker compose --progress quiet up -d --scale wordpress=1
wait-ready

docker compose run --rm -q wpcli redis disable 2>&1 | Out-Null
bench '1. No cache' -Bypass

docker compose run --rm -q wpcli redis enable 2>&1 | Out-Null
bench '2. + Redis object cache' -Bypass

bench '3. + Nginx page cache'

Write-Host '### 2 PHP backends' -ForegroundColor Yellow
docker compose --progress quiet up -d
wait-ready
Start-Sleep 10  # let Nginx re-resolve the new replica
bench '4. 2 PHP backends, Redis, page cache bypassed' -Bypass

Write-Host "`nResults: benchmarks/results.md" -ForegroundColor Green
