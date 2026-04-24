# Provision required secret files for Postgres + Mongo + JWT (no trailing newline).
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

if (Test-Path secrets/mysql_password.txt)      { Remove-Item secrets/mysql_password.txt -Force }
if (Test-Path secrets/mysql_root_password.txt) { Remove-Item secrets/mysql_root_password.txt -Force }

function Write-Secret($path, $content) {
    [IO.File]::WriteAllBytes("$PWD/$path", [Text.Encoding]::ASCII.GetBytes($content))
}

if (-not (Test-Path secrets/postgres_password.txt))   { Write-Secret 'secrets/postgres_password.txt'   'baas_app_pwd' }
if (-not (Test-Path secrets/mongo_root_password.txt)) { Write-Secret 'secrets/mongo_root_password.txt' 'change_this_root_password' }
if (-not (Test-Path secrets/mongo_app_password.txt))  { Write-Secret 'secrets/mongo_app_password.txt'  'baas_app_pwd' }
if (-not (Test-Path secrets/jwt_passphrase.txt))      { Write-Secret 'secrets/jwt_passphrase.txt'      'change_me_jwt_passphrase' }

# Strip trailing CR/LF on every existing secret to prevent driver password mismatch.
Get-ChildItem secrets/*.txt | ForEach-Object {
    $bytes = [IO.File]::ReadAllBytes($_.FullName)
    $end = $bytes.Length
    while ($end -gt 0 -and ($bytes[$end-1] -eq 10 -or $bytes[$end-1] -eq 13)) { $end-- }
    if ($end -ne $bytes.Length) {
        [IO.File]::WriteAllBytes($_.FullName, $bytes[0..($end-1)])
        Write-Host "Stripped trailing newline from $($_.Name)"
    }
}

Get-ChildItem secrets | Format-Table Name, Length
