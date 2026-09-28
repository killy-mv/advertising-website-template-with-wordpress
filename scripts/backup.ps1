# Back up the database and uploaded media into backups\<timestamp>\
# Usage:  powershell -ExecutionPolicy Bypass -File scripts\backup.ps1
# Files are created inside the containers and copied out with `docker compose cp`
# (piping binary data through Windows PowerShell would corrupt it).
# Replicas share the uploads volume, so replica 1 (--index 1) sees everything.
$ErrorActionPreference = 'Continue'
Set-Location (Split-Path $PSScriptRoot -Parent)

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$dir   = "backups\$stamp"
New-Item -ItemType Directory -Force $dir | Out-Null

Write-Host '==> Database' -ForegroundColor Cyan
docker compose exec -T db sh -c 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction --routines --triggers "$MARIADB_DATABASE" | gzip > /tmp/db.sql.gz'
if ($LASTEXITCODE) { throw 'database dump failed' }
docker compose cp db:/tmp/db.sql.gz "$dir\db.sql.gz" 2>&1 | Out-Null
docker compose exec -T db rm /tmp/db.sql.gz

Write-Host '==> Uploads' -ForegroundColor Cyan
docker compose exec -T --index 1 wordpress sh -c 'mkdir -p /var/www/html/wp-content/uploads && tar czf /tmp/uploads.tgz -C /var/www/html/wp-content uploads'
if ($LASTEXITCODE) { throw 'uploads archive failed' }
docker compose cp --index 1 wordpress:/tmp/uploads.tgz "$dir\uploads.tgz" 2>&1 | Out-Null
docker compose exec -T --index 1 wordpress rm /tmp/uploads.tgz

Get-ChildItem $dir | ForEach-Object { '{0,-14} {1,10:N0} KB' -f $_.Name, ($_.Length / 1KB) }
Write-Host "Backup saved to $dir" -ForegroundColor Green
