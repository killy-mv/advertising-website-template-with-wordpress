# Restore a backup made by backup.ps1. OVERWRITES the current database and uploads.
# Usage:  powershell -ExecutionPolicy Bypass -File scripts\restore.ps1 -Backup 20260928-101500 [-Force]
param([Parameter(Mandatory)] [string]$Backup, [switch]$Force)
$ErrorActionPreference = 'Continue'
Set-Location (Split-Path $PSScriptRoot -Parent)

$dir = "backups\$Backup"
if (-not (Test-Path "$dir\db.sql.gz")) { throw "No backup found at $dir" }

if (-not $Force) {
    $answer = Read-Host "This replaces the current database and uploads with $dir. Type 'yes' to continue"
    if ($answer -ne 'yes') { Write-Host 'Cancelled.'; exit 1 }
}

Write-Host '==> Database' -ForegroundColor Cyan
docker compose cp "$dir\db.sql.gz" db:/tmp/db.sql.gz 2>&1 | Out-Null
docker compose exec -T db sh -c 'gunzip -c /tmp/db.sql.gz | mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE" && rm /tmp/db.sql.gz'
if ($LASTEXITCODE) { throw 'database restore failed' }

if (Test-Path "$dir\uploads.tgz") {
    Write-Host '==> Uploads' -ForegroundColor Cyan
    docker compose cp --index 1 "$dir\uploads.tgz" wordpress:/tmp/uploads.tgz 2>&1 | Out-Null
    docker compose exec -T --index 1 wordpress sh -c 'rm -rf /var/www/html/wp-content/uploads && tar xzf /tmp/uploads.tgz -C /var/www/html/wp-content && chown -R www-data:www-data /var/www/html/wp-content/uploads && rm /tmp/uploads.tgz'
}

Write-Host '==> Clearing caches' -ForegroundColor Cyan
docker compose run --rm -q wpcli cache flush
docker compose run --rm -q wpcli page-cache flush
Write-Host 'Restore complete.' -ForegroundColor Green
