# scripts/setup.ps1 — Windows / PowerShell bootstrap
$ErrorActionPreference = 'Stop'

Write-Host "==> Preparing project files..." -ForegroundColor Cyan

if (-not (Test-Path .env))     { Copy-Item .env.example .env }
if (-not (Test-Path app))      { New-Item -ItemType Directory app | Out-Null }
if (-not (Test-Path secrets))  { New-Item -ItemType Directory secrets | Out-Null }
if (-not (Test-Path models))   { New-Item -ItemType Directory models | Out-Null }

# Drop legacy MySQL secrets if still present (we use Postgres + Mongo now).
if (Test-Path secrets/mysql_password.txt)      { Remove-Item secrets/mysql_password.txt -Force }
if (Test-Path secrets/mysql_root_password.txt) { Remove-Item secrets/mysql_root_password.txt -Force }

function Write-Secret($path, $content) {
    [IO.File]::WriteAllBytes("$PWD/$path", [Text.Encoding]::ASCII.GetBytes($content))
}

if (-not (Test-Path secrets/postgres_password.txt))   { Write-Secret 'secrets/postgres_password.txt'   'baas_app_pwd' }
if (-not (Test-Path secrets/mongo_root_password.txt)) { Write-Secret 'secrets/mongo_root_password.txt' 'change_this_root_password' }
if (-not (Test-Path secrets/mongo_app_password.txt))  { Write-Secret 'secrets/mongo_app_password.txt'  'baas_app_pwd' }
if (-not (Test-Path secrets/jwt_passphrase.txt))      { Write-Secret 'secrets/jwt_passphrase.txt'      'change_me_jwt_passphrase' }

# Strip any trailing CR/LF from existing secret files (drivers would otherwise
# treat \r\n as part of the password, causing "Access denied" failures).
Get-ChildItem secrets/*.txt | ForEach-Object {
    $bytes = [IO.File]::ReadAllBytes($_.FullName)
    $end = $bytes.Length
    while ($end -gt 0 -and ($bytes[$end-1] -eq 10 -or $bytes[$end-1] -eq 13)) { $end-- }
    if ($end -ne $bytes.Length) {
        [IO.File]::WriteAllBytes($_.FullName, $bytes[0..($end-1)])
        Write-Host "Stripped trailing newline from $($_.Name)"
    }
}

Write-Host "OK Setup complete." -ForegroundColor Green
Write-Host "Next: make build && make init && make up   (or: make dev)"
