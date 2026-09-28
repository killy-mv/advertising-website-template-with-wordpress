# One-command setup for Windows: builds the stack, installs WordPress, loads demo content.
# Usage (from the project folder):  powershell -ExecutionPolicy Bypass -File scripts\setup.ps1
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)

if (-not (Test-Path .env)) {
    Copy-Item .env.example .env
    Write-Warning 'Created .env from .env.example. Edit the passwords, then run this script again.'
    exit 1
}
$envVars = @{}
Get-Content .env | Where-Object { $_ -match '^\w+=' } | ForEach-Object { $k, $v = $_ -split '=', 2; $envVars[$k] = $v.Trim('"') }

function wp { docker compose run --rm -q wpcli @args; if ($LASTEXITCODE) { throw "wp $args failed" } }

Write-Host '==> Starting containers' -ForegroundColor Cyan
docker compose up -d --build --remove-orphans
if ($LASTEXITCODE) { throw 'docker compose up failed' }

Write-Host '==> Waiting for WordPress files' -ForegroundColor Cyan
for ($i = 0; $i -lt 30; $i++) {
    docker compose exec -T wordpress test -f /var/www/html/wp-config.php
    if (-not $LASTEXITCODE) { break }
    Start-Sleep 2
}

docker compose run --rm -q wpcli core is-installed 2>$null
if ($LASTEXITCODE) {
    Write-Host '==> Installing WordPress' -ForegroundColor Cyan
    wp core install --url="$($envVars.SITE_URL)" --title="$($envVars.SITE_TITLE)" `
        --admin_user="$($envVars.ADMIN_USER)" --admin_password="$($envVars.ADMIN_PASSWORD)" `
        --admin_email="$($envVars.ADMIN_EMAIL)" --skip-email
}

Write-Host '==> Configuring' -ForegroundColor Cyan
wp theme activate restaurant-theme
wp rewrite structure '/%postname%/'
wp eval-file /scripts/seed.php

Write-Host '==> Redis object cache' -ForegroundColor Cyan
wp plugin install redis-cache --activate
docker compose run --rm -q wpcli redis enable 2>$null  # errors harmlessly if already enabled
wp cache flush

Write-Host "`nDone! Site:  $($envVars.SITE_URL)" -ForegroundColor Green
Write-Host "Admin: $($envVars.SITE_URL)/wp-admin  (user: $($envVars.ADMIN_USER), password in .env)"
